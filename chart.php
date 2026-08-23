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
 * Render a chart for a published ad-hoc query.
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use report_sql\local\chart_presenter;
use report_sql\local\query;

$id       = required_param('id', PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);
$format   = optional_param('format', '', PARAM_ALPHA);

if ($courseid) {
    require_login($courseid);
    $context = context_course::instance($courseid);
} else {
    require_login();
    $context = context_system::instance();
}

$PAGE->set_context($context);

require_once(__DIR__ . '/lib.php');
report_sql_require_enabled();

$q   = query::get($id);
$rec = $q->record();

if ($rec->status !== query::STATUS_PUBLISHED) {
    throw new moodle_exception('errchartnotpublished', 'report_sql');
}

// The chart reads the same data as the RB report, so it is gated by exactly the same report-level
// access as /reportbuilder/view.php and the block: managers always, everyone else only if core RB's
// context + audience admit them. This is the single authoritative gate — a wide-audience report is
// reachable here by any audience member (e.g. a teacher), with rows still scoped by the per-user /
// teacher-course filters. (No separate plugin-capability gate: that was stricter than the audience
// and wrongly blocked course-level teachers from a site-wide report's chart.)
if (!$q->current_user_can_view_report()) {
    throw new moodle_exception(
        'nopermissions',
        'error',
        '',
        get_string('viewchart', 'report_sql')
    );
}

$chartmeta = $rec->chartmeta ? json_decode($rec->chartmeta, true) : [];
if (empty($chartmeta['type']) || $chartmeta['type'] === 'none') {
    throw new moodle_exception('errchartnotconfigured', 'report_sql');
}

$xcol     = (string) ($chartmeta['xcol'] ?? '');
$ycol     = (string) ($chartmeta['ycol'] ?? '');
$rowlimit = max(1, min(5000, (int) ($chartmeta['rowlimit'] ?? 200)));
$type     = $chartmeta['type'];

// Fetch only the published columns: columnsmeta is denylist-stripped at publish time, while the
// physical VIEW may still hold denied columns (password etc.) — never select *. Per-user queries
// are additionally scoped to the viewing user, mirroring the RB base condition in adhoc_query.
if (!$q->columns_meta()) {
    throw new moodle_exception('errchartnotconfigured', 'report_sql');
}
// Single shared fetch path: applies the same per-user / teacher-course row scoping as the RB
// report, so chart and CSV output cannot leak rows the report table would hide.
$rows = $q->fetch_rows_for_viewer($rowlimit);

// CSV export — stream and exit before any HTML output.
if ($format === 'csv') {
    $filename = clean_filename($rec->name) . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    if ($rows) {
        fputcsv($out, array_keys($rows[0]));
        // Neutralise spreadsheet formula injection: a leading =, +, -, @, tab or CR would
        // execute as a formula when the CSV is opened in Excel/Sheets.
        $escape = static fn($v) => preg_match('/^[=+\-@\t\r]/', (string) $v) ? "'" . $v : $v;
        foreach ($rows as $row) {
            fputcsv($out, array_map($escape, $row));
        }
    }
    fclose($out);
    exit;
}

// Single rendering path: the chart is now a Report Builder report. Send the HTML view there so the
// old inline Chart.js render is retired (CSV export above still streams from here). Falls through to
// the legacy render only when no chart report exists yet (e.g. a query not re-published since the
// chart report was introduced).
$chartreportid = $q->chart_report_id();
if ($chartreportid > 0) {
    redirect(new moodle_url('/reportbuilder/view.php', ['id' => $chartreportid]));
}

[$labels, $values] = chart_presenter::chart_series($rows, $xcol, $ycol, $q->column_textcase($xcol), $q->column_dateformat($xcol));

$chart = match ($type) {
    'line'            => new \core\chart_line(),
    'pie', 'doughnut' => new \core\chart_pie(),
    default           => new \core\chart_bar(),
};
if ($type === 'doughnut') {
    $chart->set_doughnut(true);
}

// Pie/doughnut segment values only show on hover (Chart.js tooltip). Append the value to each
// segment label so the number is always visible in the legend alongside its slice.
$chartlabels = $labels;
if ($type === 'pie' || $type === 'doughnut') {
    $chartlabels = array_map(static function ($label, $value) {
        // Drop a trailing .0 so whole numbers read cleanly (e.g. "42" not "42.0").
        $num = rtrim(rtrim(format_float($value, 2), '0'), '.');
        return $label . ' (' . $num . ')';
    }, $labels, $values);
}

$series = new \core\chart_series($ycol, $values);
$chart->add_series($series);
$chart->set_labels($chartlabels);
$chart->set_title(format_string($rec->name));

$PAGE->set_url(new moodle_url(
    '/report/sql/chart.php',
    ['id' => $id] + ($courseid ? ['courseid' => $courseid] : [])
));
$PAGE->set_pagelayout($courseid ? 'incourse' : 'admin');
$PAGE->set_title($rec->name);
$PAGE->set_heading($rec->name);

$indexurl = new moodle_url('/report/sql/index.php', $courseid ? ['courseid' => $courseid] : []);
$csvurl   = new moodle_url(
    '/report/sql/chart.php',
    ['id' => $id, 'format' => 'csv'] + ($courseid ? ['courseid' => $courseid] : [])
);

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($rec->name));
echo $OUTPUT->render_chart($chart, false);

$PAGE->requires->js_call_amd('report_sql/chart_download', 'init', [clean_filename($rec->name)]);

$actions = html_writer::link(
    $indexurl,
    html_writer::tag('i', '', ['class' => 'fa fa-arrow-left mr-1', 'aria-hidden' => 'true']) .
        get_string('back'),
    ['class' => 'btn btn-secondary btn-sm mr-2']
);
$actions .= html_writer::link(
    $csvurl,
    get_string('chartexportcsv', 'report_sql'),
    ['class' => 'btn btn-secondary btn-sm mr-2']
);
$actions .= html_writer::tag(
    'button',
    get_string('chartdownloadpng', 'report_sql'),
    ['id' => 'report-sql-download-png', 'class' => 'btn btn-secondary btn-sm mr-2']
);
$actions .= html_writer::tag(
    'button',
    get_string('chartprint', 'report_sql'),
    ['class' => 'btn btn-secondary btn-sm', 'onclick' => 'window.print(); return false;']
);

echo html_writer::div($actions, 'mt-3 noprint');

echo $OUTPUT->footer();
