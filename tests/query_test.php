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

/**
 * Regression net for the query lifecycle (save / publish / unpublish / duplicate / delete /
 * listing) before the query god-class is split into focused units.
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \report_sql\local\query
 */
final class query_test extends \advanced_testcase {
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

    /**
     * Drop any VIEWs left behind by publish() before the framework reset runs. Moodle's PHPUnit
     * reset enumerates tables and issues DROP TABLE, which errors on a VIEW — so a test that leaves
     * a query published would otherwise fail in teardown rather than in the test body.
     */
    protected function tearDown(): void {
        global $DB;
        $prefix = $DB->get_prefix() . 'report_sql_v_';
        $views = $DB->get_records_sql(
            "SELECT table_name FROM information_schema.views WHERE table_name LIKE ?",
            [$prefix . '%']
        );
        foreach ($views as $view) {
            $name = $view->table_name ?? reset($view);
            $DB->execute('DROP VIEW IF EXISTS ' . $name);
        }
        parent::tearDown();
    }

    public function test_save_creates_draft_owned_by_current_user(): void {
        global $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();

        $id = query::save($this->formdata(['name' => 'My draft']));

        $record = $DB->get_record(query::TABLE, ['id' => $id], '*', MUST_EXIST);
        $this->assertSame('My draft', $record->name);
        $this->assertSame(query::STATUS_DRAFT, $record->status);
        $this->assertSame((int) $USER->id, (int) $record->ownerid);
        $this->assertNull($record->viewname);
        $this->assertNull($record->reportid);
    }

    public function test_save_updates_existing_record_in_place(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $id = query::save($this->formdata(['name' => 'Original']));
        $sameid = query::save($this->formdata(['id' => $id, 'name' => 'Renamed']));

        $this->assertSame($id, $sameid);
        $this->assertSame('Renamed', $DB->get_field(query::TABLE, 'name', ['id' => $id]));
        $this->assertSame(1, $DB->count_records(query::TABLE));
    }

    public function test_publish_sets_status_view_report_and_columnsmeta(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $id = query::save($this->formdata());
        query::get($id)->publish();

        $record = $DB->get_record(query::TABLE, ['id' => $id], '*', MUST_EXIST);
        $this->assertSame(query::STATUS_PUBLISHED, $record->status);
        $this->assertNotEmpty($record->viewname);
        $this->assertNotEmpty($record->reportid);

        $meta = json_decode($record->columnsmeta, true);
        $this->assertArrayHasKey('id', $meta);
        $this->assertSame('int', $meta['id']['type']);

        // The view actually exists and exposes its columns. Use the plugin's own DB-portable
        // introspection: core get_columns() returns nothing for a VIEW on PostgreSQL.
        $columns = \report_sql\local\sql\view::columns($record->viewname);
        $this->assertArrayHasKey('id', $columns);
    }

    public function test_publish_binds_queryid_config_to_report(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $id = query::save($this->formdata());
        query::get($id)->publish();
        $reportid = (int) query::get($id)->reportid();

        $this->assertSame(
            (string) $id,
            get_config('report_sql', 'queryid_for_report_' . $reportid)
        );
    }

    public function test_from_report_id_resolves_report_to_owning_query(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $id = query::save($this->formdata(['name' => 'Owner']));
        query::get($id)->publish();
        $reportid = (int) query::get($id)->reportid();

        $resolved = query::from_report_id($reportid);
        $this->assertNotNull($resolved);
        $this->assertSame($id, $resolved->id());
        $this->assertSame('Owner', $resolved->name());
    }

    public function test_from_report_id_null_for_unknown_report(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        // No config binding for this report id → no owning query.
        $this->assertNull(query::from_report_id(987654));
    }

    public function test_from_report_id_null_for_nonpositive_id(): void {
        $this->resetAfterTest();

        $this->assertNull(query::from_report_id(0));
        $this->assertNull(query::from_report_id(-5));
    }

