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
 * Per-query usage panel: who opens one report source, how often, and the recent trend.
 *
 * View history is manager-level data, so the page is gated on the viewall capability. Reached from
 * the row action on the Report usage overview (usage.php).
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use report_sql\local\query;
use report_sql\local\usagestats;

$id = required_param('id', PARAM_INT);

require_login();
$context = context_system::instance();
require_capability('report/sql:viewall', $context);

$q = query::get($id);
$rec = $q->record();

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/report/sql/query_usage.php', ['id' => $id]));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('usage:detailtitle', 'report_sql', format_string($rec->name)));
$PAGE->set_heading(format_string($rec->name));

$summary = usagestats::summary($id);
$viewdatefmt = get_string('strftimeviewdate', 'report_sql');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('usage:detailtitle', 'report_sql', format_string($rec->name)));

$backbutton = html_writer::div(
    html_writer::link(
        new moodle_url('/report/sql/usage.php'),
        html_writer::tag('i', '', ['class' => 'fa fa-arrow-left me-1', 'aria-hidden' => 'true']) .
            get_string('back'),
        ['class' => 'btn btn-secondary']
    ),
    'mb-3'
);
echo $backbutton;

if ($summary->views === 0) {
    echo $OUTPUT->notification(get_string('usage:nodata', 'report_sql'), 'info');
    echo $OUTPUT->footer();
    exit;
}

// Headline figures.
$cards = [
    [get_string('usage:views', 'report_sql'), $summary->views],
    [get_string('usage:uniqueviewers', 'report_sql'), $summary->viewers],
    [get_string('usage:firstviewed', 'report_sql'), userdate($summary->firstviewed, $viewdatefmt)],
    [get_string('usage:lastviewed', 'report_sql'), userdate($summary->lastviewed, $viewdatefmt)],
];
$cardhtml = '';
foreach ($cards as [$label, $value]) {
    $cardhtml .= html_writer::div(
        html_writer::div($label, 'text-muted small') .
            html_writer::div($value, 'h4 mb-0'),
        'card body p-3 me-2 mb-2 border rounded'
    );
}
echo html_writer::div($cardhtml, 'd-flex flex-wrap mb-4');

// Trend chart — daily opens over the last 30 days.
$trend = usagestats::daily_counts($id, 30);
$chart = new \core\chart_bar();
$chart->add_series(new \core\chart_series(get_string('usage:views', 'report_sql'), $trend->counts));
$chart->set_labels($trend->labels);
echo $OUTPUT->heading(get_string('usage:trend', 'report_sql'), 3);
echo $OUTPUT->render_chart($chart, false);

// Top viewers.
$topviewers = usagestats::top_viewers($id, 10);
if ($topviewers) {
    echo $OUTPUT->heading(get_string('usage:topviewers', 'report_sql'), 3);
    $table = new html_table();
    $table->head = [
        get_string('user'),
        get_string('usage:views', 'report_sql'),
        get_string('usage:lastviewed', 'report_sql'),
    ];
    $table->attributes['class'] = 'generaltable';
    foreach ($topviewers as $viewer) {
        $table->data[] = [s($viewer->fullname), $viewer->views, userdate($viewer->lastviewed, $viewdatefmt)];
    }
    echo html_writer::table($table);
}

// Per-report breakdown — only meaningful when the query owns more than one report.
$perreport = usagestats::per_report($id);
if (count($perreport) > 1) {
    // History outlives the report: republishing (SQL edit) or deleting an extra report drops the
    // report row while its view rows are kept, so some reportids here no longer exist. Resolve the
    // survivors in one query — link those, label the rest as deleted rather than emit a dead link.
    $rids = array_map(static fn(\stdClass $r): int => $r->reportid, $perreport);
    $liverports = $DB->get_records_list('reportbuilder_report', 'id', $rids, '', 'id, name');

    echo $OUTPUT->heading(get_string('usage:perreport', 'report_sql'), 3);
    $table = new html_table();
    $table->head = [
        get_string('usage:report', 'report_sql'),
        get_string('usage:views', 'report_sql'),
        get_string('usage:lastviewed', 'report_sql'),
    ];
    $table->attributes['class'] = 'generaltable';

    // Live reports get one row each; every deleted report's views fold into a single trailing
    // "Deleted reports" row, so the breakdown stays uncluttered yet still sums to the headline total.
    $deletedviews = 0;
    $deletedlast = 0;
    foreach ($perreport as $report) {
        if (!isset($liverports[$report->reportid])) {
            $deletedviews += $report->views;
            $deletedlast = max($deletedlast, $report->lastviewed);
            continue;
        }
        $name = $liverports[$report->reportid]->name;
        $label = ($name !== null && $name !== '')
            ? format_string($name)
            : get_string('usage:reportn', 'report_sql', $report->reportid);
        $link = html_writer::link(
            new moodle_url('/reportbuilder/view.php', ['id' => $report->reportid]),
            $label
        );
        $table->data[] = [$link, $report->views, userdate($report->lastviewed, $viewdatefmt)];
    }
    if ($deletedviews > 0) {
        $table->data[] = [
            get_string('usage:reportsdeleted', 'report_sql'),
            $deletedviews,
            userdate($deletedlast, $viewdatefmt),
        ];
    }
    echo html_writer::table($table);
}

// Recent opens.
$recent = usagestats::recent($id, 20);
if ($recent) {
    echo $OUTPUT->heading(get_string('usage:recent', 'report_sql'), 3);
    $table = new html_table();
    $table->head = [get_string('user'), get_string('usage:when', 'report_sql')];
    $table->attributes['class'] = 'generaltable';
    foreach ($recent as $view) {
        $table->data[] = [s($view->fullname), userdate($view->timeviewed, $viewdatefmt)];
    }
    echo html_writer::table($table);
}

echo $OUTPUT->footer();
