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
 * Export / import saved ad-hoc queries as portable JSON.
 *
 * Only the portable fields of a query are transferred (name, description, SQL, course scope,
 * visibility and chart config). Environment-specific or derived state — owner, status,
 * backing VIEW name, Reportbuilder report id, introspected column metadata and timestamps — is
 * never exported and is regenerated on import: every imported query lands as a fresh draft owned
 * by the importing user and must be re-published on the target site.
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class transfer {
    /** Marker identifying our export files. */
    public const FORMAT = 'report_sql';

    /** Bump when the on-disk JSON shape changes incompatibly. */
    public const FORMAT_VERSION = 1;

    /**
     * Build an export payload (ready to JSON-encode) for the given query ids.
     *
     * @param int[] $ids Query ids to export.
     * @return array{format:string,version:int,exported:int,sources:array<int,array<string,mixed>>}
     */
    public static function export(array $ids): array {
        global $DB;

        $sources = [];
        if ($ids) {
            [$insql, $params] = $DB->get_in_or_equal(array_map('intval', $ids), SQL_PARAMS_NAMED);
            $records = $DB->get_records_select(query::TABLE, "id $insql", $params, 'name ASC');
            foreach ($records as $rec) {
                $sources[] = self::record_to_source($rec);
            }
        }

        return [
            'format'   => self::FORMAT,
            'version'  => self::FORMAT_VERSION,
            'exported' => time(),
            'sources'  => $sources,
        ];
    }

    /**
     * Reduce a query DB record to its portable representation.
     *
     * @param \stdClass $rec
     * @return array<string, mixed>
     */
    private static function record_to_source(\stdClass $rec): array {
        return [
            'name'        => (string) $rec->name,
            'description' => (string) ($rec->description ?? ''),
            'querysql'    => (string) $rec->querysql,
            'courseid'    => (int) ($rec->courseid ?? 0),
            'visible'     => (int) ($rec->visible ?? 1),
            'chartmeta'   => $rec->chartmeta ? json_decode($rec->chartmeta, true) : null,
        ];
    }

    /**
     * Decode and validate an uploaded export file into a list of source descriptors.
     *
     * Each returned element is safe to display (name/description are present strings) and to feed
     * back into {@see import()}. Throwing here keeps malformed uploads out of the selection UI.
     *
     * @param string $json Raw file contents.
     * @return array<int, array<string, mixed>> Zero-indexed list of sources.
     * @throws \moodle_exception If the file is not a recognised export.
     */
    public static function parse(string $json): array {
        $data = json_decode($json, true);
        if (!is_array($data) || ($data['format'] ?? null) !== self::FORMAT) {
            throw new \moodle_exception('errimportformat', 'report_sql');
        }
        if (!isset($data['sources']) || !is_array($data['sources'])) {
            throw new \moodle_exception('errimportformat', 'report_sql');
        }

        $sources = [];
        foreach ($data['sources'] as $raw) {
            if (!is_array($raw) || !isset($raw['name'], $raw['querysql'])) {
                continue;
            }
            $sources[] = [
                'name'        => (string) $raw['name'],
                'description' => (string) ($raw['description'] ?? ''),
                'querysql'    => (string) $raw['querysql'],
                'courseid'    => (int) ($raw['courseid'] ?? 0),
                'visible'     => (int) ($raw['visible'] ?? 1),
                'chartmeta'   => isset($raw['chartmeta']) && is_array($raw['chartmeta'])
                    ? $raw['chartmeta'] : null,
            ];
        }
        return $sources;
    }

    /**
     * Insert the chosen sources as new draft queries owned by the current user.
     *
     * Each source's SQL is re-validated through {@see validator::validate()} so an export from a
     * laxer site cannot smuggle disallowed SQL past the importing site's denylist. Sources that
     * fail validation are skipped and reported back, not fatal.
     *
     * A source's exported courseid is meaningless on a different site: the target may have no course
     * with that id. Rather than store a dangling id that later fatals on
     * {@see \context_course::instance()}, an unknown courseid is demoted to 0 (site-wide) and the
     * demotion reported back. The query lands as a draft, so the owner can re-scope it via the edit
     * form before publishing — no report exists yet, so demotion cannot over-expose data.
     *
     * @param array $sources Parsed sources (e.g. from {@see parse()}).
     * @param int[] $selected Indexes into $sources to actually import.
     * @return array Count imported,
     *         name=>reason of skips, and name=>original courseid of sources demoted to site-wide.
     */
    public static function import(array $sources, array $selected): array {
        global $DB, $USER;

        $now = time();
        $imported = 0;
        $skipped = [];
        $demoted = [];

        foreach ($selected as $index) {
            $index = (int) $index;
            if (!isset($sources[$index])) {
                continue;
            }
            $source = $sources[$index];
            $name = (string) ($source['name'] ?? '');

            try {
                $sql = validator::validate((string) ($source['querysql'] ?? ''));
            } catch (\moodle_exception $e) {
                $skipped[$name] = $e->getMessage();
                continue;
            }

            // Demote a courseid that does not exist on this site to site-wide (0), and report it.
            $courseid = (int) ($source['courseid'] ?? 0);
            if ($courseid > 0 && !$DB->record_exists('course', ['id' => $courseid])) {
                $demoted[$name] = $courseid;
                $courseid = 0;
            }

            $record = (object) [
                'name'         => $name,
                'description'  => (string) ($source['description'] ?? ''),
                'querysql'     => $sql,
                'courseid'     => $courseid,
                'visible'      => (int) ($source['visible'] ?? 1),
                'chartmeta'    => !empty($source['chartmeta']) ? json_encode($source['chartmeta']) : null,
                'ownerid'      => (int) $USER->id,
                'status'       => query::STATUS_DRAFT,
                'viewname'     => null,
                'reportid'     => null,
                'columnsmeta'  => null,
                'timecreated'  => $now,
                'timemodified' => $now,
            ];
            $DB->insert_record(query::TABLE, $record);
            $imported++;
        }

        return ['imported' => $imported, 'skipped' => $skipped, 'demoted' => $demoted];
    }

    /** Absolute path to the sample report views shipped with the plugin. */
    public const BUNDLED_SAMPLES = __DIR__ . '/../../samples/samples.json';

    /**
     * The bundled sample report views, parsed and annotated for browsing.
     *
     * Single read path for the bundled file: returns an empty array if the file is absent (a
     * stripped deployment) or unparseable, so callers can present the loader without fataling.
     * Each returned source carries its 0-based `index` (stable position, used as the import
     * selector) and a `duplicate` flag — true when a query with the same name already exists on
     * this site, so the browse UI can flag it and the server can refuse to re-import it.
     *
     * @return array<int, array<string, mixed>> Parsed sources, each with added `index` and `duplicate`.
     */
    public static function bundled_samples(): array {
        global $DB;

        if (!is_readable(self::BUNDLED_SAMPLES)) {
            return [];
        }
        $json = file_get_contents(self::BUNDLED_SAMPLES);
        if ($json === false) {
            return [];
        }
        try {
            $sources = self::parse($json);
        } catch (\moodle_exception $e) {
            return [];
        }

        foreach ($sources as $index => &$source) {
            $name = (string) ($source['name'] ?? '');
            $source['index'] = $index;
            $source['duplicate'] = $name !== '' && $DB->record_exists(query::TABLE, ['name' => $name]);
        }
        unset($source);

        return $sources;
    }

    /**
     * Number of sample report views bundled with the plugin.
     *
     * Returns 0 if the bundled file is absent (a stripped deployment) or unparseable, so callers
     * can present the loader without fataling.
     *
     * @return int
     */
    public static function count_samples(): int {
        return count(self::bundled_samples());
    }

    /**
     * Import the bundled sample report views as fresh drafts owned by the current user.
     *
     * Idempotent: any bundled source whose name already exists as a query is skipped rather than
     * duplicated, so re-running this (repeat clicks, reinstall) never accumulates copies. The
     * remaining sources go through the same {@see import()} path as a normal import — each SQL is
     * re-validated, unknown courseids are demoted to site-wide, and every query lands as a draft.
     *
     * @return array{imported:int,skipped:array<string,string>,demoted:array<string,int>,duplicates:string[]}
     *         The {@see import()} result with an added list of names skipped as already present.
     */
    public static function import_samples(): array {
        $sources = self::bundled_samples();

        // Drop sources whose name already exists, so repeat runs never duplicate.
        $duplicates = [];
        $selected = [];
        foreach ($sources as $index => $source) {
            if (!empty($source['duplicate'])) {
                $duplicates[] = (string) ($source['name'] ?? '');
                continue;
            }
            $selected[] = $index;
        }

        $result = self::import($sources, $selected);
        $result['duplicates'] = $duplicates;
        return $result;
    }
}
