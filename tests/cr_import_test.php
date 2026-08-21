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

use report_sql\local\cr_import;

/**
 * Unit tests for the Configurable Reports importer's deterministic translation.
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \report_sql\local\cr_import
 */
final class cr_import_test extends \advanced_testcase {
    /**
     * STARTTIME/ENDTIME are filled with CR's own default bounds; DEBUG is stripped.
     */
    public function test_convert_known_tokens(): void {
        $r = cr_import::convert('SELECT id FROM x WHERE t > %%STARTTIME%% AND t < %%ENDTIME%% %%DEBUG%%');
        $this->assertNull($r['fatal']);
        $this->assertStringContainsString('> 0 ', $r['sql']);
        $this->assertStringContainsString('< 2145938400', $r['sql']);
        $this->assertStringNotContainsString('%%DEBUG%%', $r['sql']);
    }

    /**
     * Tokens shared with RS are left untouched.
     */
    public function test_convert_keeps_shared_tokens(): void {
        $r = cr_import::convert('SELECT %%WWWROOT%% AS w, %%COURSEID%% AS c FROM x');
        $this->assertNull($r['fatal']);
        $this->assertStringContainsString('%%WWWROOT%%', $r['sql']);
        $this->assertStringContainsString('%%COURSEID%%', $r['sql']);
    }

    /**
     * Data provider of tokens that must be rejected (no faithful mapping).
     *
     * @return array<string, array{0:string}>
     */
    public static function fatal_token_provider(): array {
        return [
            'userid'     => ['SELECT id FROM x WHERE userid = %%USERID%%'],
            'filter var' => ['SELECT id FROM x WHERE name = %%FILTER_VAR%%'],
            'filter col' => ['SELECT id FROM x WHERE c = %%FILTER_COURSES%%'],
            'categoryid' => ['SELECT id FROM x WHERE cat = %%CATEGORYID%%'],
        ];
    }

    /**
     * Unmappable/unsupported tokens must be rejected as fatal by the converter.
     *
     * @dataProvider fatal_token_provider
     * @param string $sql
     */
    public function test_convert_rejects_unmappable_tokens(string $sql): void {
        $r = cr_import::convert($sql);
        $this->assertNotNull($r['fatal']);
    }

    /**
     * Double-quoted MySQL string literals become single-quoted.
     */
    public function test_convert_double_quotes(): void {
        $r = cr_import::convert('SELECT CASE WHEN a = 1 THEN "Yes" ELSE "No" END AS x FROM t');
        $this->assertNull($r['fatal']);
        $this->assertStringContainsString("'Yes'", $r['sql']);
        $this->assertStringContainsString("'No'", $r['sql']);
        $this->assertStringNotContainsString('"Yes"', $r['sql']);
    }

    /**
     * A literal ? inside a string is rebuilt with chr(63) so RS does not read it as a bound param.
     */
    public function test_convert_questionmark(): void {
        $r = cr_import::convert("SELECT CONCAT('view.php?id=', id) FROM t");
        $this->assertNull($r['fatal']);
        $this->assertStringNotContainsString('?', $r['sql']);
        $this->assertStringContainsString('chr(63)', $r['sql']);
    }

    /**
     * FROM_UNIXTIME with and without a format maps to the %%TIMESTAMP%% token.
     */
    public function test_convert_from_unixtime(): void {
        $bare = cr_import::convert('SELECT FROM_UNIXTIME(timecreated) AS d FROM t');
        $this->assertNull($bare['fatal']);
        $this->assertStringContainsString('%%TIMESTAMP(timecreated)%%', $bare['sql']);

        $fmt = cr_import::convert("SELECT FROM_UNIXTIME(timecreated, '%Y-%m-%d') AS d FROM t");
        $this->assertNull($fmt['fatal']);
        $this->assertStringContainsString('%%TIMESTAMP(timecreated, yyyy-mm-dd)%%', $fmt['sql']);
    }

    /**
     * DATE_FORMAT wrapping FROM_UNIXTIME collapses to a single %%TIMESTAMP%% with the format.
     */
    public function test_convert_date_format_from_unixtime(): void {
        $r = cr_import::convert("SELECT DATE_FORMAT(FROM_UNIXTIME(timemodified), '%d/%m/%Y') AS d FROM t");
        $this->assertNull($r['fatal']);
        $this->assertStringContainsString('%%TIMESTAMP(timemodified, dd/mm/yyyy)%%', $r['sql']);
    }

