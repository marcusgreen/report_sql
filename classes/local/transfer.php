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
        $source = [
            'name'        => (string) $rec->name,
            'description' => (string) ($rec->description ?? ''),
            // The format travels; any embedded image *files* do not (JSON is text-only), so a
            // rich description imports as prose with its @@PLUGINFILE@@ image refs unresolved.
            'descriptionformat' => (int) ($rec->descriptionformat ?? FORMAT_HTML),
            'querysql'    => (string) $rec->querysql,
            'courseid'    => (int) ($rec->courseid ?? 0),
            'visible'     => (int) ($rec->visible ?? 1),
            'chartmeta'   => $rec->chartmeta ? json_decode($rec->chartmeta, true) : null,
            // The page-course filter column travels so a shared/sample query keeps its per-course
            // block scoping. It names an output column, so it stays valid across sites (unlike the
            // courseid, which is site-specific). Empty when the query has no page-course scoping.
            'pagecoursecolumn' => (string) ($rec->pagecoursecolumn ?? ''),
        ];

        // Auto-detected third-party plugin dependencies, from the tables the SQL references. Baked
        // in here (on a site where those plugins are installed, so the tables resolve) so the
        // importing site can hide/flag the source without any table-to-plugin guessing. Omitted
        // entirely when the query touches only core/standard tables, to keep exports tidy.
        $requires = self::detect_requires((string) $rec->querysql);
        if ($requires) {
            $source['requires'] = $requires;
        }

        return $source;
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
                'descriptionformat' => (int) ($raw['descriptionformat'] ?? FORMAT_HTML),
                'querysql'    => (string) $raw['querysql'],
                'courseid'    => (int) ($raw['courseid'] ?? 0),
                'visible'     => (int) ($raw['visible'] ?? 1),
                'chartmeta'   => isset($raw['chartmeta']) && is_array($raw['chartmeta'])
                    ? $raw['chartmeta'] : null,
                'pagecoursecolumn' => clean_param((string) ($raw['pagecoursecolumn'] ?? ''), PARAM_ALPHANUMEXT),
                // Frankenstyle component(s) this source needs on the target site (e.g. a sample
                // over a third-party plugin's tables). Absent/empty = core-only, always usable.
                // A source whose required plugin is missing is filtered out of the browse UI by
                // {@see bundled_samples()} and refused by {@see import()}.
                'requires'    => self::normalize_requires($raw['requires'] ?? []),
            ];
        }
        return $sources;
    }

    /**
     * Normalise a source's `requires` value into a clean list of frankenstyle components.
     *
     * Accepts either a single component string or an array of them; each is run through
     * {@see PARAM_COMPONENT}, blanks dropped, duplicates collapsed. A malformed component cleans
     * to '' and is dropped here, so it never reaches the availability check.
     *
     * @param mixed $value Raw `requires` from the JSON (string, array, or absent).
     * @return string[] Zero or more frankenstyle component names.
     */
    private static function normalize_requires($value): array {
        if (is_string($value)) {
            $value = $value === '' ? [] : [$value];
        }
        if (!is_array($value)) {
            return [];
        }
        $out = [];
        foreach ($value as $component) {
            $component = clean_param((string) $component, PARAM_COMPONENT);
            if ($component !== '') {
                $out[] = $component;
            }
        }
        return array_values(array_unique($out));
    }

    /**
     * Per-request cache of the third-party table => component map. Null until first built.
     *
     * @var array<string,string>|null
     */
    private static $tablemap = null;

    /**
     * Work out which third-party plugins a query depends on, from the tables it references.
     *
     * Extracts the query's `{table}` references and maps each to the installed non-standard plugin
     * that owns it (see {@see thirdparty_table_map()}). Only third-party owners are returned — core
     * and standard-plugin tables are always present, so they need no `requires` marker. Run at
     * export time (on a site where the plugin is installed, so the tables resolve), the result is
     * baked into the export's `requires` field, giving the importing site a certain, named
     * dependency without any table-to-plugin guessing on its end.
     *
     * @param string $sql The query SQL.
     * @return string[] Distinct frankenstyle components, sorted.
     */
    public static function detect_requires(string $sql): array {
        $map = self::thirdparty_table_map();
        if (!$map) {
            return [];
        }
        $components = [];
        foreach (validator::braced_tables($sql) as $table) {
            if (isset($map[$table])) {
                $components[$map[$table]] = true;
            }
        }
        $components = array_keys($components);
        sort($components);
        return $components;
    }

    /**
     * Map of table name => owning component for every installed non-standard plugin.
     *
     * Built by reading each third-party plugin's db/install.xml and collecting its `<TABLE>` names.
     * Standard/core plugins are skipped — only third-party ownership is of interest, and their
     * tables are always present on any Moodle so never gate an import. install.xml is a trusted
     * plugin file; table declarations are matched directly rather than through the xmldb loader to
     * avoid that dependency. Cached per request (export may call {@see detect_requires()} once per
     * source).
     *
     * @return array<string,string> Lower-cased table name => frankenstyle component.
     */
    private static function thirdparty_table_map(): array {
        if (self::$tablemap !== null) {
            return self::$tablemap;
        }

        $map = [];
        $manager = \core_plugin_manager::instance();
        foreach ($manager->get_plugins() as $plugins) {
            foreach ($plugins as $info) {
                if ($info->is_standard() || !$info->is_installed_and_upgraded()) {
                    continue;
                }
                $dir = \core_component::get_component_directory($info->component);
                if ($dir === null) {
                    continue;
                }
                $installxml = $dir . '/db/install.xml';
                if (!is_readable($installxml)) {
                    continue;
                }
                $contents = file_get_contents($installxml);
                if ($contents === false) {
                    continue;
                }
                if (preg_match_all('/<TABLE\s+NAME="([^"]+)"/i', $contents, $matches)) {
                    foreach ($matches[1] as $table) {
                        $map[strtolower($table)] = $info->component;
                    }
                }
            }
        }

        self::$tablemap = $map;
        return $map;
    }

    /**
     * Is a required frankenstyle component present and upgraded on this site?
     *
     * A bare `core` requirement is always satisfied. A plugin component is available only when the
     * plugin manager knows it and it is installed and upgraded — a present-on-disk-but-not-yet-
     * upgraded plugin does not count, since its tables may not exist. An unknown/malformed
     * component is unavailable.
     *
     * @param string $component Frankenstyle component (e.g. 'mod_attendance').
     * @return bool
     */
    public static function component_available(string $component): bool {
        $component = trim($component);
        if ($component === '') {
            return true;
        }
        [$type, $name] = \core_component::normalize_component($component);
        if ($type === 'core') {
            return true;
        }
        if ($name === null) {
            return false;
        }
        $info = \core_plugin_manager::instance()->get_plugin_info($component);
        return $info !== null && $info->is_installed_and_upgraded();
    }

    /**
     * Describe a source's required components for the browse UI.
     *
     * Flags whether each is third-party (non-standard) — the browse UI badges only those, since a
     * standard/core dependency is always present and needs no "requires" note — and whether it is
     * installed, so the "show all" reveal can mark a missing dependency as such.
     *
     * @param string[] $components Frankenstyle components (already normalised).
     * @return array<int, array{component:string,name:string,thirdparty:bool,installed:bool}>
     */
    private static function requires_meta(array $components): array {
        $meta = [];
        foreach ($components as $component) {
            $info = \core_plugin_manager::instance()->get_plugin_info($component);
            // A not-installed plugin has no lang pack, so 'pluginname' would render as a broken
            // string placeholder — fall back to the raw frankenstyle component. An absent plugin
            // is treated as third-party (core is always installed, so it never reaches here unmet).
            $installed = $info !== null;
            $hasname = $installed && get_string_manager()->string_exists('pluginname', $component);
            $meta[] = [
                'component'  => $component,
                'name'       => $hasname ? get_string('pluginname', $component) : $component,
                'thirdparty' => !$installed || !$info->is_standard(),
                'installed'  => $installed,
            ];
        }
        return $meta;
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

            // Refuse a source whose required plugin is not installed — its tables would be absent,
            // so it could never publish. Belt-and-braces: bundled_samples() already hides these,
            // but a client could post a stale index or an uploaded file could name a missing plugin.
            $missing = '';
            foreach ($source['requires'] ?? [] as $component) {
                if (!self::component_available($component)) {
                    $missing = $component;
                    break;
                }
            }
            if ($missing !== '') {
                $skipped[$name] = get_string('samples:requiresmissing', 'report_sql', $missing);
                continue;
            }

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
                'descriptionformat' => (int) ($source['descriptionformat'] ?? FORMAT_HTML),
                'querysql'     => $sql,
                'courseid'     => $courseid,
                'visible'      => (int) ($source['visible'] ?? 1),
                'chartmeta'    => !empty($source['chartmeta']) ? json_encode($source['chartmeta']) : null,
                // Page-course column names an output column of this query's SQL; if the SQL was
                // rewritten to no longer produce it, the draft's owner can clear it in the edit form
                // and publish/fetch fails closed until then. Empty string stores as NULL (unscoped).
                'pagecoursecolumn' => ($source['pagecoursecolumn'] ?? '') !== '' ? $source['pagecoursecolumn'] : null,
                'ownerid'      => (int) $USER->id,
                'status'       => query::STATUS_DRAFT,
                'viewname'     => null,
                'reportid'     => null,
                'columnsmeta'  => null,
                'timecreated'  => $now,
                'timemodified' => $now,
            ];
            $newid = $DB->insert_record(query::TABLE, $record);
            \report_sql\event\query_created::create_and_trigger($newid, $name);
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
     * selector), a `duplicate` flag (a query with the same name already exists), an `available`
     * flag (all required plugins installed) and `requiresmeta` (per-dependency display info).
     *
     * By default a sample whose required plugin is missing is dropped — it could neither preview
     * nor publish here. Pass `$includeunavailable = true` to keep it (still flagged
     * `available = false`) so the browse UI's "show all" reveal can list it, disabled. Keys are
     * preserved either way (no reindex), so each source's `index` still equals its array key,
     * which the import selector relies on. Callers that import (count/bulk/selection) use the
     * default, so a missing-plugin sample is never importable.
     *
     * @param bool $includeunavailable Keep (flag) samples with a missing required plugin.
     * @return array<int, array<string, mixed>> Parsed sources, each with added `index`,
     *         `duplicate`, `available` and `requiresmeta`.
     */
    public static function bundled_samples(bool $includeunavailable = false): array {
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
            $available = true;
            foreach ($source['requires'] ?? [] as $component) {
                if (!self::component_available($component)) {
                    $available = false;
                    break;
                }
            }
            if (!$available && !$includeunavailable) {
                unset($sources[$index]);
                continue;
            }

            $name = (string) ($source['name'] ?? '');
            $source['index'] = $index;
            $source['available'] = $available;
            $source['duplicate'] = $name !== '' && $DB->record_exists(query::TABLE, ['name' => $name]);
            // Per-required-component display info for the browse badge (names + third-party/installed).
            $source['requiresmeta'] = self::requires_meta($source['requires'] ?? []);
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