    public function test_unpublish_reverts_to_draft_and_clears_artefacts(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $id = query::save($this->formdata());
        query::get($id)->publish();
        query::get($id)->unpublish();

        $record = $DB->get_record(query::TABLE, ['id' => $id], '*', MUST_EXIST);
        $this->assertSame(query::STATUS_DRAFT, $record->status);
        $this->assertNull($record->viewname);
        $this->assertNull($record->reportid);
        $this->assertNull($record->columnsmeta);
    }

    public function test_sql_edit_while_published_demotes_to_draft(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $id = query::save($this->formdata());
        query::get($id)->publish();
        $reportid = (int) query::get($id)->reportid();

        // Changing the SQL on a published query tears down view + report and returns to draft.
        query::save($this->formdata(['id' => $id, 'querysql' => 'SELECT id, username FROM {user}']));

        $record = $DB->get_record(query::TABLE, ['id' => $id], '*', MUST_EXIST);
        $this->assertSame(query::STATUS_DRAFT, $record->status);
        $this->assertNull($record->viewname);
        $this->assertNull($record->reportid);
        // The bound report config key is cleaned up by tear_down().
        $this->assertFalse(get_config('report_sql', 'queryid_for_report_' . $reportid));
    }

    public function test_duplicate_creates_draft_copy(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $id = query::save($this->formdata(['name' => 'Source']));
        query::get($id)->publish();
        $copyid = query::get($id)->duplicate();

        $this->assertNotSame($id, $copyid);
        $copy = $DB->get_record(query::TABLE, ['id' => $copyid], '*', MUST_EXIST);
        $this->assertSame(query::STATUS_DRAFT, $copy->status);
        $this->assertNull($copy->viewname);
        $this->assertNull($copy->reportid);
        $this->assertSame('SELECT id FROM {user}', $copy->querysql);
    }

    public function test_delete_removes_record_and_artefacts(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $id = query::save($this->formdata());
        query::get($id)->publish();
        $reportid = (int) query::get($id)->reportid();
        // Seed a view-history row; delete() must cascade it so nothing is orphaned.
        query::record_view($id, $reportid, 2, time());
        $this->assertSame(1, $DB->count_records(query::TABLE_VIEW, ['queryid' => $id]));

        query::get($id)->delete();

        $this->assertFalse($DB->record_exists(query::TABLE, ['id' => $id]));
        $this->assertFalse(get_config('report_sql', 'queryid_for_report_' . $reportid));
        $this->assertSame(0, $DB->count_records(query::TABLE_VIEW, ['queryid' => $id]));
    }

    public function test_visible_to_current_user_admin_sees_all(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $draftid = query::save($this->formdata(['name' => 'Draft one']));
        $pubid = query::save($this->formdata(['name' => 'Published two']));
        query::get($pubid)->publish();

        $visible = query::visible_to_current_user();
        $this->assertArrayHasKey($draftid, $visible);
        $this->assertArrayHasKey($pubid, $visible);
    }

    public function test_teacher_course_ids_returns_only_taught_courses(): void {
        $this->resetAfterTest();

        $gen = $this->getDataGenerator();
        $teacher = $gen->create_user();
        $taught = $gen->create_course();
        $studied = $gen->create_course();

        $gen->enrol_user($teacher->id, $taught->id, 'editingteacher');
        $gen->enrol_user($teacher->id, $studied->id, 'student');

        $ids = query::teacher_course_ids((int) $teacher->id);

        $this->assertContains((int) $taught->id, $ids);
        $this->assertNotContains((int) $studied->id, $ids);
    }

    public function test_teacher_course_ids_requires_active_enrolment(): void {
        global $DB;
        $this->resetAfterTest();

        $gen     = $this->getDataGenerator();
        $teacher = $gen->create_user();
        $course  = $gen->create_course();

        // Teacher role assigned at the course context, but NOT enrolled.
        $coursecontext = \context_course::instance($course->id);
        $editingteacher = $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
        role_assign($editingteacher, $teacher->id, $coursecontext->id);

        // Role without an enrolment must not count.
        $this->assertNotContains((int) $course->id, query::teacher_course_ids((int) $teacher->id));

        // Suspended enrolment must not count either.
        $gen->enrol_user($teacher->id, $course->id, 'editingteacher', 'manual', 0, 0, ENROL_USER_SUSPENDED);
        $this->assertNotContains((int) $course->id, query::teacher_course_ids((int) $teacher->id));
    }

