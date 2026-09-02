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

use report_sql\local\sql\validator;

/**
 * Unit tests for the SQL validator.
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \report_sql\local\sql\validator
 */
final class sql_validator_test extends \advanced_testcase {
    /**
     * Data provider of SQL strings that must pass validation.
     *
     * @return array
     */
    public static function valid_provider(): array {
        return [
            'simple SELECT' => ['SELECT id, fullname FROM {course}'],
            'WITH CTE'      => ['WITH x AS (SELECT id FROM {course}) SELECT * FROM x'],
            'aggregate'     => ['SELECT COUNT(*) c FROM {user}'],
            'trailing semi' => ['SELECT 1;'],
            'JOIN with ON'  => ['SELECT u.id FROM {user} u JOIN {user_enrolments} ue ON ue.userid = u.id'],
            'LEFT JOIN ON'  => ['SELECT u.id FROM {user} u LEFT JOIN {role} r ON r.id = u.id'],
            'CROSS JOIN'    => ['SELECT a.id FROM {course} a CROSS JOIN {user} b'],
            'comma join'    => ['SELECT a.id FROM {course} a, {user} b WHERE a.id = b.id'],
            'JOIN USING'    => ['SELECT u.id FROM {user} u JOIN {role} r USING (id)'],
            'UNION'         => ['SELECT id FROM {user} UNION SELECT id FROM {course}'],
            'UNION ALL'     => ['SELECT id FROM {user} UNION ALL SELECT id FROM {course}'],
            'three-way UNION' => [
                'SELECT id FROM {user} UNION SELECT id FROM {course} UNION ALL SELECT id FROM {role}',
            ],
            'REPLACE function' => ["SELECT REPLACE(fullname, 'x', 'y') AS n FROM {course}"],
        ];
    }

    /**
     * Valid SQL is accepted by the validator.
     *
     * @dataProvider valid_provider
     * @param string $sql
     */
    public function test_valid(string $sql): void {
        $this->assertNotEmpty(validator::validate($sql));
    }

    /**
     * Data provider of SQL strings that must fail validation.
     *
     * @return array
     */
    public static function invalid_provider(): array {
        return [
            'empty'           => [''],
            'INSERT'          => ['INSERT INTO {course} VALUES (1)'],
            'UPDATE'          => ['UPDATE {course} SET fullname = \'x\''],
            'DELETE'          => ['DELETE FROM {course}'],
            'DROP'            => ['DROP TABLE {course}'],
            'multi statement' => ['SELECT 1; SELECT 2'],
            'bare multi statement' => ['SELECT 1 SELECT 2'],
            'REPLACE statement' => ['REPLACE {course} VALUES (1)'],
            'SELECT INTO'     => ['SELECT * INTO foo FROM {user}'],
            'denied table'    => ['SELECT * FROM {config}'],
            'EXECUTE'         => ['EXECUTE my_proc'],
            'CREATE VIEW'     => ['CREATE VIEW foo AS SELECT 1'],
            'JOIN missing ON' => ['SELECT u.firstname FROM {user} u JOIN {user_enrolments} ue.userid = u.id'],
            'LEFT JOIN no ON' => ['SELECT u.id FROM {user} u LEFT JOIN {role} r'],
        ];
    }

    /**
     * Invalid SQL is rejected by the validator.
     *
     * @dataProvider invalid_provider
     * @param string $sql
     */
    public function test_invalid(string $sql): void {
        $this->expectException(\moodle_exception::class);
        validator::validate($sql);
    }

    public function test_comment_only_select_passes(): void {
        // Comment-stripped form is "SELECT 1", with no trailing keyword. Should still pass.
        $this->assertNotEmpty(validator::validate('SELECT 1 /* harmless */'));
    }

    public function test_auto_brace_is_idempotent(): void {
        // Safety net for the "Show table braces in editor" setting: when braced SQL is shown in the
        // editor and re-saved, auto_brace() must not double-brace already-braced tables.
        $once = validator::auto_brace('SELECT id FROM user JOIN course ON course.id = user.id');
        $this->assertSame($once, validator::auto_brace($once));
        $this->assertStringNotContainsString('{{', $once);
        $this->assertStringContainsString('{user}', $once);
        $this->assertStringContainsString('{course}', $once);
    }

