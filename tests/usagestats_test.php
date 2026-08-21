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
use report_sql\local\usagestats;

/**
 * Tests for the per-query usage aggregates (Phase 2 usage panel).
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \report_sql\local\usagestats
 */
final class usagestats_test extends \advanced_testcase {
    /**
     * summary() counts total views, distinct viewers, and the first/last times, scoped to the query.
     */
    public function test_summary(): void {
        $this->resetAfterTest();
        $u1 = $this->getDataGenerator()->create_user();
        $u2 = $this->getDataGenerator()->create_user();

        // Query 5: u1 x2, u2 x1 across times 100/300/200. Query 9 must not leak in.
        $this->insert_view(5, 10, $u1->id, 100);
        $this->insert_view(5, 10, $u1->id, 300);
        $this->insert_view(5, 11, $u2->id, 200);
        $this->insert_view(9, 20, $u1->id, 999);

        $summary = usagestats::summary(5);

        $this->assertSame(3, $summary->views);
        $this->assertSame(2, $summary->viewers);
        $this->assertSame(100, $summary->firstviewed);
        $this->assertSame(300, $summary->lastviewed);
    }

    /**
     * summary() of a query with no views returns zeros and null times.
     */
    public function test_summary_empty(): void {
        $this->resetAfterTest();

        $summary = usagestats::summary(404);

        $this->assertSame(0, $summary->views);
        $this->assertSame(0, $summary->viewers);
        $this->assertNull($summary->firstviewed);
        $this->assertNull($summary->lastviewed);
    }

    /**
     * top_viewers() ranks users by view count, busiest first, with resolved names.
     */
    public function test_top_viewers(): void {
        $this->resetAfterTest();
        $heavy = $this->getDataGenerator()->create_user(['firstname' => 'Heavy', 'lastname' => 'User']);
        $light = $this->getDataGenerator()->create_user();

        $this->insert_view(5, 10, $heavy->id, 100);
        $this->insert_view(5, 10, $heavy->id, 200);
        $this->insert_view(5, 10, $heavy->id, 300);
        $this->insert_view(5, 10, $light->id, 150);

        $top = usagestats::top_viewers(5, 10);

        $this->assertCount(2, $top);
        $this->assertSame((int) $heavy->id, $top[0]->userid);
        $this->assertSame(3, $top[0]->views);
        $this->assertSame(300, $top[0]->lastviewed);
        $this->assertStringContainsString('Heavy', $top[0]->fullname);
        $this->assertSame((int) $light->id, $top[1]->userid);
        $this->assertSame(1, $top[1]->views);
    }

    /**
     * per_report() breaks the totals down by report id.
     */
    public function test_per_report(): void {
        $this->resetAfterTest();
        $u = $this->getDataGenerator()->create_user();

        $this->insert_view(5, 10, $u->id, 100);
        $this->insert_view(5, 10, $u->id, 400);
        $this->insert_view(5, 11, $u->id, 200);

        $perreport = usagestats::per_report(5);

        $this->assertCount(2, $perreport);
        $this->assertSame(10, $perreport[0]->reportid);
        $this->assertSame(2, $perreport[0]->views);
        $this->assertSame(400, $perreport[0]->lastviewed);
        $this->assertSame(11, $perreport[1]->reportid);
        $this->assertSame(1, $perreport[1]->views);
    }

    /**
     * recent() returns the newest opens first, capped at the limit.
     */
    public function test_recent(): void {
        $this->resetAfterTest();
        $u = $this->getDataGenerator()->create_user();

        $this->insert_view(5, 10, $u->id, 100);
        $this->insert_view(5, 10, $u->id, 300);
        $this->insert_view(5, 10, $u->id, 200);

        $recent = usagestats::recent(5, 2);

        $this->assertCount(2, $recent);
        $this->assertSame(300, $recent[0]->timeviewed);
        $this->assertSame(200, $recent[1]->timeviewed);
    }

    /**
     * daily_counts() buckets views per day across the window, zero-filling empty days.
     */
    public function test_daily_counts(): void {
        $this->resetAfterTest();
        $u = $this->getDataGenerator()->create_user();

        $today = usergetmidnight(time());
        $this->insert_view(5, 10, $u->id, $today + 3600);              // Today x2.
        $this->insert_view(5, 10, $u->id, $today + 7200);
        $this->insert_view(5, 10, $u->id, $today - 2 * DAYSECS + 60);  // Two days ago x1.

        $trend = usagestats::daily_counts(5, 7);

        $this->assertCount(7, $trend->labels);
        $this->assertCount(7, $trend->counts);
        // Last bucket = today, index 4 = two days ago (7 days: indexes 0..6, today=6).
        $this->assertSame(2, $trend->counts[6]);
        $this->assertSame(1, $trend->counts[4]);
        $this->assertSame(3, array_sum($trend->counts));
    }

    /**
     * Insert a queryview row.
     *
     * @param int $queryid
     * @param int $reportid
     * @param int|string $userid
     * @param int $timeviewed
     */
    private function insert_view(int $queryid, int $reportid, int|string $userid, int $timeviewed): void {
        global $DB;
        $DB->insert_record(query::TABLE_VIEW, (object) [
            'queryid'    => $queryid,
            'reportid'   => $reportid,
            'userid'     => (int) $userid,
            'timeviewed' => $timeviewed,
        ]);
    }
}
