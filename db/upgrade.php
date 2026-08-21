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

/**
 * Upgrade steps for report_sql.
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Apply schema/data changes for each released version.
 *
 * No steps yet: this is the plugin's first release, so db/install.xml holds the full current schema
 * and there is no earlier installed version to upgrade from. Add versioned blocks here on future
 * releases.
 *
 * @param int $oldversion The currently installed plugin version.
 * @return bool
 */
function xmldb_report_sql_upgrade($oldversion) {
    return true;
}
