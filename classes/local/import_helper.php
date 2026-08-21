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

use report_sql\local\sql\validator;
use report_sql\local\sql\view;

/**
 * Shared deterministic SQL-translation machinery for the legacy-report importers.
 *
 * The Configurable Reports ({@see cr_import}) and Ad-hoc Database Queries ({@see customsql_import})
 * importers both read a foreign report's SQL, apply a fixed set of dialect rewrites (double-quote
 * normalisation, MySQL date-function → portable token mapping, literal-`?` rebuilding), then
 * re-validate through the same {@see validator} and live dry-run the edit form uses. Everything in
 * this trait is source-agnostic; the only per-source step is {@see self::rewrite_tokens()}, which
 * each importer supplies and {@see self::convert()} calls via late static binding.
 *
 * No AI is involved: every transformation is a fixed rule. Anything the rules cannot map faithfully
 * is rejected rather than guessed.
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait import_helper {
    /**
     * Apply the deterministic translation passes to a decoded query.
     *
     * @param string $sql Decoded source SQL.
     * @return array{sql:string,notes:string[],fatal:?string} Translated SQL plus human notes, or a
     *         fatal rejection reason when a construct cannot be mapped faithfully.
     */
    public static function convert(string $sql): array {
        $notes = [];

        // 1. MySQL double-quoted string literals -> single-quoted (portable, and lets RS keep
        // case-sensitive output safe). Legacy reports are authored against MySQL where "x" is a string.
        $converted = self::rewrite_double_quotes($sql);
        if ($converted !== $sql) {
            $notes[] = get_string('crimport:notequotes', 'report_sql');
        }
        $sql = $converted;

        // 2. Source-specific placeholder tokens (supplied by the concrete importer).
        $tokenresult = static::rewrite_tokens($sql, $notes);
        if ($tokenresult['fatal'] !== null) {
            return ['sql' => $sql, 'notes' => $notes, 'fatal' => $tokenresult['fatal']];
        }
        $sql = $tokenresult['sql'];

        // 3. MySQL date functions -> portable %%TIMESTAMP%% / %%EPOCH%% / %%NOW%% tokens.
        $dateresult = self::rewrite_date_functions($sql, $notes);
        if ($dateresult['fatal'] !== null) {
            return ['sql' => $sql, 'notes' => $notes, 'fatal' => $dateresult['fatal']];
        }
        $sql = $dateresult['sql'];

        // 4. Literal `?` inside string literals -> CONCAT(..., chr(63), ...). RS treats a bare ? as a
        // bound parameter, so links such as view.php?id= must be rebuilt with chr(63).
        $qresult = self::rewrite_questionmarks($sql);
        if ($qresult !== $sql) {
            $notes[] = get_string('crimport:noteqmark', 'report_sql');
        }
        $sql = $qresult;

        return ['sql' => $sql, 'notes' => $notes, 'fatal' => null];
    }

    /**
     * Rewrite MySQL date functions to portable RS tokens, or reject ones with no clean mapping.
     *
     * Handles, in order of nesting:
     *  - DATE_FORMAT(FROM_UNIXTIME(<e>)[, '<fmt>'])  -> %%TIMESTAMP(<e>[, <neutral>])%%
     *  - FROM_UNIXTIME(<e>[, '<fmt>'])               -> %%TIMESTAMP(<e>[, <neutral>])%%
     *  - UNIX_TIMESTAMP()                            -> %%NOW%%
     *  - UNIX_TIMESTAMP(<e>)                         -> %%EPOCH(<e>)%%
     * Any remaining MySQL-only date function (DATEDIFF, DATE_ADD, DATE_SUB, STR_TO_DATE) is fatal.
     *
     * On a MySQL/MariaDB install the leftover native functions run as-is — the live dry-run executes
     * the SQL against the real database, so anything that genuinely works is kept (with a note that
     * the imported report is now tied to this DB family) rather than rejected. On other families
     * (PostgreSQL, etc.) there is no equivalent, so a leftover native function is fatal.
     *
     * @param string $sql
     * @param string[] $notes Collected notes (by reference).
     * @return array{sql:string,fatal:?string}
     */
    private static function rewrite_date_functions(string $sql, array &$notes): array {
        global $DB;
        $changed = false;

        // DATE_FORMAT / FROM_UNIXTIME -> %%TIMESTAMP%% is only safe for a top-level display column:
        // %%TIMESTAMP%% resolves to a bare epoch integer, whereas the native call returns a datetime
        // string. Substituting it as an argument to another function (e.g. DATEDIFF(NOW(),
        // FROM_UNIXTIME(x))) would feed that function an epoch int instead of a datetime and silently
        // change the result, so those nested calls are left native ($skipfunctionargs = true) and fall
        // through to the keep-on-MySQL / reject-elsewhere sweep below.

        // DATE_FORMAT(FROM_UNIXTIME(e), 'fmt') and DATE_FORMAT(FROM_UNIXTIME(e, ...), 'fmt').
        $sql = self::replace_calls($sql, 'DATE_FORMAT', function (array $args) use (&$changed) {
            if (count($args) !== 2) {
                return null; // Unsupported shape -> leave for the fatal sweep below.
            }
            $inner = trim($args[0]);
            $fu = self::match_single_call($inner, 'FROM_UNIXTIME');
            if ($fu === null) {
                return null;
            }
            $expr = trim($fu[0]); // First arg of FROM_UNIXTIME is the epoch expression.
            $neutral = self::format_to_neutral($args[1]);
            if ($neutral === null) {
                return null;
            }
            $changed = true;
            return '%%TIMESTAMP(' . $expr . ($neutral === '' ? '' : ', ' . $neutral) . ')%%';
        }, true);

        // FROM_UNIXTIME(e) and FROM_UNIXTIME(e, 'fmt').
        $sql = self::replace_calls($sql, 'FROM_UNIXTIME', function (array $args) use (&$changed) {
            if (count($args) < 1 || count($args) > 2) {
                return null;
            }
            $expr = trim($args[0]);
            $neutral = '';
            if (count($args) === 2) {
                $neutral = self::format_to_neutral($args[1]);
                if ($neutral === null) {
                    return null;
                }
            }
            $changed = true;
            return '%%TIMESTAMP(' . $expr . ($neutral === '' ? '' : ', ' . $neutral) . ')%%';
        }, true);

        // UNIX_TIMESTAMP() -> %%NOW%%, UNIX_TIMESTAMP(e) -> %%EPOCH(e)%%.
        $sql = self::replace_calls($sql, 'UNIX_TIMESTAMP', function (array $args) use (&$changed) {
            $changed = true;
            if (count($args) === 0 || (count($args) === 1 && trim($args[0]) === '')) {
                return '%%NOW%%';
            }
            if (count($args) === 1) {
                return '%%EPOCH(' . trim($args[0]) . ')%%';
            }
            return null;
        });

        if ($changed) {
            $notes[] = get_string('crimport:notedatefn', 'report_sql');
        }

        // Detect any MySQL-only date function we could not map to a portable token.
        $masked = self::mask_strings($sql);
        $remaining = [];
        foreach (
            ['DATE_FORMAT', 'FROM_UNIXTIME', 'UNIX_TIMESTAMP', 'DATEDIFF',
                  'DATE_ADD', 'DATE_SUB', 'STR_TO_DATE'] as $fn
        ) {
            if (preg_match('/\b' . $fn . '\s*\(/i', $masked)) {
                $remaining[] = $fn;
            }
        }

        if ($remaining) {
            // MySQL/MariaDB runs these natively; the live dry-run is the real gate. Keep them, but warn
            // the imported report is now tied to this DB family. Any other family cannot run them.
            if ($DB->get_dbfamily() === 'mysql') {
                $notes[] = get_string(
                    'crimport:notenativedate',
                    'report_sql',
                    implode(', ', array_unique($remaining))
                );
            } else {
                return ['sql' => $sql, 'fatal' =>
                    get_string('crimport:reasondatefn', 'report_sql', $remaining[0])];
            }
        }

        return ['sql' => $sql, 'fatal' => null];
    }

    /**
     * Translate a MySQL DATE_FORMAT/FROM_UNIXTIME format literal to RS's neutral format vocabulary.
     *
     * Returns '' for an empty/whitespace format (RS then applies its default), the neutral string on
     * success, or null when the format contains a specifier RS cannot express (so the caller rejects
     * rather than render the wrong date).
     *
     * @param string $arg The raw argument text, expected to be a quoted string literal.
     * @return string|null
     */
    private static function format_to_neutral(string $arg): ?string {
        $arg = trim($arg);
        // Must be a single-quoted (or double-quoted, pre-normalisation) string literal.
        if (!preg_match("/^'((?:[^']|'')*)'$/", $arg, $m) && !preg_match('/^"((?:[^"]|"")*)"$/', $arg, $m)) {
            return null;
        }
        $fmt = $m[1];
        if (trim($fmt) === '') {
            return '';
        }

        // MySQL specifier -> RS neutral token.
        $map = [
            '%Y' => 'yyyy', '%y' => 'yy', '%m' => 'mm', '%c' => 'mm', '%d' => 'dd', '%e' => 'dd',
            '%H' => 'hh', '%k' => 'hh', '%i' => 'mi', '%s' => 'ss', '%S' => 'ss',
            '%M' => 'month', '%b' => 'mon', '%a' => 'ddd', '%W' => 'dddd', '%%' => '%',
        ];
        $out = '';
        $len = strlen($fmt);
        for ($i = 0; $i < $len; $i++) {
            if ($fmt[$i] === '%' && $i + 1 < $len) {
                $spec = substr($fmt, $i, 2);
                if (!isset($map[$spec])) {
                    return null; // Unsupported specifier -> reject.
                }
                $out .= $map[$spec];
                $i++;
                continue;
            }
            if ($fmt[$i] === '%') {
                return null; // Trailing lone % -> reject.
            }
            $out .= $fmt[$i];
        }
        return $out;
    }

    /**
     * Replace every top-level call to a named function, passing its parsed arguments to a callback.
     *
     * Respects string literals and nested parentheses. The callback receives the argument list (raw
     * text, split on top-level commas) and returns the replacement text, or null to leave the call
     * untouched.
     *
     * @param string $sql
     * @param string $name Function name (case-insensitive).
     * @param callable $callback Receives the parsed argument list, returns replacement or null.
     * @param bool $skipfunctionargs When true, a call that is itself an argument to another function
     *        call is left untouched (the rewrite would change the value's type inside that function).
     * @return string
     */
    private static function replace_calls(
        string $sql,
        string $name,
        callable $callback,
        bool $skipfunctionargs = false
    ): string {
        $out = '';
        $offset = 0;
        $len = strlen($sql);

        while ($offset < $len) {
            // Find the next case-insensitive function name preceded by a word boundary and followed
            // by an opening paren (allowing whitespace).
            if (!preg_match('/\b' . preg_quote($name, '/') . '\s*\(/i', $sql, $m, PREG_OFFSET_CAPTURE, $offset)) {
                $out .= substr($sql, $offset);
                break;
            }
            $matchstart = $m[0][1];
            // Skip a match that sits inside a string literal, or (when requested) one that is an
            // argument to another function call.
            if (
                self::in_string($sql, $matchstart)
                    || ($skipfunctionargs && self::is_function_argument($sql, $matchstart))
            ) {
                $out .= substr($sql, $offset, $matchstart + 1 - $offset);
                $offset = $matchstart + 1;
                continue;
            }
            $parenpos = $matchstart + strlen($m[0][0]) - 1;
            $end = self::matching_paren($sql, $parenpos);
            if ($end === null) {
                $out .= substr($sql, $offset);
                break;
            }
            $argstr = substr($sql, $parenpos + 1, $end - $parenpos - 1);
            $args = self::split_args($argstr);
            $replacement = $callback($args);

            $out .= substr($sql, $offset, $matchstart - $offset);
            if ($replacement === null) {
                $out .= substr($sql, $matchstart, $end + 1 - $matchstart);
            } else {
                $out .= $replacement;
            }
            $offset = $end + 1;
        }

        return $out;
    }

    /**
     * Whether the token starting at $pos sits inside the argument list of another function call.
     *
     * Walks the SQL up to $pos tracking a stack of open parentheses, tagging each as a function-call
     * paren (immediately preceded by an identifier character) or a grouping paren. The token is a
     * function argument when the innermost still-open paren is a function-call paren. String literals
     * are skipped so parentheses inside them do not count.
     *
     * @param string $sql
     * @param int $pos Index of the token's first character.
     * @return bool
     */
    private static function is_function_argument(string $sql, int $pos): bool {
        $stack = [];      // Each entry: true = function-call paren, false = grouping paren.
        $prev = '';       // Last significant (non-space, non-string) character seen.
        $instr = false;
        $quote = '';
        for ($i = 0; $i < $pos; $i++) {
            $ch = $sql[$i];
            if ($instr) {
                if ($ch === $quote) {
                    if ($i + 1 < strlen($sql) && $sql[$i + 1] === $quote) {
                        $i++;
                        continue;
                    }
                    $instr = false;
                }
                continue;
            }
            if ($ch === "'" || $ch === '"') {
                $instr = true;
                $quote = $ch;
                $prev = $ch;
                continue;
            }
            if ($ch === '(') {
                $stack[] = (bool) preg_match('/[A-Za-z0-9_]/', $prev);
            } else if ($ch === ')') {
                array_pop($stack);
            }
            if (!ctype_space($ch)) {
                $prev = $ch;
            }
        }
        return !empty($stack) && end($stack) === true;
    }

    /**
     * If $expr is exactly a single call to $name, return its argument list; otherwise null.
     *
     * @param string $expr
     * @param string $name
     * @return array<int,string>|null
     */
    private static function match_single_call(string $expr, string $name): ?array {
        $expr = trim($expr);
        if (!preg_match('/^' . preg_quote($name, '/') . '\s*\(/i', $expr, $m)) {
            return null;
        }
        $parenpos = strlen($m[0]) - 1;
        $end = self::matching_paren($expr, $parenpos);
        if ($end === null || $end !== strlen($expr) - 1) {
            return null;
        }
        return self::split_args(substr($expr, $parenpos + 1, $end - $parenpos - 1));
    }

    /**
     * Index of the parenthesis matching the open paren at $open, respecting string literals.
     *
     * @param string $sql
     * @param int $open Index of the opening parenthesis.
     * @return int|null Index of the matching close paren, or null if unbalanced.
     */
    private static function matching_paren(string $sql, int $open): ?int {
        $depth = 0;
        $len = strlen($sql);
        $instr = false;
        $quote = '';
        for ($i = $open; $i < $len; $i++) {
            $ch = $sql[$i];
            if ($instr) {
                if ($ch === $quote) {
                    // Doubled quote is an escaped quote, not a terminator.
                    if ($i + 1 < $len && $sql[$i + 1] === $quote) {
                        $i++;
                        continue;
                    }
                    $instr = false;
                }
                continue;
            }
            if ($ch === "'" || $ch === '"') {
                $instr = true;
                $quote = $ch;
                continue;
            }
            if ($ch === '(') {
                $depth++;
            } else if ($ch === ')') {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }
        }
        return null;
    }

    /**
     * Split a function argument string on top-level commas, respecting parens and strings.
     *
     * @param string $argstr
     * @return array<int,string>
     */
    private static function split_args(string $argstr): array {
        if (trim($argstr) === '') {
            return [];
        }
        $args = [];
        $depth = 0;
        $instr = false;
        $quote = '';
        $current = '';
        $len = strlen($argstr);
        for ($i = 0; $i < $len; $i++) {
            $ch = $argstr[$i];
            if ($instr) {
                $current .= $ch;
                if ($ch === $quote) {
                    if ($i + 1 < $len && $argstr[$i + 1] === $quote) {
                        $current .= $argstr[++$i];
                        continue;
                    }
                    $instr = false;
                }
                continue;
            }
            if ($ch === "'" || $ch === '"') {
                $instr = true;
                $quote = $ch;
                $current .= $ch;
                continue;
            }
            if ($ch === '(') {
                $depth++;
            } else if ($ch === ')') {
                $depth--;
            }
            if ($ch === ',' && $depth === 0) {
                $args[] = $current;
                $current = '';
                continue;
            }
            $current .= $ch;
        }
        $args[] = $current;
        return $args;
    }

    /**
     * Whether the character at $pos lies inside a string literal.
     *
     * @param string $sql
     * @param int $pos
     * @return bool
     */
    private static function in_string(string $sql, int $pos): bool {
        $instr = false;
        $quote = '';
        for ($i = 0; $i < $pos; $i++) {
            $ch = $sql[$i];
            if ($instr) {
                if ($ch === $quote) {
                    if ($i + 1 < strlen($sql) && $sql[$i + 1] === $quote) {
                        $i++;
                        continue;
                    }
                    $instr = false;
                }
                continue;
            }
            if ($ch === "'" || $ch === '"') {
                $instr = true;
                $quote = $ch;
            }
        }
        return $instr;
    }

    /**
     * Blank the contents of string literals (keeping the quotes) so a bare regex can scan only code.
     *
     * @param string $sql
     * @return string
     */
    private static function mask_strings(string $sql): string {
        $sql = preg_replace("/'(?:[^']|'')*'/", "''", $sql) ?? $sql;
        $sql = preg_replace('/"(?:[^"]|"")*"/', '""', $sql) ?? $sql;
        return $sql;
    }

    /**
     * Convert MySQL double-quoted string literals to single-quoted ones.
     *
     * Single-quoted literals and the rest of the SQL are passed through untouched. Any single quote
     * inside a converted literal is doubled so it stays escaped.
     *
     * @param string $sql
     * @return string
     */
    private static function rewrite_double_quotes(string $sql): string {
        $pattern = "/(?P<sq>'(?:[^']|'')*')|(?P<dq>\"(?:[^\"]|\"\")*\")/";
        return preg_replace_callback($pattern, static function (array $m): string {
            if (($m['sq'] ?? '') !== '') {
                return $m['sq'];
            }
            $inner = substr($m['dq'], 1, -1);
            $inner = str_replace('""', '"', $inner);      // Un-escape doubled double-quotes.
            $inner = str_replace("'", "''", $inner);       // Escape single quotes for the new literal.
            return "'" . $inner . "'";
        }, $sql) ?? $sql;
    }

    /**
     * Rewrite any single-quoted string literal containing `?` into a CONCAT(... chr(63) ...) chain.
     *
     * @param string $sql
     * @return string
     */
    private static function rewrite_questionmarks(string $sql): string {
        $pattern = "/'(?:[^']|'')*'/";
        return preg_replace_callback($pattern, static function (array $m): string {
            $literal = $m[0];
            if (strpos($literal, '?') === false) {
                return $literal;
            }
            $inner = substr($literal, 1, -1);
            $parts = explode('?', $inner);
            $pieces = [];
            foreach ($parts as $part) {
                $pieces[] = "'" . $part . "'";
            }
            return 'CONCAT(' . implode(', chr(63), ', $pieces) . ')';
        }, $sql) ?? $sql;
    }

    /**
     * Map a legacy report courseid to an RS course scope.
     *
     * Both legacy plugins use courseid 1 (the site course) for site-wide reports; RS uses 0. A real
     * course id is kept only if that course still exists, else demoted to site-wide.
     *
     * @param int $sourcecourseid
     * @return int
     */
    private static function map_courseid(int $sourcecourseid): int {
        global $DB;
        if ($sourcecourseid <= 1) {
            return 0;
        }
        return $DB->record_exists('course', ['id' => $sourcecourseid]) ? $sourcecourseid : 0;
    }

    /**
     * Reduce a legacy HTML summary/description to a plain-text description.
     *
     * @param string $summary
     * @return string
     */
    private static function clean_summary(string $summary): string {
        return trim(html_to_text($summary, 0, false));
    }

    /**
     * Run the live dry-run checks against already-validated SQL.
     *
     * Mirrors {@see \report_sql\external\validate_sql::execute()}: a single-row fetch to
     * exercise tables/columns and select-list expressions, then a CREATE/DROP VIEW to catch
     * duplicate output column names. Returns a cleaned error string, or null when the SQL runs.
     *
     * @param string $validated SQL already passed through {@see validator::validate()}.
     * @return string|null
     */
    private static function dry_run(string $validated): ?string {
        global $DB, $CFG;

        $resolved = view::resolve_placeholders($validated);

        try {
            $DB->get_records_sql("SELECT * FROM ({$resolved}) rs_dryrun LIMIT 1", []);
        } catch (\dml_exception $e) {
            $detail = $e->error ?: ($e->debuginfo ?: $e->getMessage());
            // SELECT * across joined tables yields duplicate output column names (e.g. several `id`),
            // which a derived table / VIEW rejects. Give the actionable hint, not the raw DB error.
            if (stripos($detail, 'Duplicate column name') !== false) {
                return get_string('errduplicatecolumn', 'report_sql');
            }
            return validator::clean_error($detail);
        }

        $testview = $CFG->prefix . \report_sql\local\sql\privilege_check::PROBE_NAME . '_col';
        try {
            $DB->change_database_structure("CREATE OR REPLACE VIEW {$testview} AS {$resolved}");
            $DB->change_database_structure("DROP VIEW IF EXISTS {$testview}");
        } catch (\moodle_exception $e) {
            $detail = $e->debuginfo ?: $e->getMessage();
            if (stripos($detail, 'Duplicate column name') !== false) {
                return get_string('errduplicatecolumn', 'report_sql');
            }
            return validator::clean_error($detail);
        }

        return null;
    }
}