    public function test_teacher_course_ids_empty_when_teaches_nothing(): void {
        $this->resetAfterTest();
        $student = $this->getDataGenerator()->create_user();
        $this->assertSame([], query::teacher_course_ids((int) $student->id));
    }

    public function test_save_coursecolumn_accepts_valid_and_rejects_unknown_column(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        // The courseid is a real column of this query, gibberish is not.
        $id = query::save($this->formdata(['querysql' => 'SELECT id, courseid FROM {enrol}']));
        query::get($id)->publish();

        query::save($this->formdata([
            'id'           => $id,
            'querysql'     => 'SELECT id, courseid FROM {enrol}',
            'coursecolumn' => 'courseid',
        ]));
        $this->assertSame('courseid', $DB->get_field(query::TABLE, 'coursecolumn', ['id' => $id]));

        query::save($this->formdata([
            'id'           => $id,
            'querysql'     => 'SELECT id, courseid FROM {enrol}',
            'coursecolumn' => 'nosuchcolumn',
        ]));
        $this->assertNull($DB->get_field(query::TABLE, 'coursecolumn', ['id' => $id]));
    }

    public function test_published_menu_lists_only_published(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $draft = query::save($this->formdata(['name' => 'Draft menu']));
        $pub   = query::save($this->formdata(['name' => 'Published menu']));
        query::get($pub)->publish();

        $menu = query::published_menu();
        $this->assertArrayHasKey($pub, $menu);
        $this->assertArrayNotHasKey($draft, $menu);
        $this->assertSame('Published menu', $menu[$pub]);
    }

    public function test_fetch_rows_for_viewer_scopes_to_taught_courses(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $gen     = $this->getDataGenerator();
        $teacher = $gen->create_user();
        $taught  = $gen->create_course();
        $other   = $gen->create_course();
        $gen->enrol_user($teacher->id, $taught->id, 'editingteacher');

        $sql = 'SELECT id AS courseid, shortname FROM {course}';
        $id  = query::save($this->formdata(['querysql' => $sql]));
        query::get($id)->publish();
        // Designate the course filter column (published-edit path; SQL unchanged so stays published).
        query::save($this->formdata(['id' => $id, 'querysql' => $sql, 'coursecolumn' => 'courseid']));

        $this->setUser($teacher);
        $rows = query::get($id)->fetch_rows_for_viewer();
        $ids  = array_map(static fn($r): int => (int) $r['courseid'], $rows);

        $this->assertContains((int) $taught->id, $ids);
        $this->assertNotContains((int) $other->id, $ids);
    }

    public function test_fetch_rows_for_viewer_empty_when_teaches_nothing(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $student = $this->getDataGenerator()->create_user();
        $sql = 'SELECT id AS courseid, shortname FROM {course}';
        $id  = query::save($this->formdata(['querysql' => $sql]));
        query::get($id)->publish();
        query::save($this->formdata(['id' => $id, 'querysql' => $sql, 'coursecolumn' => 'courseid']));

        $this->setUser($student);
        $this->assertSame([], query::get($id)->fetch_rows_for_viewer());
    }

    public function test_fetch_rows_for_viewer_scopes_to_page_course(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $gen     = $this->getDataGenerator();
        $oncourse = $gen->create_course();
        $other    = $gen->create_course();

        $sql = 'SELECT id AS courseid, shortname FROM {course}';
        $id  = query::save($this->formdata(['querysql' => $sql]));
        query::get($id)->publish();
        // Designate the page-course filter column (published-edit path; SQL unchanged).
        query::save($this->formdata(['id' => $id, 'querysql' => $sql, 'pagecoursecolumn' => 'courseid']));

        // Passing the page's course id limits rows to that course only.
        $rows = query::get($id)->fetch_rows_for_viewer(0, (int) $oncourse->id);
        $this->assertCount(1, $rows);
        // Once scoped to a single course the filter column is a constant, so it is hidden from output.
        $this->assertArrayNotHasKey('courseid', $rows[0]);
        $this->assertSame($oncourse->shortname, $rows[0]['shortname']);
        $this->assertNotSame($other->shortname, $rows[0]['shortname']);
    }

