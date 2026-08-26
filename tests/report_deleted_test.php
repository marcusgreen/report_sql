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

use core_reportbuilder\local\helpers\report as reporthelper;
use report_sql\local\query;

/**
 * Tests for healing a query whose bound Report Builder report is deleted outside this plugin
 * (directly through core's /reportbuilder/index.php).
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \report_sql\observer::report_deleted
 * @covers    \report_sql\local\query::on_report_deleted
 */
final class report_deleted_test extends \advanced_testcase {
    /**
     * Build save() form data for a published-able query.
     *
     * @param array $extra Overrides.
     * @return \stdClass
     */
    private function formdata(array $extra = []): \stdClass {
        return (object) array_merge([
            'name'     => 'Deletion heal view',
            'querysql' => 'SELECT id FROM {user}',
            'courseid' => 0,
            'visible'  => 1,
        ], $extra);
    }

    /**
     * Drop leftover VIEWs before the framework reset (which cannot DROP TABLE a VIEW).
     */
    protected function tearDown(): void {
        global $DB;
        $prefix = $DB->get_prefix() . 'report_sql_v_';
        $views = $DB->get_records_sql(
            "SELECT table_name FROM information_schema.views WHERE table_name LIKE ?",
            [$prefix . '%']
        );
        foreach ($views as $view) {
            $name = $view->table_name ?? reset($view);
            $DB->execute('DROP VIEW IF EXISTS ' . $name);
        }
        parent::tearDown();
    }

    /**
     * Deleting the primary data report through core demotes the query to a draft and sweeps every
     * dangling binding — reportid, viewname, columnsmeta and the queryid config — so the plugin's
     * "View report" link can no longer fatal on a missing report.
     */
    public function test_external_delete_of_data_report_demotes_query_to_draft(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $id = query::save($this->formdata());
        query::get($id)->publish();
        $reportid = (int) query::get($id)->reportid();

        // Sanity: published and bound before the deletion.
        $this->assertSame((string) $id, get_config('report_sql', 'queryid_for_report_' . $reportid));

        // Delete the report exactly as core's /reportbuilder/index.php would: this fires
        // \core_reportbuilder\event\report_deleted through the live pipeline, so our registered
        // observer runs.
        reporthelper::delete_report($reportid);

        $record = $DB->get_record(query::TABLE, ['id' => $id], '*', MUST_EXIST);
        $this->assertSame(query::STATUS_DRAFT, $record->status);
        $this->assertNull($record->reportid);
        $this->assertNull($record->chartreportid);
        $this->assertNull($record->viewname);
        $this->assertNull($record->columnsmeta);

        // Bindings and the report are gone (the orphan view drop is best-effort and shares the
        // well-covered unpublish path; its DB-engine-specific visibility is not asserted here).
        $this->assertEmpty(get_config('report_sql', 'queryid_for_report_' . $reportid));
        $this->assertFalse($DB->record_exists('reportbuilder_report', ['id' => $reportid]));
    }

    /**
     * After healing, the query re-publishes cleanly to a fresh report.
     */
    public function test_healed_query_can_be_republished(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $id = query::save($this->formdata());
        query::get($id)->publish();
        $reportid = (int) query::get($id)->reportid();

        reporthelper::delete_report($reportid);
        $this->assertSame(query::STATUS_DRAFT, query::get($id)->status());

        query::get($id)->publish();
        $newreportid = (int) query::get($id)->reportid();

        $this->assertSame(query::STATUS_PUBLISHED, query::get($id)->status());
        $this->assertNotEmpty($newreportid);
        $this->assertNotSame($reportid, $newreportid);
    }

    /**
     * Deleting the companion chart report clears only its denormalised id; the data report and the
     * published state are untouched.
     */
    public function test_external_delete_of_chart_report_clears_only_chartreportid(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $id = query::save($this->formdata([
            'querysql'      => 'SELECT id AS x, id AS y FROM {user}',
            'chart_type'    => 'bar',
            'chart_xcol'    => 'x',
            'chart_ycol'    => 'y',
        ]));
        query::get($id)->publish();

        $datareportid = (int) query::get($id)->reportid();
        $chartreportid = (int) $DB->get_field(query::TABLE, 'chartreportid', ['id' => $id]);
        $this->assertNotEmpty($chartreportid);

        reporthelper::delete_report($chartreportid);

        $record = $DB->get_record(query::TABLE, ['id' => $id], '*', MUST_EXIST);
        // Data report + publish state survive; only the chart id is cleared.
        $this->assertSame(query::STATUS_PUBLISHED, $record->status);
        $this->assertEquals($datareportid, (int) $record->reportid);
        $this->assertNull($record->chartreportid);
        $this->assertEmpty(get_config('report_sql', 'queryid_for_report_' . $chartreportid));
        // The data report binding is intact.
        $this->assertSame((string) $id, get_config('report_sql', 'queryid_for_report_' . $datareportid));
    }

    /**
     * Deleting a report that is not one of ours leaves published queries alone.
     */
    public function test_external_delete_of_foreign_report_is_noop(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $id = query::save($this->formdata());
        query::get($id)->publish();
        $reportid = (int) query::get($id)->reportid();

        // A report created directly on a core datasource, unrelated to this plugin.
        $foreignreport = reporthelper::create_report((object) [
            'name'   => 'Unrelated report',
            'source' => \core_user\reportbuilder\datasource\users::class,
        ], false);
        reporthelper::delete_report((int) $foreignreport->get('id'));

        // Our query is untouched.
        $record = $DB->get_record(query::TABLE, ['id' => $id], '*', MUST_EXIST);
        $this->assertSame(query::STATUS_PUBLISHED, $record->status);
        $this->assertEquals($reportid, (int) $record->reportid);
        $this->assertSame((string) $id, get_config('report_sql', 'queryid_for_report_' . $reportid));
    }

    /**
     * The plugin's own unpublish still tears down cleanly — the re-entrancy guard stops the
     * report_deleted observer from interfering with a deletion we initiate ourselves.
     */
    public function test_plugin_unpublish_not_disturbed_by_observer(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $id = query::save($this->formdata());
        query::get($id)->publish();
        $reportid = (int) query::get($id)->reportid();

        query::get($id)->unpublish();

        $record = $DB->get_record(query::TABLE, ['id' => $id], '*', MUST_EXIST);
        $this->assertSame(query::STATUS_DRAFT, $record->status);
        $this->assertNull($record->reportid);
        $this->assertFalse($DB->record_exists('reportbuilder_report', ['id' => $reportid]));
        $this->assertEmpty(get_config('report_sql', 'queryid_for_report_' . $reportid));
    }
}
