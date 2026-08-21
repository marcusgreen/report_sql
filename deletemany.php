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
 * Select saved ad-hoc queries and delete them in bulk (drops backing views + reports).
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use report_sql\local\query;

require_login();

$context = context_system::instance();
require_capability('report/sql:author', $context);

$returnurl = new moodle_url('/report/sql/index.php');
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/report/sql/deletemany.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('delete', 'report_sql'));
$PAGE->set_heading(get_string('reportsources', 'report_sql'));

$viewall = has_capability('report/sql:viewall', $context);

// Resolve the queries the current user is allowed to delete: authors may delete their own,
// viewall users may delete anything they can see.
$queries = query::visible_to_current_user();
if (!$viewall) {
    $queries = array_filter($queries, static fn($q): bool => (int) $q->ownerid === (int) $USER->id);
}
// Admin-created queries are locked to site admins regardless of capability.
if (!is_siteadmin($USER)) {
    $queries = array_filter($queries, static fn($q): bool => !is_siteadmin($q->ownerid));
}
$deletable = [];
foreach ($queries as $rec) {
    $deletable[(int) $rec->id] = $rec;
}

// Filter a posted id list down to ids the user is actually allowed to delete.
$resolveselection = static function () use ($deletable): array {
    $ids = optional_param_array('queryids', [], PARAM_INT);
    return array_values(array_filter($ids, static fn($id): bool => isset($deletable[(int) $id])));
};

// Final step: the user confirmed, so delete each selected query.
if (optional_param('confirm', 0, PARAM_INT)) {
    require_sesskey();
    $ids = $resolveselection();
    if (!$ids) {
        redirect(
            $returnurl,
            get_string('errnodeleteselection', 'report_sql'),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }
    // Tear-down can emit debugging() (e.g. a stale report/view that no longer deletes cleanly).
    // If that raw output reached the stream before redirect(), redirect() would close the
    // session and then render a full navigation page, mutating session caches after close
    // ("mutated the session after it was closed"). Buffer the loop so the output never trips
    // redirect into render mode, then fold any captured notice into the redirect message.
    ob_start();
    foreach ($ids as $id) {
        query::get($id)->delete();
    }
    $noise = trim(ob_get_clean());

    if ($noise !== '') {
        redirect(
            $returnurl,
            get_string('deleted') . ' ' . html_to_text($noise),
            null,
            \core\output\notification::NOTIFY_WARNING
        );
    }
    redirect(
        $returnurl,
        get_string('deleted'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// Confirmation step: the user chose what to delete, now show a confirm prompt.
if (optional_param('selected', 0, PARAM_INT)) {
    require_sesskey();
    $ids = $resolveselection();
    if (!$ids) {
        redirect(
            $returnurl,
            get_string('errnodeleteselection', 'report_sql'),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('delete', 'report_sql'));

    echo html_writer::tag('p', get_string('confirmdeletemany', 'report_sql', count($ids)));
    echo html_writer::start_tag('ul');
    foreach ($ids as $id) {
        echo html_writer::tag('li', format_string($deletable[$id]->name));
    }
    echo html_writer::end_tag('ul');

    // POST form carrying the selected ids into the final confirm=1 step.
    echo html_writer::start_tag('form', [
        'method' => 'post',
        'action' => (new moodle_url('/report/sql/deletemany.php'))->out(false),
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'confirm', 'value' => 1]);
    foreach ($ids as $id) {
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'queryids[]', 'value' => $id]);
    }
    echo html_writer::empty_tag('input', [
        'type'  => 'submit',
        'class' => 'btn btn-danger',
        'value' => get_string('delete', 'report_sql'),
    ]);
    echo ' ' . html_writer::link($returnurl, get_string('cancel'), ['class' => 'btn btn-secondary']);
    echo html_writer::end_tag('form');

    echo $OUTPUT->footer();
    exit;
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('delete', 'report_sql'));

if (!$deletable) {
    echo $OUTPUT->notification(get_string('noqueries', 'report_sql'), 'info');
    echo $OUTPUT->single_button($returnurl, get_string('back'), 'get');
    echo $OUTPUT->footer();
    exit;
}

echo html_writer::tag('p', get_string('deleteselecthelp', 'report_sql'));

echo html_writer::start_tag('form', [
    'method' => 'post',
    'action' => (new moodle_url('/report/sql/deletemany.php'))->out(false),
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'selected', 'value' => 1]);

// Master toggle to select / deselect every query at once.
echo html_writer::start_div('form-check mb-2');
echo html_writer::empty_tag('input', [
    'type'  => 'checkbox',
    'class' => 'form-check-input',
    'id'    => 'report-sql-toggleall',
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

foreach ($deletable as $rec) {
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
    ]);
    echo html_writer::tag('label', $label, ['class' => 'form-check-label', 'for' => 'q' . $rec->id]);
    echo html_writer::end_div();
}

echo html_writer::start_div('mt-3');
echo html_writer::empty_tag('input', [
    'type'  => 'submit',
    'class' => 'btn btn-danger',
    'value' => get_string('deleteselected', 'report_sql'),
]);
echo ' ' . html_writer::link($returnurl, get_string('cancel'), ['class' => 'btn btn-secondary']);
echo html_writer::end_div();
echo html_writer::end_tag('form');

echo $OUTPUT->footer();
