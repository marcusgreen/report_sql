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

use report_sql\local\sql\analyser;

/**
 * Unit tests for the report-source analyser (Check button backend).
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \report_sql\local\sql\analyser
 */
final class analyser_test extends \advanced_testcase {
    /**
     * Invalid SQL fails cleanly with ok=false and an error message.
     */
    public function test_invalid_sql_reports_error(): void {
        $this->resetAfterTest();
        $result = analyser::analyse('DELETE FROM {user}');
        $this->assertFalse($result['ok']);
        $this->assertNotEmpty($result['error']);
    }

    /**
     * SQL that passes static validation but references a non-existent table fails the live
     * dry-run with ok=false and a message, rather than silently reporting rowcount -1.
     */
    public function test_unknown_table_reports_error(): void {
        $this->resetAfterTest();
        $result = analyser::analyse('SELECT id FROM {nosuchtable}');
        $this->assertFalse($result['ok']);
        $this->assertNotEmpty($result['error']);
    }

    /**
     * A non-existent column in an otherwise valid query is caught by the dry-run.
     */
    public function test_unknown_column_reports_error(): void {
        $this->resetAfterTest();
        $result = analyser::analyse('SELECT nosuchcolumn FROM {user}');
        $this->assertFalse($result['ok']);
        $this->assertNotEmpty($result['error']);
    }

    /**
     * A valid, runnable query passes the dry-run: ok=true and no error.
     */
    public function test_runnable_sql_has_no_error(): void {
        $this->resetAfterTest();
        $result = analyser::analyse('SELECT id FROM {user}');
        $this->assertTrue($result['ok']);
        $this->assertSame('', $result['error']);
    }

    /**
     * The row count matches the number of rows the query returns.
     */
    public function test_row_count(): void {
        $this->resetAfterTest();
        $before = analyser::analyse('SELECT id FROM {user}')['rowcount'];
        $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->create_user();
        $after = analyser::analyse('SELECT id FROM {user}')['rowcount'];
        $this->assertSame($before + 2, $after);
    }

    /**
     * An integer column whose name looks like a date and whose value is a plausible
     * epoch is suggested for the %%TIMESTAMP()%% token.
     */
    public function test_date_column_suggested(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user(['timecreated' => time()]);

        $result = analyser::analyse('SELECT id, timecreated FROM {user} WHERE id = ' . (int) $user->id);
        $this->assertTrue($result['ok']);
        $this->assertContainsEquals('timecreated', $result['datecolumns']);
    }

    /**
     * Several date-like columns are each returned as a distinct clickable column name.
     */
    public function test_multiple_date_columns(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user(['timecreated' => time(), 'timemodified' => time()]);

        $result = analyser::analyse(
            'SELECT id, timecreated, timemodified FROM {user} WHERE id = ' . (int) $user->id
        );
        $this->assertTrue($result['ok']);
        $this->assertContainsEquals('timecreated', $result['datecolumns']);
        $this->assertContainsEquals('timemodified', $result['datecolumns']);
    }

    /**
     * lastlogin is recognised as a date column (name-implied), even sampled as 0 ("never").
     */
    public function test_lastlogin_suggested(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user(['lastlogin' => 0]);

        $result = analyser::analyse('SELECT id, lastlogin FROM {user} WHERE id = ' . (int) $user->id);
        $this->assertTrue($result['ok']);
        $this->assertContainsEquals('lastlogin', $result['datecolumns']);
    }

    /**
     * A plain non-date integer column is not suggested.
     */
    public function test_plain_column_not_suggested(): void {
        $this->resetAfterTest();
        $result = analyser::analyse('SELECT id FROM {user}');
        $this->assertTrue($result['ok']);
        $this->assertSame([], $result['datecolumns']);
    }

    /**
     * A column already wrapped in %%TIMESTAMP()%% is not re-listed.
     */
    public function test_timestamp_token_not_re_suggested(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user(['timecreated' => time()]);

        $result = analyser::analyse(
            'SELECT id, %%TIMESTAMP(timecreated)%% AS timecreated FROM {user} WHERE id = ' . (int) $user->id
        );
        $this->assertTrue($result['ok']);
        $this->assertSame([], $result['datecolumns']);
    }

