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
 * Tests for the export / import transfer of saved queries.
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \report_sql\local\transfer
 */
final class transfer_test extends \advanced_testcase {
    /**
     * Build a minimal portable source descriptor as produced by transfer::parse().
     *
     * @param array $extra Extra/override fields.
     * @return array
     */
    private function source(array $extra = []): array {
        return array_merge([
            'name'        => 'Imported view',
            'description' => '',
            'querysql'    => 'SELECT id FROM {user}',
            'courseid'    => 0,
            'visible'     => 1,
            'chartmeta'   => null,
        ], $extra);
    }

    public function test_import_demotes_unknown_courseid_to_sitewide(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        // 123456 is an id no course in a fresh test site will have.
        $sources = [$this->source(['name' => 'Stale', 'courseid' => 123456])];
        $result = transfer::import($sources, [0]);

        $this->assertSame(1, $result['imported']);
        $this->assertArrayHasKey('Stale', $result['demoted']);
        $this->assertSame(123456, $result['demoted']['Stale']);

        $rec = $DB->get_record(query::TABLE, ['name' => 'Stale'], '*', MUST_EXIST);
        $this->assertSame(0, (int) $rec->courseid);
    }

    public function test_import_fires_query_created_event(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $sink = $this->redirectEvents();
        transfer::import([$this->source(['name' => 'Audited'])], [0]);
        $events = $sink->get_events();
        $sink->close();

        $created = array_filter($events, static function ($e) {
            return $e instanceof \report_sql\event\query_created;
        });
        $this->assertCount(1, $created);
        $event = reset($created);
        $this->assertSame('Audited', $event->other['name']);
    }

    public function test_pagecoursecolumn_round_trips_export_to_import(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        // Save a query carrying a page-course column, then export it.
        $rec = (object) [
            'name'         => 'Scoped source',
            'description'  => '',
            'querysql'     => 'SELECT id, courseid FROM {course}',
            'courseid'     => 0,
            'visible'      => 1,
            'pagecoursecolumn' => 'courseid',
            'ownerid'      => 2,
            'status'       => query::STATUS_DRAFT,
            'timecreated'  => time(),
            'timemodified' => time(),
        ];
        $srcid = $DB->insert_record(query::TABLE, $rec);

        $payload = transfer::export([$srcid]);
        $this->assertSame('courseid', $payload['sources'][0]['pagecoursecolumn']);

        // Drop the seed so the re-imported draft (same name) is the only match below.
        $DB->delete_records(query::TABLE, ['id' => $srcid]);

        // Parse the encoded payload back and import: the page-course column survives onto the draft.
        $sources = transfer::parse(json_encode($payload));
        $this->assertSame('courseid', $sources[0]['pagecoursecolumn']);

        transfer::import($sources, [0]);
        $imported = $DB->get_record(query::TABLE, ['name' => 'Scoped source', 'status' => query::STATUS_DRAFT],
            '*', MUST_EXIST);
        $this->assertSame('courseid', $imported->pagecoursecolumn);
    }

    public function test_import_omitted_pagecoursecolumn_is_null(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        // A source without the field (e.g. an older export) imports with a NULL page-course column.
        transfer::import([$this->source(['name' => 'Unscoped'])], [0]);
        $rec = $DB->get_record(query::TABLE, ['name' => 'Unscoped'], '*', MUST_EXIST);
        $this->assertNull($rec->pagecoursecolumn);
    }

    public function test_import_preserves_existing_courseid(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();

        $sources = [$this->source(['name' => 'Scoped', 'courseid' => (int) $course->id])];
        $result = transfer::import($sources, [0]);

        $this->assertSame(1, $result['imported']);
        $this->assertArrayNotHasKey('Scoped', $result['demoted']);

        $rec = $DB->get_record(query::TABLE, ['name' => 'Scoped'], '*', MUST_EXIST);
        $this->assertSame((int) $course->id, (int) $rec->courseid);
    }

    public function test_count_samples_matches_shipped_file(): void {
        $this->resetAfterTest();

        // The bundled file ships 27 sample report views.
        $this->assertSame(27, transfer::count_samples());
    }

    public function test_author_without_siteconfig_can_import_sample(): void {
        global $DB;
        $this->resetAfterTest();

        // A plain user granted only report/sql:author at system context — no
        // moodle/site:config. This mirrors the samples.php gate (an author externalpage), which
        // is the sole gate on the import: transfer::import() itself performs no capability check.
        $user = $this->getDataGenerator()->create_user();
        $syscontext = \context_system::instance();
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('report/sql:author', CAP_ALLOW, $roleid, $syscontext->id);
        role_assign($roleid, $user->id, $syscontext->id);
        $this->setUser($user);

        $this->assertTrue(has_capability('report/sql:author', $syscontext));
        $this->assertFalse(has_capability('moodle/site:config', $syscontext));

        $result = transfer::import([$this->source(['name' => 'Authored'])], [0]);
        $this->assertSame(1, $result['imported']);

        // The imported query lands as a draft owned by the author.
        $rec = $DB->get_record(query::TABLE, ['name' => 'Authored'], '*', MUST_EXIST);
        $this->assertSame(query::STATUS_DRAFT, $rec->status);
        $this->assertSame((int) $user->id, (int) $rec->ownerid);
    }

    public function test_import_samples_is_idempotent(): void {
        global $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();

        $count = transfer::count_samples();
        $this->assertGreaterThan(0, $count);

        // Every shipped sample is portable (date handling uses %%TIMESTAMP()%% / %%NOW%% tokens
        // rather than dialect-specific functions), so all of them import cleanly on any database.
        $first = transfer::import_samples();
        $imported = $first['imported'];
        $this->assertSame($count, $imported, 'skipped: ' . json_encode($first['skipped']));
        $this->assertSame([], $first['duplicates']);
        $this->assertSame($imported, $DB->count_records(query::TABLE));

        $rec = $DB->get_records(query::TABLE, null, '', '*', 0, 1);
        $rec = reset($rec);
        $this->assertSame(query::STATUS_DRAFT, $rec->status);
        $this->assertSame((int) $USER->id, (int) $rec->ownerid);

        // Second run adds nothing: every already-imported name is reported as a duplicate and the
        // table count is unchanged.
        $second = transfer::import_samples();
        $this->assertSame(0, $second['imported']);
        $this->assertCount($imported, $second['duplicates']);
        $this->assertSame($imported, $DB->count_records(query::TABLE));
    }
}
