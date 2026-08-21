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

use core_reportbuilder\event\report_viewed;
use report_sql\local\query;

/**
 * Tests for the report_viewed observer that records report-source usage.
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \report_sql\observer::report_viewed
 */
final class observer_test extends \advanced_testcase {
    /**
     * Viewing a report bound to one of our queries writes exactly one queryview row.
     */
    public function test_report_viewed_records_bound_report(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        // Report id 42 is bound to query id 7 via the plugin's config binding.
        set_config('queryid_for_report_42', 7, 'report_sql');

        $this->trigger_report_viewed(42);

        $rows = $DB->get_records(query::TABLE_VIEW);
        $this->assertCount(1, $rows);
        $row = reset($rows);
        $this->assertEquals(7, $row->queryid);
        $this->assertEquals(42, $row->reportid);
    }

    /**
     * Viewing a report that is not one of ours writes nothing.
     */
    public function test_report_viewed_ignores_foreign_report(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        // No binding for report id 99.
        $this->trigger_report_viewed(99);

        $this->assertSame(0, $DB->count_records(query::TABLE_VIEW));
    }

    /**
     * Fire a core report_viewed event for the given report id through the live event pipeline, so
     * the plugin's registered observer runs exactly as it would in production.
     *
     * @param int $reportid Report id carried as the event objectid.
     */
    private function trigger_report_viewed(int $reportid): void {
        report_viewed::create([
            'context'  => \context_system::instance(),
            'objectid' => $reportid,
        ])->trigger();
    }
}
