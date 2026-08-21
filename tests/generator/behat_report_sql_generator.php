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
 * Behat data generator for report_sql.
 *
 * Lets feature files create report-source queries with:
 *   Given the following "report_sql > queries" exist:
 *     | name      | querysql             | published |
 *     | Draft one | SELECT id FROM {user}| 0         |
 *
 * @package   report_sql
 * @category  test
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_report_sql_generator extends behat_generator_base {
    /**
     * Entities this plugin can create through the generic "the following ... exist" step.
     *
     * @return array
     */
    protected function get_creatable_entities(): array {
        return [
            'queries' => [
                'singular'      => 'query',
                'datagenerator' => 'query',
                'required'      => ['name'],
                // Resolve human-readable references to ids (get_course_id / get_user_id in the base).
                'switchids'     => ['course' => 'courseid', 'user' => 'ownerid'],
            ],
        ];
    }
}
