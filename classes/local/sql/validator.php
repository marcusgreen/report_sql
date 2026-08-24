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
 * SELECT-only SQL validator for ad-hoc queries.
 *
 * Defence-in-depth gate before any user-authored SQL reaches the database.
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class validator {
    /**
     * MySQL-specific date functions that will fail on PostgreSQL.
     *
     * For datetime → Unix epoch, use the portable `%%EPOCH(datetime)%%` token instead of a native
     * function; it expands to the live family's spelling in
     * {@see \report_sql\local\sql\view::resolve_placeholders()}.
     *
     * @var string[]
     */
    private const MYSQL_DATE_FUNCTIONS = [
        'DATE_FORMAT', 'STR_TO_DATE', 'DATE_ADD', 'DATE_SUB',
        'DATEDIFF', 'UNIX_TIMESTAMP', 'FROM_UNIXTIME',
    ];

    /** @var string[] PostgreSQL-specific date/time functions that will fail on MySQL. */
    private const PGSQL_DATE_FUNCTIONS = [
        'TO_TIMESTAMP', 'TO_CHAR', 'DATE_TRUNC', 'AGE',
        'CLOCK_TIMESTAMP', 'TIMEOFDAY', 'MAKE_DATE', 'MAKE_TIME',
        'MAKE_TIMESTAMP', 'MAKE_TIMESTAMPTZ',
    ];

    /** @var string[] Warnings from the most recent validate() call. */
    private static array $warnings = [];

    /** @var string[] Forbidden SQL keywords. Matched as whole tokens after comment/string stripping. */
    private const DENY_KEYWORDS = [
        'INSERT', 'UPDATE', 'DELETE', 'DROP', 'TRUNCATE', 'ALTER', 'CREATE',
        'GRANT', 'REVOKE', 'REPLACE', 'CALL', 'LOAD', 'HANDLER', 'LOCK',
        'UNLOCK', 'RENAME', 'COMMIT', 'ROLLBACK', 'SAVEPOINT', 'USE',
        'EXEC', 'EXECUTE', 'INTO', 'OUTFILE', 'COPY', 'VACUUM', 'MERGE',
        'ATTACH', 'DETACH', 'PRAGMA',
    ];

    /**
     * @var string[] Built-in baseline of Moodle tables that must never be exposed.
     * Seeds the default of the admin-editable 'denytables' setting; {@see denied_tables()}
     * reads the setting at runtime so an admin's edits (including removals) take effect.
     */
    private const DENY_TABLES = [
        // Core configuration.
        'config', 'config_plugins', 'config_log',
        'adminpresets_it',
        // Passwords.
        'user_password_history', 'user_password_resets',
        // Sessions.
        'sessions', 'mnet_session',
        // OAuth2.
        'oauth2_issuer', 'oauth2_endpoint', 'oauth2_user_field_mapping',
        'oauth2_access_token', 'oauth2_refresh_token', 'oauth2_system_account',
        'auth_oauth2_linked_login', 'badge_backpack_oauth2',
        // Web service / API tokens.
        'external_tokens', 'external_services', 'external_services_users',
        'lti_access_tokens', 'enrol_lti_lti2_share_key',
        // Keys & secrets.
        'user_private_key', 'tool_mfa_secrets', 'messageinbound_datakeys',
        'message_airnotifier_devices',
        // Tasks.
        'task_adhoc',
    ];

    /**
     * Return warnings accumulated during the most recent validate() call.
     *
     * @return string[]
     */
    public static function get_warnings(): array {
        return self::$warnings;
    }

    /**
     * Validate user-supplied SQL.
     *
     * @param string $sql Raw SQL.
     * @return string Stripped, normalised SQL ready for further use.
     * @throws \moodle_exception when the SQL is rejected.
     */
    public static function validate(string $sql): string {
        self::$warnings = [];
        $sql = trim($sql);
        if ($sql === '') {
            throw new \moodle_exception('errnotselect', 'report_sql');
        }

        // Wrap bare table references in {}-placeholders so users do not need to type them.
        $sql = self::auto_brace($sql);

        $stripped = self::strip_comments_and_strings($sql);

        // A ? inside string literals (e.g. URL query strings like view.php?id=) is treated as
        // a positional DML parameter by Moodle's database layer, causing "Expected N, got 0" errors.
        if (strpos($sql, '?') !== false) {
            throw new \moodle_exception('errquestionmark', 'report_sql');
        }

        // Unfilled placeholder artifacts copied from ad-hoc report templates (e.g. ## or
        // %%FILTER_USERS%%) reach the DB as broken SQL. ## also starts a MySQL comment, truncating
        // the statement into a cryptic syntax error. Catch these and name the offending token.
        // The supported tokens (%%WWWROOT%%, %%COURSEID%%, %%NOW%%, %%CONTEXT_*%%, %%TIMESTAMP(expr)%%) are all
        // substituted at view-build time (see view::resolve_placeholders), so they are exempt from
        // this rejection. %%COURSEID%% additionally requires the query to carry a course scope —
        // enforced in the edit form.
        //
        // Scan with string literals blanked (but comments kept) so a doubled LIKE wildcard
        // such as LIKE '%%smi%%', or a literal '##' inside a string, is not misread as a token;
        // a bare ## outside any string is still caught because comments are left intact here.
        if (preg_match_all('/##+|%%[^%\n]*%%/', self::strip_strings($sql), $ms)) {
            foreach ($ms[0] as $token) {
                if (!self::is_supported_token($token)) {
                    // Token %%USERID%% (and the USER_ID / USERIDS / USER_IDS spellings) is a common ask for a
                    // "current viewer" placeholder. The fixed view cannot carry a per-request user id, so
                    // steer authors to the "Restrict to viewing user" form field instead of the generic hint.
                    if (preg_match('/^%%\s*USER_?IDS?\s*%%$/i', $token)) {
                        throw new \moodle_exception('errplaceholderuserid', 'report_sql', '', $token);
                    }
                    throw new \moodle_exception('errplaceholder', 'report_sql', '', $token);
                }
            }
        }

        // Single statement — reject both semicolon-separated and bare multi-statement SQL.
        if (str_contains(rtrim($stripped, "; \t\n\r"), ';')) {
            throw new \moodle_exception('errmultistatement', 'report_sql');
        }
        if (self::count_top_level_selects($stripped) > 1) {
            throw new \moodle_exception('errmultistatement', 'report_sql');
        }

        // Parse the query. greenlion's parser does not understand Moodle's {table} braces,
        // so feed it a brace-stripped copy. The parse tree drives the statement-type and
        // JOIN-condition checks below; the denylists that follow are kept as defence-in-depth.
        $tree = self::parse(self::strip_braces(rtrim($sql, "; \t\n\r")));

        // Statement type: top-level keys must describe a SELECT/WITH/UNION read query only.
        self::check_statement_type($tree);

        // Token denylist (defence-in-depth — the parser alone does not block, e.g., INTO OUTFILE).
        foreach (self::DENY_KEYWORDS as $kw) {
            // REPLACE(...) the string function is legitimate in a SELECT list; only the
            // REPLACE statement (never followed by an opening paren) is blocked.
            $pattern = $kw === 'REPLACE'
                ? '/\bREPLACE\b(?!\s*\()/i'
                : '/\b' . preg_quote($kw, '/') . '\b/i';
            if (preg_match($pattern, $stripped)) {
                throw new \moodle_exception('errdeniedkeyword', 'report_sql', '', $kw);
            }
        }

        // Table denylist (Moodle {tablename} syntax).
        if (preg_match_all('/\{([a-z0-9_]+)\}/i', $stripped, $matches)) {
            foreach ($matches[1] as $table) {
                if (in_array(strtolower($table), self::denied_tables(), true)) {
                    throw new \moodle_exception('errdeniedtable', 'report_sql', '', $table);
                }
            }
        }

        // Column denylist (admin-configured, e.g. password,secret,sesskey). The view's introspected
        // metadata is also denylist-stripped at publish time, but that only filters by *output*
        // column name — `SELECT password AS pw` would slip a renamed denied column through. Reject
        // any reference to a denied column name as a bare identifier token here (string literals and
        // comments are already blanked in $stripped, so a denied word inside a literal is ignored).
        foreach (self::denied_columns() as $col) {
            if (preg_match('/\b' . preg_quote($col, '/') . '\b/i', $stripped)) {
                throw new \moodle_exception('errdeniedcolumn', 'report_sql', '', $col);
            }
        }

        // Each JOIN needs an ON/USING condition — catch the common mistake before the
        // DB returns a cryptic syntax error.
        self::check_join_conditions($tree, $stripped);

        global $CFG;
        $dbtype = $CFG->dbtype ?? 'mysqli';

        // Warn about MySQL-specific date functions that will fail on PostgreSQL.
        if ($dbtype === 'pgsql') {
            foreach (self::MYSQL_DATE_FUNCTIONS as $fn) {
                if (preg_match('/\b' . $fn . '\s*\(/i', $stripped)) {
                    self::$warnings[] = get_string('warnmysqldatefn', 'report_sql', $fn);
                }
            }

            // Mixed-case double-quoted aliases (e.g. `AS "Course_Shortname"`) become case-sensitive
            // view columns on PostgreSQL that Report Builder's unquoted SQL cannot reference. Rather
            // than warn, view::normalise_aliases() lowercases them at view-build time.
        }

        // Error on PostgreSQL-specific date/time functions when running MySQL/MariaDB.
        if ($dbtype !== 'pgsql') {
            foreach (self::PGSQL_DATE_FUNCTIONS as $fn) {
                if (preg_match('/\b' . preg_quote($fn, '/') . '\s*\(/i', $stripped)) {
                    throw new \moodle_exception('errpgsqldatefn', 'report_sql', '', $fn);
                }
            }
        }

        return rtrim($sql, "; \t\n\r");
    }

    /**
     * Whether a `%%...%%` token is one the plugin substitutes at view-build time.
     *
     * Exact tokens: %%WWWROOT%%, %%COURSEID%%, %%COURSECONTEXT%%, %%NOW%% and the %%CONTEXT_*%% level
     * constants (%%CONTEXT_COURSE%% etc.). The parameterised %%TIMESTAMP(expr)%% (epoch
     * column → datetime, rendered per dialect), %%EPOCH(datetime)%% (datetime literal/expr → epoch
     * int, per dialect) and %%CASE(expr, mode)%% (text column → upper/lower/title/sentence case,
     * applied per-viewer as a display callback) tokens are matched by shape; their inner expression
     * is resolved in {@see \report_sql\local\sql\view::resolve_placeholders()}.
     *
     * @param string $token A token captured by the %%..%% scan, including the surrounding %%.
     * @return bool
     */
    private static function is_supported_token(string $token): bool {
        $exacttokens = array_merge(
            ['%%WWWROOT%%', '%%COURSEID%%', '%%COURSECONTEXT%%', '%%NOW%%'],
            array_keys(view::context_level_tokens())
        );
        foreach ($exacttokens as $exact) {
            if (strcasecmp($token, $exact) === 0) {
                return true;
            }
        }
        return (bool) preg_match('/^%%(?:TIMESTAMP|EPOCH|CASE)\(.+\)%%$/i', $token);
    }

    /**
     * Parse SQL into greenlion's parse tree, lazily loading the bundled library.
     *
     * @param string $sql Brace-stripped SQL (the parser does not understand {table} syntax).
     * @return array Parse tree keyed by clause (SELECT, FROM, WHERE, ...).
     * @throws \moodle_exception when the SQL cannot be parsed.
     */
    private static function parse(string $sql): array {
        global $CFG;

        if (!class_exists(\PHPSQLParser\PHPSQLParser::class)) {
            require_once($CFG->dirroot . '/report/sql/lib/php-sql-parser/vendor/autoload.php');
        }

        try {
            $parser = new \PHPSQLParser\PHPSQLParser($sql);
            $tree = $parser->parsed;
        } catch (\Throwable $e) {
            throw new \moodle_exception('errparse', 'report_sql', '', $e->getMessage());
        }

        if (!is_array($tree) || $tree === []) {
            throw new \moodle_exception('errnotselect', 'report_sql');
        }
        return $tree;
    }

    /**
     * Confirm the parsed statement is a read-only SELECT/WITH/UNION query.
     *
     * The parse tree's top-level keys name the statement: a SELECT yields SELECT/FROM/WHERE/...,
     * whereas INSERT/UPDATE/DELETE/DROP/etc. surface their own keyword as a top-level key. Any
     * key outside the read-only whitelist rejects the query.
     *
     * @param array $tree Parse tree from {@see self::parse()}.
     * @return void
     * @throws \moodle_exception when the statement is not a pure read query.
     */
    private static function check_statement_type(array $tree): void {
        $allowed = [
            'SELECT', 'FROM', 'WHERE', 'GROUP', 'HAVING', 'ORDER', 'LIMIT', 'OFFSET',
            'WITH', 'UNION', 'UNION ALL', 'EXCEPT', 'INTERSECT', 'BRACKET', 'OPTIONS',
        ];
        $keys = array_map('strtoupper', array_keys($tree));

        foreach ($keys as $key) {
            if (!in_array($key, $allowed, true)) {
                throw new \moodle_exception('errnotselect', 'report_sql');
            }
        }

        // Must actually be a query that returns rows, not just clause fragments.
        $reads = ['SELECT', 'WITH', 'UNION', 'UNION ALL', 'EXCEPT', 'INTERSECT', 'BRACKET'];
        if (!array_intersect($reads, $keys)) {
            throw new \moodle_exception('errnotselect', 'report_sql');
        }
    }

    /**
     * Reject JOINs that have no ON or USING condition.
     *
     * Without this the DB returns an opaque syntax error (e.g. "...near '.userid = u.id'")
     * for a JOIN like `JOIN user_enrolments ue.userid = u.id` where the author forgot ON.
     * Walks the parse tree's FROM list: the first table is the base (no condition); each
     * subsequent table must carry a ref_type (ON/USING). CROSS and NATURAL joins legitimately
     * omit the condition. The parser collapses CROSS JOIN to join_type=JOIN, so when an explicit
     * CROSS JOIN appears we skip the JOIN-typed checks rather than risk a false positive — the
     * live DB dry-run remains the final gate.
     *
     * @param array $tree Parse tree from {@see self::parse()}.
     * @param string $stripped SQL with comments and string literals already removed.
     * @return void
     * @throws \moodle_exception when a JOIN lacks an ON/USING condition.
     */
    private static function check_join_conditions(array $tree, string $stripped): void {
        if (empty($tree['FROM']) || !is_array($tree['FROM'])) {
            return;
        }

        $hascrossjoin = (bool) preg_match('/\bCROSS\s+JOIN\b/i', $stripped);

        foreach ($tree['FROM'] as $i => $from) {
            if ($i === 0) {
                continue; // Base table — no join condition expected.
            }
            // The greenlion parser uses false (not null) for absent values, so cast before strtoupper.
            $jointype = strtoupper((string) ($from['join_type'] ?? ''));
            $reftype = strtoupper((string) ($from['ref_type'] ?? ''));

            if ($reftype !== '') {
                continue; // Has ON/USING.
            }
            if (in_array($jointype, ['CROSS', 'NATURAL'], true)) {
                continue; // Conditionless join by design.
            }
            // A join_type of JOIN also covers an explicit CROSS JOIN; stay lenient if one is present.
            if ($jointype === 'JOIN' && $hascrossjoin) {
                continue;
            }
            throw new \moodle_exception('errjoinnoon', 'report_sql');
        }
    }

    /**
     * Replace string literals and comments with whitespace so the keyword scan can't be evaded
     * via comments or string content.
     *
     * @param string $sql
     * @return string
     */
    private static function strip_comments_and_strings(string $sql): string {
        // Block comments.
        $sql = preg_replace('/\/\*.*?\*\//s', ' ', $sql) ?? '';
        // Line comments.
        $sql = preg_replace('/--[^\n]*/', ' ', $sql) ?? '';
        $sql = preg_replace('/#[^\n]*/', ' ', $sql) ?? '';
        // String literals (single, double, backtick).
        $sql = preg_replace("/'(?:[^']|'')*'/", "''", $sql) ?? '';
        $sql = preg_replace('/"(?:[^"]|"")*"/', '""', $sql) ?? '';
        return $sql;
    }

    /**
     * Replace string literals with an empty literal, leaving comments intact.
     *
     * Used by the placeholder-token scan so SQL inside string literals (e.g. a doubled LIKE
     * wildcard '%%smi%%', or a literal '##') is not mistaken for an unfilled %%token%% / ## artifact,
     * while a bare ## outside any string is still detectable.
     *
     * @param string $sql
     * @return string
     */
    private static function strip_strings(string $sql): string {
        $sql = preg_replace("/'(?:[^']|'')*'/", "''", $sql) ?? '';
        $sql = preg_replace('/"(?:[^"]|"")*"/', '""', $sql) ?? '';
        return $sql;
    }

    /**
     * Wrap bare table references with Moodle's `{tablename}` syntax when the user omits the braces.
     *
     * Walks the SQL once with a tokenising regex that ignores strings, comments and already-braced
     * names. Tables are only braced when they appear in a position where a table is expected
     * (immediately after FROM or JOIN, or after a comma inside a FROM-list). Subqueries push and
     * pop their own table-list state on parentheses so an outer FROM does not leak into them.
     *
     * @param string $sql Raw SQL.
     * @return string SQL with bare table references braced.
     */
    public static function auto_brace(string $sql): string {
        global $DB, $CFG;
        try {
            $tables = $DB->get_tables();
        } catch (\Throwable $e) {
            return $sql;
        }
        if (!$tables) {
            return $sql;
        }
        $tableset  = array_flip(array_map('strtolower', $tables));
        $dbprefix  = strtolower($CFG->prefix ?? '');

        $pattern = '/'
            . '(?P<str>\'(?:[^\']|\'\')*\')'
            . '|(?P<dstr>"(?:[^"]|"")*")'
            . '|(?P<lcmt>--[^\n]*)'
            . '|(?P<hcmt>\#[^\n]*)'
            . '|(?P<bcmt>\/\*[\s\S]*?\*\/)'
            . '|(?P<braced>\{[a-z0-9_]+\})'
            . '|(?P<lparen>\()'
            . '|(?P<rparen>\))'
            . '|(?P<comma>,)'
            . '|(?P<id>[A-Za-z_][A-Za-z0-9_]*)'
            . '/s';

        $clauseend = ['where', 'group', 'order', 'having', 'limit',
            'on', 'using', 'union', 'except', 'intersect'];

        $expecting = false;
        $infromlist = false;
        $stack = [];

        $result = preg_replace_callback(
            $pattern,
            static function (array $m) use ($tableset, $dbprefix, $clauseend, &$expecting, &$infromlist, &$stack): string {
                if (isset($m['str']) && $m['str'] !== '') {
                    return $m['str'];
                }
                if (isset($m['dstr']) && $m['dstr'] !== '') {
                    return $m['dstr'];
                }
                if (isset($m['lcmt']) && $m['lcmt'] !== '') {
                    return $m['lcmt'];
                }
                if (isset($m['hcmt']) && $m['hcmt'] !== '') {
                    return $m['hcmt'];
                }
                if (isset($m['bcmt']) && $m['bcmt'] !== '') {
                    return $m['bcmt'];
                }
                if (isset($m['braced']) && $m['braced'] !== '') {
                    if ($expecting) {
                        $expecting = false;
                        $infromlist = true;
                    }
                    return $m['braced'];
                }
                if (isset($m['lparen']) && $m['lparen'] !== '') {
                    $stack[] = [$expecting, $infromlist];
                    $expecting = false;
                    $infromlist = false;
                    return '(';
                }
                if (isset($m['rparen']) && $m['rparen'] !== '') {
                    if ($stack) {
                        [$expecting, $infromlist] = array_pop($stack);
                    }
                    return ')';
                }
                if (isset($m['comma']) && $m['comma'] !== '') {
                    if ($infromlist) {
                        $expecting = true;
                    }
                    return ',';
                }
                $id = $m['id'];
                $idl = strtolower($id);

                // Strip "prefix_" or the actual configured DB prefix (e.g. "mdl_") so users can
                // paste SQL from tools that output fully-prefixed names. This runs regardless of
                // position so qualified column references (e.g. prefix_user.deleted) are rewritten
                // to {user}.deleted too, not just the table reference after FROM/JOIN.
                $stripped = null;
                if (str_starts_with($idl, 'prefix_')) {
                    $stripped = substr($idl, 7);
                } else if ($dbprefix !== '' && str_starts_with($idl, $dbprefix)) {
                    $stripped = substr($idl, strlen($dbprefix));
                }
                if ($stripped !== null && isset($tableset[$stripped])) {
                    if ($expecting) {
                        $expecting = false;
                        $infromlist = true;
                    }
                    return '{' . $stripped . '}';
                }

                if ($expecting && isset($tableset[$idl])) {
                    $expecting = false;
                    $infromlist = true;
                    return '{' . $idl . '}';
                }

                if ($idl === 'from' || $idl === 'join') {
                    $expecting = true;
                    $infromlist = true;
                } else if (in_array($idl, $clauseend, true)) {
                    $expecting = false;
                    $infromlist = false;
                } else {
                    $expecting = false;
                }
                return $id;
            },
            $sql
        );
        return $result ?? $sql;
    }

    /**
     * Strip Moodle `{tablename}` braces for display, leaving string/comment contents untouched.
     *
     * @param string $sql Stored SQL.
     * @return string SQL with braces removed around table references.
     */
    public static function strip_braces(string $sql): string {
        $pattern = '/'
            . '(?P<str>\'(?:[^\']|\'\')*\')'
            . '|(?P<dstr>"(?:[^"]|"")*")'
            . '|(?P<lcmt>--[^\n]*)'
            . '|(?P<hcmt>\#[^\n]*)'
            . '|(?P<bcmt>\/\*[\s\S]*?\*\/)'
            . '|\{(?P<tbl>[a-z0-9_]+)\}'
            . '/is';
        $result = preg_replace_callback($pattern, static function (array $m): string {
            if (isset($m['str']) && $m['str'] !== '') {
                return $m['str'];
            }
            if (isset($m['dstr']) && $m['dstr'] !== '') {
                return $m['dstr'];
            }
            if (isset($m['lcmt']) && $m['lcmt'] !== '') {
                return $m['lcmt'];
            }
            if (isset($m['hcmt']) && $m['hcmt'] !== '') {
                return $m['hcmt'];
            }
            if (isset($m['bcmt']) && $m['bcmt'] !== '') {
                return $m['bcmt'];
            }
            return $m['tbl'];
        }, $sql);
        return $result ?? $sql;
    }

    /**
     * Count SELECT keywords at parenthesis depth 0 to detect bare multi-statement SQL
     * (two SELECTs with no semicolon separator).
     *
     * A SELECT introduced by a set operator (UNION/EXCEPT/INTERSECT, optionally followed by
     * ALL/DISTINCT) continues the same statement, so it is not counted.
     *
     * @param string $stripped SQL with comments and string literals already removed.
     * @return int
     */
    private static function count_top_level_selects(string $stripped): int {
        $depth = 0;
        $count = 0;
        $aftersetop = false;
        $tokens = preg_split(
            '/(\(|\)|\b(?:SELECT|UNION|EXCEPT|INTERSECT)\b)/i',
            $stripped,
            -1,
            PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY
        );
        foreach ($tokens as $tok) {
            if ($tok === '(') {
                $depth++;
                $aftersetop = false;
            } else if ($tok === ')') {
                $depth--;
            } else if (in_array(strtoupper($tok), ['UNION', 'EXCEPT', 'INTERSECT'], true)) {
                if ($depth === 0) {
                    $aftersetop = true;
                }
            } else if (strcasecmp($tok, 'SELECT') === 0) {
                if ($depth === 0 && !$aftersetop) {
                    $count++;
                }
                $aftersetop = false;
            } else if (
                $depth === 0 && $aftersetop
                    && !in_array(strtoupper(trim($tok)), ['', 'ALL', 'DISTINCT'], true)
            ) {
                // Anything other than ALL/DISTINCT between the set operator and its SELECT
                // ends the set operation, so the next SELECT counts as a new statement.
                $aftersetop = false;
            }
        }
        return $count;
    }

    /**
     * Strip DB-server internals from an error message before showing it to a user.
     *
     * MySQL embeds the schema name and Moodle table prefix in error strings, e.g.
     * "Table 'mdl52.mdl_xuser' doesn't exist". Strip both so the user sees
     * "Table 'xuser' doesn't exist".
     *
     * @param string $msg Raw error from dml_exception.
     * @return string Cleaned message.
     */
    public static function clean_error(string $msg): string {
        global $CFG;
        if (!empty($CFG->dbname)) {
            $msg = str_replace($CFG->dbname . '.', '', $msg);
        }
        if (!empty($CFG->prefix)) {
            $msg = preg_replace('/\b' . preg_quote($CFG->prefix, '/') . '/', '', $msg);
        }
        return $msg;
    }

    /**
     * Extract the names of named placeholders (:name) appearing in the SQL.
     *
     * @param string $sql
     * @return string[] Sorted unique placeholder names.
     */
    public static function placeholders(string $sql): array {
        $stripped = self::strip_comments_and_strings($sql);
        preg_match_all('/(?<!:):([a-z_][a-z0-9_]*)/i', $stripped, $m);
        $names = array_values(array_unique($m[1] ?? []));
        sort($names);
        return $names;
    }

    /**
     * Lowercased denylist of sensitive column names from admin config (denycolumns).
     *
     * Mirrors {@see \report_sql\local\sql\view::columns()}'s output-name filter, but is
     * applied to the SQL source so aliased denied columns cannot leak.
     *
     * @return string[]
     */
    private static function denied_columns(): array {
        $raw = (string) get_config('report_sql', 'denycolumns');
        if ($raw === '') {
            return [];
        }
        $items = preg_split('/[\s,]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return array_map('strtolower', $items);
    }

    /**
     * The built-in table denylist as a comma-separated string, used to seed the default
     * value of the 'denytables' admin setting (see settings.php).
     *
     * @return string
     */
    public static function default_denytables(): string {
        return implode(',', self::DENY_TABLES);
    }

    /**
     * Lowercased denylist of tables to reject. Admin-editable via the 'denytables' setting,
     * which is seeded from {@see DENY_TABLES}. The setting is the single source of truth: an
     * admin may add, remove or clear entries. Only when the setting has never been saved
     * (config unset) does the built-in baseline apply.
     *
     * @return string[]
     */
    private static function denied_tables(): array {
        $raw = get_config('report_sql', 'denytables');
        if ($raw === false) {
            // Setting never saved — fall back to the built-in baseline.
            return self::DENY_TABLES;
        }
        $items = preg_split('/[\s,]+/', (string) $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return array_map('strtolower', $items);
    }
}
