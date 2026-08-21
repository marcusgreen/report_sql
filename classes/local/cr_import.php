<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

declare(strict_types=1);

namespace report_sql\local;

use report_sql\local\sql\validator;

/**
 * Import SQL reports from the Configurable Reports block (block_configurable_reports).
 *
 * Reads each `type='sql'` Configurable Reports instance, decodes its embedded query, applies the
 * deterministic Configurable-Reports → Report-sources translation shared via {@see import_helper}
 * (token swaps, MySQL date-function rewrites, double-quote and `?` normalisation), then re-validates
 * the result through the same {@see validator} and live dry-run the edit form uses. Reports that
 * translate cleanly are handed to {@see transfer::import()} and land as fresh drafts owned by the
 * importer; everything else is rejected with a printed reason and never written.
 *
 * No AI is involved: every transformation here is a fixed rule. Anything the rules cannot map
 * faithfully (e.g. %%USERID%%, %%FILTER_*%%, DATEDIFF) is rejected rather than guessed.
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cr_import {
    use import_helper;

    /** Configurable Reports DB table. */
    private const CR_TABLE = 'block_configurable_reports';

    /**
     * Whether the Configurable Reports block is installed (its report table exists).
     *
     * @return bool
     */
    public static function available(): bool {
        global $DB;
        return $DB->get_manager()->table_exists(self::CR_TABLE);
    }

    /**
     * Discover all Configurable Reports instances and classify each for import.
     *
     * @return array<int, array{id:int,name:string,type:string,verdict:string,reason:string,
     *         notes:string[],source:?array<string,mixed>}> Keyed by CR report id.
     */
    public static function discover(): array {
        global $DB;

        if (!self::available()) {
            return [];
        }

        $out = [];
        foreach ($DB->get_records(self::CR_TABLE, null, 'name ASC') as $rec) {
            $out[(int) $rec->id] = self::classify($rec);
        }
        return $out;
    }

    /**
     * Classify a single Configurable Reports record: importable or rejected, with reason/notes.
     *
     * @param \stdClass $rec A block_configurable_reports row.
     * @return array{id:int,name:string,type:string,verdict:string,reason:string,
     *         notes:string[],source:?array<string,mixed>}
     */
    public static function classify(\stdClass $rec): array {
        $base = [
            'id'      => (int) $rec->id,
            'name'    => (string) $rec->name,
            'type'    => (string) ($rec->type ?? ''),
            'verdict' => 'reject',
            'reason'  => '',
            'notes'   => [],
            'source'  => null,
        ];

        if (($rec->type ?? '') !== 'sql') {
            $base['reason'] = get_string('crimport:reasonnotsql', 'report_sql', $base['type'] ?: '?');
            return $base;
        }

        $sql = self::extract_sql($rec);
        if ($sql === '') {
            $base['reason'] = get_string('crimport:reasonnosql', 'report_sql');
            return $base;
        }

        // Deterministic CR → RS translation.
        $converted = self::convert($sql);
        if ($converted['fatal'] !== null) {
            $base['reason'] = $converted['fatal'];
            return $base;
        }

        // Static validation (denylist, SELECT-only, supported tokens, ? rejection, ...).
        try {
            $validated = validator::validate($converted['sql']);
        } catch (\moodle_exception $e) {
            $base['reason'] = $e->getMessage();
            return $base;
        }

        // Live dry-run: catches bad/dropped tables (e.g. mdl_log), missing columns, dialect errors
        // and VIEW duplicate-column problems — exactly the failures static checks cannot see.
        $dryrunerror = self::dry_run($validated);
        if ($dryrunerror !== null) {
            $base['reason'] = $dryrunerror;
            return $base;
        }

        // Accepted.
        $base['verdict'] = 'import';
        $base['notes'] = array_merge($converted['notes'], validator::get_warnings());
        $base['source'] = [
            'name'        => (string) $rec->name,
            'description' => self::clean_summary((string) ($rec->summary ?? '')),
            'querysql'    => $validated,
            'courseid'    => self::map_courseid((int) ($rec->courseid ?? 0)),
            'visible'     => (int) ($rec->visible ?? 1) ? 1 : 0,
            'chartmeta'   => null,
        ];
        return $base;
    }

    /**
     * Import the selected Configurable Reports instances as draft queries.
     *
     * Re-discovers and re-classifies (never trusts ids blindly), keeps only those whose verdict is
     * 'import', and feeds them to {@see transfer::import()} so they share the standard re-validation,
     * courseid-demotion and draft-creation path.
     *
     * @param int[] $ids CR report ids selected by the admin.
     * @return array{imported:int,skipped:array<string,string>,demoted:array<string,int>,
     *         rejected:array<string,string>} import() result plus names rejected at classify time.
     */
    public static function import(array $ids): array {
        $wanted = array_flip(array_map('intval', $ids));
        $classified = self::discover();

        $sources = [];
        $selected = [];
        $rejected = [];
        foreach ($classified as $id => $info) {
            if (!isset($wanted[$id])) {
                continue;
            }
            if ($info['verdict'] !== 'import' || $info['source'] === null) {
                $rejected[$info['name']] = $info['reason'];
                continue;
            }
            $selected[] = count($sources);
            $sources[] = $info['source'];
        }

        $result = transfer::import($sources, $selected);
        $result['rejected'] = $rejected;
        return $result;
    }

    /**
     * Decode the SQL embedded in a Configurable Reports record's serialised `components` blob.
     *
     * Mirrors block_configurable_reports' own `cr_unserialize()`: the blob is
     * serialize(urlencode_recursive(...)), with config objects stored as `O:6:"object"`. We rewrite
     * that to stdClass before unserialising and urldecode the recovered query.
     *
     * @param \stdClass $rec A block_configurable_reports row.
     * @return string The decoded SQL, or '' if none could be recovered.
     */
    private static function extract_sql(\stdClass $rec): string {
        $blob = (string) ($rec->components ?? '');
        if ($blob === '') {
            return '';
        }
        $blob = preg_replace('/O:6:"object"/', 'O:8:"stdClass"', $blob);
        $data = @unserialize($blob, ['allowed_classes' => [\stdClass::class]]);
        if (!is_array($data) || !isset($data['customsql']['config'])) {
            return '';
        }
        $config = (array) $data['customsql']['config'];
        if (!isset($config['querysql'])) {
            return '';
        }
        return trim(urldecode((string) $config['querysql']));
    }

    /**
     * Rewrite CR placeholder tokens to their RS equivalents, or reject unmappable ones.
     *
     * @param string $sql
     * @param string[] $notes Collected human-readable notes (passed by reference).
     * @return array{sql:string,fatal:?string}
     */
    private static function rewrite_tokens(string $sql, array &$notes): array {
        // Faithful CR substitutions: STARTTIME/ENDTIME are the time-range filter bounds CR fills with
        // 0 and a far-future epoch when no range is chosen; DEBUG is a flag CR strips from the SQL.
        $direct = [
            '%%STARTTIME%%' => '0',
            '%%ENDTIME%%'   => '2145938400',
            '%%DEBUG%%'     => '',
        ];
        foreach ($direct as $token => $replacement) {
            if (stripos($sql, $token) !== false) {
                $sql = str_ireplace($token, $replacement, $sql);
                $notes[] = get_string('crimport:notetoken', 'report_sql', $token);
            }
        }

        // Scan every remaining token. %%WWWROOT%% and %%COURSEID%% are shared with RS and kept as-is.
        // Anything else has no faithful mapping, so reject and name it.
        if (preg_match_all('/%%[A-Za-z0-9_]+%%/', $sql, $ms)) {
            foreach (array_unique($ms[0]) as $token) {
                $upper = strtoupper($token);
                if ($upper === '%%WWWROOT%%' || $upper === '%%COURSEID%%') {
                    continue;
                }
                if (preg_match('/^%%\s*USER_?IDS?\s*%%$/i', $token)) {
                    return ['sql' => $sql, 'fatal' =>
                        get_string('crimport:reasonuserid', 'report_sql', $token)];
                }
                if (stripos($token, '%%FILTER') === 0) {
                    return ['sql' => $sql, 'fatal' =>
                        get_string('crimport:reasonfilter', 'report_sql', $token)];
                }
                return ['sql' => $sql, 'fatal' =>
                    get_string('crimport:reasontoken', 'report_sql', $token)];
            }
        }

        return ['sql' => $sql, 'fatal' => null];
    }
}
