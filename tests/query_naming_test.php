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

use report_sql\local\query_naming;

/**
 * Tests for the name/description heuristics extracted from query.
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \report_sql\local\query_naming
 */
final class query_naming_test extends \advanced_testcase {
    public function test_from_question_takes_first_sentence_and_capitalises(): void {
        $this->assertSame(
            'List all active users',
            query_naming::from_question('list all active users. with extra trailing detail here')
        );
    }

    public function test_from_question_truncates_on_word_boundary(): void {
        $long = str_repeat('word ', 30);
        $name = query_naming::from_question($long);
        $this->assertLessThanOrEqual(60, \core_text::strlen($name));
        // Truncation lands on a word boundary, so no partial trailing word.
        $this->assertStringEndsWith('word', rtrim($name));
    }

    public function test_from_question_empty_falls_back_to_generated_name(): void {
        $this->assertSame(
            get_string('ai:generatedname', 'report_sql'),
            query_naming::from_question('   ')
        );
    }

    public function test_is_error_fix_prompt_detects_fix_error_phrasing(): void {
        $this->assertTrue(query_naming::is_error_fix_prompt('Fix this SQL error: bad column'));
        $this->assertFalse(query_naming::is_error_fix_prompt('list all users'));
        // Needs both "fix" at the start and "error" somewhere.
        $this->assertFalse(query_naming::is_error_fix_prompt('fix the report layout'));
    }

    public function test_refers_to_existing_sql_demonstratives(): void {
        $this->assertTrue(query_naming::refers_to_existing_sql('add a column to this query'));
        $this->assertTrue(query_naming::refers_to_existing_sql('modify the above report to exclude guests'));
        $this->assertTrue(query_naming::refers_to_existing_sql('also show the email address'));
        // The word "also" alone pulls in the existing SQL.
        $this->assertTrue(query_naming::refers_to_existing_sql('also the last login time'));
        // Error-fix prompts always refer to the current SQL.
        $this->assertTrue(query_naming::refers_to_existing_sql('Fix this SQL error: bad column'));
    }

    public function test_refers_to_existing_sql_fresh_description_is_false(): void {
        $this->assertFalse(query_naming::refers_to_existing_sql('list all active users'));
        $this->assertFalse(query_naming::refers_to_existing_sql('show students enrolled in more than 3 courses'));
    }

    public function test_from_sql_single_table(): void {
        $name = query_naming::from_sql('SELECT id FROM {user}');
        $this->assertSame(
            get_string('ai:sqlname', 'report_sql', 'User'),
            $name
        );
    }

    public function test_from_sql_strips_prefix_and_braces_and_joins(): void {
        global $CFG;
        $sql = 'SELECT u.id FROM ' . $CFG->prefix . 'user u JOIN {course} c ON c.id = u.id';
        $name = query_naming::from_sql($sql);
        // Both tables surface, prefix and braces stripped.
        $this->assertStringContainsString('User', $name);
        $this->assertStringContainsString('Course', $name);
    }

    public function test_from_sql_no_tables_falls_back(): void {
        $this->assertSame(
            get_string('ai:generatedname', 'report_sql'),
            query_naming::from_sql('SELECT 1')
        );
    }

    public function test_description_from_sql_lists_columns_and_tables(): void {
        $desc = query_naming::description_from_sql('SELECT id, username FROM {user}');
        $expected = get_string(
            'ai:sqldescription',
            'report_sql',
            (object) ['columns' => 'id, username', 'tables' => 'user']
        );
        $this->assertSame($expected, $desc);
    }

    public function test_description_from_sql_select_star_omits_columns(): void {
        $desc = query_naming::description_from_sql('SELECT * FROM {user}');
        $this->assertSame(
            get_string('ai:sqldescriptionnocols', 'report_sql', 'user'),
            $desc
        );
    }
}
