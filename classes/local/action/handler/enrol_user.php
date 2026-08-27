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
 * Manually enrol selected users into a course, in a chosen role.
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrol_user extends manual_enrol_action {
    public function key(): string {
        return 'enrol_user';
    }

    public function label(): string {
        return get_string('actionenrol', 'report_sql');
    }

    public function required_capability(): string {
        return 'enrol/manual:enrol';
    }

    protected function apply_one(int $subjectid, context $targetctx, array $params): void {
        $courseid = (int) $targetctx->instanceid;
        $instance = $this->manual_instance($courseid);
        $plugin = enrol_get_plugin('manual');
        $plugin->enrol_user($instance, $subjectid, $this->resolve_roleid($params));
    }

    /**
     * The role to assign on enrolment: the configured param, else the site's default student role,
     * else 0 (enrolled with no role).
     *
     * @param array $params
     * @return int
     */
    private function resolve_roleid(array $params): int {
        $roleid = (int) ($params['roleid'] ?? 0);
        if ($roleid > 0) {
            return $roleid;
        }
        $studentroles = get_archetype_roles('student');
        $first = reset($studentroles);
        return $first ? (int) $first->id : 0;
    }
}
