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
 * List saved ad-hoc queries.
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use core_reportbuilder\system_report_factory;
use report_sql\reportbuilder\local\systemreports\queries;

$courseid = optional_param('courseid', 0, PARAM_INT);

if ($courseid) {
    require_login($courseid);
    $context = context_course::instance($courseid);
    if (
        !has_capability('report/sql:view', $context) &&
        !has_capability('report/sql:viewown', $context) &&
        !has_capability('report/sql:author', context_system::instance()) &&
        !has_capability('report/sql:viewall', context_system::instance())
    ) {
        require_capability('report/sql:view', $context);
    }
} else {
    require_login();
    $context = context_system::instance();
    if (
        !has_capability('report/sql:viewall', $context) &&
        !has_capability('report/sql:author', $context) &&
        !has_capability('report/sql:view', $context)
    ) {
        require_capability('report/sql:view', $context);
    }
}

require_once(__DIR__ . '/lib.php');
report_sql_require_enabled();

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url(
    '/report/sql/index.php',
    $courseid ? ['courseid' => $courseid] : []
));
$PAGE->set_pagelayout($courseid ? 'incourse' : 'admin');
$PAGE->set_title(get_string('queries', 'report_sql'));
$PAGE->set_heading(get_string('reportsources', 'report_sql'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('queries', 'report_sql') .
    $OUTPUT->help_icon('pluginexplained', 'report_sql'));

$syscontext = context_system::instance();
if (has_capability('report/sql:author', $syscontext)) {
    // Wrapped with a stable id so the user tour can anchor a step to the New report view button.
    $newbutton = html_writer::div(
        $OUTPUT->single_button(
            new moodle_url(
                '/report/sql/edit.php',
                $courseid ? ['courseid' => $courseid] : []
            ),
            get_string('addnew', 'report_sql'),
            'get'
        ),
        '',
        ['id' => 'rs-tour-newbutton']
    );

    // The sample browser is author-gated (same as this button's outer guard), so any author can
    // open it.
    $samplesbutton = $OUTPUT->single_button(
        new moodle_url('/report/sql/samples.php', ['single' => 1]),
        get_string('samples:samplelinklabel', 'report_sql'),
        'get'
    );

    echo html_writer::div($newbutton . $samplesbutton, 'd-flex flex-wrap gap-2 align-items-start');
}

// Render the Bulk actions menu (export / import / delete) shown at the foot of the listing.
$rendertransferbuttons = function () use ($OUTPUT, $syscontext) {
    if (!has_capability('report/sql:author', $syscontext)) {
        return;
    }
    $menu = new action_menu();
    $menu->set_menu_trigger(get_string('bulkactions', 'report_sql'), 'btn btn-secondary');
    $menu->add(new action_menu_link_secondary(
        new moodle_url('/report/sql/export.php'),
        null,
        get_string('export', 'report_sql')
    ));
    $menu->add(new action_menu_link_secondary(
        new moodle_url('/report/sql/import.php'),
        null,
        get_string('import', 'report_sql')
    ));
    $menu->add(new action_menu_link_secondary(
        new moodle_url('/report/sql/deletemany.php'),
        null,
        get_string('delete', 'report_sql')
    ));

    // Managers (viewall) get a Report usage button on the same row as Bulk actions.
    $usagebutton = '';
    if (has_capability('report/sql:viewall', $syscontext)) {
        $usagebutton = html_writer::link(
            new moodle_url('/report/sql/usage.php'),
            get_string('usage:linklabel', 'report_sql'),
            ['class' => 'btn btn-secondary']
        );
    }

    echo html_writer::div($OUTPUT->render($menu) . $usagebutton, 'd-flex flex-wrap gap-2 mt-4');
};

// Render the queries listing as a Report Builder system report: paging, per-column sorting and
// filtering come for free. The 'courseid' parameter scopes the base visibility condition to the
// course (see queries::build_visibility_condition()); row visibility mirrors
// query::visible_to_current_user().
$report = system_report_factory::create(
    queries::class,
    $context,
    'report_sql',
    '',
    0,
    ['courseid' => $courseid]
);
echo $report->output();

$rendertransferbuttons();

echo $OUTPUT->footer();