    public function test_fetch_rows_for_viewer_page_course_zero_is_unfiltered(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $gen   = $this->getDataGenerator();
        $a     = $gen->create_course();
        $b     = $gen->create_course();

        $sql = 'SELECT id AS courseid, shortname FROM {course}';
        $id  = query::save($this->formdata(['querysql' => $sql]));
        query::get($id)->publish();
        query::save($this->formdata(['id' => $id, 'querysql' => $sql, 'pagecoursecolumn' => 'courseid']));

        // Pagecourseid 0 (e.g. block off a course page) skips the filter: both courses present.
        $rows = query::get($id)->fetch_rows_for_viewer(0, 0);
        $ids  = array_map(static fn($r): int => (int) $r['courseid'], $rows);
        $this->assertContains((int) $a->id, $ids);
        $this->assertContains((int) $b->id, $ids);
    }

    /**
     * Create a non-admin user holding author + approve + viewall at system context, to prove the
     * admin-owner lockdown overrides capability rather than being just another missing cap.
     *
     * @return \stdClass The created user.
     */
    private function privileged_nonadmin(): \stdClass {
        $gen  = $this->getDataGenerator();
        $user = $gen->create_user();
        $syscontext = \context_system::instance();
        $roleid = $gen->create_role();
        foreach (['author', 'approve', 'viewall'] as $cap) {
            assign_capability('report/sql:' . $cap, CAP_ALLOW, $roleid, $syscontext->id, true);
        }
        role_assign($roleid, $user->id, $syscontext->id);
        accesslib_clear_all_caches_for_unit_testing();
        $this->assertFalse(is_siteadmin($user), 'test user must not be a site admin');
        return $user;
    }

    public function test_can_modify_reflects_admin_ownership(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $adminowned = query::save($this->formdata(['name' => 'Admin owned']));

        $user = $this->privileged_nonadmin();
        $this->setUser($user);
        $this->assertFalse(query::get($adminowned)->can_modify());

        $userowned = query::save($this->formdata(['name' => 'User owned']));
        $this->assertTrue(query::get($userowned)->can_modify());

        // A site admin may modify the admin-owned query.
        $this->setAdminUser();
        $this->assertTrue(query::get($adminowned)->can_modify());
        $this->assertTrue(query::get($userowned)->can_modify());
    }

    public function test_nonadmin_cannot_save_admin_owned_query(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $id = query::save($this->formdata(['name' => 'Admin owned']));

        $this->setUser($this->privileged_nonadmin());
        $this->expectException(\required_capability_exception::class);
        query::save($this->formdata(['id' => $id, 'name' => 'Hijacked']));
    }

    public function test_nonadmin_cannot_delete_admin_owned_query(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $id = query::save($this->formdata(['name' => 'Admin owned']));

        $this->setUser($this->privileged_nonadmin());
        $this->expectException(\required_capability_exception::class);
        query::get($id)->delete();
    }

    public function test_nonadmin_cannot_publish_admin_owned_query(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $id = query::save($this->formdata(['name' => 'Admin owned']));

        $this->setUser($this->privileged_nonadmin());
        $this->expectException(\required_capability_exception::class);
        query::get($id)->publish();
    }

    public function test_nonadmin_cannot_unpublish_admin_owned_query(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $id = query::save($this->formdata(['name' => 'Admin owned']));
        query::get($id)->publish();

        $this->setUser($this->privileged_nonadmin());
        $this->expectException(\required_capability_exception::class);
        query::get($id)->unpublish();
    }