    /**
     * A select-list UPPER()/LOWER() call over a column is offered as a %%CASE()%% click-to-wrap,
     * carrying the requested output name and mode.
     */
    public function test_case_column_suggested(): void {
        $this->resetAfterTest();
        $result = analyser::analyse('SELECT id, UPPER(username) AS uname, LOWER(email) AS mail FROM {user}');
        $this->assertTrue($result['ok']);
        $this->assertContainsEquals(['col' => 'uname', 'mode' => 'upper'], $result['casecolumns']);
        $this->assertContainsEquals(['col' => 'mail', 'mode' => 'lower'], $result['casecolumns']);
    }

    /**
     * The MySQL-only UCASE()/LCASE() aliases map to the same upper/lower modes.
     */
    public function test_case_column_mysql_aliases(): void {
        global $DB;
        if ($DB->get_dbfamily() !== 'mysql') {
            $this->markTestSkipped('UCASE()/LCASE() are MySQL-only; the live dry-run rejects them elsewhere.');
        }
        $this->resetAfterTest();
        $result = analyser::analyse('SELECT UCASE(username) AS u, LCASE(username) AS l FROM {user}');
        $this->assertTrue($result['ok']);
        $this->assertContainsEquals(['col' => 'u', 'mode' => 'upper'], $result['casecolumns']);
        $this->assertContainsEquals(['col' => 'l', 'mode' => 'lower'], $result['casecolumns']);
    }

    /**
     * A column already using the %%CASE()%% token, and a plain column, are not listed; nor is an
     * UPPER() that only appears in the WHERE clause (no output column to wrap).
     */
    public function test_case_column_not_over_suggested(): void {
        $this->resetAfterTest();
        $result = analyser::analyse(
            "SELECT id, %%CASE(username, upper)%% AS uname FROM {user} WHERE UPPER(email) = 'X'"
        );
        $this->assertTrue($result['ok']);
        $this->assertSame([], $result['casecolumns']);
    }

    /**
     * A case call that does not span the whole select-list item (concatenated with more SQL) is
     * not offered — wrapping only its inner argument would drop the rest.
     */
    public function test_case_column_partial_expression_skipped(): void {
        $this->resetAfterTest();
        $result = analyser::analyse("SELECT UPPER(firstname) || ' ' || lastname AS n FROM {user}");
        $this->assertTrue($result['ok']);
        $this->assertSame([], $result['casecolumns']);
    }

    /**
     * Sorting by an unindexed column, when the table has indexed columns, produces an index
     * suggestion naming the sorted column and the indexed alternatives.
     */
    public function test_sort_unindexed_column_suggests_index(): void {
        $this->resetAfterTest();
        // Column {user}.description has no index; {user} has indexed columns (e.g. email).
        $result = analyser::analyse('SELECT id, description FROM {user} ORDER BY description');
        $this->assertTrue($result['ok']);
        $joined = implode("\n", $result['indexinfo']);
        $this->assertStringContainsStringIgnoringCase('description', $joined);
        $this->assertStringContainsStringIgnoringCase('indexed columns available', $joined);
    }

    /**
     * Sorting by an indexed column produces no index suggestion.
     */
    public function test_sort_indexed_column_no_suggestion(): void {
        $this->resetAfterTest();
        // Column {user}.email is indexed, so sorting by it needs no advice.
        $result = analyser::analyse('SELECT id, email FROM {user} ORDER BY email');
        $this->assertTrue($result['ok']);
        $this->assertSame([], $result['indexinfo']);
    }

    /**
     * A query with no ORDER BY produces no index suggestion (the blanket index dump is gone).
     */
    public function test_no_order_by_no_index_output(): void {
        $this->resetAfterTest();
        $result = analyser::analyse('SELECT id FROM {user}');
        $this->assertTrue($result['ok']);
        $this->assertSame([], $result['indexinfo']);
    }

    /**
     * The primary key counts as indexed: sorting by "id" gives no suggestion even though
     * get_indexes() omits the primary key.
     */
    public function test_sort_by_primary_key_no_suggestion(): void {
        $this->resetAfterTest();
        $result = analyser::analyse('SELECT id, firstname FROM {user} ORDER BY id');
        $this->assertTrue($result['ok']);
        $this->assertSame([], $result['indexinfo']);
    }