    public function test_strip_braces_round_trips_with_auto_brace(): void {
        // Displaying braced SQL brace-free and re-bracing on save must return the original braced form.
        $braced = 'SELECT id FROM {user} u JOIN {course} c ON c.id = u.id';
        $this->assertSame($braced, validator::auto_brace(validator::strip_braces($braced)));
    }

    public function test_string_literal_does_not_evade_keyword_scan(): void {
        // String literals are blanked before scan, so "DROP" inside a literal won't trigger.
        $this->assertNotEmpty(validator::validate("SELECT 'DROP TABLE x' AS s"));
    }

    public function test_doubled_like_wildcard_is_not_mistaken_for_token(): void {
        // A LIKE pattern with doubled wildcards (e.g. '%%smi%%') sits inside a string literal,
        // which is blanked before the placeholder scan, so it must not be rejected as an
        // unfilled %%...%% token.
        $this->assertNotEmpty(
            validator::validate("SELECT id FROM {user} WHERE username LIKE '%%smi%%'")
        );
    }

    public function test_double_hash_inside_string_literal_is_allowed(): void {
        // A literal '##' inside a string is not an unfilled ad-hoc artifact; only a bare ##
        // (outside any string) should be rejected.
        $this->assertNotEmpty(
            validator::validate("SELECT id, '##' AS label FROM {user}")
        );
    }

    public function test_context_level_token_is_supported(): void {
        // Token %%CONTEXT_COURSE%% is a recognised token, so validation must not reject it as an
        // unfilled placeholder.
        $this->assertNotEmpty(
            validator::validate('SELECT id FROM {context} WHERE contextlevel = %%CONTEXT_COURSE%%')
        );
    }

    public function test_group_concat_token_is_supported(): void {
        // Token %%GROUP_CONCAT(...)%% is recognised by shape, so validation must not reject it as an
        // unfilled placeholder (including the DISTINCT + comma-in-separator form).
        $this->assertNotEmpty(
            validator::validate(
                "SELECT cc.id, %%GROUP_CONCAT(DISTINCT c.format, ', ')%% AS formats "
                . 'FROM {course} c JOIN {course_categories} cc ON cc.id = c.category GROUP BY cc.id'
            )
        );
    }

    public function test_unknown_context_token_is_rejected(): void {
        // A made-up %%CONTEXT_*%% name is not in the supported set and must be rejected.
        $this->expectException(\moodle_exception::class);
        validator::validate('SELECT id FROM {context} WHERE contextlevel = %%CONTEXT_GALAXY%%');
    }