    public function test_nonadmin_can_modify_own_query(): void {
        $this->resetAfterTest();
        $user = $this->privileged_nonadmin();
        $this->setUser($user);

        // Regression: the lockdown must not block a non-admin editing a non-admin-owned query.
        $id = query::save($this->formdata(['name' => 'User owned']));
        $editedid = query::save($this->formdata(['id' => $id, 'name' => 'User edited']));
        $this->assertSame($id, $editedid);
        query::get($id)->publish();
        $this->assertSame(query::STATUS_PUBLISHED, query::get($id)->status());
        query::get($id)->unpublish();
        $this->assertSame(query::STATUS_DRAFT, query::get($id)->status());
    }

    /**
     * order_by_sorting() recovers the query's ORDER BY as [column => direction], preserving order.
     */
    public function test_order_by_sorting_parses_columns_and_directions(): void {
        $this->assertSame(
            ['shortname' => SORT_ASC, 'usercount' => SORT_DESC],
            query::order_by_sorting('SELECT r.shortname, c FROM {role} r ORDER BY r.shortname ASC, usercount DESC')
        );
    }

    /**
     * A query with no ORDER BY yields no sorting.
     */
    public function test_order_by_sorting_empty_without_order_by(): void {
        $this->assertSame([], query::order_by_sorting('SELECT id FROM {user}'));
    }

    /**
     * Terms that cannot map to an output column — expressions and ordinal positions — are skipped;
     * a trailing LIMIT does not leak into the last term. Alias prefixes are stripped and lowercased.
     */
    public function test_order_by_sorting_skips_expressions_and_ordinals(): void {
        $this->assertSame(
            ['name' => SORT_ASC],
            query::order_by_sorting('SELECT name FROM {user} ORDER BY 1, LOWER(name) DESC, U.Name LIMIT 10')
        );
    }

    /**
     * End-to-end: the inline preview renders rows in the query's ORDER BY order, and does so without
     * exposing a header sort link (the ordering is a default sort on a non-sortable column).
     */
    public function test_preview_orders_rows_and_hides_sort_link(): void {
        global $CFG;
        require_once($CFG->dirroot . '/report/sql/lib.php');

        $this->resetAfterTest();
        $this->setAdminUser();

        // Three users whose insertion order (b, a, c) differs from the requested ORDER BY (a, b, c).
        foreach (['rsorder_b', 'rsorder_a', 'rsorder_c'] as $username) {
            $this->getDataGenerator()->create_user(['username' => $username]);
        }

        $sql = "SELECT u.username AS uname FROM {user} u WHERE u.username LIKE 'rsorder\\_%' ORDER BY uname ASC";
        $html = \report_sql_output_fragment_preview(['sql' => $sql, 'courseid' => 0]);

        // Rows appear in ORDER BY order, not insertion order.
        $posa = strpos($html, 'rsorder_a');
        $posb = strpos($html, 'rsorder_b');
        $posc = strpos($html, 'rsorder_c');
        $this->assertNotFalse($posa);
        $this->assertLessThan($posb, $posa);
        $this->assertLessThan($posc, $posb);

        // No header sort link/icon: flexible_table only emits data-sortby for sortable columns.
        $this->assertStringNotContainsString('data-sortby', $html);
    }

    /**
     * The published report carries the query's ORDER BY as default column sorting, so its columns are
     * created with sortenabled + the right direction.
     */
    public function test_published_report_applies_order_by_sorting(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $id = query::save($this->formdata([
            'name'     => 'Ordered',
            'querysql' => 'SELECT u.username AS uname FROM {user} u ORDER BY uname DESC',
        ]));
        query::get($id)->publish();
        $reportid = query::get($id)->reportid();

        $sorted = $DB->get_records_select(
            'reportbuilder_column',
            'reportid = :rid AND sortenabled = 1',
            ['rid' => $reportid]
        );
        $this->assertCount(1, $sorted);
        $column = reset($sorted);
        $this->assertStringEndsWith(':uname', $column->uniqueidentifier);
        $this->assertEquals(SORT_DESC, $column->sortdirection);
    }
}
