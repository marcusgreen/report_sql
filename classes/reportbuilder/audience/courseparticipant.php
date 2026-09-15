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
 * Audience matching the enrolled participants of a single course.
 *
 * Generated programmatically by {@see \report_sql\local\query::publish()} for queries
 * scoped to a course (courseid > 0); also manually addable through the core Report Builder
 * audience picker, with its own course-selector widget. The bound course id is carried in
 * configdata as ['courseid' => int].
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class courseparticipant extends base {
    /**
     * Course picker; when injected programmatically at publish time, courseid is preset and
     * the field is simply not touched by the user.
     *
     * @param MoodleQuickForm $mform
     */
    public function get_config_form(MoodleQuickForm $mform): void {
        $mform->addElement('course', 'courseid', get_string('course'), ['multiple' => false]);
        $mform->addRule('courseid', null, 'required', null, 'client');
    }

    /**
     * Match users with an active enrolment in the bound course.
     *
     * @param string $usertablealias
     * @return array{0:string,1:string,2:array<string,mixed>} [$join, $where, $params]
     */
    public function get_sql(string $usertablealias): array {
        $courseid = (int) ($this->get_configdata()['courseid'] ?? 0);

        $paramcourseid = database::generate_param_name();
        [$ue, $e] = database::generate_aliases(2);

        $join = "
            JOIN {user_enrolments} {$ue} ON {$ue}.userid = {$usertablealias}.id
            JOIN {enrol} {$e} ON {$e}.id = {$ue}.enrolid
                AND {$e}.courseid = :{$paramcourseid} AND {$e}.status = 0";
        $where = "{$ue}.status = 0";

        return [$join, $where, [$paramcourseid => $courseid]];
    }

    /**
     * Friendly name of this audience type.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('audiencecourseparticipant', 'report_sql');
    }

    /**
     * Description shown on the report's audience card, naming the bound course.
     *
     * @return string
     */
    public function get_description(): string {
        global $DB;

        $courseid = (int) ($this->get_configdata()['courseid'] ?? 0);
        $coursename = $DB->get_field('course', 'fullname', ['id' => $courseid]);
        if ($coursename === false) {
            $coursename = get_string('audiencecoursemissing', 'report_sql');
        }

        return get_string('audiencecourseparticipantdesc', 'report_sql', format_string($coursename));
    }

    /**
     * Anyone with approve capability can add this audience type (its own course picker lets
     * them choose the course, independent of any report_sql query).
     *
     * @return bool
     */
    public function user_can_add(): bool {
        return has_capability('report/sql:approve', context_system::instance());
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
