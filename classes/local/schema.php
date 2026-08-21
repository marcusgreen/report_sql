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

/**
 * Builds and caches the database schema and foreign-key map that drive editor autocomplete.
 *
 * Both products are expensive: the column map calls get_columns() for every table (~450+), and the
 * foreign-key map parses the install.xml of core plus every installed plugin. They are needed only
 * by the SQL editor, so they are fetched lazily over AJAX rather than dumped into every edit page,
 * and cached in MUC keyed by the Moodle version (schema only changes on upgrade).
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class schema {
    /** @var string MUC cache key for the schema payload. */
    private const CACHE_KEY = 'data';

    /**
     * Return the cached schema payload, rebuilding it if missing or stale.
     *
     * @return array{tables: array<string, string[]>, fkmap: array<string, array>}
     */
    public static function get(): array {
        global $CFG;

        $cache = \cache::make('report_sql', 'schema');
        $cached = $cache->get(self::CACHE_KEY);
        if (is_array($cached) && ($cached['version'] ?? null) === $CFG->version) {
            return ['tables' => $cached['tables'], 'fkmap' => $cached['fkmap']];
        }

        $data = [
            'tables' => self::build_tables(),
            'fkmap'  => self::build_fk_map(),
        ];
        $cache->set(self::CACHE_KEY, ['version' => $CFG->version] + $data);

        return $data;
    }

    /**
     * Build a map of table name => list of column names from the live database.
     *
     * @return array<string, string[]>
     */
    private static function build_tables(): array {
        global $DB;
        $tables = [];
        foreach ($DB->get_tables() as $table) {
            $tables[$table] = array_keys($DB->get_columns($table));
        }
        return $tables;
    }

    /**
     * Build a foreign-key map from all installed plugins' install.xml files.
     *
     * Returns ['tablename' => ['colname' => ['reftable' => '...', 'refcol' => '...'], ...], ...].
     *
     * @return array<string, array>
     */
    private static function build_fk_map(): array {
        global $CFG;

        $map = [];
        $xmlfiles = [];

        $corefile = $CFG->libdir . '/db/install.xml';
        if (file_exists($corefile)) {
            $xmlfiles[] = $corefile;
        }

        foreach (array_keys(\core_component::get_plugin_types()) as $type) {
            foreach (\core_component::get_plugin_list($type) as $plugindir) {
                $xmlfile = $plugindir . '/db/install.xml';
                if (file_exists($xmlfile)) {
                    $xmlfiles[] = $xmlfile;
                }
            }
        }

        foreach ($xmlfiles as $file) {
            $xml = @simplexml_load_file($file);
            if (!$xml || !isset($xml->TABLES)) {
                continue;
            }
            foreach ($xml->TABLES->TABLE as $table) {
                $tablename = strtolower((string) $table['NAME']);
                if (!isset($table->KEYS)) {
                    continue;
                }
                foreach ($table->KEYS->KEY as $key) {
                    if (strtolower((string) $key['TYPE']) !== 'foreign') {
                        continue;
                    }
                    $fields = array_map('trim', explode(',', strtolower((string) $key['FIELDS'])));
                    $reftable = strtolower((string) $key['REFTABLE']);
                    $reffields = array_map('trim', explode(',', strtolower((string) $key['REFFIELDS'])));
                    foreach ($fields as $i => $field) {
                        $map[$tablename][$field] = [
                            'reftable' => $reftable,
                            'refcol'   => $reffields[$i] ?? $reffields[0],
                        ];
                    }
                }
            }
        }

        return $map;
    }
}
