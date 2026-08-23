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

use report_sql\local\query;
use report_sql\local\report_visibility;

/**
 * Tests for the audience picker storage contract (audiencemeta build / explode / persistence).
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \report_sql\local\query
 * @covers    \report_sql\local\report_visibility
 */
final class audience_test extends \advanced_testcase {
    /**
     * Build a minimal valid form-data object for query::save().
     *
     * @param array $extra Extra/override fields.
     * @return \stdClass
     */
    private function formdata(array $extra = []): \stdClass {
        return (object) array_merge([
            'name'     => 'Test view',
            'querysql' => 'SELECT id FROM {user}',
            'courseid' => 0,
            'visible'  => 1,
        ], $extra);
    }

    public function test_default_audience_persists_as_null(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $id = query::save($this->formdata(['audiencetype' => 'default']));

        $this->assertNull($DB->get_field(query::TABLE, 'audiencemeta', ['id' => $id]));
    }

    public function test_allusers_audience_persists(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $id = query::save($this->formdata(['audiencetype' => 'allusers']));

        $meta = json_decode($DB->get_field(query::TABLE, 'audiencemeta', ['id' => $id]), true);
        $this->assertSame('allusers', $meta['type']);
    }

    public function test_courserole_audience_keeps_roles(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $id = query::save($this->formdata([
            'courseid'      => $course->id,
            'audiencetype'  => 'courserole',
            'audienceroles' => ['3', '4'],
        ]));

        $meta = json_decode($DB->get_field(query::TABLE, 'audiencemeta', ['id' => $id]), true);
        $this->assertSame('courserole', $meta['type']);
        $this->assertSame([3, 4], $meta['roles']);
    }

    public function test_none_audience_persists(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $id = query::save($this->formdata(['audiencetype' => 'none']));

        $meta = json_decode($DB->get_field(query::TABLE, 'audiencemeta', ['id' => $id]), true);
        $this->assertSame('none', $meta['type']);
    }

    public function test_explode_roundtrips_courserole(): void {
        $json = json_encode(['type' => 'courserole', 'roles' => [3, 5]]);

        $flat = report_visibility::explode_audiencemeta($json);

        $this->assertSame('courserole', $flat['audiencetype']);
        $this->assertSame([3, 5], $flat['audienceroles']);
        $this->assertSame([], $flat['audiencecohorts']);
    }

    public function test_explode_null_is_default(): void {
        $flat = report_visibility::explode_audiencemeta(null);

        $this->assertSame('default', $flat['audiencetype']);
        $this->assertSame([], $flat['audienceroles']);
        $this->assertSame([], $flat['audiencecohorts']);
    }

    /**
     * Build a courserole audience instance bound to the given course/roles.
     *
     * @param int $courseid
     * @param int[] $roles
     * @return \report_sql\reportbuilder\audience\courserole
     */
    private function courserole_instance(int $courseid, array $roles): reportbuilder\audience\courserole {
        $record = (object) ['configdata' => json_encode(['courseid' => $courseid, 'roles' => $roles])];
        return reportbuilder\audience\courserole::instance(0, $record);
    }

    public function test_courserole_get_sql_matches_for_existing_course(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        [$join, $where] = $this->courserole_instance((int) $course->id, [3])->get_sql('u');

        $this->assertNotEmpty($join);
        $this->assertStringContainsString('role_assignments', $join);
        $this->assertNotSame('1 = 0', $where);
    }

    public function test_courserole_get_sql_with_deleted_course_matches_no_one(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $courseid = (int) $course->id;
        delete_course($course, false);

        // A stale audience pointing at the deleted course must not throw; it matches no one.
        [$join, $where, $params] = $this->courserole_instance($courseid, [3])->get_sql('u');

        $this->assertSame('', $join);
        $this->assertSame('1 = 0', $where);
        $this->assertSame([], $params);
    }

    public function test_courserole_get_sql_with_no_roles_matches_no_one(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        [$join, $where] = $this->courserole_instance((int) $course->id, [])->get_sql('u');

        $this->assertSame('', $join);
        $this->assertSame('1 = 0', $where);
    }

    public function test_course_deleted_detaches_published_query(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $id = query::save($this->formdata([
            'courseid'     => $course->id,
            'audiencetype' => 'courserole',
            'audienceroles' => ['3'],
        ]));
        query::get($id)->publish();

        $reportid = (int) $DB->get_field(query::TABLE, 'reportid', ['id' => $id]);
        $this->assertNotEmpty($reportid);
        // The report started life in the course context.
        $coursecontext = \context_course::instance($course->id);
        $report = \core_reportbuilder\local\models\report::get_record(['id' => $reportid]);
        $this->assertSame((int) $coursecontext->id, (int) $report->get('contextid'));

        delete_course($course, false);

        // Query degraded to site-wide, picker forced to none.
        $rec = $DB->get_record(query::TABLE, ['id' => $id]);
        $this->assertSame(0, (int) $rec->courseid);
        $this->assertSame('none', json_decode($rec->audiencemeta, true)['type']);

        // Report re-pointed to system context (no dangling contextid) and audiences cleared.
        $report = \core_reportbuilder\local\models\report::get_record(['id' => $reportid]);
        $this->assertSame((int) \context_system::instance()->id, (int) $report->get('contextid'));
        $this->assertSame(0, \core_reportbuilder\local\models\audience::count_records(['reportid' => $reportid]));
        // The get_context() call must no longer throw.
        $this->assertInstanceOf(\core\context\system::class, $report->get_context());

        // Tear the query down so its backing VIEW is dropped before the DB reset — otherwise the
        // reset issues DROP TABLE on the surviving view and fails.
        query::get($id)->delete();
    }

    public function test_course_deleted_detaches_additional_reports(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $id = query::save($this->formdata([
            'courseid'      => $course->id,
            'audiencetype'  => 'courserole',
            'audienceroles' => ['3'],
        ]));
        query::get($id)->publish();

        // A second report bound to the same query — only tracked in config_plugins, not reportid.
        $additionalid = query::get($id)->create_additional_report();
        $coursecontext = \context_course::instance($course->id);
        $additional = \core_reportbuilder\local\models\report::get_record(['id' => $additionalid]);
        $this->assertSame((int) $coursecontext->id, (int) $additional->get('contextid'));

        delete_course($course, false);

        // The additional report must be detached too, not just the primary one.
        $additional = \core_reportbuilder\local\models\report::get_record(['id' => $additionalid]);
        $this->assertSame((int) \context_system::instance()->id, (int) $additional->get('contextid'));
        $this->assertSame(0, \core_reportbuilder\local\models\audience::count_records(['reportid' => $additionalid]));
        $this->assertInstanceOf(\core\context\system::class, $additional->get_context());

        // And tearing the query down deletes both reports without raising debugging().
        query::get($id)->delete();
        $this->assertFalse(\core_reportbuilder\local\models\report::get_record(['id' => $additionalid]));
    }
}
