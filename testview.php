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
 * Admin probe: verify that the DB user can CREATE/DROP views.
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use report_sql\local\sql\privilege_check;

require_login();
require_capability('moodle/site:config', context_system::instance());

admin_externalpage_setup('report_sql_testview');

// The probe issues real DDL (CREATE/DROP VIEW), so it is a state-changing
// action: only run it in response to an explicit POST with a valid sesskey,
// never as a side effect of a plain GET load (CSRF-safe, guideline S8).
$run = optional_param('run', 0, PARAM_BOOL);
$result = ($run && confirm_sesskey()) ? privilege_check::probe() : null;

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('testview:title', 'report_sql'));

if ($result === null) {
    echo html_writer::tag('p', get_string('testview:intro', 'report_sql'));
} else if ($result['ok']) {
    echo $OUTPUT->notification(
        get_string('testview:ok', 'report_sql'),
        \core\output\notification::NOTIFY_SUCCESS
    );
} else {
    echo $OUTPUT->notification(
        get_string('testview:fail', 'report_sql', s($result['error'])),
        \core\output\notification::NOTIFY_ERROR
    );
    echo html_writer::tag('p', get_string('testview:grantshint', 'report_sql'));
}

echo $OUTPUT->single_button(
    new moodle_url('/report/sql/testview.php', ['run' => 1]),
    get_string('testview:run', 'report_sql'),
    'post'
);

echo html_writer::link(
    new moodle_url('/admin/settings.php', ['section' => 'report_sql']),
    get_string('back')
);

echo $OUTPUT->footer();
