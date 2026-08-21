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
 * Installs the bundled user tour for the report views list page.
 *
 * tool_usertours' own shipped-tour mechanism only scans core's tours directory, so a third-party
 * plugin has to import its tour itself. This is done once at install/upgrade and is idempotent: if
 * a tour already targets the plugin's index page it is left alone, so re-running never duplicates it
 * and an admin who deletes or edits the tour is not overridden.
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tour {
    /** @var string Path glob the bundled tour matches; also used to detect an existing import. */
    private const PATHMATCH = '/report/sql/index.php%';

    /**
     * Import the bundled tour unless one already exists for the plugin's index page.
     *
     * @return void
     */
    public static function install(): void {
        global $CFG, $DB;

        // The tool_usertours plugin must be present (it is core, but guard for safety).
        if (!class_exists(\tool_usertours\manager::class)) {
            return;
        }

        // Idempotent: skip if a tour already targets the plugin's index page.
        if ($DB->record_exists('tool_usertours_tours', ['pathmatch' => self::PATHMATCH])) {
            return;
        }

        $file = $CFG->dirroot . '/report/sql/tours/report_sql_tour.json';
        if (!is_readable($file)) {
            return;
        }

        $json = file_get_contents($file);
        if ($json === false) {
            return;
        }

        \tool_usertours\manager::import_tour_from_json($json);
    }
}
