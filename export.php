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
 * Select saved ad-hoc queries and download them as a JSON export.
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/filelib.php');

use report_sql\local\query;
use report_sql\local\transfer;

require_login();

$context = context_system::instance();
require_capability('report/sql:author', $context);

$returnurl = new moodle_url('/report/sql/index.php');
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/report/sql/export.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('export', 'report_sql'));
$PAGE->set_heading(get_string('reportsources', 'report_sql'));

// Download step: build the JSON for the selected ids and stream it.
if (optional_param('download', 0, PARAM_INT)) {
    require_sesskey();
    $ids = optional_param_array('queryids', [], PARAM_INT);
    if (!$ids) {
        redirect(
            $returnurl,
            get_string('errnoexportselection', 'report_sql'),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }
    $payload = transfer::export($ids);
    // Name the file after the query when a single one is exported; otherwise fall back to a dated name.
    if (count($payload['sources']) === 1) {
        $base = preg_replace('/\s+/', '_', trim($payload['sources'][0]['name']));
        $filename = clean_filename(($base !== '' ? $base : 'reportsource') . '.json');
    } else {
        $filename = clean_filename('report_sql-export-' . userdate(time(), '%Y%m%d-%H%M') . '.json');
    }
    send_file(
        json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        $filename,
        0,
        0,
        true,
        true,
        'application/json'
    );
}

$queries = query::visible_to_current_user();
// Authors can only export what they own; viewall users may export anything they can see.
if (!has_capability('report/sql:viewall', $context)) {
    $queries = array_filter($queries, static fn($q): bool => (int) $q->ownerid === (int) $USER->id);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('export', 'report_sql'));

if (!$queries) {
    echo $OUTPUT->notification(get_string('noqueries', 'report_sql'), 'info');
    echo $OUTPUT->single_button($returnurl, get_string('back'), 'get');
    echo $OUTPUT->footer();
    exit;
}

echo html_writer::tag('p', get_string('exportselecthelp', 'report_sql'));

echo html_writer::start_tag('form', [
    'method' => 'post',
    'action' => (new moodle_url('/report/sql/export.php'))->out(false),
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'download', 'value' => 1]);

// Master toggle to select / deselect every query at once.
echo html_writer::start_div('form-check mb-2');
echo html_writer::empty_tag('input', [
    'type'    => 'checkbox',
    'class'   => 'form-check-input',
    'id'      => 'report-sql-toggleall',
    'checked' => 'checked',
]);
echo html_writer::tag(
    'label',
    get_string('selectall'),
    ['class' => 'form-check-label font-weight-bold', 'for' => 'report-sql-toggleall']
);
echo html_writer::end_div();

$PAGE->requires->js_amd_inline(<<<'JS'
require(['jquery'], function($) {
    var $master = $('#report-sql-toggleall');
    var $items = $('input[name="queryids[]"]');
    $master.on('change', function() {
        $items.prop('checked', $master.prop('checked'));
    });
    $items.on('change', function() {
        $master.prop('checked', $items.length === $items.filter(':checked').length);
    });
});
JS);

foreach ($queries as $rec) {
    $label = format_string($rec->name) .
        ' ' . html_writer::tag(
            'span',
            get_string('status_' . $rec->status, 'report_sql'),
            ['class' => 'badge badge-secondary ml-1']
        );
    echo html_writer::start_div('form-check');
    echo html_writer::empty_tag('input', [
        'type'  => 'checkbox',
        'class' => 'form-check-input',
        'name'  => 'queryids[]',
        'id'    => 'q' . $rec->id,
        'value' => $rec->id,
        'checked' => 'checked',
    ]);
    echo html_writer::tag('label', $label, ['class' => 'form-check-label', 'for' => 'q' . $rec->id]);
    echo html_writer::end_div();
}

echo html_writer::start_div('mt-3');
echo html_writer::empty_tag('input', [
    'type'  => 'submit',
    'class' => 'btn btn-primary',
    'value' => get_string('exportselected', 'report_sql'),
]);
echo ' ' . html_writer::link($returnurl, get_string('cancel'), ['class' => 'btn btn-secondary']);
echo html_writer::end_div();
echo html_writer::end_tag('form');

echo $OUTPUT->footer();