    /**
     * UNIX_TIMESTAMP() maps to %%NOW%%; with an argument it maps to %%EPOCH%%.
     */
    public function test_convert_unix_timestamp(): void {
        $now = cr_import::convert('SELECT id FROM t WHERE x < UNIX_TIMESTAMP()');
        $this->assertNull($now['fatal']);
        $this->assertStringContainsString('%%NOW%%', $now['sql']);

        $epoch = cr_import::convert("SELECT id FROM t WHERE x < UNIX_TIMESTAMP('2020-01-01')");
        $this->assertNull($epoch['fatal']);
        $this->assertStringContainsString("%%EPOCH('2020-01-01')%%", $epoch['sql']);
    }

    /**
     * MySQL-only date functions with no clean mapping are rejected on non-MySQL databases, but kept
     * (they run natively, validated by the live dry-run) on MySQL/MariaDB.
     */
    public function test_convert_unmappable_date_fn_depends_on_dbfamily(): void {
        global $DB;
        $r = cr_import::convert('SELECT DATEDIFF(NOW(), created) FROM t');
        if ($DB->get_dbfamily() === 'mysql') {
            $this->assertNull($r['fatal']);
            $this->assertStringContainsString('DATEDIFF', $r['sql']);
        } else {
            $this->assertNotNull($r['fatal']);
        }
    }

    /**
     * A FROM_UNIXTIME format with an unsupported specifier cannot be mapped to a portable token. On
     * MySQL the native call is kept; elsewhere it is rejected rather than rendered wrong.
     */
    public function test_convert_unknown_format_specifier_depends_on_dbfamily(): void {
        global $DB;
        $r = cr_import::convert("SELECT FROM_UNIXTIME(t, '%W') FROM x");
        if ($DB->get_dbfamily() === 'mysql') {
            $this->assertNull($r['fatal']);
        } else {
            $this->assertNotNull($r['fatal']);
        }
    }

    /**
     * A non-SQL CR report type is rejected at classify time without touching the DB further.
     */
    public function test_classify_rejects_non_sql_type(): void {
        $this->resetAfterTest();
        $rec = (object) [
            'id'         => 1,
            'name'       => 'A timeline report',
            'type'       => 'timeline',
            'components' => '',
            'courseid'   => 1,
            'visible'    => 1,
            'summary'    => '',
        ];
        $info = cr_import::classify($rec);
        $this->assertSame('reject', $info['verdict']);
        $this->assertNotEmpty($info['reason']);
    }

    /**
     * A clean SQL report classifies as importable with a usable source.
     */
    public function test_classify_accepts_clean_sql(): void {
        $this->resetAfterTest();
        $rec = (object) [
            'id'         => 2,
            'name'       => 'Simple user list',
            'type'       => 'sql',
            'components' => self::make_components('SELECT id, username FROM prefix_user'),
            'courseid'   => 1,
            'visible'    => 1,
            'summary'    => '<p>All users</p>',
        ];
        $info = cr_import::classify($rec);
        $this->assertSame('import', $info['verdict']);
        $this->assertIsArray($info['source']);
        $this->assertSame(0, $info['source']['courseid']); // CR site course (1) -> site-wide (0).
    }

    /**
     * A SQL report referencing a table that does not exist fails the live dry-run.
     */
    public function test_classify_rejects_dead_table(): void {
        $this->resetAfterTest();
        $rec = (object) [
            'id'         => 3,
            'name'       => 'References a missing table',
            'type'       => 'sql',
            'components' => self::make_components('SELECT id FROM prefix_no_such_table_xyz'),
            'courseid'   => 1,
            'visible'    => 1,
            'summary'    => '',
        ];
        $info = cr_import::classify($rec);
        $this->assertSame('reject', $info['verdict']);
    }

    /**
     * Build a CR `components` blob the way the block stores it: serialize(urlencode_recursive(...))
     * with the config object tagged O:6:"object".
     *
     * @param string $sql
     * @return string
     */
    private static function make_components(string $sql): string {
        $config = (object) ['querysql' => urlencode($sql)];
        $data = ['customsql' => ['config' => $config]];
        $serialized = serialize($data);
        return str_replace('O:8:"stdClass"', 'O:6:"object"', $serialized);
    }
}
