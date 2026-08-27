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
 * Lifecycle actions on a saved ad-hoc query: publish / unpublish / open report.
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use report_sql\local\query;

require_login();
require_sesskey();

$id     = required_param('id', PARAM_INT);
$action = required_param('action', PARAM_ALPHA);

$context = context_system::instance();

require_once(__DIR__ . '/lib.php');
report_sql_require_enabled();

$query   = query::get($id);

$returnurl = new moodle_url('/report/sql/index.php');

try {
    switch ($action) {
        case 'publish':
            require_capability('report/sql:approve', $context);
            $query->publish();
            $msg = get_string('publish', 'report_sql');
            break;
        case 'unpublish':
            require_capability('report/sql:approve', $context);
            $query->unpublish();
            $msg = get_string('unpublish', 'report_sql');
            break;
        case 'copy':
            require_capability('report/sql:author', $context);
            // Ownership/viewall is enforced inside duplicate() on the source query.
            $newid = $query->duplicate();
            redirect(
                new moodle_url('/report/sql/edit.php', ['id' => $newid]),
                get_string('copysuccess', 'report_sql'),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
            break;
        default:
            throw new moodle_exception('invalidaction');
    }
} catch (\moodle_exception $e) {
    redirect($returnurl, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
}

redirect($returnurl, $msg, null, \core\output\notification::NOTIFY_SUCCESS);
