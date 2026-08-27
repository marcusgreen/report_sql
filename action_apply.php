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
 * Dispatch a bulk action posted from actions.php over the selected rows.
 *
 * Double-gated (report/sql:actexecute at the report context AND the data report's own view
 * permission); each op is additionally checked per subject in its own target context by the handler
 * ({@see \report_sql\local\action\base_action}). Destructive ops (unenrol / suspend) show a confirm
 * interstitial first. Every applied action is logged via the action_applied event.
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use core\output\notification;
use report_sql\event\action_applied;
use report_sql\local\action\action_registry;
use report_sql\local\query;

/** @var int Maximum rows a single bulk action may touch. */
const REPORT_SQL_ACTION_MAXROWS = 500;

$id         = required_param('id', PARAM_INT);
$op         = required_param('op', PARAM_ALPHANUMEXT);
$subjectids = optional_param('subjectids', '', PARAM_SEQUENCE);
$confirm    = optional_param('confirm', 0, PARAM_BOOL);

require_login();
require_sesskey();

require_once(__DIR__ . '/lib.php');
report_sql_require_enabled();

$query = query::get($id);

if ($query->record()->status !== query::STATUS_PUBLISHED) {
    throw new moodle_exception('errchartnotpublished', 'report_sql');
}
if (!$query->actions_enabled()) {
    throw new moodle_exception('erractionsdisabled', 'report_sql');
}
// The op must be one this query offers, and a known handler.
if (!in_array($op, $query->action_ops(), true) || !($handler = action_registry::instance($op))) {
    throw new moodle_exception('erractionopinvalid', 'report_sql');
}

$courseid = $query->courseid();
$context = $courseid > 0 ? context_course::instance($courseid) : context_system::instance();

require_capability('report/sql:actexecute', $context);
if (!$query->current_user_can_view_report()) {
    throw new moodle_exception('nopermissions', 'error', '', get_string('actionsettings', 'report_sql'));
}

$returnurl = new moodle_url('/report/sql/actions.php', ['id' => $id]);

// Normalise the posted selection to a bounded set of distinct positive ids.
$ids = array_values(array_unique(array_filter(
    array_map('intval', explode(',', $subjectids)),
    static fn(int $v): bool => $v > 0
)));
if (!$ids) {
    redirect($returnurl, get_string('actionnorows', 'report_sql'), null, notification::NOTIFY_WARNING);
}
if (count($ids) > REPORT_SQL_ACTION_MAXROWS) {
    redirect(
        $returnurl,
        get_string('erractiontoomany', 'report_sql', REPORT_SQL_ACTION_MAXROWS),
        null,
        notification::NOTIFY_ERROR
    );
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/report/sql/action_apply.php', ['id' => $id]));
$PAGE->set_pagelayout($courseid ? 'incourse' : 'admin');
$PAGE->set_title(get_string('actionsettings', 'report_sql'));
$PAGE->set_heading(format_string($query->name()));

// Destructive ops (unenrol / suspend) get a confirmation step before anything is changed.
if ($handler->is_destructive() && !$confirm) {
    $continueurl = new moodle_url('/report/sql/action_apply.php', [
        'id'         => $id,
        'op'         => $op,
        'subjectids' => implode(',', $ids),
        'confirm'    => 1,
        'sesskey'    => sesskey(),
    ]);
    $message = get_string('actionconfirm', 'report_sql', (object) [
        'op'    => $handler->label(),
        'count' => count($ids),
    ]);
    echo $OUTPUT->header();
    echo $OUTPUT->confirm($message, $continueurl, $returnurl);
    echo $OUTPUT->footer();
    exit;
}

// Run it. The handler applies the per-subject capability gate and isolates per-subject failures.
$result = $handler->execute($ids, $context, $query->action_params());

action_applied::create_and_trigger_action(
    $id,
    $query->name(),
    $context,
    $op,
    $result->applied_count(),
    $result->skipped_count()
);

$notice = get_string('actiondone', 'report_sql', (object) [
    'applied' => $result->applied_count(),
    'skipped' => $result->skipped_count(),
]);
$type = $result->applied_count() > 0 ? notification::NOTIFY_SUCCESS : notification::NOTIFY_WARNING;
redirect($returnurl, $notice, null, $type);
