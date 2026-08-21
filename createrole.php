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
 * Create the optional "Report author" role.
 *
 * Linked from the post-install notification and the plugin settings page. Lets an admin create a
 * system-context role that grants report authoring (and, optionally, publishing and view-all) to
 * non-administrators. Deliberately opt-in: authoring runs arbitrary SQL, so the role is effectively
 * a site-wide data-read grant — hence the warning shown before confirmation.
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use report_sql\form\createrole_form;
use report_sql\local\roles;

require_login();

admin_externalpage_setup('report_sql_createrole');

$indexurl = new moodle_url('/report/sql/index.php');
$rolesurl = new moodle_url('/admin/roles/assign.php', ['contextid' => context_system::instance()->id]);

$mform = new createrole_form(new moodle_url('/report/sql/createrole.php'));

if ($mform->is_cancelled()) {
    redirect($indexurl);
} else if ($data = $mform->get_data()) {
    $result = roles::create_report_author_role(
        !empty($data->approve),
        !empty($data->viewall),
        !empty($data->aigenerate)
    );

    $message = $result['created']
        ? get_string('createrole:done', 'report_sql')
        : get_string('createrole:updated', 'report_sql');
    // Send the admin to the system role-assignment page so they can add people straight away.
    redirect($rolesurl, $message, null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('createrole:title', 'report_sql'));

// If the role already exists, say so — the form then updates its capabilities in place.
if ($DB->record_exists('role', ['shortname' => roles::REPORT_AUTHOR_SHORTNAME])) {
    echo $OUTPUT->notification(get_string('createrole:exists', 'report_sql'), \core\output\notification::NOTIFY_INFO);
}

echo html_writer::tag('p', get_string('createrole:intro', 'report_sql'));
echo $OUTPUT->notification(get_string('createrole:warning', 'report_sql'), \core\output\notification::NOTIFY_WARNING);

$mform->display();
echo $OUTPUT->footer();
