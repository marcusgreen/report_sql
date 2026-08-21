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

namespace report_sql\local;

/**
 * Heuristics that derive a human-readable query name / description from a natural-language question
 * or from the meaning of a SQL statement.
 *
 * These are pure string→string functions (no DB, no state) used when AI generation produces SQL for
 * a query that has no name yet, so the generated query is immediately saveable (name is required).
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class query_naming {
    /**
     * Derive a concise, human-readable query name from a natural-language question.
     *
     * Takes the first sentence, collapses whitespace, trims to ~60 chars on a word boundary and
     * capitalises the first letter.
     *
     * @param string $question The natural-language prompt the user typed.
     * @return string A non-empty query name.
     */
    public static function from_question(string $question): string {
        $text = trim((string) preg_replace('/\s+/', ' ', $question));
        if (preg_match('/^(.*?[.?!])(\s|$)/u', $text, $m)) {
            $text = $m[1];
        }
        $text = rtrim($text, " \t\n\r\0\x0B.?!,;:");

        $max = 60;
        if (\core_text::strlen($text) > $max) {
            $truncated = \core_text::substr($text, 0, $max);
            $space = \core_text::strrpos($truncated, ' ');
            $text = $space > 0 ? \core_text::substr($truncated, 0, (int) $space) : $truncated;
            $text = rtrim($text);
        }

        if ($text === '') {
            return get_string('ai:generatedname', 'report_sql');
        }
        return \core_text::strtoupper(\core_text::substr($text, 0, 1)) . \core_text::substr($text, 1);
    }

    /**
     * Whether an AI question is a "fix this SQL error" style prompt rather than a real description
     * of the wanted data. Such prompts (e.g. the one editor.es6.js pre-fills on a validation
     * failure) make for useless query names, so callers derive the name/description from the
     * generated SQL instead.
     *
     * @param string $question
     * @return bool
     */
    public static function is_error_fix_prompt(string $question): bool {
        return (bool) preg_match('/^\s*fix\b/i', $question)
            && (bool) preg_match('/\berror\b/i', $question);
    }

    /**
     * Whether an AI question is talking about the SQL already in the editor rather than describing a
     * brand-new report from scratch. When true, the caller feeds the current SQL (select-only) field
     * to the AI as the basis of the prompt, so "add a column to this", "fix this error", "also show
     * the email" etc. operate on what the author already has instead of starting over.
     *
     * @param string $question
     * @return bool
     */
    public static function refers_to_existing_sql(string $question): bool {
        // A "fix this SQL error" prompt always refers to the current SQL.
        if (self::is_error_fix_prompt($question)) {
            return true;
        }
        // Demonstratives pointing at the SQL ("this query", "the above report", "existing select"...).
        $demonstrative = '/\b(this|that|the\s+above|above|existing|current)\s+'
            . '(sql|query|report|statement|view|select)\b/i';
        if (preg_match($demonstrative, $question)) {
            return true;
        }
        // Verbs that act on something already present rather than create from nothing.
        return (bool) preg_match(
            '/\b(modif(?:y|ies)|amend|change|adjust|tweak|extend|update|rewrite|refactor|'
                . 'add\s+(?:a\s+)?(?:column|field|to\s+this)|also|'
                . 'instead\s+of|rather\s+than)\b/i',
            $question
        );
    }

    /**
     * Derive a query name from the meaning of a SQL statement: the tables it reads from.
     *
     * @param string $sql
     * @return string A non-empty query name.
     */
    public static function from_sql(string $sql): string {
        $tables = self::extract_tables($sql);
        if (!$tables) {
            return get_string('ai:generatedname', 'report_sql');
        }
        $names = array_map(static fn(string $t): string => ucfirst($t), $tables);
        if (count($names) === 1) {
            $label = $names[0];
        } else if (count($names) === 2) {
            $label = $names[0] . ' & ' . $names[1];
        } else {
            $label = $names[0] . ', ' . $names[1] . ' +' . (count($names) - 2);
        }
        return \core_text::substr(get_string('ai:sqlname', 'report_sql', $label), 0, 60);
    }

    /**
     * Derive a query description from the meaning of a SQL statement: the columns it selects and
     * the tables it reads from.
     *
     * @param string $sql
     * @return string
     */
    public static function description_from_sql(string $sql): string {
        $tables = self::extract_tables($sql);
        if (!$tables) {
            return get_string('ai:generatedname', 'report_sql');
        }
        $tablelist = implode(', ', $tables);
        $cols = self::extract_select_columns($sql);
        if ($cols) {
            return get_string(
                'ai:sqldescription',
                'report_sql',
                (object) ['columns' => implode(', ', $cols), 'tables' => $tablelist]
            );
        }
        return get_string('ai:sqldescriptionnocols', 'report_sql', $tablelist);
    }

    /**
     * Extract distinct table names referenced in FROM/JOIN clauses, stripped of `{}` braces and the
     * Moodle table prefix.
     *
     * @param string $sql
     * @return string[]
     */
    private static function extract_tables(string $sql): array {
        global $CFG;
        $tables = [];
        if (preg_match_all('/\b(?:FROM|JOIN)\s+\{?([a-z][a-z0-9_]*)\}?/i', $sql, $m)) {
            $prefix = strtolower((string) $CFG->prefix);
            foreach ($m[1] as $raw) {
                $t = strtolower($raw);
                if ($prefix !== '' && strpos($t, $prefix) === 0) {
                    $t = substr($t, strlen($prefix));
                }
                if ($t !== '' && !in_array($t, $tables, true)) {
                    $tables[] = $t;
                }
            }
        }
        return $tables;
    }

    /**
     * Best-effort extraction of selected column names from a SELECT list, capped at six. Returns an
     * empty array for `SELECT *` or any list containing parentheses (functions/subqueries), where a
     * naive comma split would be unreliable.
     *
     * @param string $sql
     * @return string[]
     */
    private static function extract_select_columns(string $sql): array {
        if (!preg_match('/\bSELECT\b\s+(?:DISTINCT\s+)?(.*?)\s+\bFROM\b/is', $sql, $m)) {
            return [];
        }
        $list = trim($m[1]);
        if ($list === '' || strpos($list, '*') !== false || strpos($list, '(') !== false) {
            return [];
        }
        $cols = [];
        foreach (explode(',', $list) as $part) {
            $part = trim($part);
            // phpcs:ignore moodle.Strings.ForbiddenStrings.Found
            if (preg_match('/\bAS\s+["`]?([a-z0-9_ ]+)["`]?$/i', $part, $am)) {
                $cols[] = trim($am[1]);
            } else {
                $tokens = preg_split('/\s+/', $part) ?: [$part];
                $last = (string) end($tokens);
                $last = preg_replace('/^.*\./', '', $last);
                // phpcs:ignore moodle.Strings.ForbiddenStrings.Found
                $cols[] = trim((string) $last, '"`');
            }
            if (count($cols) >= 6) {
                break;
            }
        }
        return array_values(array_filter($cols, static fn(string $c): bool => $c !== ''));
    }
}
