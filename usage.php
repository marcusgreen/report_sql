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
 * Report-usage overview: how often each published report source has been opened.
 *
 * Rendered as a Report Builder system report (search / sort / filter / export), sourced from the
 * report_sql_queryview audit table. Gated on the viewall capability because view history
 * is manager-level data.
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use core_reportbuilder\system_report_factory;
use report_sql\reportbuilder\local\systemreports\usage;

admin_externalpage_setup('report_sql_usage');

$context = context_system::instance();
require_capability('report/sql:viewall', $context);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('usage:title', 'report_sql'));
echo html_writer::div(get_string('usage:intro', 'report_sql'), 'text-muted mb-3');

$report = system_report_factory::create(usage::class, $context, 'report_sql');
echo $report->output();

echo html_writer::div(
    html_writer::link(
        new moodle_url('/report/sql/index.php'),
        get_string('back'),
        ['class' => 'btn btn-secondary']
    ),
    'mt-3'
);

echo $OUTPUT->footer();
