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

use report_sql\local\sql\view;

/**
 * Tests for VIEW building and the portable date/time placeholder tokens.
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \report_sql\local\sql\view
 */
final class view_test extends \advanced_testcase {
    /**
     * %%TIMESTAMP(expr[, format])%% resolves to the bare epoch expression — no DB date function,
     * format dropped — so the column stays an integer that sorts chronologically.
     */
    public function test_resolve_strips_timestamp_token_to_epoch(): void {
        $this->resetAfterTest();

        $resolved = view::resolve_placeholders(
            'SELECT %%TIMESTAMP(u.lastaccess)%% AS a, %%TIMESTAMP(u.timecreated, dd/mm/yyyy)%% AS b FROM {user} u'
        );

        $this->assertStringContainsString('(u.lastaccess) AS a', $resolved);
        $this->assertStringContainsString('(u.timecreated) AS b', $resolved);
        // No date function and no leftover token or format text.
        $this->assertStringNotContainsStringIgnoringCase('from_unixtime', $resolved);
        $this->assertStringNotContainsStringIgnoringCase('to_timestamp', $resolved);
        $this->assertStringNotContainsString('%%', $resolved);
        $this->assertStringNotContainsString('dd/mm/yyyy', $resolved);
    }

    /**
     * %%EPOCH('literal')%% expands to the live family's datetime → epoch spelling: UNIX_TIMESTAMP on
     * MySQL/MariaDB, EXTRACT(EPOCH FROM TIMESTAMP '...')::int on PostgreSQL.
     */
    public function test_resolve_epoch_token_literal_is_dialect(): void {
        global $DB;
        $this->resetAfterTest();

        $resolved = view::resolve_placeholders(
            "SELECT %%EPOCH('2015-01-01 00:00:00')%% AS t FROM {user}"
        );

        if ($DB->get_dbfamily() === 'postgres') {
            $this->assertStringContainsString("EXTRACT(EPOCH FROM TIMESTAMP '2015-01-01 00:00:00')::int", $resolved);
        } else {
            $this->assertStringContainsString("UNIX_TIMESTAMP('2015-01-01 00:00:00')", $resolved);
        }
        $this->assertStringNotContainsString('%%', $resolved);
    }

    /**
     * %%EPOCH(expr)%% wraps a non-literal expression in parens on PostgreSQL (no TIMESTAMP cast).
     */
    public function test_resolve_epoch_token_expr_is_dialect(): void {
        global $DB;
        $this->resetAfterTest();

        $resolved = view::resolve_placeholders('SELECT %%EPOCH(u.timecreated)%% AS t FROM {user} u');

        if ($DB->get_dbfamily() === 'postgres') {
            $this->assertStringContainsString('EXTRACT(EPOCH FROM (u.timecreated))::int', $resolved);
        } else {
            $this->assertStringContainsString('UNIX_TIMESTAMP(u.timecreated)', $resolved);
        }
        $this->assertStringNotContainsString('%%', $resolved);
    }

    /**
     * %%NOW%% expands to the current-epoch expression for the live database.
     */
    public function test_resolve_now_token_is_dialect(): void {
        global $DB;
        $this->resetAfterTest();

        $resolved = view::resolve_placeholders('SELECT id FROM {user} WHERE lastlogin > %%NOW%%');

        if ($DB->get_dbfamily() === 'postgres') {
            $this->assertStringContainsString('EXTRACT(EPOCH FROM now())::int', $resolved);
        } else {
            $this->assertStringContainsString('UNIX_TIMESTAMP()', $resolved);
        }
        $this->assertStringNotContainsString('%%', $resolved);
    }

    /**
     * %%COURSECONTEXT%% resolves to the bound course's context row id, and to 0 site-wide.
     */
    public function test_resolve_course_context_token(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $courseid = (int) $course->id;
        $contextid = \context_course::instance($courseid)->id;

        $resolved = view::resolve_placeholders(
            'SELECT id FROM {role_assignments} WHERE contextid = %%COURSECONTEXT%%',
            $courseid
        );
        $this->assertStringContainsString('contextid = ' . $contextid, $resolved);
        $this->assertStringNotContainsString('%%', $resolved);

        // Site-wide (courseid 0) has no course context — resolves to 0.
        $sitewide = view::resolve_placeholders(
            'SELECT id FROM {role_assignments} WHERE contextid = %%COURSECONTEXT%%'
        );
        $this->assertStringContainsString('contextid = 0', $sitewide);
    }

