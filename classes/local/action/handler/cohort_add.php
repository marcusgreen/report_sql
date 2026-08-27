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
use moodle_exception;
use report_sql\local\action\base_action;

/**
 * Add selected users to a cohort.
 *
 * The target cohort is author-configured (params['cohortid']). The capability moodle/cohort:assign
 * is checked in the cohort's own context (system or a course category).
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cohort_add extends base_action {
    public function key(): string {
        return 'cohort_add';
    }

    public function label(): string {
        return get_string('actioncohortadd', 'report_sql');
    }

    public function required_capability(): string {
        return 'moodle/cohort:assign';
    }

    protected function target_context(int $subjectid, context $reportctx, array $params): context {
        global $DB;
        $cohortid = (int) ($params['cohortid'] ?? 0);
        if ($cohortid <= 0) {
            throw new moodle_exception('actionerrnocohort', 'report_sql');
        }
        $cohort = $DB->get_record('cohort', ['id' => $cohortid], 'id, contextid', MUST_EXIST);
        return context::instance_by_id((int) $cohort->contextid);
    }

    protected function apply_one(int $subjectid, context $targetctx, array $params): void {
        global $CFG;
        require_once($CFG->dirroot . '/cohort/lib.php');

        $cohortid = (int) $params['cohortid'];
        if (!cohort_is_member($cohortid, $subjectid)) {
            cohort_add_member($cohortid, $subjectid);
        }
    }
}
