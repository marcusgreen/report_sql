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
 * Import saved ad-hoc queries from a JSON export file.
 *
 * Step 1: upload the file. Step 2: tick which sources to import. Each chosen source becomes a new
 * draft owned by the importing user. The decoded file is round-tripped through a hidden field
 * between steps so the user never has to upload twice.
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use report_sql\form\import_form;
use report_sql\local\transfer;

require_login();

$context = context_system::instance();
require_capability('report/sql:author', $context);

$returnurl = new moodle_url('/report/sql/index.php');
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/report/sql/import.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('import', 'report_sql'));
$PAGE->set_heading(get_string('reportsources', 'report_sql'));

// Step 3: the selection form was submitted — create the chosen drafts.
if (optional_param('doimport', 0, PARAM_INT)) {
    require_sesskey();
    $payload  = optional_param('payload', '', PARAM_RAW);
    $selected = optional_param_array('sources', [], PARAM_INT);

    $json = base64_decode($payload, true);
    if ($json === false) {
        redirect(
            $returnurl,
            get_string('errimportformat', 'report_sql'),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }
    $sources = transfer::parse($json);

    if (!$selected) {
        redirect(
            $returnurl,
            get_string('errnoimportselection', 'report_sql'),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }

    $result = transfer::import($sources, $selected);
    $message = get_string('importdone', 'report_sql', $result['imported']);
    if ($result['skipped']) {
        $message .= ' ' . get_string(
            'importskipped',
            'report_sql',
            implode(', ', array_keys($result['skipped']))
        );
    }
    if (!empty($result['demoted'])) {
        $message .= ' ' . get_string(
            'importdemoted',
            'report_sql',
            implode(', ', array_keys($result['demoted']))
        );
    }
    redirect($returnurl, $message, null, \core\output\notification::NOTIFY_SUCCESS);
}

$mform = new import_form();

if ($mform->is_cancelled()) {
    redirect($returnurl);
}

// Step 2: a file was uploaded — parse it and show the per-source selection form.
if ($data = $mform->get_data()) {
    $json = $mform->get_file_content('importfile');
    if ($json === false) {
        redirect(
            $PAGE->url,
            get_string('errimportformat', 'report_sql'),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }

    $sources = transfer::parse($json);

    // Hide any source whose required third-party plugin is not installed here — its tables are
    // absent, so it could never publish. Keys are preserved (no reindex) so each survivor's $index
    // still matches the freshly re-parsed payload at the import step. import() also refuses these,
    // but hiding them keeps an unselectable, un-importable row out of the picker.
    $hidden = [];
    foreach ($sources as $index => $source) {
        foreach ($source['requires'] ?? [] as $component) {
            if (!transfer::component_available($component)) {
                $hidden[] = (string) $source['name'];
                unset($sources[$index]);
                break;
            }
        }
    }

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('import', 'report_sql'));

    if (!$sources) {
        // Either the file held nothing importable, or every source needs a missing plugin.
        $msg = $hidden
            ? get_string('importallhidden', 'report_sql', implode(', ', $hidden))
            : get_string('errimportempty', 'report_sql');
        echo $OUTPUT->notification($msg, 'error');
        echo $OUTPUT->single_button($PAGE->url, get_string('back'), 'get');
        echo $OUTPUT->footer();
        exit;
    }

    echo html_writer::tag('p', get_string('importselecthelp', 'report_sql'));

    if ($hidden) {
        echo $OUTPUT->notification(
            get_string('importhidden', 'report_sql', implode(', ', $hidden)),
            \core\output\notification::NOTIFY_INFO
        );
    }

    echo html_writer::start_tag('form', [
        'method' => 'post',
        'action' => $PAGE->url->out(false),
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'doimport', 'value' => 1]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'payload', 'value' => base64_encode($json)]);

    foreach ($sources as $index => $source) {
        $label = format_string($source['name']);
        if ($source['description'] !== '') {
            $label .= ' ' . html_writer::tag(
                'small',
                shorten_text(s($source['description']), 80),
                ['class' => 'text-muted']
            );
        }
        echo html_writer::start_div('form-check');
        echo html_writer::empty_tag('input', [
            'type'    => 'checkbox',
            'class'   => 'form-check-input',
            'name'    => 'sources[]',
            'id'      => 's' . $index,
            'value'   => $index,
            'checked' => 'checked',
        ]);
        echo html_writer::tag('label', $label, ['class' => 'form-check-label', 'for' => 's' . $index]);
        echo html_writer::end_div();
    }

    echo html_writer::start_div('mt-3');
    echo html_writer::empty_tag('input', [
        'type'  => 'submit',
        'class' => 'btn btn-primary',
        'value' => get_string('importselected', 'report_sql'),
    ]);
    echo ' ' . html_writer::link($returnurl, get_string('cancel'), ['class' => 'btn btn-secondary']);
    echo html_writer::end_div();
    echo html_writer::end_tag('form');

    echo $OUTPUT->footer();
    exit;
}

// Step 1: show the upload form.
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('import', 'report_sql'));
echo html_writer::tag('p', get_string('importuploadhelp', 'report_sql'));
$mform->display();
echo $OUTPUT->footer();