    /**
     * %%CONTEXT_*%% tokens resolve to the matching Moodle context-level constant, case-insensitively
     * and without needing a course scope.
     */
    public function test_resolve_context_level_tokens(): void {
        $resolved = view::resolve_placeholders(
            'SELECT id FROM {context} WHERE contextlevel = %%CONTEXT_COURSE%%'
        );
        $this->assertStringContainsString('contextlevel = ' . CONTEXT_COURSE, $resolved);
        $this->assertStringNotContainsString('%%', $resolved);

        // Every advertised token maps to its core constant, matched case-insensitively.
        foreach (view::context_level_tokens() as $token => $level) {
            $out = view::resolve_placeholders('SELECT 1 WHERE x = ' . strtolower($token));
            $this->assertSame('SELECT 1 WHERE x = ' . $level, $out);
        }
    }

    /**
     * timestamp_columns() maps each token's output column (AS alias, else trailing identifier) to
     * its requested format ('' when none).
     */
    public function test_timestamp_columns_parses_aliases_and_formats(): void {
        $sql = 'SELECT '
            . '%%TIMESTAMP(u.lastaccess)%% AS lastaccess, '          // Aliased, no format.
            . '%%TIMESTAMP(u.timecreated, ddd dd Mon yyyy)%% AS created, ' // Aliased + format.
            . '%%TIMESTAMP(fp.modified)%%  created_updated, '        // Implicit alias (no AS).
            . '%%TIMESTAMP(fp.deleted, dd/mm/yyyy)%% deleted_at, '   // Implicit alias + format.
            . '%%TIMESTAMP(timemodified)%%, '                        // No alias -> trailing ident.
            . "CONCAT(firstname, ' ', %%TIMESTAMP(lastlogin, dd/mm/yy)%%) AS junk " // In expr, aliased outer.
            . 'FROM {user} u';

        $map = view::timestamp_columns($sql);

        $this->assertSame('', $map['lastaccess']);
        $this->assertSame('ddd dd Mon yyyy', $map['created']);
        // Implicit alias (AS keyword omitted) names the column, not the expression's trailing ident.
        $this->assertSame('', $map['created_updated']);
        $this->assertArrayNotHasKey('modified', $map);
        $this->assertSame('dd/mm/yyyy', $map['deleted_at']);
        $this->assertSame('', $map['timemodified']);
        // The lastlogin token has no AS of its own; it is named after its trailing identifier.
        $this->assertSame('dd/mm/yy', $map['lastlogin']);
    }

    /**
     * %%CASE(expr, mode)%% resolves to the bare text expression (the mode is dropped from the SQL
     * and applied later as a display callback), leaving no token or mode word behind.
     */
    public function test_resolve_strips_case_token_to_bare_expr(): void {
        $this->resetAfterTest();

        $resolved = view::resolve_placeholders(
            'SELECT %%CASE(u.lastname, upper)%% AS surname, %%CASE(u.city, sentence)%% FROM {user} u'
        );

        $this->assertStringContainsString('(u.lastname) AS surname', $resolved);
        $this->assertStringContainsString('(u.city)', $resolved);
        $this->assertStringNotContainsString('%%', $resolved);
        $this->assertStringNotContainsStringIgnoringCase('upper', $resolved);
        $this->assertStringNotContainsStringIgnoringCase('sentence', $resolved);
    }

    /**
     * case_columns() maps each token's output column (AS alias, else trailing identifier) to its
     * case mode, and ignores unknown modes.
     */
    public function test_case_columns_parses_aliases_and_modes(): void {
        $sql = 'SELECT '
            . '%%CASE(u.lastname, upper)%% AS surname, '   // Aliased.
            . '%%CASE(u.firstname, title)%% AS given, '    // Aliased.
            . '%%CASE(u.middlename, upper)%% middle, '      // Implicit alias (no AS).
            . '%%CASE(city)%% , '                          // No mode -> not a case column.
            . '%%CASE(u.username, lower)%%, '              // No alias -> trailing identifier.
            . '%%CASE(u.email, bogus)%% AS mail '          // Unknown mode -> ignored.
            . 'FROM {user} u';

        $map = view::case_columns($sql);

        $this->assertSame('upper', $map['surname']);
        $this->assertSame('title', $map['given']);
        $this->assertSame('upper', $map['middle']);
        $this->assertArrayNotHasKey('middlename', $map);
        $this->assertSame('lower', $map['username']);
        $this->assertArrayNotHasKey('city', $map);
        $this->assertArrayNotHasKey('mail', $map);
    }

