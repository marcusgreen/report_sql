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

use report_sql\local\roles;

/**
 * Tests for the optional "Report author" role helper.
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \report_sql\local\roles
 */
final class roles_test extends \advanced_testcase {
    /**
     * Permission level recorded for a capability on the role, or null if not set.
     *
     * @param int $roleid
     * @param string $capability
     * @return int|null
     */
    private function role_cap(int $roleid, string $capability): ?int {
        global $DB;
        $val = $DB->get_field('role_capabilities', 'permission', [
            'roleid'     => $roleid,
            'capability' => $capability,
        ]);
        return $val === false ? null : (int) $val;
    }

    public function test_create_grants_all_three_when_requested(): void {
        global $DB;
        $this->resetAfterTest();

        $result = roles::create_report_author_role(true, true);

        $this->assertTrue($result['created']);
        $role = $DB->get_record('role', ['shortname' => roles::REPORT_AUTHOR_SHORTNAME], '*', MUST_EXIST);
        $this->assertSame($result['roleid'], (int) $role->id);

        $this->assertSame(CAP_ALLOW, $this->role_cap((int) $role->id, 'report/sql:author'));
        $this->assertSame(CAP_ALLOW, $this->role_cap((int) $role->id, 'report/sql:approve'));
        $this->assertSame(CAP_ALLOW, $this->role_cap((int) $role->id, 'report/sql:viewall'));

        // Assignable at system context only.
        $levels = $DB->get_fieldset_select('role_context_levels', 'contextlevel', 'roleid = ?', [$role->id]);
        $this->assertSame([CONTEXT_SYSTEM], array_map('intval', $levels));
    }

    public function test_author_only_when_options_off(): void {
        $this->resetAfterTest();

        $result = roles::create_report_author_role(false, false);
        $roleid = $result['roleid'];

        $this->assertSame(CAP_ALLOW, $this->role_cap($roleid, 'report/sql:author'));
        $this->assertNull($this->role_cap($roleid, 'report/sql:approve'));
        $this->assertNull($this->role_cap($roleid, 'report/sql:viewall'));
    }

    public function test_is_idempotent_and_updates_in_place(): void {
        global $DB;
        $this->resetAfterTest();

        $first = roles::create_report_author_role(true, true);
        $this->assertTrue($first['created']);

        // Second call: same role, options reduced — must not duplicate and must remove cleared caps.
        $second = roles::create_report_author_role(false, false);
        $this->assertFalse($second['created']);
        $this->assertSame($first['roleid'], $second['roleid']);
        $this->assertSame(1, $DB->count_records('role', ['shortname' => roles::REPORT_AUTHOR_SHORTNAME]));

        $this->assertSame(CAP_ALLOW, $this->role_cap($second['roleid'], 'report/sql:author'));
        $this->assertNull($this->role_cap($second['roleid'], 'report/sql:approve'));
        $this->assertNull($this->role_cap($second['roleid'], 'report/sql:viewall'));
    }

    public function test_assigned_user_can_author(): void {
        $this->resetAfterTest();

        $result = roles::create_report_author_role(true, true);
        $user = $this->getDataGenerator()->create_user();
        $syscontext = \context_system::instance();
        role_assign($result['roleid'], $user->id, $syscontext->id);

        $this->assertTrue(has_capability('report/sql:author', $syscontext, $user->id));
        $this->assertTrue(has_capability('report/sql:approve', $syscontext, $user->id));
        $this->assertTrue(has_capability('report/sql:viewall', $syscontext, $user->id));
    }
}
