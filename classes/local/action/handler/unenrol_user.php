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

/**
 * Remove selected users' manual enrolment from a course. Destructive.
 *
 * Only the manual enrolment instance is touched — other enrolment methods (self, cohort sync, LDAP)
 * are left alone, matching what a manual enrol could undo.
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class unenrol_user extends manual_enrol_action {
    public function key(): string {
        return 'unenrol_user';
    }

    public function label(): string {
        return get_string('actionunenrol', 'report_sql');
    }

    public function required_capability(): string {
        return 'enrol/manual:unenrol';
    }

    public function is_destructive(): bool {
        return true;
    }

    protected function apply_one(int $subjectid, context $targetctx, array $params): void {
        $courseid = (int) $targetctx->instanceid;
        $instance = $this->manual_instance($courseid);
        $plugin = enrol_get_plugin('manual');
        $plugin->unenrol_user($instance, $subjectid);
    }
}