    /**
     * %%VIEWER(expr)%% resolves to the bare expression as an ordinary output column — the view stays
     * unscoped; the per-viewer filter is applied later from the recorded useridcolumn.
     */
    public function test_resolve_strips_viewer_token_to_bare_expr(): void {
        $this->resetAfterTest();

        $resolved = view::resolve_placeholders(
            'SELECT %%VIEWER(u.id)%% AS uid, %%TEACHES(c.id)%% AS tc, '
            . '%%PAGECOURSE(c.id)%% AS pc, u.firstname FROM {user} u'
        );

        $this->assertStringContainsString('(u.id) AS uid', $resolved);
        $this->assertStringContainsString('(c.id) AS tc', $resolved);
        $this->assertStringContainsString('(c.id) AS pc', $resolved);
        $this->assertStringNotContainsString('%%', $resolved);
        $this->assertStringNotContainsStringIgnoringCase('VIEWER', $resolved);
        $this->assertStringNotContainsStringIgnoringCase('TEACHES', $resolved);
        $this->assertStringNotContainsStringIgnoringCase('PAGECOURSE', $resolved);
    }

    /**
     * filter_token_columns() names each token's output column (AS alias, else trailing identifier); a
     * token whose expression cannot be named is dropped, but filter_token_count() still counts it so
     * publish can reject rather than publish with the intended row scope silently unapplied. Works for
     * each filter token (VIEWER / TEACHES / PAGECOURSE).
     */
    public function test_filter_token_columns_and_count(): void {
        $aliased = 'SELECT %%VIEWER(u.id)%% AS viewerid, x FROM t';
        $this->assertSame(['viewerid' => true], view::filter_token_columns($aliased, 'VIEWER'));
        $this->assertSame(1, view::filter_token_count($aliased, 'VIEWER'));

        $bare = 'SELECT %%TEACHES(c.id)%%, x FROM t';
        $this->assertSame(['id' => true], view::filter_token_columns($bare, 'TEACHES'));
        $this->assertSame(1, view::filter_token_count($bare, 'TEACHES'));

        $page = 'SELECT %%PAGECOURSE(cc.courseid)%% AS coursecol, x FROM t';
        $this->assertSame(['coursecol' => true], view::filter_token_columns($page, 'PAGECOURSE'));

        // A token of one kind is not counted as another kind.
        $this->assertSame(0, view::filter_token_count($aliased, 'TEACHES'));

        // Expression with no trailing identifier and no alias — unnameable, so no column entry, but the
        // token is still counted.
        $unnameable = 'SELECT %%VIEWER(COALESCE(a.id, b.id))%%, x FROM t';
        $this->assertSame([], view::filter_token_columns($unnameable, 'VIEWER'));
        $this->assertSame(1, view::filter_token_count($unnameable, 'VIEWER'));

        $none = 'SELECT id FROM t';
        $this->assertSame([], view::filter_token_columns($none, 'VIEWER'));
        $this->assertSame(0, view::filter_token_count($none, 'VIEWER'));
    }

    /**
     * %%LINK(expr, 'path')%% resolves to the bare expression (the path is dropped from the SQL and
     * applied later as a display callback), leaving no token or path behind.
     */
    public function test_resolve_strips_link_token_to_bare_expr(): void {
        $this->resetAfterTest();

        $resolved = view::resolve_placeholders(
            "SELECT %%LINK(u.id, '/user/view.php?id={}')%% AS profile, "
            . "%%LINK(c.id, '/course/view.php?id={}')%% FROM {user} u"
        );

        $this->assertStringContainsString('(u.id) AS profile', $resolved);
        $this->assertStringContainsString('(c.id)', $resolved);
        $this->assertStringNotContainsString('%%', $resolved);
        $this->assertStringNotContainsString('/user/view.php', $resolved);
        $this->assertStringNotContainsString('/course/view.php', $resolved);
    }

