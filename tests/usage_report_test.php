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

use core_reportbuilder\system_report_factory;
use report_sql\local\query;
use report_sql\reportbuilder\local\systemreports\usage;

/**
 * Smoke test for the usage system report (report-view audit surfaced through the Reports API).
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \report_sql\reportbuilder\local\systemreports\usage
 */
final class usage_report_test extends \advanced_testcase {
    /**
     * Drop any published VIEWs before the framework reset (which cannot DROP TABLE a view).
     */
    protected function tearDown(): void {
        global $DB;
        $prefix = $DB->get_prefix() . 'report_sql_v_';
        $views = $DB->get_records_sql(
            "SELECT table_name FROM information_schema.views WHERE table_schema = DATABASE() AND table_name LIKE ?",
            [$prefix . '%']
        );
        foreach ($views as $view) {
            $name = $view->table_name ?? reset($view);
            $DB->execute('DROP VIEW IF EXISTS ' . $name);
        }
        parent::tearDown();
    }

    /**
     * A published query with recorded views renders in the usage report; an unpublished draft does not.
     */
    public function test_report_lists_published_with_view_counts(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();

        $published = query::save((object) [
            'name'     => 'Busy source',
            'querysql' => 'SELECT id FROM {user}',
            'courseid' => 0,
            'visible'  => 1,
        ]);
        query::get($published)->publish();
        $reportid = (int) query::get($published)->reportid();
        query::record_view($published, $reportid, 2, time());
        query::record_view($published, $reportid, 3, time());

        // A draft never appears — the report is filtered to published sources.
        query::save((object) [
            'name'     => 'Idle draft',
            'querysql' => 'SELECT id FROM {user}',
            'courseid' => 0,
            'visible'  => 1,
        ]);

        $PAGE->set_url(new \moodle_url('/report/sql/usage.php'));
        $report = system_report_factory::create(usage::class, \context_system::instance(), 'report_sql');
        $output = $report->output();

        $this->assertStringContainsString('Busy source', $output);
        $this->assertStringNotContainsString('Idle draft', $output);
    }
}