    public function test_join_without_on_reports_specific_error(): void {
        // A JOIN missing its ON condition should raise the dedicated, friendly message
        // rather than letting the DB return an opaque syntax error.
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('errjoinnoon', 'report_sql'));
        validator::validate('SELECT u.firstname FROM {user} u JOIN {user_enrolments} ue.userid = u.id');
    }

    public function test_denied_column_rejected_even_when_aliased(): void {
        $this->resetAfterTest();
        set_config('denycolumns', 'password,secret,sesskey', 'report_sql');

        // Aliasing the denied source column must not slip it past the denylist.
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('errdeniedcolumn', 'report_sql', 'password'));
        validator::validate('SELECT password AS pw FROM {user}');
    }

    public function test_denied_column_in_literal_is_allowed(): void {
        $this->resetAfterTest();
        set_config('denycolumns', 'password,secret,sesskey', 'report_sql');

        // The denied word only appears inside a string literal, which is blanked before the scan.
        $this->assertNotEmpty(validator::validate("SELECT id, 'password' AS label FROM {user}"));
    }

    public function test_denytable_setting_adds_custom_table(): void {
        $this->resetAfterTest();
        // A table not in the built-in baseline is rejected once the admin adds it to 'denytables'.
        set_config('denytables', validator::default_denytables() . ',my_secret_table', 'report_sql');

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('errdeniedtable', 'report_sql', 'my_secret_table'));
        validator::validate('SELECT id FROM {my_secret_table}');
    }

    public function test_denytable_setting_can_remove_baseline_entry(): void {
        $this->resetAfterTest();
        // The setting is the single source of truth: dropping a baseline entry re-exposes that table.
        // Seed it with everything except 'config'.
        $tables = array_diff(explode(',', validator::default_denytables()), ['config']);
        set_config('denytables', implode(',', $tables), 'report_sql');

        $this->assertNotEmpty(validator::validate('SELECT id FROM {config}'));
    }

    public function test_denytable_baseline_applies_when_setting_unset(): void {
        $this->resetAfterTest();
        // With no 'denytables' saved, the built-in baseline still blocks a protected table.
        set_config('denytables', null, 'report_sql');

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('errdeniedtable', 'report_sql', 'sessions'));
        validator::validate('SELECT id FROM {sessions}');
    }

    public function test_qualified_table_reference_is_rejected(): void {
        // A schema-qualified (dotted) table name is never braced by auto_brace() and so would
        // slip past the {tablename} DENY_TABLES scan, reaching the wider DB surface. Reject it.
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('errqualifiedtable', 'report_sql', 'information_schema.columns'));
        validator::validate('SELECT * FROM information_schema.columns');
    }

    public function test_qualified_table_in_comma_list_is_rejected(): void {
        // The dotted reference is the *second* table in a comma FROM-list, so a single
        // FROM/JOIN regex would miss it — the parse-tree walk still catches it.
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('errqualifiedtable', 'report_sql', 'mysql.user'));
        validator::validate('SELECT * FROM {user} u, mysql.user m');
    }

    public function test_qualified_table_in_join_is_rejected(): void {
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('errqualifiedtable', 'report_sql', 'information_schema.tables'));
        validator::validate('SELECT t.id FROM {user} u JOIN information_schema.tables t ON t.id = u.id');
    }

    public function test_qualified_table_in_subquery_is_rejected(): void {
        // Nested inside a derived-table subquery — the recursive walk reaches it.
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('errqualifiedtable', 'report_sql', 'mysql.user'));
        validator::validate('SELECT x.id FROM (SELECT id FROM mysql.user) x');
    }

    public function test_qualified_column_reference_still_allowed(): void {
        // A dotted *column* reference (alias.column) is expr_type=colref, not a table position,
        // so it must not be mistaken for a schema-qualified table.
        $sql = 'SELECT u.id, u.firstname FROM {user} u WHERE u.deleted = 0';
        $this->assertNotEmpty(validator::validate($sql));
    }

    public function test_cte_reference_still_allowed(): void {
        // A CTE name referenced in FROM is a bare (dotless) word, so it passes the dotted-table check.
        $sql = 'WITH recent AS (SELECT id FROM {user}) SELECT id FROM recent';
        $this->assertNotEmpty(validator::validate($sql));
    }

    public function test_mixed_case_quoted_alias_no_longer_warns(): void {
        $sql = 'SELECT ue.userid, c.shortname AS "Course_Shortname" '
            . 'FROM {user_enrolments} ue JOIN {enrol} e ON ue.enrolid = e.id '
            . 'JOIN {course} c ON e.courseid = c.id';

        validator::validate($sql);
        // Mixed-case aliases are now lowercased at view-build time, not warned about.
        $this->assertEmpty(validator::get_warnings());
    }

    public function test_normalise_aliases_lowercases_quoted_alias_on_postgres(): void {
        global $DB;
        $sql = 'SELECT c.shortname AS "Course_Shortname" FROM {course} c';
        $out = \report_sql\local\sql\view::normalise_aliases($sql);

        if ($DB->get_dbfamily() === 'postgres') {
            // PostgreSQL: double-quoted alias is lowercased to match RB's case-folded reference.
            $this->assertStringContainsString('AS "course_shortname"', $out);
        } else {
            // MySQL/MariaDB fold case anyway, so the alias is left as written.
            $this->assertStringContainsString('AS "Course_Shortname"', $out);
        }
    }

    public function test_placeholders(): void {
        $names = validator::placeholders(
            'SELECT id FROM {course} WHERE category = :cat AND timecreated > :since AND :cat = :cat'
        );
        $this->assertSame(['cat', 'since'], $names);
    }

    /**
     * The %%USERID%% family of tokens gets the dedicated message steering authors to the
     * "Restrict to viewing user" form field, not the generic unfilled-placeholder hint.
     *
     * @dataProvider userid_token_provider
     * @param string $token
     */
    public function test_userid_placeholder_reports_specific_error(string $token): void {
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('errplaceholderuserid', 'report_sql', $token));
        validator::validate("SELECT id FROM {user} WHERE id = {$token}");
    }

    /**
     * Case and spelling variants the validator routes to errplaceholderuserid.
     *
     * @return array<string, array{string}>
     */
    public static function userid_token_provider(): array {
        return [
            'upper'          => ['%%USERID%%'],
            'lower'          => ['%%userid%%'],
            'mixed'          => ['%%UserId%%'],
            'underscore'     => ['%%USER_ID%%'],
            'plural'         => ['%%USERIDS%%'],
            'underscoreplur' => ['%%USER_IDS%%'],
            'inner spaces'   => ['%% USERID %%'],
        ];
    }

    /**
     * A near-miss token that is not the userid family still gets the generic placeholder error.
     */
    public function test_non_userid_placeholder_reports_generic_error(): void {
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('errplaceholder', 'report_sql', '%%FILTER_USERS%%'));
        validator::validate('SELECT id FROM {user} WHERE id = %%FILTER_USERS%%');
    }

    /**
     * A ? inside a %%...%% token is accepted: the token is resolved away by
     * {@see \report_sql\local\sql\view::resolve_placeholders()} before the SQL reaches the
     * database, so that ? never becomes a positional bind parameter. Pins the exemption the
     * client-side mirror in amd/src/sqlfix.js has to reproduce.
     *
     * @dataProvider token_questionmark_provider
     * @param string $sql
     */
    public function test_questionmark_inside_token_is_allowed(string $sql): void {
        $this->assertNotEmpty(validator::validate($sql));
    }

    /**
     * Tokens whose arguments legitimately carry a ? — every useful Moodle URL is a view.php?id= form.
     *
     * @return array<string, array{string}>
     */
    public static function token_questionmark_provider(): array {
        return [
            'LINK two-arg' => [
                "SELECT %%LINK(u.id, '/user/view.php?id={}')%% AS profile FROM {user} u",
            ],
            'LINK three-arg' => [
                "SELECT u.id AS userid, %%LINK(u.firstname, userid, '/user/view.php?id={}')%% AS n " .
                    'FROM {user} u',
            ],
            'LINK with multi-param path' => [
                "SELECT %%LINK(c.id, '/mod/forum/view.php?id={}&p=1')%% AS c FROM {course} c",
            ],
            'token beside a clean literal' => [
                "SELECT %%LINK(u.id, '/user/view.php?id={}')%% AS p, 'plain' AS t FROM {user} u",
            ],
        ];
    }

    /**
     * A ? outside any token is still rejected — the database layer would read it as a positional
     * bind parameter and fail with a parameter-count error.
     *
     * @dataProvider bare_questionmark_provider
     * @param string $sql
     */
    public function test_bare_questionmark_is_rejected(string $sql): void {
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('errquestionmark', 'report_sql'));
        validator::validate($sql);
    }

    /**
     * A ? that has to stay rejected, including one sitting alongside a token that exempts its own.
     *
     * @return array<string, array{string}>
     */
    public static function bare_questionmark_provider(): array {
        return [
            'literal URL' => [
                "SELECT CONCAT('/course/view.php?id=', c.id) AS url FROM {course} c",
            ],
            'bare placeholder' => [
                'SELECT id FROM {user} WHERE id = ?',
            ],
            'bare literal beside a token' => [
                "SELECT %%LINK(u.id, '/user/view.php?id={}')%% AS p, " .
                    "CONCAT('/x.php?y=', u.id) AS u FROM {user} u",
            ],
        ];
    }
}
