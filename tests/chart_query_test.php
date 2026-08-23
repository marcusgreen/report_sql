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
use report_sql\reportbuilder\local\entities\chart_view;
use report_sql\reportbuilder\source\chart_query;

/**
 * Tests for the single-cell chart report: the chart_query source, chart_view entity, and the
 * publish-time create/reuse/delete wiring on query.
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \report_sql\reportbuilder\source\chart_query
 * @covers    \report_sql\reportbuilder\local\entities\chart_view
 */
final class chart_query_test extends \advanced_testcase {
    /**
     * Build valid form-data for query::save(). A two-column query gives the chart an x and y.
     *
     * @param array $extra Extra/override fields.
     * @return \stdClass
     */
    private function formdata(array $extra = []): \stdClass {
        return (object) array_merge([
            'name'     => 'Chart view',
            'querysql' => 'SELECT id AS x, id AS y FROM {user}',
            'courseid' => 0,
            'visible'  => 1,
        ], $extra);
    }

    /**
     * Form-data with a bar chart configured over the x/y columns.
     *
     * @param array $extra
     * @return \stdClass
     */
    private function chartformdata(array $extra = []): \stdClass {
        return $this->formdata(array_merge([
            'chart_type' => 'bar',
            'chart_xcol' => 'x',
            'chart_ycol' => 'y',
        ], $extra));
    }

    /**
     * Report persistents whose source is the chart datasource.
     *
     * @return \core_reportbuilder\local\models\report[]
     */
    private function chart_reports(): array {
        return \core_reportbuilder\local\models\report::get_records(['source' => chart_query::class]);
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
     * Publishing a query with a chart configured creates a bound chart report.
     *
     * @return void
     */
    public function test_publish_with_chart_creates_chart_report(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $id = query::save($this->chartformdata());
        query::get($id)->publish();

        $reports = $this->chart_reports();
        $this->assertCount(1, $reports);
        $chartreport = reset($reports);
        // Bound to the query via the shared config key.
        $bound = (int) get_config('report_sql', 'queryid_for_report_' . $chartreport->get('id'));
        $this->assertSame($id, $bound);
        // Denormalised onto the query record for base-field / UI use.
        $this->assertSame(
            (int) $chartreport->get('id'),
            (int) $DB->get_field(query::TABLE, 'chartreportid', ['id' => $id])
        );
        $this->assertSame((int) $chartreport->get('id'), query::get($id)->chart_report_id());
    }

    /**
     * Publishing a query with no chart configured creates no chart report.
     *
     * @return void
     */
    public function test_publish_without_chart_creates_no_chart_report(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $id = query::save($this->formdata());
        query::get($id)->publish();

        $this->assertCount(0, $this->chart_reports());
    }

    /**
     * Re-publishing reuses the existing chart report rather than duplicating it.
     *
     * @return void
     */
    public function test_chart_report_is_idempotent_on_republish(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $id = query::save($this->chartformdata());
        query::get($id)->publish();
        $first = reset($this->chart_reports());

        query::get($id)->publish();
        $reports = $this->chart_reports();
        $this->assertCount(1, $reports);
        $this->assertSame((int) $first->get('id'), (int) reset($reports)->get('id'));
    }

    /**
     * Switching the chart type to none on re-publish deletes the chart report and its binding.
     *
     * @return void
     */
    public function test_switch_to_no_chart_deletes_chart_report(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $id = query::save($this->chartformdata());
        query::get($id)->publish();
        $chartreportid = (int) reset($this->chart_reports())->get('id');

        // Re-save with no chart, then re-publish.
        query::save($this->formdata(['id' => $id, 'chart_type' => 'none']));
        query::get($id)->publish();

        $this->assertCount(0, $this->chart_reports());
        $this->assertFalse(get_config('report_sql', 'queryid_for_report_' . $chartreportid));
        // The denormalised column is cleared too.
        $this->assertNull($DB->get_field(query::TABLE, 'chartreportid', ['id' => $id]));
    }

    /**
     * Deleting the query tears down the chart report too (swept via bound_report_ids).
     *
     * @return void
     */
    public function test_delete_query_removes_chart_report(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $id = query::save($this->chartformdata());
        query::get($id)->publish();
        $this->assertCount(1, $this->chart_reports());

        query::get($id)->delete();
        $this->assertCount(0, $this->chart_reports());
    }

    /**
     * render_chart_cell() returns an inline base64 SVG <img> for a published chart query.
     *
     * @return void
     */
    public function test_render_chart_cell_returns_svg_image(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $id = query::save($this->chartformdata());
        query::get($id)->publish();

        $html = chart_view::render_chart_cell($id);
        $this->assertStringContainsString('<img', $html);
        $this->assertStringContainsString('data:image/svg+xml;base64,', $html);

        // The decoded payload is a real SVG document.
        $this->assertSame(1, preg_match('/base64,([A-Za-z0-9+\/=]+)/', $html, $m));
        $svg = base64_decode($m[1]);
        $this->assertStringStartsWith('<svg', $svg);
    }

    /**
     * render_chart_cell() returns empty for a missing id, an unpublished query, or one with no chart.
     *
     * @return void
     */
    public function test_render_chart_cell_empty_cases(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        // Missing id.
        $this->assertSame('', chart_view::render_chart_cell(0));
        $this->assertSame('', chart_view::render_chart_cell(999999));

        // Published but no chart configured.
        $nochart = query::save($this->formdata());
        query::get($nochart)->publish();
        $this->assertSame('', chart_view::render_chart_cell($nochart));
    }

    /**
     * The chart report is viewable through the core Report Builder report content generator,
     * exercising the source's single-row scoping end to end.
     *
     * @return void
     */
    public function test_chart_report_renders_single_row(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $id = query::save($this->chartformdata());
        query::get($id)->publish();
        $chartreportid = (int) reset($this->chart_reports())->get('id');

        $report = \core_reportbuilder\manager::get_report_from_persistent(
            \core_reportbuilder\local\models\report::get_record(['id' => $chartreportid], MUST_EXIST)
        );
        $this->assertNotNull($report);
        // One column (the chart) and, scoped to the single bound query row, exactly one data row.
        $this->assertCount(1, $report->get_active_columns());
    }
}
