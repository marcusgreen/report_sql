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

namespace report_sql;

use report_sql\local\query;
use report_sql\task\purge_views;

/**
 * Tests for the purge_views retention task.
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \report_sql\task\purge_views
 */
final class purge_views_test extends \advanced_testcase {
    /**
     * With a retention window set, rows older than it are deleted and recent rows are kept.
     */
    public function test_execute_deletes_only_old_rows(): void {
        global $DB;
        $this->resetAfterTest();

        set_config('viewretaindays', 30, 'report_sql');
        $now = time();
        $old = $this->insert_view($now - 40 * DAYSECS);
        $recent = $this->insert_view($now - 10 * DAYSECS);

        (new purge_views())->execute();

        $this->assertFalse($DB->record_exists(query::TABLE_VIEW, ['id' => $old]));
        $this->assertTrue($DB->record_exists(query::TABLE_VIEW, ['id' => $recent]));
    }

    /**
     * A retention window of 0 disables purging — everything is kept.
     */
    public function test_execute_keeps_all_when_retention_zero(): void {
        global $DB;
        $this->resetAfterTest();

        set_config('viewretaindays', 0, 'report_sql');
        $this->insert_view(time() - 3650 * DAYSECS);

        (new purge_views())->execute();

        $this->assertSame(1, $DB->count_records(query::TABLE_VIEW));
    }

    /**
     * Insert a queryview row at the given view time.
     *
     * @param int $timeviewed Epoch seconds.
     * @return int New row id.
     */
    private function insert_view(int $timeviewed): int {
        global $DB;
        return (int) $DB->insert_record(query::TABLE_VIEW, (object) [
            'queryid'    => 1,
            'reportid'   => 1,
            'userid'     => 2,
            'timeviewed' => $timeviewed,
        ]);
    }
}
