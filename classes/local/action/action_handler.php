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

namespace report_sql\local\action;

use context;

/**
 * A built-in bulk operation that can be run over rows selected in an actionable report.
 *
 * Handlers never touch the plugin's UI or SQL — each one wraps a single core Moodle API (enrol,
 * message, cohort, user update). {@see base_action} implements the security-critical part
 * ({@see execute()}: the per-subject capability gate and error isolation) once, so a concrete
 * handler only declares its identity/capability and implements {@see base_action::apply_one()}.
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface action_handler {
    /** Subject of the action is a user (subject id = user id). */
    public const SUBJECT_USER = 'user';

    /** Subject of the action is a course (subject id = course id). */
    public const SUBJECT_COURSE = 'course';

    /**
     * Stable machine key, e.g. 'enrol_user'. Stored in actionsmeta.ops and posted by the action bar.
     *
     * @return string
     */
    public function key(): string;

    /**
     * Localised label shown in the action bar `<select>`.
     *
     * @return string
     */
    public function label(): string;

    /**
     * Whether this operates on users or courses (drives which identity column is the subject).
     *
     * @return string One of SUBJECT_USER | SUBJECT_COURSE.
     */
    public function subject_type(): string;

    /**
     * Core capability the operator must hold, checked per target context at execute time (never a
     * substitute for the plugin's own report/sql:actexecute gate — this is the second gate).
     *
     * @return string
     */
    public function required_capability(): string;

    /**
     * Whether applying the action is hard to reverse (unenrol, suspend). Destructive ops get a
     * confirmation interstitial before dispatch.
     *
     * @return bool
     */
    public function is_destructive(): bool;

    /**
     * Run the action over the selected subject ids.
     *
     * @param int[] $subjectids
     * @param context $reportctx Context of the report the selection came from.
     * @param array $params Author-configured op parameters (e.g. roleid, cohortid).
     * @return action_result
     */
    public function execute(array $subjectids, context $reportctx, array $params): action_result;
}
