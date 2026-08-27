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

namespace report_sql\local\action\handler;

use context;
use context_course;
use moodle_exception;
use report_sql\local\action\base_action;
use stdClass;

/**
 * Shared course + manual-enrolment resolution for the enrol / unenrol handlers.
 *
 * Both operate on users but the capability is checked in the target course context. The course is
 * taken from the author-configured `courseid` param, falling back to the report's own course
 * context (for a course-scoped query). Requires an enabled manual enrolment instance in the course.
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class manual_enrol_action extends base_action {
    protected function target_context(int $subjectid, context $reportctx, array $params): context {
        return context_course::instance($this->resolve_courseid($reportctx, $params));
    }

    /**
     * The course these enrolments act on: the configured param, else the report's course context.
     *
     * @param context $reportctx
     * @param array $params
     * @return int
     */
    protected function resolve_courseid(context $reportctx, array $params): int {
        $courseid = (int) ($params['courseid'] ?? 0);
        if ($courseid <= 0) {
            $coursectx = $reportctx->get_course_context(false);
            $courseid = $coursectx ? (int) $coursectx->instanceid : 0;
        }
        if ($courseid <= SITEID) {
            throw new moodle_exception('actionerrnocourse', 'report_sql');
        }
        return $courseid;
    }

    /**
     * The enabled manual enrolment instance for a course, or throw if none.
     *
     * @param int $courseid
     * @return stdClass
     */
    protected function manual_instance(int $courseid): stdClass {
        $instances = enrol_get_instances($courseid, true);
        foreach ($instances as $instance) {
            if ($instance->enrol === 'manual') {
                return $instance;
            }
        }
        throw new moodle_exception('actionerrnomanual', 'report_sql');
    }
}
