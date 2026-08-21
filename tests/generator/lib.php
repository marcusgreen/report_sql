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

use report_sql\local\query;

/**
 * Data generator for report_sql.
 *
 * Creates report-source query records for tests. Shared by PHPUnit (via
 * $this->getDataGenerator()->get_plugin_generator('report_sql')) and Behat (via the
 * companion behat generator and the "the following ... exist" step).
 *
 * @package   report_sql
 * @category  test
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_sql_generator extends component_generator_base {
    /** @var int Running counter for default query names. */
    protected $querycount = 0;

    /**
     * Create a report-source query record, optionally published.
     *
     * The record is always inserted as a draft first; when 'published' is truthy (or status is
     * 'published') {@see query::publish()} then builds the backing VIEW and Report Builder report,
     * exactly as the edit form would, so the resulting state matches a real publish.
     *
     * @param array|stdClass|null $record Overrides. Recognised keys: name, description, querysql,
     *        courseid, visible, ownerid, useridcolumn, coursecolumn, pagecoursecolumn, and the
     *        pseudo-key 'published' (bool). 'status' = 'published' is treated as published = true.
     * @return stdClass The stored query record.
     */
    public function create_query($record = null): stdClass {
        global $DB, $USER;

        $this->querycount++;
        $record = (array) $record;

        $publish = !empty($record['published'])
            || (($record['status'] ?? '') === query::STATUS_PUBLISHED);
        unset($record['published']);

        $now = time();
        $record += [
            'name'         => 'Report source ' . $this->querycount,
            'description'  => '',
            'querysql'     => 'SELECT id AS userid, firstname, lastname FROM {user}',
            'courseid'     => 0,
            'visible'      => 1,
            'ownerid'      => $USER->id,
            'timecreated'  => $now,
            'timemodified' => $now,
        ];
        // Publishing is driven through query::publish(); never insert a published row directly.
        $record['status'] = query::STATUS_DRAFT;

        $id = $DB->insert_record('report_sql_query', (object) $record);

        if ($publish) {
            query::get($id)->publish();
        }

        return $DB->get_record('report_sql_query', ['id' => $id], '*', MUST_EXIST);
    }
}
