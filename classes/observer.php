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

declare(strict_types=1);

namespace report_sql;

use core\event\course_deleted;
use core_reportbuilder\event\report_deleted;
use core_reportbuilder\event\report_viewed;
use report_sql\local\query;
use report_sql\local\report_visibility;

/**
 * Event observers for report_sql.
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * Detach any course-scoped queries when their course is deleted.
     *
     * A deleted course takes its context with it; reports we placed in that context would be left
     * with a dangling contextid that fatals the plugin's index list and the Report Builder viewer.
     *
     * @param course_deleted $event
     */
    public static function course_deleted(course_deleted $event): void {
        report_visibility::on_course_deleted((int) $event->objectid);
    }

    /**
     * Record a view when one of this plugin's published reports is opened.
     *
     * report_viewed fires for every report site-wide, so the common case is a report that is not
     * ours: the queryid_for_report_<id> config is memory-cached per request, so that miss costs no
     * DB hit and a row is written only for a bound plugin report.
     *
     * @param report_viewed $event
     */
    public static function report_viewed(report_viewed $event): void {
        $reportid = (int) $event->objectid;
        $queryid = (int) get_config('report_sql', 'queryid_for_report_' . $reportid);
        if ($queryid <= 0) {
            return; // Not one of our data reports.
        }
        query::record_view($queryid, $reportid, (int) $event->userid, (int) $event->timecreated);
    }

    /**
     * Heal a query whose bound Report Builder report was deleted directly through core's
     * /reportbuilder/index.php, which otherwise leaves a dangling reportid and makes the plugin's
     * "View report" link fatal with core RB's invalid-report error.
     *
     * report_deleted fires for every report site-wide; on_report_deleted() cheaply ignores any
     * report not bound to one of our queries (the queryid config is memory-cached per request).
     *
     * @param report_deleted $event
     */
    public static function report_deleted(report_deleted $event): void {
        query::on_report_deleted((int) $event->objectid);
    }
}
