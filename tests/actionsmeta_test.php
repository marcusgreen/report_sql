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
use report_sql\local\transfer;

/**
 * Tests for actionable-report config: the actionsmeta accessors, its build on save, and its
 * (params-stripped) portability through export/import.
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \report_sql\local\query
 * @covers \report_sql\local\transfer
 */
final class actionsmeta_test extends \advanced_testcase {
    /**
     * Insert a published query with two output columns and return its id.
     *
     * @return int
     */
    private function make_published_query(): int {
        global $DB, $USER;
        $now = time();
        return (int) $DB->insert_record(query::TABLE, (object) [
            'name'         => 'Users',
            'description'  => '',
            'querysql'     => 'SELECT id, name FROM {user}',
            'ownerid'      => (int) $USER->id,
            'status'       => query::STATUS_PUBLISHED,
            'viewname'     => 'report_sql_v_test',
            'reportid'     => null,
            'columnsmeta'  => json_encode(['id' => ['type' => 'int'], 'name' => ['type' => 'text']]),
            'courseid'     => 0,
            'visible'      => 1,
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);
    }

    /**
     * Form data that enables actions on the given query.
     *
     * @param int $id
     * @param bool $enabled
     * @return \stdClass
     */
    private function form_data(int $id, bool $enabled): \stdClass {
        return (object) [
            'id'                   => $id,
            'name'                 => 'Users',
            'querysql'             => 'SELECT id, name FROM {user}',
            'courseid'             => 0,
            'visible'              => 1,
            'audiencetype'         => 'default',
            'action_enabled'       => $enabled ? 1 : 0,
            'action_subject'       => 'user',
            'action_subjectcolumn' => 'id',
            'action_ops'           => ['message_user', 'bogus', 'suspend_user'],
            'action_roleid'        => 5,
            'action_courseid'      => 0,
            'action_cohortid'      => 0,
            'action_messagetext'   => 'hello',
        ];
    }

    /**
     * Saving with actions enabled stores a validated actionsmeta; the accessors read it back and
     * unknown ops are dropped.
     */
    public function test_save_builds_actionsmeta(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $id = $this->make_published_query();
        query::save($this->form_data($id, true));

        $query = query::get($id);
        $this->assertTrue($query->actions_enabled());
        $this->assertSame(['message_user', 'suspend_user'], $query->action_ops());
        $this->assertSame('id', $query->action_subjectcolumn());
        $this->assertSame(5, (int) $query->action_params()['roleid']);
    }

    /**
     * A subject column that is not one of the query's output columns is rejected (actions off).
     */
    public function test_save_rejects_unknown_subject_column(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $id = $this->make_published_query();
        $data = $this->form_data($id, true);
        $data->action_subjectcolumn = 'not_a_column';
        query::save($data);

        $this->assertFalse(query::get($id)->actions_enabled());
    }

    /**
     * Disabling actions clears actionsmeta.
     */
    public function test_save_disabled_clears_actionsmeta(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $id = $this->make_published_query();
        query::save($this->form_data($id, true));
        $this->assertTrue(query::get($id)->actions_enabled());

        query::save($this->form_data($id, false));
        $this->assertFalse(query::get($id)->actions_enabled());
        $this->assertSame([], query::get($id)->action_ops());
    }

    /**
     * export() carries actionsmeta but strips the local-id params; import() lands it on a fresh draft.
     */
    public function test_transfer_roundtrip_strips_params(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $id = $this->make_published_query();
        query::save($this->form_data($id, true));

        $export = transfer::export([$id]);
        $source = $export['sources'][0];
        $this->assertArrayHasKey('actionsmeta', $source);
        $this->assertTrue($source['actionsmeta']['enabled']);
        $this->assertArrayNotHasKey('params', $source['actionsmeta'], 'local-id params must not travel');

        // Round-trip through parse() + import().
        $sources = transfer::parse(json_encode($export));
        transfer::import($sources, [0]);

        global $DB;
        $imported = $DB->get_records(query::TABLE, ['status' => query::STATUS_DRAFT], 'id DESC', '*', 0, 1);
        $draft = reset($imported);
        $meta = json_decode($draft->actionsmeta, true);
        $this->assertTrue($meta['enabled']);
        $this->assertSame(['message_user', 'suspend_user'], $meta['ops']);
        $this->assertSame('id', $meta['subjectcolumn']);
        $this->assertArrayNotHasKey('params', $meta);
    }
}
