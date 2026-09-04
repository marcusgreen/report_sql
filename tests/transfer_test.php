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
     * Drop any report_sql views left behind by a test that published a query. Moodle's per-test DB
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
        $imported = $DB->get_record(
            query::TABLE,
            ['name' => 'Scoped source', 'status' => query::STATUS_DRAFT],
            '*',
            MUST_EXIST
        );
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

    /**
     * The bundled %%VIEWER%% sample not only imports but publishes: the token is adopted as the
     * per-viewer filter (useridcolumn), proving the SQL executes and the scoping survives import.
     */
    public function test_bundled_viewer_sample_publishes_and_scopes(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        transfer::import_samples();
        $rec = $DB->get_record_select(
            query::TABLE,
            $DB->sql_like('name', ':name'),
            ['name' => '%' . $DB->sql_like_escape('My course enrolments') . '%'],
            '*',
            MUST_EXIST
        );

        query::get((int) $rec->id)->publish();

        $published = $DB->get_record(query::TABLE, ['id' => $rec->id], '*', MUST_EXIST);
        $this->assertSame(query::STATUS_PUBLISHED, $published->status);
        // The %%VIEWER(ue.userid)%% token was adopted as the per-viewer filter column.
        $this->assertSame('viewerid', $published->useridcolumn);
    }

    /**
     * The bundled %%TEACHES%% sample publishes and adopts the marked column as the teacher-course
     * filter (coursecolumn), proving the SQL executes and the scoping survives import.
     */
    public function test_bundled_teaches_sample_publishes_and_scopes(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        transfer::import_samples();
        $rec = $DB->get_record_select(
            query::TABLE,
            $DB->sql_like('name', ':name'),
            ['name' => '%' . $DB->sql_like_escape('Enrolments in courses I teach') . '%'],
            '*',
            MUST_EXIST
        );

        query::get((int) $rec->id)->publish();

        $published = $DB->get_record(query::TABLE, ['id' => $rec->id], '*', MUST_EXIST);
        $this->assertSame(query::STATUS_PUBLISHED, $published->status);
        $this->assertSame('courseid', $published->coursecolumn);
    }

    public function test_parse_normalises_requires(): void {
        // String, array, blanks and a malformed entry all normalise to a clean de-duped list.
        $payload = [
            'format'  => transfer::FORMAT,
            'version' => transfer::FORMAT_VERSION,
            'sources' => [
                ['name' => 'A', 'querysql' => 'SELECT 1', 'requires' => 'mod_quiz'],
                ['name' => 'B', 'querysql' => 'SELECT 1', 'requires' => ['mod_quiz', 'mod_quiz', '', 'not a component!']],
                ['name' => 'C', 'querysql' => 'SELECT 1'],
            ],
        ];
        $sources = transfer::parse(json_encode($payload));

        $this->assertSame(['mod_quiz'], $sources[0]['requires']);
        $this->assertSame(['mod_quiz'], $sources[1]['requires']);
        $this->assertSame([], $sources[2]['requires']);
    }

    public function test_component_available(): void {
        // Core and an always-present standard plugin are available; nonsense is not.
        $this->assertTrue(transfer::component_available(''));
        $this->assertTrue(transfer::component_available('core'));
        $this->assertTrue(transfer::component_available('mod_quiz'));
        $this->assertFalse(transfer::component_available('mod_thisdoesnotexist'));
    }

    public function test_import_refuses_missing_required_plugin(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $sources = [$this->source(['name' => 'Needs plugin', 'requires' => ['mod_thisdoesnotexist']])];
        $result = transfer::import($sources, [0]);

        $this->assertSame(0, $result['imported']);
        $this->assertArrayHasKey('Needs plugin', $result['skipped']);
        $this->assertFalse($DB->record_exists(query::TABLE, ['name' => 'Needs plugin']));
    }

    public function test_import_allows_present_required_plugin(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $sources = [$this->source(['name' => 'Needs quiz', 'requires' => ['mod_quiz']])];
        $result = transfer::import($sources, [0]);

        $this->assertSame(1, $result['imported']);
        $this->assertTrue($DB->record_exists(query::TABLE, ['name' => 'Needs quiz']));
    }

    public function test_detect_requires_core_only_is_empty(): void {
        // A query over core tables only declares no third-party dependency.
        $this->assertSame([], transfer::detect_requires('SELECT id FROM {user} u JOIN {course} c ON 1=1'));
    }

    public function test_detect_requires_flags_thirdparty_table(): void {
        // block_configurable_reports is a third-party plugin installed on this test site; a query
        // over its table is detected as requiring that component, while the core {course} join it
        // also references is ignored.
        if (!\core_component::get_component_directory('block_configurable_reports')) {
            $this->markTestSkipped('block_configurable_reports not installed');
        }
        $sql = 'SELECT cr.id FROM {block_configurable_reports} cr JOIN {course} c ON c.id = cr.courseid';
        $this->assertSame(['block_configurable_reports'], transfer::detect_requires($sql));
    }

    public function test_export_bakes_detected_requires(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        if (!\core_component::get_component_directory('block_configurable_reports')) {
            $this->markTestSkipped('block_configurable_reports not installed');
        }

        $id = $DB->insert_record(query::TABLE, (object) [
            'name'         => 'CR listing',
            'description'  => '',
            'querysql'     => 'SELECT id, name FROM {block_configurable_reports}',
            'courseid'     => 0,
            'visible'      => 1,
            'ownerid'      => 2,
            'status'       => query::STATUS_DRAFT,
            'timecreated'  => time(),
            'timemodified' => time(),
        ]);

        $payload = transfer::export([$id]);
        $this->assertSame(['block_configurable_reports'], $payload['sources'][0]['requires']);

        // Round-trips: parse preserves it, so the importing site's hide/badge machinery sees it.
        $sources = transfer::parse(json_encode($payload));
        $this->assertSame(['block_configurable_reports'], $sources[0]['requires']);
    }

    public function test_export_core_only_omits_requires(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $id = $DB->insert_record(query::TABLE, (object) [
            'name'         => 'Core only',
            'description'  => '',
            'querysql'     => 'SELECT id FROM {user}',
            'courseid'     => 0,
            'visible'      => 1,
            'ownerid'      => 2,
            'status'       => query::STATUS_DRAFT,
            'timecreated'  => time(),
            'timemodified' => time(),
        ]);

        $payload = transfer::export([$id]);
        $this->assertArrayNotHasKey('requires', $payload['sources'][0]);
    }

    public function test_bundled_samples_include_unavailable_superset(): void {
        $this->resetAfterTest();

        $available = transfer::bundled_samples();
        $all = transfer::bundled_samples(true);

        // Default set is available-only; every entry is flagged available and present in the superset.
        foreach ($available as $index => $source) {
            $this->assertTrue($source['available']);
            $this->assertArrayHasKey($index, $all);
        }
        // Superset never drops any available sample; it only adds unavailable ones.
        $this->assertGreaterThanOrEqual(count($available), count($all));
        // Any extra entry (only in the superset) is an unavailable one.
        foreach (array_diff_key($all, $available) as $source) {
            $this->assertFalse($source['available']);
        }
    }
}
