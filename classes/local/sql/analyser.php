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

namespace report_sql\local\sql;

/**
 * Gives an author feedback on a report source: date-like columns that should use
 * the %%TIMESTAMP()%% token, the result row count, and per-table index / full-scan
 * information so an excessively slow report can be spotted before publishing.
 *
 * Everything here goes through the Moodle DB API ($DB): get_columns()/view::columns(),
 * count_records_sql(), get_indexes() and get_records_sql("EXPLAIN ...").
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class analyser {
    /** @var int Result size above which the report is warned as potentially slow. */
    private const LARGE_RESULT = 50000;

    /** @var int Estimated per-table scan rows above which a full scan is worth flagging. */
    private const SCAN_ROWS = 1000;

    /**
     * @var string Column-name fragments that hint at a stored Unix timestamp. Kept deliberately
     * broad — the sampled-value epoch check ({@see self::EPOCH_FLOOR}) rebuts non-date ints such as
     * counts, ids and durations (e.g. enrolperiod = 2592000 falls below the epoch floor), so a loose
     * name match rarely yields a false suggestion when the query returns rows.
     */
    private const DATE_NAME_PATTERN = '/time|date|created|modified|start|end|expir|due|login|logout'
        . '|access|seen|stamp|cron|sync|sent|finish|run/i';

    /** @var int Earliest epoch a sampled integer must reach to look like a real date (2000-01-01). */
    private const EPOCH_FLOOR = 946684800;

    /** @var int Per-session statement timeout (ms) applied around the advisory DB probes. */
    private const PROBE_TIMEOUT_MS = 5000;

    /**
     * Analyse a report source and return structured feedback.
     *
     * @param string $sql Raw author SQL.
     * @param int $courseid Bound course id (0 = site-wide) for placeholder resolution.
     * @param string|null $viewname Name (unprefixed) of a live view the caller has already built and
     *  executed for this exact SQL — the inline preview passes its throwaway view here. When given,
     *  the dry-run gate and the private probe view are skipped: the caller has already proven the SQL
     *  runs, and date-column introspection reuses the supplied view instead of building a second one.
     * @return array{ok: bool, error: string, rowcount: int, datecolumns: string[],
     *     casecolumns: array<array{col: string, mode: string}>, suggestions: string[],
     *     warnings: string[], indexinfo: string[]}
     */
    public static function analyse(string $sql, int $courseid = 0, ?string $viewname = null): array {
        $result = [
            'ok'          => true,
            'error'       => '',
            'rowcount'    => 0,
            'datecolumns' => [],
            'casecolumns' => [],
            'suggestions' => [],
            'warnings'    => [],
            'indexinfo'   => [],
        ];

        // Reuse the static validator so we analyse the same auto-braced SQL that publish would.
        try {
            $validated = validator::validate($sql);
        } catch (\moodle_exception $e) {
            $result['ok'] = false;
            $result['error'] = $e->getMessage();
            return $result;
        }

        $resolved = view::resolve_placeholders($validated, $courseid);

        // Cap the (potentially expensive) probe queries with a per-session statement timeout so a
        // pathological report cannot stall a database connection. Each probe already treats a DB
        // error as "skip", so a fired timeout degrades to advisory-not-available, never a hang.
        self::set_statement_timeout(self::PROBE_TIMEOUT_MS);
        try {
            // Syntax / missing table / missing column / row-dependent select-list errors: run the
            // query for a single row and surface the DB message. Unlike the advisory probes below
            // (which degrade silently on error), a query that will not execute is a hard failure the
            // author needs to see, so report it and stop. Mirrors the publish-time live check in
            // {@see \report_sql\external\validate_sql}. Skipped when the caller supplied a
            // view: building + rendering that view already proved the SQL executes.
            if ($viewname === null) {
                $error = self::dry_run($resolved);
                if ($error !== null) {
                    $result['ok'] = false;
                    $result['error'] = $error;
                    return $result;
                }
            }

            $result['rowcount'] = self::row_count($resolved, $result['warnings']);
            $result['datecolumns'] = self::date_columns($validated, $resolved, $viewname);
            $result['indexinfo'] = self::index_report($validated, $resolved, $result['warnings']);
        } finally {
            // The DB connection may be reused (or persistent) for the rest of the request, so always
            // restore the server default — never leak the cap onto later, unrelated queries.
            self::set_statement_timeout(0);
        }
        self::performance_hints($validated, $result['rowcount'], $result['warnings']);
        // Purely textual, no DB — safe outside the probe-timeout block.
        $result['casecolumns'] = self::case_columns($validated);

        return $result;
    }

    /**
     * Apply (or, with $ms = 0, clear) a per-session SQL statement timeout. Best-effort and
     * dialect-branched: MySQL caps SELECTs via max_execution_time (ms); MariaDB via
     * max_statement_time (seconds); Postgres via statement_timeout (ms, all statements). Other
     * families have no portable knob and run uncapped. A SET the server rejects is swallowed.
     *
     * @param int $ms Timeout in milliseconds; 0 restores the server default.
     */
    private static function set_statement_timeout(int $ms): void {
        global $DB;

        $family = $DB->get_dbfamily();
        try {
            if ($family === 'postgres') {
                $DB->execute($ms > 0 ? 'SET statement_timeout = ' . (int) $ms : 'RESET statement_timeout');
            } else if ($family === 'mysql') {
                if (self::is_mariadb()) {
                    // MariaDB: seconds (fractional allowed), SELECT statements only.
                    $DB->execute($ms > 0
                        ? 'SET SESSION max_statement_time = ' . sprintf('%.3F', max(0.001, $ms / 1000))
                        : 'SET SESSION max_statement_time = DEFAULT');
                } else {
                    // MySQL 5.7+: milliseconds, SELECT statements only.
                    $DB->execute($ms > 0
                        ? 'SET SESSION max_execution_time = ' . (int) $ms
                        : 'SET SESSION max_execution_time = DEFAULT');
                }
            }
            // Other families (mssql, oracle, sqlite): no portable per-session timeout — skip.
        } catch (\dml_exception $e) {
            // Setting the cap is advisory; if the server rejects it, the probes just run uncapped.
            return;
        }
    }

    /**
     * Whether the connected MySQL-family server is MariaDB (its statement-timeout knob and units
     * differ from Oracle MySQL's).
     *
     * @return bool
     */
    private static function is_mariadb(): bool {
        global $DB;
        $info = $DB->get_server_info();
        $desc = strtolower(($info['description'] ?? '') . ' ' . ($info['version'] ?? ''));
        return strpos($desc, 'maria') !== false;
    }

    /**
     * Execute the resolved SQL for a single row so syntax errors, missing tables/columns and
     * row-dependent select-list errors surface as a cleaned message the author can act on.
     *
     * @param string $resolved Placeholder-resolved SQL.
     * @return string|null Cleaned DB error, or null when the query runs.
     */
    private static function dry_run(string $resolved): ?string {
        global $DB;
        // Wrap as a subquery so a trailing line comment in the author SQL cannot swallow the LIMIT
        // (`... -- note` would otherwise comment out an appended LIMIT and fetch the whole table).
        // LIMIT 1 (not 0) materialises a row, so select-list expressions are actually evaluated —
        // catching row-dependent errors like to_char() on a bigint that LIMIT 0 would let through.
        try {
            $DB->get_records_sql("SELECT * FROM ({$resolved}) rs_dryrun LIMIT 1", []);
        } catch (\dml_exception $e) {
            $detail = $e->error ?: ($e->debuginfo ?: $e->getMessage());
            return validator::clean_error($detail);
        }
        return null;
    }

    /**
     * Count the rows the report would return, warning when the result is large. Returns -1 when the
     * count cannot be taken (the {@see self::dry_run()} probe has already confirmed the SQL runs, so
     * -1 here means only that COUNT(*) over the wrapped query was itself uncountable).
     *
     * @param string $resolved Placeholder-resolved SQL.
     * @param string[] $warnings Warnings collector (by reference).
     * @return int
     */
    private static function row_count(string $resolved, array &$warnings): int {
        global $DB;
        try {
            $count = $DB->count_records_sql("SELECT COUNT(*) FROM ({$resolved}) rs_count");
        } catch (\dml_exception $e) {
            return -1;
        }
        if ($count >= self::LARGE_RESULT) {
            $warnings[] = get_string('checklargeresult', 'report_sql', $count);
        }
        return $count;
    }

    /**
     * Find integer output columns that look like stored Unix timestamps. Their source
     * expression should be wrapped in %%TIMESTAMP()%% (which types + formats + keeps them
     * sortable); the Test-query UI turns each name into a click-to-wrap control.
     *
     * @param string $validated Auto-braced validated SQL.
     * @param string $resolved Placeholder-resolved SQL.
     * @param string|null $viewname Unprefixed name of a live view already built for this SQL. When
     *  given, its columns are introspected directly instead of building a throwaway probe view.
     * @return string[] Date-like output column names (empty when none).
     */
    private static function date_columns(string $validated, string $resolved, ?string $viewname = null): array {
        global $DB, $CFG;

        $datecols = []; // Output column names that look like stored dates.
        $already = view::timestamp_columns($validated); // Keyed by lowercased column name.

        if ($viewname !== null) {
            // Reuse the caller's live view (e.g. the inline preview) rather than standing up a
            // second throwaway probe view for the same SQL.
            try {
                $columns = view::columns($viewname);
            } catch (\moodle_exception $e) {
                return $datecols;
            }
        } else {
            $probe = privilege_check::PROBE_NAME . '_chk';
            $fullprobe = $CFG->prefix . $probe;
            $columns = [];
            try {
                $DB->change_database_structure("CREATE OR REPLACE VIEW {$fullprobe} AS {$resolved}");
                $columns = view::columns($probe);
                $DB->change_database_structure("DROP VIEW IF EXISTS {$fullprobe}");
            } catch (\moodle_exception $e) {
                // If the probe view cannot be built, skip date suggestions silently — validate_sql
                // is the endpoint that reports SQL faults; analyse() is advisory only.
                $DB->change_database_structure("DROP VIEW IF EXISTS {$fullprobe}");
                return $datecols;
            }
        }

        // Sample one row so a name match can be corroborated by a plausible-epoch value.
        $sample = null;
        try {
            $rows = $DB->get_records_sql("SELECT * FROM ({$resolved}) rs_sample LIMIT 1", []);
            $sample = $rows ? (array) reset($rows) : [];
        } catch (\dml_exception $e) {
            $sample = [];
        }

        $ceiling = time() + (10 * YEARSECS); // Allow dates up to ~10 years out (e.g. course end).
        foreach ($columns as $name => $info) {
            $lname = strtolower((string) $name);
            if (isset($already[$lname])) {
                continue; // Already a %%TIMESTAMP()%% column.
            }
            if (!in_array((string) ($info->meta_type ?? ''), ['I', 'R'], true)) {
                continue; // Only integer columns can hold an epoch.
            }
            if (!preg_match(self::DATE_NAME_PATTERN, $lname)) {
                continue;
            }
            // Corroborate with the sampled value when we have one for this column. 0/null is a
            // common "never" sentinel (e.g. lastlogin on a user who never logged in), so treat it
            // as neutral — only a non-zero value clearly outside the epoch window rebuts the name.
            $plausible = true;
            if (is_array($sample) && array_key_exists($lname, array_change_key_case($sample, CASE_LOWER))) {
                $val = array_change_key_case($sample, CASE_LOWER)[$lname];
                if ($val !== null && is_numeric($val) && (int) $val !== 0) {
                    $plausible = ($val >= self::EPOCH_FLOOR && $val <= $ceiling);
                }
            }
            if ($plausible) {
                $datecols[] = (string) $name;
            }
        }
        return $datecols;
    }

    /**
     * Surface index information only where it is actionable, and flag full table scans via EXPLAIN
     * (dialect-branched). The blanket per-table index dump is deliberately gone — the only index
     * line produced is a suggestion to sort by an indexed column when the query's ORDER BY targets
     * an unindexed one (an unindexed sort makes the database order the whole result).
     *
     * @param string $validated Auto-braced validated SQL (table names are {braced}).
     * @param string $resolved Placeholder-resolved SQL.
     * @param string[] $warnings Collected warnings (appended in place).
     * @return string[] Actionable index lines (empty unless the sort hint applies).
     */
    private static function index_report(string $validated, string $resolved, array &$warnings): array {
        $lines = [];

        // The {tablename} placeholders survive validation, so referenced tables are easy to recover.
        preg_match_all('/\{(\w+)\}/', $validated, $m);
        $tables = array_unique($m[1]);

        $ordercols = self::order_by_columns($validated);
        if ($ordercols) {
            $indexed = self::indexed_leading_columns($tables);
            // Only advise when the sort is on something we know is unindexed *and* there is an
            // indexed column to point at — otherwise there is no useful index to show.
            if ($indexed && !array_intersect($ordercols, $indexed)) {
                $lines[] = get_string('checksortindex', 'report_sql', (object) [
                    'sortcol' => implode(', ', $ordercols),
                    'indexed' => implode(', ', $indexed),
                ]);
            }
        }

        self::explain_scans($resolved, $warnings);
        return $lines;
    }

    /**
     * Lowercased column identifiers in the top-level ORDER BY, alias prefixes and sort direction
     * stripped. Ordinal positions (ORDER BY 1) and expression terms (containing parens) are ignored
     * — only bare column references can be matched against an index.
     *
     * @param string $validated Auto-braced validated SQL.
     * @return string[] Distinct ordered column names, lowercased (empty when there is no ORDER BY).
     */
    private static function order_by_columns(string $validated): array {
        if (!preg_match('/\bORDER\s+BY\b(.*)$/is', $validated, $m)) {
            return [];
        }
        // Trim anything after the ORDER BY list (LIMIT/OFFSET); ORDER BY is the last clause otherwise.
        $tail = preg_split('/\b(?:LIMIT|OFFSET|FETCH)\b/i', $m[1])[0];
        $cols = [];
        foreach (explode(',', $tail) as $term) {
            $term = trim($term);
            if ($term === '' || strpos($term, '(') !== false) {
                continue; // Skip expressions — an index cannot be matched to them here.
            }
            // Take the identifier, drop a trailing ASC/DESC and any NULLS FIRST/LAST.
            $token = preg_split('/\s+/', $term)[0];
            $token = preg_replace('/^\{?\w+\}?\./', '', $token); // Strip {table}. or alias. prefix.
            $token = strtolower(trim($token, '`"[]{}'));
            if ($token !== '' && !ctype_digit($token)) {
                $cols[$token] = true;
            }
        }
        return array_keys($cols);
    }

    /**
     * Distinct lowercased *leading* columns of every index on the referenced base tables, including
     * the primary key (which get_indexes() omits). Only the leading column of an index can satisfy
     * an ORDER BY / anchor a lookup, so that is what is offered as the indexed alternative.
     *
     * @param string[] $tables Base table names (unbraced).
     * @return string[] Lowercased indexable column names.
     */
    private static function indexed_leading_columns(array $tables): array {
        global $DB;

        $cols = [];
        foreach ($tables as $table) {
            try {
                $indexes = $DB->get_indexes($table);
                $columns = $DB->get_columns($table);
            } catch (\dml_exception $e) {
                continue; // Not a real table (e.g. a CTE name caught by the regex) — skip.
            }
            foreach ($indexes as $index) {
                $lead = $index['columns'][0] ?? null;
                if ($lead !== null) {
                    $cols[strtolower((string) $lead)] = true;
                }
            }
            // The get_indexes() call excludes the primary key, but the PK is indexed too (e.g. "id").
            foreach ($columns as $col) {
                if (!empty($col->primary_key)) {
                    $cols[strtolower((string) $col->name)] = true;
                }
            }
        }
        return array_keys($cols);
    }

    /**
     * Run EXPLAIN and warn about full table scans. MySQL/MariaDB read the structured
     * plan rows; Postgres greps the textual plan for "Seq Scan"; other families skip.
     *
     * @param string $resolved Placeholder-resolved SQL.
     * @param string[] $warnings Collected warnings (appended in place).
     */
    private static function explain_scans(string $resolved, array &$warnings): void {
        global $DB;

        $family = $DB->get_dbfamily();
        $scans = []; // Moodle table name => estimated scanned rows, deduped.
        try {
            if ($family === 'mysql') {
                $plan = $DB->get_records_sql("EXPLAIN {$resolved}", []);
                foreach ($plan as $row) {
                    $row = (array) $row;
                    $type = strtoupper((string) ($row['type'] ?? ''));
                    $key = $row['key'] ?? null;
                    $estrows = (int) ($row['rows'] ?? 0);
                    if ($type === 'ALL' && ($key === null || $key === '') && $estrows >= self::SCAN_ROWS) {
                        $scans[self::unprefix((string) ($row['table'] ?? '?'))] = $estrows;
                    }
                }
            } else if ($family === 'postgres') {
                $plan = $DB->get_records_sql("EXPLAIN {$resolved}", []);
                foreach ($plan as $row) {
                    $row = (array) $row;
                    $line = (string) reset($row);
                    // Only warn on a large seq scan — small tables always seq-scan, and an index
                    // would not help. The rows= estimate on the plan node gives the scale.
                    if (preg_match('/Seq Scan on (\w+)/i', $line, $sm)) {
                        $estrows = preg_match('/\brows=(\d+)/', $line, $rm) ? (int) $rm[1] : 0;
                        if ($estrows >= self::SCAN_ROWS) {
                            $scans[self::unprefix($sm[1])] = $estrows;
                        }
                    }
                }
            }
            // Other families: no portable EXPLAIN — the sort hint (if any) still stands.
        } catch (\dml_exception $e) {
            // EXPLAIN is best-effort; a failure here does not invalidate the rest of the report.
            return;
        }

        foreach ($scans as $table => $estrows) {
            $indexed = self::indexed_leading_columns([$table]);
            $warnings[] = get_string('checkfullscan', 'report_sql', (object) [
                'table'   => $table,
                'rows'    => $estrows,
                'indexed' => $indexed ? implode(', ', $indexed) : '-',
            ]);
        }
    }

    /**
     * Static-SQL performance heuristics that EXPLAIN does not surface directly: query shapes that
     * scale badly with the result size or that quietly defeat an index. Purely textual — advisory
     * only, so each pattern is deliberately conservative to keep false positives low.
     *
     * @param string $validated Auto-braced validated SQL (string literals still present).
     * @param int $rowcount Result row count from {@see self::row_count()}, or -1 if unknown.
     * @param string[] $warnings Collected warnings (appended in place).
     */
    private static function performance_hints(string $validated, int $rowcount, array &$warnings): void {
        // String literals blanked for the structural scans; the raw SQL is kept for the LIKE
        // check, which needs to see the pattern literal itself.
        $nostr = self::blank_literals($validated);

        // A LIKE pattern that opens with % or _ cannot use a B-tree index — full scan.
        if (preg_match("/\\bLIKE\\s+N?'\\s*[%_]/i", $validated)) {
            $warnings[] = get_string('checkleadingwildcard', 'report_sql');
        }

        // DISTINCT over a large result sorts + de-duplicates the whole set.
        if ($rowcount >= self::LARGE_RESULT && preg_match('/\bSELECT\s+DISTINCT\b/i', $nostr)) {
            $warnings[] = get_string('checkdistinctlarge', 'report_sql', $rowcount);
        }

        // A subquery in the select list runs once per output row.
        if (self::has_select_list_subquery($nostr)) {
            $warnings[] = get_string('checkselectsubquery', 'report_sql');
        }

        // A function wrapping a column on the filtered side of WHERE is non-sargable.
        if (self::has_nonsargable_where($nostr)) {
            $warnings[] = get_string('checknonsargable', 'report_sql');
        }
    }

    /**
     * Whether a subquery appears in the top-level SELECT list (between the outer SELECT and its
     * matching FROM at paren depth 0). Such a subquery is evaluated per returned row.
     *
     * WITH (CTE) queries are skipped: their leading `AS (SELECT ...)` opens a paren before the outer
     * select list, which this simple depth scan cannot disambiguate without a full parse.
     *
     * @param string $nostr SQL with string literals blanked.
     * @return bool
     */
    private static function has_select_list_subquery(string $nostr): bool {
        if (preg_match('/^\s*WITH\b/i', $nostr)) {
            return false;
        }
        $pos = stripos($nostr, 'select');
        if ($pos === false) {
            return false;
        }
        $rest = substr($nostr, $pos + 6);
        $len = strlen($rest);
        for ($i = 0; $i < $len; $i++) {
            $ch = $rest[$i];
            if ($ch === '(') {
                if (preg_match('/^\(\s*select\b/i', substr($rest, $i, 12))) {
                    return true; // Matches a (SELECT ... in the select list.
                }
            } else if ($ch === ')') {
                // Unbalanced ) here means we left the select list into an outer scope — stop.
                return false;
            } else if (
                ($i === 0 || (!ctype_alnum($rest[$i - 1]) && $rest[$i - 1] !== '_'))
                && preg_match('/^from\b/i', substr($rest, $i))
            ) {
                return false; // Reached the top-level FROM with no select-list subquery.
            }
        }
        return false;
    }

    /**
     * Whether the WHERE clause wraps a column in a function on the filtered side, which prevents
     * the database using an index on that column (non-sargable). Only the first argument being a
     * bare identifier / {table} reference (not a literal) is treated as a wrapped column.
     *
     * @param string $nostr SQL with string literals blanked.
     * @return bool
     */
    private static function has_nonsargable_where(string $nostr): bool {
        $where = self::where_region($nostr);
        if ($where === '') {
            return false;
        }
        return (bool) preg_match(
            '/\b(?:LOWER|UPPER|DATE|YEAR|MONTH|DAY|TO_CHAR|DATE_FORMAT|DATE_TRUNC|'
            . 'SUBSTR|SUBSTRING|CAST|CONVERT)\s*\(\s*[A-Za-z_{]/i',
            $where
        );
    }

    /**
     * Extract the WHERE clause text up to the next major clause keyword. Approximate (a subquery's
     * own WHERE is included) but adequate for an advisory scan.
     *
     * @param string $nostr SQL with string literals blanked.
     * @return string WHERE-clause text, or '' when there is no WHERE.
     */
    private static function where_region(string $nostr): string {
        if (!preg_match('/\bWHERE\b/i', $nostr, $m, PREG_OFFSET_CAPTURE)) {
            return '';
        }
        $tail = substr($nostr, $m[0][1] + strlen($m[0][0]));
        return preg_split('/\b(?:GROUP|ORDER|HAVING|LIMIT|WINDOW)\b/i', $tail)[0];
    }

    /** @var string SQL-side case functions the %%CASE()%% token supersedes (UCASE/LCASE are MySQL-only). */
    private const CASE_FUNCTIONS = 'UPPER|LOWER|UCASE|LCASE';

    /**
     * Find output columns whose select-list expression is a raw SQL case call — UPPER()/LOWER(), or
     * the MySQL-only UCASE()/LCASE() — wrapping a value. Such a call bakes the transform into the
     * *stored* column, so Report Builder sorts/filters on the upper/lowercased text (and UCASE/LCASE
     * are not portable). The %%CASE(expr, upper|lower)%% token applies the same case at display time
     * while keeping the column sorting/filtering on the original value, so the Test-query UI offers
     * each of these as a click-to-wrap control ({@see \report_sql\external\test_query}).
     *
     * Only a call spanning the whole select-list item is matched (optional leading DISTINCT), and
     * only where the item has a usable output name (an `AS` alias, else a trailing identifier — a
     * bare `UPPER(col)` ends in ")", so an unaliased one is skipped, matching the unreferenceable
     * view column it would produce). Mirrors the naming rule in {@see view::case_columns()}.
     *
     * @param string $validated Auto-braced validated SQL.
     * @return array<array{col: string, mode: string}> One {col, mode} per raw case column (empty when none).
     */
    private static function case_columns(string $validated): array {
        $mask = self::mask_sql($validated);
        $region = self::select_list_region($mask);
        if ($region === null) {
            return [];
        }
        [$start, $end] = $region;
        $out = [];
        foreach (self::split_items($mask, $start, $end) as [$from, $to]) {
            $orig = substr($validated, $from, $to - $from);
            $maskitem = substr($mask, $from, $to - $from);

            // Trailing "AS alias" located on the mask (so quoted text can't fool it), sliced from
            // the original. What precedes it is the column expression.
            $expr = $orig;
            $alias = '';
            if (preg_match('/\bas\s+(["`]?)(\w+)\1\s*$/i', $maskitem, $am, PREG_OFFSET_CAPTURE)) {
                $expr = substr($orig, 0, $am[0][1]);
                $alias = trim((string) preg_replace('/^\s*as\s+/i', '', substr($orig, $am[0][1])), " `\"");
            }
            $maskexpr = substr($maskitem, 0, strlen($expr));

            // The whole expression must be a single case call (bar an optional leading DISTINCT).
            if (
                !preg_match(
                    '/^\s*(?:DISTINCT\s+)?(' . self::CASE_FUNCTIONS . ')\s*\(/i',
                    $maskexpr,
                    $fm,
                    PREG_OFFSET_CAPTURE
                )
            ) {
                continue;
            }
            $open = strpos($maskexpr, '(', (int) $fm[1][1]);
            $close = self::match_paren($maskexpr, (int) $open);
            if ($close === false || trim(substr($maskexpr, $close + 1)) !== '') {
                continue; // Text after the call (e.g. UPPER(x) || 'y') — not a pure case column.
            }
            $mode = in_array(strtolower($fm[1][0]), ['upper', 'ucase'], true) ? 'upper' : 'lower';

            $name = $alias;
            if ($name === '' && preg_match('/([A-Za-z_]\w*)\s*$/', $expr, $im)) {
                $name = $im[1];
            }
            if ($name === '') {
                continue;
            }
            $out[] = ['col' => $name, 'mode' => $mode];
        }
        return $out;
    }

    /**
     * Length-preserving mask of SQL comments and quoted spans (string literals and `…`/[…] quoted
     * identifiers): every masked character becomes a space, so offset scans over the result map 1:1
     * onto the original SQL. Mirrors the client-side maskSql() in amd/src/test.js.
     *
     * @param string $sql
     * @return string Same-length SQL with comments/quotes blanked to spaces.
     */
    private static function mask_sql(string $sql): string {
        $out = $sql;
        $n = strlen($sql);
        $i = 0;
        while ($i < $n) {
            $two = substr($sql, $i, 2);
            if ($two === '/*') {
                $j = strpos($sql, '*/', $i + 2);
                $j = ($j === false) ? $n : $j + 2;
                $out = substr_replace($out, str_repeat(' ', $j - $i), $i, $j - $i);
                $i = $j;
            } else if ($two === '--' || $sql[$i] === '#') {
                $j = strpos($sql, "\n", $i);
                $j = ($j === false) ? $n : $j;
                $out = substr_replace($out, str_repeat(' ', $j - $i), $i, $j - $i);
                $i = $j;
            } else if ($sql[$i] === "'" || $sql[$i] === '"' || $sql[$i] === '`' || $sql[$i] === '[') {
                $close = $sql[$i] === '[' ? ']' : $sql[$i];
                $out[$i] = ' ';
                $i++;
                while ($i < $n) {
                    if ($sql[$i] === $close) {
                        if (($sql[$i + 1] ?? '') === $close) { // Doubled closer is an escape.
                            $out[$i] = ' ';
                            $out[$i + 1] = ' ';
                            $i += 2;
                            continue;
                        }
                        $out[$i] = ' ';
                        $i++;
                        break;
                    }
                    $out[$i] = ' ';
                    $i++;
                }
            } else {
                $i++;
            }
        }
        return $out;
    }

    /**
     * Offsets of the top-level (paren depth 0) select list — between the outer SELECT and its
     * matching FROM. Handles CTEs / select-list subqueries because those sit at a deeper paren
     * level. Mirrors selectListRegion() in amd/src/test.js.
     *
     * @param string $mask Length-preserving masked SQL ({@see self::mask_sql()}).
     * @return array{0: int, 1: int}|null [selectlist start, FROM offset], or null when not found.
     */
    private static function select_list_region(string $mask): ?array {
        $depth = 0;
        $selend = -1;
        $len = strlen($mask);
        for ($i = 0; $i < $len; $i++) {
            $ch = $mask[$i];
            if ($ch === '(') {
                $depth++;
            } else if ($ch === ')') {
                $depth--;
            } else if ($depth === 0) {
                if ($selend === -1) {
                    if (self::word_start($mask, $i) && preg_match('/^select\b/i', substr($mask, $i, 7))) {
                        $selend = $i + 6;
                    }
                } else if (self::word_start($mask, $i) && preg_match('/^from\b/i', substr($mask, $i, 5))) {
                    return [$selend, $i];
                }
            }
        }
        return null;
    }

    /**
     * Split a select list into top-level (paren depth 0) items as [from, to] offset pairs.
     * Mirrors splitItems() in amd/src/test.js.
     *
     * @param string $mask Masked SQL.
     * @param int $start Select-list start offset.
     * @param int $end Select-list end offset (position of FROM).
     * @return array<array{0: int, 1: int}>
     */
    private static function split_items(string $mask, int $start, int $end): array {
        $items = [];
        $depth = 0;
        $from = $start;
        for ($i = $start; $i < $end; $i++) {
            $ch = $mask[$i];
            if ($ch === '(') {
                $depth++;
            } else if ($ch === ')') {
                $depth--;
            } else if ($ch === ',' && $depth === 0) {
                $items[] = [$from, $i];
                $from = $i + 1;
            }
        }
        $items[] = [$from, $end];
        return $items;
    }

    /**
     * Index of the ")" matching the "(" at $open, or false if unbalanced. Scans a masked string so
     * parens inside string literals are already blanked out.
     *
     * @param string $mask Masked SQL.
     * @param int $open Offset of the opening "(".
     * @return int|false
     */
    private static function match_paren(string $mask, int $open): int|false {
        $depth = 0;
        $len = strlen($mask);
        for ($i = $open; $i < $len; $i++) {
            if ($mask[$i] === '(') {
                $depth++;
            } else if ($mask[$i] === ')') {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }
        }
        return false;
    }

    /**
     * Whether the token at offset $i begins on a word boundary.
     *
     * @param string $mask
     * @param int $i
     * @return bool
     */
    private static function word_start(string $mask, int $i): bool {
        return $i === 0 || !ctype_alnum($mask[$i - 1]) && $mask[$i - 1] !== '_';
    }

    /**
     * Blank single/double-quoted string literals (content removed, delimiters kept) so structural
     * scans are not confused by SQL-like text inside literals.
     *
     * @param string $sql
     * @return string
     */
    private static function blank_literals(string $sql): string {
        $sql = preg_replace("/'(?:[^']|'')*'/", "''", $sql) ?? $sql;
        $sql = preg_replace('/"(?:[^"]|"")*"/', '""', $sql) ?? $sql;
        return $sql;
    }

    /**
     * Strip the Moodle table prefix (e.g. "mdl_") from a physical table name so the feedback
     * shows the Moodle table name authors recognise ("mdl_user" → "user").
     *
     * @param string $table Physical table name as reported by EXPLAIN.
     * @return string Table name without the configured prefix.
     */
    private static function unprefix(string $table): string {
        global $CFG;
        $prefix = $CFG->prefix;
        if ($prefix !== '' && strpos($table, $prefix) === 0) {
            return substr($table, strlen($prefix));
        }
        return $table;
    }
}
