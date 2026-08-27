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
 * Actionable view of a published query: rows with a select-all checkbox column plus a bulk-action
 * bar that runs a built-in Moodle operation (enrol / unenrol / suspend / message / cohort) over the
 * selected rows. The table is a plugin-owned {@see \report_sql\reportbuilder\local\systemreports\query_actions}
 * system report (core RB datasource reports have no row-selection UI); the bar posts to action_apply.php.
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use core_reportbuilder\system_report_factory;
use report_sql\form\bulk_action_form;
use report_sql\local\query;
use report_sql\reportbuilder\local\systemreports\query_actions;

$id = required_param('id', PARAM_INT);

require_login();

require_once(__DIR__ . '/lib.php');
report_sql_require_enabled();

$query = query::get($id);
$rec   = $query->record();

if ($rec->status !== query::STATUS_PUBLISHED) {
    throw new moodle_exception('errchartnotpublished', 'report_sql');
}
if (!$query->actions_enabled()) {
    throw new moodle_exception('erractionsdisabled', 'report_sql');
}

// The report lives in its course context when the query is course-scoped, else system — matching
// where report_visibility places the data report and where actexecute is evaluated.
$courseid = $query->courseid();
if ($courseid > 0) {
    $context = context_course::instance($courseid);
} else {
    $context = context_system::instance();
}

// Gate: both the plugin's bulk-action capability and the data report's own view permission. The
// system report re-checks the same pair in can_view(); this is the page-level guard.
require_capability('report/sql:actexecute', $context);
if (!$query->current_user_can_view_report()) {
    throw new moodle_exception('nopermissions', 'error', '', get_string('actionsettings', 'report_sql'));
}

$url = new moodle_url('/report/sql/actions.php', ['id' => $id]);
$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout($courseid ? 'incourse' : 'admin');
$PAGE->set_title(get_string('actionsettings', 'report_sql'));
$PAGE->set_heading(format_string($query->name()));

// Bulk-action bar; JS fills its hidden subjectids from the checked rows and enables Apply.
$form = new bulk_action_form(new moodle_url('/report/sql/action_apply.php'), [
    'id'  => $id,
    'ops' => $query->action_ops(),
]);

$report = system_report_factory::create(
    query_actions::class,
    $context,
    'report_sql',
    '',
    0,
    ['queryid' => $id, 'withcheckboxes' => true]
);

$PAGE->requires->js_call_amd('report_sql/bulk_actions', 'init', [
    'reportwrapperid' => 'rs-actions-report',
    'formid'          => bulk_action_form::FORM_ID,
]);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('actionsettings', 'report_sql'));
echo html_writer::div($report->output(), '', ['id' => 'rs-actions-report']);
$form->display();
echo $OUTPUT->footer();
