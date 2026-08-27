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
use context_user;
use core\session\manager;
use moodle_exception;
use report_sql\local\action\base_action;

/**
 * Suspend selected user accounts (blocks login, keeps the record). Destructive.
 *
 * Refuses to touch site administrators or the operator's own account — both would be surprising and
 * a footgun in a bulk run — recording them as skipped rather than acting.
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class suspend_user extends base_action {
    public function key(): string {
        return 'suspend_user';
    }

    public function label(): string {
        return get_string('actionsuspend', 'report_sql');
    }

    public function required_capability(): string {
        return 'moodle/user:update';
    }

    public function is_destructive(): bool {
        return true;
    }

    protected function target_context(int $subjectid, context $reportctx, array $params): context {
        global $USER;
        if ($subjectid == $USER->id) {
            throw new moodle_exception('actionerrself', 'report_sql');
        }
        if (is_siteadmin($subjectid)) {
            throw new moodle_exception('actionerradmin', 'report_sql');
        }
        return context_user::instance($subjectid);
    }

    protected function apply_one(int $subjectid, context $targetctx, array $params): void {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/user/lib.php');

        $user = $DB->get_record('user', ['id' => $subjectid, 'deleted' => 0], 'id, suspended', MUST_EXIST);
        if ((int) $user->suspended === 1) {
            // Already suspended — nothing to do, treat as applied (idempotent).
            return;
        }
        user_update_user((object) ['id' => $subjectid, 'suspended' => 1], false, true);
        // Kill any live sessions so the block takes effect immediately.
        manager::kill_user_sessions($subjectid);
    }
}
