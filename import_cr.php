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
 * Import SQL reports from the Configurable Reports block as draft report sources.
 *
 * Lists every block_configurable_reports instance split into importable (translates cleanly through
 * {@see \report_sql\local\cr_import}) and rejected (with a reason). The admin ticks the ones
 * to import; each lands as a fresh draft owned by the current user, ready to publish.
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use report_sql\local\cr_import;

require_login();

admin_externalpage_setup('report_sql_importcr');

$indexurl = new moodle_url('/report/sql/index.php');
$pageurl = new moodle_url('/report/sql/import_cr.php');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('crimport:title', 'report_sql'));

if (!cr_import::available()) {
    echo $OUTPUT->notification(
        get_string('crimport:unavailable', 'report_sql'),
        \core\output\notification::NOTIFY_WARNING
    );
    echo html_writer::link($indexurl, get_string('back'));
    echo $OUTPUT->footer();
    exit;
}

// Handle the submitted selection.
if (optional_param('import', 0, PARAM_BOOL) && confirm_sesskey()) {
    $ids = optional_param_array('report', [], PARAM_INT);
    if (empty($ids)) {
        redirect(
            $pageurl,
            get_string('crimport:noneselected', 'report_sql'),
            null,
            \core\output\notification::NOTIFY_WARNING
        );
    }

    $result = cr_import::import($ids);

    $messages = [get_string('importdone', 'report_sql', $result['imported'])];
    if (!empty($result['demoted'])) {
        $messages[] = get_string('importdemoted', 'report_sql', implode(', ', array_keys($result['demoted'])));
    }
    if (!empty($result['skipped'])) {
        $messages[] = get_string('importskipped', 'report_sql', implode(', ', array_keys($result['skipped'])));
    }
    if (!empty($result['rejected'])) {
        $messages[] = get_string('importskipped', 'report_sql', implode(', ', array_keys($result['rejected'])));
    }

    redirect($indexurl, implode(' ', $messages), null, \core\output\notification::NOTIFY_SUCCESS);
}

$classified = cr_import::discover();

$importable = array_filter($classified, static fn(array $i): bool => $i['verdict'] === 'import');
$rejected = array_filter($classified, static fn(array $i): bool => $i['verdict'] === 'reject');

echo html_writer::tag('p', get_string('crimport:intro', 'report_sql'));

if (empty($importable)) {
    echo $OUTPUT->notification(
        get_string('crimport:noneimportable', 'report_sql'),
        \core\output\notification::NOTIFY_INFO
    );
} else {
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $pageurl->out(false)]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'import', 'value' => 1]);

    echo $OUTPUT->heading(get_string('crimport:importableheading', 'report_sql'), 3);

    $table = new html_table();
    $table->head = [
        '',
        get_string('crimport:colname', 'report_sql'),
        get_string('crimport:colnotes', 'report_sql'),
    ];
    $table->attributes['class'] = 'generaltable';
    foreach ($importable as $id => $info) {
        $checkbox = html_writer::empty_tag('input', [
            'type'    => 'checkbox',
            'name'    => 'report[]',
            'value'   => $id,
            'checked' => 'checked',
            'id'      => 'cr_report_' . $id,
        ]);
        $notes = $info['notes']
            ? html_writer::alist($info['notes'])
            : html_writer::tag(
                'span',
                get_string('crimport:noteclean', 'report_sql'),
                ['class' => 'text-muted']
            );
        $table->data[] = [
            $checkbox,
            html_writer::label(s($info['name']), 'cr_report_' . $id),
            $notes,
        ];
    }
    echo html_writer::table($table);

    echo html_writer::div(
        html_writer::empty_tag('input', [
            'type'  => 'submit',
            'class' => 'btn btn-primary',
            'value' => get_string('crimport:importselected', 'report_sql'),
        ]),
        'mt-2'
    );
    echo html_writer::end_tag('form');
}

if (!empty($rejected)) {
    echo $OUTPUT->heading(get_string('crimport:rejectedheading', 'report_sql'), 3);
    $table = new html_table();
    $table->head = [
        get_string('crimport:colname', 'report_sql'),
        get_string('crimport:coltype', 'report_sql'),
        get_string('crimport:colreason', 'report_sql'),
    ];
    $table->attributes['class'] = 'generaltable';
    foreach ($rejected as $info) {
        $table->data[] = [s($info['name']), s($info['type']), s($info['reason'])];
    }
    echo html_writer::table($table);
}

echo html_writer::div(html_writer::link($indexurl, get_string('back')), 'mt-3');

echo $OUTPUT->footer();
