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
use report_sql\reportbuilder\local\systemreports\queries;

/**
 * Smoke test for the queries system report, covering the usage columns' correlated subqueries.
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \report_sql\reportbuilder\local\systemreports\queries
 */
final class queries_report_test extends \advanced_testcase {
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
     * The report builds and renders with the usage columns, counting recorded views per query.
     */
    public function test_report_renders_usage_columns(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $id = query::save((object) [
            'name'     => 'Counted view',
            'querysql' => 'SELECT id FROM {user}',
            'courseid' => 0,
            'visible'  => 1,
        ]);
        query::get($id)->publish();
        $reportid = (int) query::get($id)->reportid();
        query::record_view($id, $reportid, 2, time());
        query::record_view($id, $reportid, 3, time());

        global $PAGE;
        $PAGE->set_url(new \moodle_url('/report/sql/index.php'));

        $report = system_report_factory::create(queries::class, \context_system::instance());

        // Rendering executes the base SQL plus the viewcount/lastviewed correlated subqueries.
        $output = $report->output();
        $this->assertStringContainsString('Counted view', $output);
    }
}
