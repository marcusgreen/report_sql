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
 * Import SQL reports from the Ad-hoc Database Queries report (report_customsql).
 *
 * Reads each report_customsql_queries row, takes its plain-text `querysql`, applies the same
 * deterministic translation as {@see cr_import} via the shared {@see import_helper} (double-quote
 * normalisation, MySQL date-function → portable token mapping, literal-`?` rebuilding) plus the
 * customsql-specific token rewrites, then re-validates through the same {@see validator} and live
 * dry-run the edit form uses. Reports that translate cleanly are handed to {@see transfer::import()}
 * and land as fresh drafts owned by the importer; everything else is rejected with a printed reason
 * and never written.
 *
 * Unlike Configurable Reports, customsql stores SQL as a plain column (no serialised blob) and has no
 * per-course scope, so every imported draft lands site-wide (courseid 0). customsql's named `:param`
 * placeholders are interactive run-time inputs with no Report Sources equivalent, so any report using
 * them is rejected (rebuild as a Report Builder filter after importing).
 *
 * No AI is involved: every transformation here is a fixed rule. Anything the rules cannot map
 * faithfully (e.g. %%USERID%%, :params, DATEDIFF) is rejected rather than guessed.
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class customsql_import {
    use import_helper;

    /** Ad-hoc Database Queries report table. */
    private const CUSTOMSQL_TABLE = 'report_customsql_queries';

    /**
     * Whether the Ad-hoc Database Queries report is installed (its query table exists).
     *
     * @return bool
     */
    public static function available(): bool {
        global $DB;
        return $DB->get_manager()->table_exists(self::CUSTOMSQL_TABLE);
    }

    /**
     * Discover all customsql queries and classify each for import.
     *
     * @return array<int, array{id:int,name:string,type:string,verdict:string,reason:string,
     *         notes:string[],source:?array<string,mixed>}> Keyed by customsql query id.
     */
    public static function discover(): array {
        global $DB;

        if (!self::available()) {
            return [];
        }

        $out = [];
        foreach ($DB->get_records(self::CUSTOMSQL_TABLE, null, 'displayname ASC') as $rec) {
            $out[(int) $rec->id] = self::classify($rec);
        }
        return $out;
    }

    /**
     * Classify a single customsql query: importable or rejected, with reason/notes.
     *
     * @param \stdClass $rec A report_customsql_queries row.
     * @return array{id:int,name:string,type:string,verdict:string,reason:string,
     *         notes:string[],source:?array<string,mixed>}
     */
    public static function classify(\stdClass $rec): array {
        $base = [
            'id'      => (int) $rec->id,
            'name'    => (string) ($rec->displayname ?? ''),
            'type'    => 'sql',
            'verdict' => 'reject',
            'reason'  => '',
            'notes'   => [],
            'source'  => null,
        ];

        $sql = trim((string) ($rec->querysql ?? ''));
        if ($sql === '') {
            $base['reason'] = get_string('crimport:reasonnosql', 'report_sql');
            return $base;
        }

        // Deterministic customsql → RS translation.
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

        // Live dry-run: catches bad/dropped tables, missing columns, dialect errors and VIEW
        // duplicate-column problems — exactly the failures static checks cannot see.
        $dryrunerror = self::dry_run($validated);
        if ($dryrunerror !== null) {
            $base['reason'] = $dryrunerror;
            return $base;
        }

        // Accepted. customsql has no course scope or visibility flag, so land site-wide and visible;
        // the admin re-applies any access restriction by setting course/visibility before publishing.
        $base['verdict'] = 'import';
        $base['notes'] = array_merge($converted['notes'], validator::get_warnings());
        $base['source'] = [
            'name'        => (string) ($rec->displayname ?? ''),
            'description' => self::clean_summary((string) ($rec->description ?? '')),
            'querysql'    => $validated,
            'courseid'    => 0,
            'visible'     => 1,
            'chartmeta'   => null,
        ];
        return $base;
    }

    /**
     * Import the selected customsql queries as draft queries.
     *
     * Re-discovers and re-classifies (never trusts ids blindly), keeps only those whose verdict is
     * 'import', and feeds them to {@see transfer::import()} so they share the standard re-validation,
     * courseid-demotion and draft-creation path.
     *
     * @param int[] $ids customsql query ids selected by the admin.
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
     * Rewrite customsql placeholder tokens to their RS equivalents, or reject unmappable ones.
     *
     * @param string $sql
     * @param string[] $notes Collected human-readable notes (passed by reference).
     * @return array{sql:string,fatal:?string}
     */
    private static function rewrite_tokens(string $sql, array &$notes): array {
        // Time-range bounds customsql fills from its report period; with no period chosen it uses 0
        // and a far-future epoch, the same neutral bounds cr_import applies.
        $direct = [
            '%%STARTTIME%%' => '0',
            '%%ENDTIME%%'   => '2145938400',
        ];
        foreach ($direct as $token => $replacement) {
            if (stripos($sql, $token) !== false) {
                $sql = str_ireplace($token, $replacement, $sql);
                $notes[] = get_string('crimport:notetoken', 'report_sql', $token);
            }
        }

        // customsql escape tokens for characters that cannot be typed literally in its editor:
        // %%Q%% -> ?, %%C%% -> :, %%S%% -> ;. These only appear inside string literals (e.g. URLs),
        // so substituting the literal character is faithful. The shared convert() pass then rebuilds
        // any literal ? as chr(63) so RS does not read it as a bound parameter.
        $escapes = ['%%Q%%' => '?', '%%C%%' => ':', '%%S%%' => ';'];
        $escaped = false;
        foreach ($escapes as $token => $replacement) {
            if (stripos($sql, $token) !== false) {
                $sql = str_ireplace($token, $replacement, $sql);
                $escaped = true;
            }
        }
        if ($escaped) {
            $notes[] = get_string('customsqlimport:noteescape', 'report_sql');
        }

        // Named :param placeholders are interactive run-time inputs with no RS equivalent. Detect them
        // outside string literals (so embedded colons / Postgres casts do not false-positive) and
        // reject — they must be rebuilt as Report Builder filters after importing.
        if (preg_match('/(?<!:):[a-z][a-z0-9_]*/i', self::mask_strings($sql), $pm)) {
            return ['sql' => $sql, 'fatal' =>
                get_string('customsqlimport:reasonparam', 'report_sql', $pm[0])];
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
                return ['sql' => $sql, 'fatal' =>
                    get_string('crimport:reasontoken', 'report_sql', $token)];
            }
        }

        return ['sql' => $sql, 'fatal' => null];
    }
}
