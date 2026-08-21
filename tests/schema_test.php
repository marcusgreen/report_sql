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

use report_sql\external\get_schema;
use report_sql\local\schema;

/**
 * Tests for the cached schema/foreign-key map and its external endpoint.
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \report_sql\local\schema
 */
final class schema_test extends \advanced_testcase {
    /**
     * schema::get() returns the live tables with their columns and an install.xml FK map.
     */
    public function test_get_returns_tables_and_fkmap(): void {
        $this->resetAfterTest();

        $data = schema::get();

        $this->assertArrayHasKey('tables', $data);
        $this->assertArrayHasKey('fkmap', $data);

        // The user table and its columns are always present.
        $this->assertArrayHasKey('user', $data['tables']);
        $this->assertContains('id', $data['tables']['user']);
        $this->assertContains('username', $data['tables']['user']);

        // The user_enrolments.userid column is a foreign key onto user.id in core install.xml.
        $this->assertSame('user', $data['fkmap']['user_enrolments']['userid']['reftable']);
        $this->assertSame('id', $data['fkmap']['user_enrolments']['userid']['refcol']);
    }

    /**
     * A second call is served from the MUC cache rather than rebuilt.
     */
    public function test_get_is_cached(): void {
        $this->resetAfterTest();

        $first = schema::get();

        // Poison the live schema: a fresh build would include this table, a cached read won't.
        $cache = \cache::make('report_sql', 'schema');
        $stored = $cache->get('data');
        $stored['tables']['sentinel_table'] = ['id'];
        $cache->set('data', $stored);

        $second = schema::get();
        $this->assertArrayHasKey('sentinel_table', $second['tables']);
        $this->assertSame($first['fkmap'], $second['fkmap']);
    }

    /**
     * The external endpoint returns the schema and FK map as JSON strings.
     */
    public function test_external_returns_json(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $result = get_schema::execute();

        $tables = json_decode($result['schema'], true);
        $fkmap = json_decode($result['fkmap'], true);

        $this->assertArrayHasKey('user', $tables);
        $this->assertContains('id', $tables['user']);
        $this->assertSame('user', $fkmap['user_enrolments']['userid']['reftable']);
    }

    /**
     * The external endpoint requires the author capability.
     */
    public function test_external_requires_capability(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());

        $this->expectException(\required_capability_exception::class);
        get_schema::execute();
    }
}