    /**
     * link_columns() maps each token's output column (AS alias, else trailing identifier) to its
     * site-relative path, and rejects absolute / external / non-slash paths.
     */
    public function test_link_columns_parses_aliases_and_rejects_offsite(): void {
        $sql = 'SELECT '
            . "%%LINK(u.id, '/user/view.php?id={}')%% AS profile, "   // Aliased, site-relative.
            . "%%LINK(c.id, '/course/view.php?id={}')%% course, "     // Implicit alias (no AS).
            . "%%LINK(u.username, '/user/profile.php?u={}')%%, "      // No alias -> trailing identifier.
            . "%%LINK(u.email, 'https://evil.example/{}')%% AS ext, " // Absolute/external -> rejected.
            . "%%LINK(u.city, 'user/view.php?id={}')%% AS rel "       // No leading slash -> rejected.
            . 'FROM {user} u';

        $map = view::link_columns($sql);

        $this->assertSame('/user/view.php?id={}', $map['profile']['path']);
        $this->assertNull($map['profile']['keycol']);
        $this->assertSame('/course/view.php?id={}', $map['course']['path']);
        $this->assertSame('/user/profile.php?u={}', $map['username']['path']);
        $this->assertArrayNotHasKey('ext', $map);
        $this->assertArrayNotHasKey('rel', $map);
    }

    /**
     * The 3-argument %%LINK(display, keycol, 'path')%% form records the key column (which fills {})
     * separately from the display expression, and resolve_placeholders() still emits only the bare
     * display expression (both the key identifier and the path are dropped from the SQL).
     */
    public function test_link_columns_captures_key_column(): void {
        $sql = 'SELECT u.id AS userid, '
            . "%%LINK(CONCAT(u.firstname, ' ', u.lastname), userid, '/user/view.php?id={}')%% AS fullname "
            . 'FROM {user} u';

        $map = view::link_columns($sql);
        $this->assertSame('/user/view.php?id={}', $map['fullname']['path']);
        $this->assertSame('userid', $map['fullname']['keycol']);

        $resolved = view::resolve_placeholders($sql);
        $this->assertStringContainsString("(CONCAT(u.firstname, ' ', u.lastname)) AS fullname", $resolved);
        $this->assertStringNotContainsString('%%', $resolved);
        $this->assertStringNotContainsString(', userid,', $resolved);
        $this->assertStringNotContainsString('/user/view.php', $resolved);
    }

    /**
     * %%GROUP_CONCAT(expr, 'sep')%% resolves to the live family's aggregate spelling:
     * GROUP_CONCAT(expr SEPARATOR 'sep') on MySQL/MariaDB, string_agg((expr)::text, 'sep') on Postgres.
     */
    public function test_resolve_group_concat_token_is_dialect(): void {
        global $DB;
        $this->resetAfterTest();

        $resolved = view::resolve_placeholders(
            "SELECT cc.id, %%GROUP_CONCAT(c.shortname, ', ')%% AS names FROM {course} c "
            . 'JOIN {course_categories} cc ON cc.id = c.category GROUP BY cc.id'
        );

        if ($DB->get_dbfamily() === 'postgres') {
            $this->assertStringContainsString("string_agg((c.shortname)::text, ', ')", $resolved);
        } else {
            $this->assertStringContainsString("GROUP_CONCAT(c.shortname SEPARATOR ', ')", $resolved);
        }
        $this->assertStringNotContainsString('%%', $resolved);
    }

    /**
     * The separator is optional and defaults to ',' (MySQL's native default) when omitted.
     */
    public function test_resolve_group_concat_default_separator(): void {
        global $DB;
        $this->resetAfterTest();

        $resolved = view::resolve_placeholders('SELECT %%GROUP_CONCAT(u.lastname)%% AS names FROM {user} u');

        if ($DB->get_dbfamily() === 'postgres') {
            $this->assertStringContainsString("string_agg((u.lastname)::text, ',')", $resolved);
        } else {
            $this->assertStringContainsString("GROUP_CONCAT(u.lastname SEPARATOR ',')", $resolved);
        }
    }

    /**
     * A leading DISTINCT keyword is carried through into the same position for both engines, and a
     * separator literal containing a comma (', ') is not mis-split at that inner comma.
     */
    public function test_resolve_group_concat_distinct_and_comma_separator(): void {
        global $DB;
        $this->resetAfterTest();

        $resolved = view::resolve_placeholders(
            "SELECT %%GROUP_CONCAT(DISTINCT c.format, ', ')%% AS formats FROM {course} c"
        );

        if ($DB->get_dbfamily() === 'postgres') {
            $this->assertStringContainsString("string_agg(DISTINCT (c.format)::text, ', ')", $resolved);
        } else {
            $this->assertStringContainsString("GROUP_CONCAT(DISTINCT c.format SEPARATOR ', ')", $resolved);
        }
        $this->assertStringNotContainsString('%%', $resolved);
    }
}