    /**
     * A LIKE pattern with a leading wildcard is flagged as non-indexable.
     */
    public function test_leading_wildcard_warned(): void {
        $this->resetAfterTest();
        $result = analyser::analyse("SELECT id FROM {user} WHERE username LIKE '%admin'");
        $this->assertTrue($result['ok']);
        $this->assertStringContainsStringIgnoringCase('wildcard', implode("\n", $result['warnings']));
    }

    /**
     * An anchored LIKE pattern (no leading wildcard) is not flagged.
     */
    public function test_anchored_like_not_warned(): void {
        $this->resetAfterTest();
        $result = analyser::analyse("SELECT id FROM {user} WHERE username LIKE 'admin%'");
        $this->assertTrue($result['ok']);
        $this->assertStringNotContainsStringIgnoringCase('wildcard', implode("\n", $result['warnings']));
    }

    /**
     * A function wrapping a column in WHERE is flagged as non-sargable.
     */
    public function test_nonsargable_where_warned(): void {
        $this->resetAfterTest();
        $result = analyser::analyse("SELECT id FROM {user} WHERE LOWER(username) = 'admin'");
        $this->assertTrue($result['ok']);
        $this->assertStringContainsStringIgnoringCase('non-sargable', implode("\n", $result['warnings']));
    }

    /**
     * A subquery in the SELECT list is flagged as per-row work.
     */
    public function test_select_list_subquery_warned(): void {
        $this->resetAfterTest();
        $result = analyser::analyse('SELECT id, (SELECT COUNT(*) FROM {user}) AS total FROM {user}');
        $this->assertTrue($result['ok']);
        $this->assertStringContainsStringIgnoringCase('subquery', implode("\n", $result['warnings']));
    }

    /**
     * A subquery used only in the FROM/WHERE (not the select list) is not flagged as a
     * select-list subquery.
     */
    public function test_where_subquery_not_flagged_as_select_list(): void {
        $this->resetAfterTest();
        $result = analyser::analyse(
            'SELECT id FROM {user} WHERE id IN (SELECT userid FROM {user_enrolments})'
        );
        $this->assertTrue($result['ok']);
        $this->assertStringNotContainsStringIgnoringCase('per returned row', implode("\n", $result['warnings']));
    }

    /**
     * A plain query trips none of the performance heuristics.
     */
    public function test_plain_query_no_performance_warnings(): void {
        $this->resetAfterTest();
        $joined = implode("\n", analyser::analyse('SELECT id, username FROM {user}')['warnings']);
        $this->assertStringNotContainsStringIgnoringCase('wildcard', $joined);
        $this->assertStringNotContainsStringIgnoringCase('non-sargable', $joined);
        $this->assertStringNotContainsStringIgnoringCase('subquery', $joined);
    }

    /**
     * When a caller supplies a pre-built view (the inline-preview path), analyse() introspects it
     * for date columns instead of building its own probe view, and does not leave that probe view
     * behind. The result matches the no-view path.
     */
    public function test_analyse_reuses_supplied_view(): void {
        global $USER, $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $user = $this->getDataGenerator()->create_user(['timecreated' => time()]);

        $sql = 'SELECT id, timecreated FROM {user} WHERE id = ' . (int) $user->id;
        $validated = \report_sql\local\sql\validator::validate($sql);
        $viewname = \report_sql\local\sql\view::create_or_replace_preview((int) $USER->id, $validated, 0);

        try {
            $result = analyser::analyse($sql, 0, $viewname);
        } finally {
            \report_sql\local\sql\view::drop_preview((int) $USER->id);
        }

        $this->assertTrue($result['ok']);
        $this->assertContainsEquals('timecreated', $result['datecolumns']);
        // The private probe view is never created on the supplied-view path, so none is left behind.
        $probe = \report_sql\local\sql\privilege_check::PROBE_NAME . '_chk';
        $this->assertArrayNotHasKey($probe, $DB->get_tables(false));
    }
}
