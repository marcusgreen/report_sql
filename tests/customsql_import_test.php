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

use report_sql\local\customsql_import;

/**
 * Unit tests for the Ad-hoc Database Queries importer's deterministic translation.
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \report_sql\local\customsql_import
 * @covers \report_sql\local\import_helper
 */
final class customsql_import_test extends \advanced_testcase {
    /**
     * STARTTIME/ENDTIME are filled with neutral bounds; shared date/quote rewrites still apply.
     */
    public function test_convert_known_tokens(): void {
        $r = customsql_import::convert('SELECT id FROM x WHERE t > %%STARTTIME%% AND t < %%ENDTIME%%');
        $this->assertNull($r['fatal']);
        $this->assertStringContainsString('> 0 ', $r['sql']);
        $this->assertStringContainsString('< 2145938400', $r['sql']);
    }

    /**
     * Tokens shared with RS are left untouched.
     */
    public function test_convert_keeps_shared_tokens(): void {
        $r = customsql_import::convert('SELECT %%WWWROOT%% AS w, %%COURSEID%% AS c FROM x');
        $this->assertNull($r['fatal']);
        $this->assertStringContainsString('%%WWWROOT%%', $r['sql']);
        $this->assertStringContainsString('%%COURSEID%%', $r['sql']);
    }

    /**
     * customsql escape tokens become their literal characters; the ? then becomes chr(63).
     */
    public function test_convert_escape_tokens(): void {
        $r = customsql_import::convert("SELECT CONCAT('view.php%%Q%%id=', id) AS link FROM x");
        $this->assertNull($r['fatal']);
        $this->assertStringNotContainsString('%%Q%%', $r['sql']);
        $this->assertStringNotContainsString('?', $r['sql']);
        $this->assertStringContainsString('chr(63)', $r['sql']);
    }

    /**
     * A named :param placeholder has no RS equivalent and is rejected.
     */
    public function test_convert_rejects_named_param(): void {
        $r = customsql_import::convert('SELECT id FROM x WHERE courseid = :courseid');
        $this->assertNotNull($r['fatal']);
    }

    /**
     * A colon inside a string literal must not be mistaken for a named parameter.
     */
    public function test_convert_ignores_colon_in_string(): void {
        $r = customsql_import::convert("SELECT 'http://example.com' AS site FROM x");
        $this->assertNull($r['fatal']);
    }

    /**
     * %%USERID%% has no per-viewer equivalent and is rejected.
     */
    public function test_convert_rejects_userid(): void {
        $r = customsql_import::convert('SELECT id FROM x WHERE userid = %%USERID%%');
        $this->assertNotNull($r['fatal']);
    }

    /**
     * Unknown %% tokens are rejected.
     */
    public function test_convert_rejects_unknown_token(): void {
        $r = customsql_import::convert('SELECT id FROM x WHERE c = %%CATEGORYID%%');
        $this->assertNotNull($r['fatal']);
    }

    /**
     * Shared date-function rewriting still maps FROM_UNIXTIME to the %%TIMESTAMP%% token.
     */
    public function test_convert_from_unixtime(): void {
        $r = customsql_import::convert("SELECT FROM_UNIXTIME(timecreated, '%Y-%m-%d') AS d FROM t");
        $this->assertNull($r['fatal']);
        $this->assertStringContainsString('%%TIMESTAMP(timecreated, yyyy-mm-dd)%%', $r['sql']);
    }

    /**
     * MySQL-only date functions with no clean mapping are rejected on non-MySQL databases, but kept
     * (they run natively, validated by the live dry-run) on MySQL/MariaDB.
     */
    public function test_convert_unmappable_date_fn_depends_on_dbfamily(): void {
        global $DB;
        $r = customsql_import::convert('SELECT DATEDIFF(NOW(), created) FROM t');
        if ($DB->get_dbfamily() === 'mysql') {
            $this->assertNull($r['fatal']);
            $this->assertStringContainsString('DATEDIFF', $r['sql']);
        } else {
            $this->assertNotNull($r['fatal']);
        }
    }

    /**
     * A FROM_UNIXTIME used as a display column is rewritten to %%TIMESTAMP%%, but one nested as an
     * argument to another date function (DATEDIFF) is left native — %%TIMESTAMP%% resolves to a bare
     * epoch int and would break the surrounding function. On MySQL the native nested call is kept
     * (works); elsewhere the leftover function is rejected.
     */
    public function test_convert_nested_from_unixtime_not_rewritten(): void {
        global $DB;
        $sql = 'SELECT id, username, FROM_UNIXTIME(lastlogin) AS days '
            . 'FROM prefix_user WHERE DATEDIFF(NOW(), FROM_UNIXTIME(lastlogin)) < 120';
        $r = customsql_import::convert($sql);

        // The display column is always rewritten regardless of DB family.
        $this->assertStringContainsString('%%TIMESTAMP(lastlogin)%% AS days', $r['sql']);
        // The nested call is never turned into a token (exactly one token in the whole query).
        $this->assertSame(1, substr_count($r['sql'], '%%TIMESTAMP'));
        $this->assertStringContainsString('DATEDIFF(NOW(), FROM_UNIXTIME(lastlogin))', $r['sql']);

        if ($DB->get_dbfamily() === 'mysql') {
            $this->assertNull($r['fatal']);
        } else {
            $this->assertNotNull($r['fatal']);
        }
    }

    /**
     * A clean query classifies as importable, site-wide and visible.
     */
    public function test_classify_accepts_clean_sql(): void {
        $this->resetAfterTest();
        $rec = (object) [
            'id'          => 2,
            'displayname' => 'Simple user list',
            'description' => 'All users',
            'querysql'    => 'SELECT id, username FROM prefix_user',
        ];
        $info = customsql_import::classify($rec);
        $this->assertSame('import', $info['verdict']);
        $this->assertIsArray($info['source']);
        $this->assertSame(0, $info['source']['courseid']);
        $this->assertSame(1, $info['source']['visible']);
        $this->assertSame('Simple user list', $info['name']);
    }

    /**
     * A query referencing a table that does not exist fails the live dry-run.
     */
    public function test_classify_rejects_dead_table(): void {
        $this->resetAfterTest();
        $rec = (object) [
            'id'          => 3,
            'displayname' => 'References a missing table',
            'description' => '',
            'querysql'    => 'SELECT id FROM prefix_no_such_table_xyz',
        ];
        $info = customsql_import::classify($rec);
        $this->assertSame('reject', $info['verdict']);
    }

    /**
     * An empty querysql is rejected without touching the DB.
     */
    public function test_classify_rejects_empty_sql(): void {
        $this->resetAfterTest();
        $rec = (object) [
            'id'          => 4,
            'displayname' => 'Empty',
            'description' => '',
            'querysql'    => '   ',
        ];
        $info = customsql_import::classify($rec);
        $this->assertSame('reject', $info['verdict']);
        $this->assertNotEmpty($info['reason']);
    }
}
