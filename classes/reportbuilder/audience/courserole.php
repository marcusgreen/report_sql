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

namespace report_sql\reportbuilder\audience;

use context_course;
use context_system;
use core_reportbuilder\local\audiences\base;
use core_reportbuilder\local\helpers\database;
use MoodleQuickForm;

/**
 * Audience matching users who hold one of the given roles in a single course.
 *
 * A role assignment counts when it is made AT the course context or at any ancestor context (so a
 * manager assigned at category or site level still matches, mirroring how Moodle role inheritance
 * works). Generated programmatically by {@see \report_sql\local\report_visibility::apply()};
 * also manually addable through the core Report Builder audience picker, with its own
 * course + role picker widgets.
 *
 * configdata: ['courseid' => int, 'roles' => int[]].
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class courserole extends base {
    /**
     * Course picker + role picker. The role list is drawn from roles assignable at course
     * context level generically (using the front page course as a stand-in context, the same
     * trick core's systemrole audience uses at system context) rather than any specific
     * course, since the config form is rendered before a course may have been chosen.
     *
     * @param MoodleQuickForm $mform
     */
    public function get_config_form(MoodleQuickForm $mform): void {
        $mform->addElement('course', 'courseid', get_string('course'), ['multiple' => false]);
        $mform->addRule('courseid', null, 'required', null, 'client');

        $roles = get_assignable_roles(context_course::instance(SITEID), ROLENAME_ALIAS);
        $mform->addElement('autocomplete', 'roles', get_string('selectrole', 'role'), $roles, ['multiple' => true]);
        $mform->addRule('roles', null, 'required', null, 'client');
    }

    /**
     * Match users with one of the configured roles assigned at the course or an ancestor context.
     *
     * @param string $usertablealias
     * @return array{0:string,1:string,2:array<string,mixed>} [$join, $where, $params]
     */
    public function get_sql(string $usertablealias): array {
        global $DB;

        $config   = $this->get_configdata();
        $courseid = (int) ($config['courseid'] ?? 0);
        $roles    = array_map('intval', (array) ($config['roles'] ?? []));

        // No roles configured can never match anyone.
        if (!$roles) {
            return ['', '1 = 0', []];
        }

        // The bound course may have been deleted since the audience was created; a missing
        // course context must not bubble up and break the whole report list, so match no one.
        $coursecontext = context_course::instance($courseid, IGNORE_MISSING);
        if (!$coursecontext) {
            return ['', '1 = 0', []];
        }

        [$ra, $ctx] = database::generate_aliases(2);
        [$insql, $inparams] = $DB->get_in_or_equal($roles, SQL_PARAMS_NAMED, database::generate_param_name('_'));
        $coursectxid = database::generate_param_name();
        $coursepath  = database::generate_param_name();
        $pathmatch   = database::generate_param_name();

        $join = "
            JOIN {role_assignments} {$ra} ON {$ra}.userid = {$usertablealias}.id
            JOIN {context} {$ctx} ON {$ctx}.id = {$ra}.contextid";

        // Role assigned AT the course context, OR at any ancestor (its path is a prefix of ours).
        // The '/%' wildcard is bound as a parameter so sql_like() sees no literal % in its pattern.
        $where = "{$ra}.roleid {$insql} AND ({$ctx}.id = :{$coursectxid} OR " .
            $DB->sql_like(":{$coursepath}", $DB->sql_concat("{$ctx}.path", ":{$pathmatch}")) . ")";

        $params = $inparams + [
            $coursectxid => $coursecontext->id,
            $coursepath  => $coursecontext->path,
            $pathmatch   => '/%',
        ];

        return [$join, $where, $params];
    }

    /**
     * Friendly name of this audience type.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('audiencecourserole', 'report_sql');
    }

    /**
     * Description shown on the report's audience card, naming the bound course and roles.
     *
     * @return string
     */
    public function get_description(): string {
        global $DB;

        $config   = $this->get_configdata();
        $courseid = (int) ($config['courseid'] ?? 0);
        $roleids  = array_map('intval', (array) ($config['roles'] ?? []));

        $coursename = $DB->get_field('course', 'fullname', ['id' => $courseid]);
        if ($coursename === false) {
            $coursename = get_string('audiencecoursemissing', 'report_sql');
        }

        $roles = $roleids ? $DB->get_records_list('role', 'id', $roleids, 'sortorder') : [];
        $rolenames = role_fix_names($roles, context_system::instance(), ROLENAME_ALIAS, true);

        return get_string('audiencecourseroledesc', 'report_sql', format_string($coursename)) . ' '
            . $this->format_description_for_multiselect($rolenames);
    }

    /**
     * Anyone with approve capability and at least one assignable course-level role can add
     * this audience type (its own course + role picker let them configure it, independent of
     * any report_sql query).
     *
     * @return bool
     */
    public function user_can_add(): bool {
        if (!has_capability('report/sql:approve', context_system::instance())) {
            return false;
        }

        return (bool) get_assignable_roles(context_course::instance(SITEID), ROLENAME_ALIAS);
    }

    /**
     * Editable while the bound course still exists and the user retains approve capability.
     *
     * @return bool
     */
    public function user_can_edit(): bool {
        if (!has_capability('report/sql:approve', context_system::instance())) {
            return false;
        }

        $courseid = (int) ($this->get_configdata()['courseid'] ?? 0);
        return (bool) context_course::instance($courseid, IGNORE_MISSING);
    }
}
