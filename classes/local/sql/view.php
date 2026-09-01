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

use report_sql\local\sql\validator;

/**
 * Manages database VIEWs that back published ad-hoc queries.
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class view {
    /** @var string Prefix for generated view names (without Moodle prefix). */
    public const NAME_PREFIX = 'report_sql_v_';

    /** @var string Prefix for throwaway inline-preview views (one reusable slot per user). */
    public const PREVIEW_NAME_PREFIX = 'report_sql_v_preview_';

    /**
     * Build the view name for a given query id (without Moodle prefix).
     *
     * @param int $queryid
     * @return string
     */
    public static function name_for(int $queryid): string {
        return self::NAME_PREFIX . $queryid;
    }

    /**
     * Build the throwaway preview view name for a user (without Moodle prefix). One reusable slot
     * per user, CREATE OR REPLACEd on each preview, so previewing never accumulates views.
     *
     * @param int $userid
     * @return string
     */
    public static function preview_name_for(int $userid): string {
        return self::PREVIEW_NAME_PREFIX . $userid;
    }

    /**
     * (Re)create the VIEW for a saved query.
     *
     * Replaces Moodle's `{tablename}` placeholders with prefixed table names before issuing
     * CREATE OR REPLACE VIEW. The provided SQL must already have been validated.
     *
     * @param int $queryid
     * @param string $validatedsql
     * @param int $courseid Course scope to substitute into %%COURSEID%% (0 = site-wide).
     * @return string The view name (without prefix) on success.
     * @throws \moodle_exception
     */
    public static function create_or_replace(int $queryid, string $validatedsql, int $courseid = 0): string {
        return self::issue_create_or_replace(self::name_for($queryid), $validatedsql, $courseid);
    }

    /**
     * (Re)create the throwaway inline-preview VIEW for a user. Same DDL path as
     * {@see create_or_replace()}, but targets the per-user reusable preview slot rather than a
     * query-id view. The provided SQL must already have been validated.
     *
     * @param int $userid
     * @param string $validatedsql
     * @param int $courseid Course scope to substitute into %%COURSEID%% (0 = site-wide).
     * @return string The preview view name (without prefix).
     * @throws \moodle_exception
     */
    public static function create_or_replace_preview(int $userid, string $validatedsql, int $courseid = 0): string {
        return self::issue_create_or_replace(self::preview_name_for($userid), $validatedsql, $courseid);
    }

    /**
     * Drop a user's throwaway preview VIEW, if it exists.
     *
     * @param int $userid
     */
    public static function drop_preview(int $userid): void {
        global $DB, $CFG;
        $fullname = $CFG->prefix . self::preview_name_for($userid);
        $DB->change_database_structure("DROP VIEW IF EXISTS {$fullname}");
    }

    /**
     * Issue CREATE OR REPLACE VIEW for the given (unprefixed) view name, resolving placeholders and
     * normalising aliases first. Shared by the published-view and preview-view paths.
     *
     * @param string $viewname View name without the Moodle prefix.
     * @param string $validatedsql Already-validated SQL.
     * @param int $courseid Course scope to substitute into %%COURSEID%%.
     * @return string The view name (echoed back).
     * @throws \moodle_exception
     */
    private static function issue_create_or_replace(string $viewname, string $validatedsql, int $courseid): string {
        global $DB, $CFG;

        $fullname = $CFG->prefix . $viewname;
        $resolved = self::compile($validatedsql, $courseid);

        $ddl = "CREATE OR REPLACE VIEW {$fullname} AS {$resolved}";

        try {
            $DB->change_database_structure($ddl);
        } catch (\dml_exception | \ddl_change_structure_exception $e) {
            // A failed CREATE VIEW throws ddl_change_structure_exception (a moodle_exception, not a
            // dml_exception); its ->error is the bare DB message while ->sql carries the raw DDL. Use
            // ->error so the leaked CREATE ... VIEW statement and mdl_ prefix never reach the author —
            // the compiled SQL is surfaced separately by report_sql_compiled_sql_details().
            $detail = validator::clean_error($e->error ?: ($e->debuginfo ?: $e->getMessage()));
            if (stripos($detail, 'Duplicate column name') !== false) {
                throw new \moodle_exception(
                    'errcreateview',
                    'report_sql',
                    '',
                    get_string('errduplicatecolumn', 'report_sql')
                );
            }
            throw new \moodle_exception('errcreateview', 'report_sql', '', $detail);
        }
        return $viewname;
    }

    /**
     * The exact SQL a VIEW is built from for the given validated SQL and course scope: placeholders
     * resolved, then aliases normalised — i.e. the SQL that actually runs on the database. Exposed so
     * an error surface (live check, Test query, inline preview) can show the compiled SQL beside the
     * DB error, whose line/column numbers refer to this string, not the author's un-substituted text.
     *
     * @param string $validatedsql Already-validated SQL.
     * @param int $courseid Course scope to substitute into %%COURSEID%% (0 = site-wide).
     * @return string The compiled SQL sent to CREATE OR REPLACE VIEW.
     */
    public static function compile(string $validatedsql, int $courseid = 0): string {
        return self::normalise_aliases(self::resolve_placeholders($validatedsql, $courseid));
    }

    /**
     * Drop the VIEW for a saved query, if it exists.
     *
     * @param int $queryid
     */
    public static function drop(int $queryid): void {
        global $DB, $CFG;

        $viewname = self::name_for($queryid);
        $fullname = $CFG->prefix . $viewname;

        try {
            $DB->change_database_structure("DROP VIEW IF EXISTS {$fullname}");
        } catch (\dml_exception $e) {
            $detail = $e->error ?: ($e->debuginfo ?: $e->getMessage());
            throw new \moodle_exception('errdropview', 'report_sql', '', $detail);
        }
    }

    /**
     * Replace spaces with underscores in quoted column aliases so the resulting VIEW has
     * identifier-safe column names. Operates on both double-quoted and backtick-quoted aliases.
     *
     * e.g.  AS "Common world format"  →  AS "Common_world_format"
     *
     * On PostgreSQL, double-quoted identifiers are case-sensitive, so a mixed-case alias like
     * `AS "Course_Shortname"` becomes a case-sensitive view column that Report Builder's unquoted
     * SQL (which PostgreSQL folds to lowercase) cannot reference. Lowercase double-quoted aliases
     * so the view column matches RB's case-folded reference. MySQL folds case anyway, so its
     * aliases are left untouched.
     *
     * @param string $sql
     * @return string
     */
    public static function normalise_aliases(string $sql): string {
        global $DB;
        $pg = $DB->get_dbfamily() === 'postgres';
        return preg_replace_callback(
            // phpcs:ignore moodle.Strings.ForbiddenStrings.Found
            '/\bAS\s+(["`])([^"`]+)\1/i',
            static function (array $m) use ($pg): string {
                $alias = str_replace(' ', '_', $m[2]);
                if ($pg && $m[1] === '"') {
                    $alias = strtolower($alias);
                }
                return 'AS ' . $m[1] . $alias . $m[1];
            },
            $sql
        ) ?? $sql;
    }

    /**
     * Map of `%%CONTEXT_*%%` tokens to their Moodle context-level constant values.
     *
     * Mirrors core's CONTEXT_* constants by name so the token reads like the constant a Moodle
     * developer already knows (e.g. `%%CONTEXT_COURSE%%` → CONTEXT_COURSE → 50) instead of a magic
     * number in `mdl_context.contextlevel = 50`. Values come from the live constants so they can
     * never drift from core.
     *
     * @return array<string,int> Uppercase token (with surrounding %%) => context level.
     */
    public static function context_level_tokens(): array {
        return [
            '%%CONTEXT_SYSTEM%%'    => CONTEXT_SYSTEM,
            '%%CONTEXT_USER%%'      => CONTEXT_USER,
            '%%CONTEXT_COURSECAT%%' => CONTEXT_COURSECAT,
            '%%CONTEXT_COURSE%%'    => CONTEXT_COURSE,
            '%%CONTEXT_MODULE%%'    => CONTEXT_MODULE,
            '%%CONTEXT_BLOCK%%'     => CONTEXT_BLOCK,
        ];
    }

    /**
     * Prompt rules describing the report_sql %%…%% tokens, for appending to an
     * AI SQL-generation prompt (e.g. local_sqlchat's api::generate_sql third arg).
     *
     * This is the single place that teaches an LLM about our tokens: local_sqlchat
     * is token-agnostic and appends whatever string this returns. If report_sql
     * is not installed the caller never obtains this text, so no tokens are emitted.
     * The tokens are resolved later by {@see self::resolve_placeholders()} when the
     * view is built, so generated SQL stays portable across MySQL/MariaDB/PostgreSQL.
     *
     * @return string Rule lines (each starting with "- "), or '' if none apply.
     */
    public static function ai_prompt_rules(): string {
        return <<<RULES
- report_sql portable tokens: this SQL will be saved as a report, so
  prefer these %%…%% tokens over raw dialect functions — they are rewritten for
  the live database when the report is built.
  CRITICAL SYNTAX: every token is bounded by a leading %% and a trailing %%, and
  the function-style tokens close their parenthesis BEFORE that trailing %% —
  i.e. %%NAME(args)%%. Always write the complete closing )%% ; a token missing
  its )%% (e.g. %%CASE(u.city, upper) with no )%%) is invalid and breaks the SQL.
  Count: for every "%%" you open, emit a matching "%%" to close, and for every
  "(" inside a token emit its ")" before the closing %%.
  - Table aliases: write plain unprefixed table names (course, user,
    course_categories) — report_sql rewrites each to its real prefixed name
    (course -> mdl_course) before the query runs. Because of that rewrite, a plain
    table name is NOT a valid column qualifier. Therefore give EVERY table you
    reference by a dotted column an explicit alias, and qualify columns with that
    alias — e.g. FROM course_categories cat JOIN course c ON c.category = cat.id,
    then cat.name / c.id. Never reference course.id when the table has no "course"
    alias: after the rewrite the table is mdl_course, so course.id is a dangling
    reference and the report fails to build.
  - Dates: Moodle stores dates as Unix-epoch INTEGER columns (name contains one
    of time, date, created, modified, start, end, expir, due, login, logout,
    access, seen, stamp, cron, sync, sent, finish, run). Wrap such a column's
    SELECT output in %%TIMESTAMP(expr)%% so it renders as a sortable date — e.g.
    SELECT %%TIMESTAMP(u.timecreated)%% AS timecreated. Keep the raw column in
    WHERE, ORDER BY, GROUP BY and joins; do NOT wrap non-date integers (ids,
    counts, durations such as enrolperiod).
  - %%NOW%% is the current time as a bare epoch INTEGER (seconds). It is NOT a
    function: write %%NOW%%, never %%NOW()%% or NOW(). Do relative-time maths on it
    with plain integer arithmetic in seconds (1 hour = 3600, 1 day = 86400) — e.g.
    "last 24 hours" is WHERE timemodified >= %%NOW%% - 86400, "last 7 days" is
    %%NOW%% - 7 * 86400. Never use SQL INTERVAL, DATE_SUB/DATE_ADD, or NOW() for
    relative time — those are dialect-specific and will be rejected.
  - %%EPOCH(datetime)%% converts a fixed datetime STRING LITERAL to a Unix-epoch
    integer, for WHERE against an epoch column — e.g. WHERE timecreated >=
    %%EPOCH('2025-01-01 00:00:00')%%. Do NOT put %%NOW%%, INTERVAL, or arithmetic
    inside %%EPOCH()%%; for "now" or relative time use %%NOW%% arithmetic (above).
  - Do NOT wrap a computed epoch such as %%NOW%% - 86400 in %%TIMESTAMP()%% or
    %%EPOCH()%%: %%TIMESTAMP()%% is display-only over a STORED epoch column, and
    %%EPOCH()%% takes a datetime literal — a WHERE bound is already an integer.
  - Text case (display only, stored value and sort/filter unchanged):
    %%CASE(expr, mode)%% with mode one of upper|lower|title|sentence — e.g.
    %%CASE(u.lastname, upper)%% AS lastname. Note the full closing )%% after the
    mode. expr must not contain '%'.
  - Aggregate a column into one delimited string (portable across MySQL and
    Postgres — do NOT write raw GROUP_CONCAT or string_agg):
    %%GROUP_CONCAT(expr[, 'sep'])%% — e.g.
    %%GROUP_CONCAT(u.lastname, ', ')%% AS names. The separator is an optional
    single-quoted literal (defaults to ','); an optional leading DISTINCT is
    allowed, e.g. %%GROUP_CONCAT(DISTINCT c.format, ', ')%%. Use inside a query
    that has a GROUP BY. Note the full closing )%%. expr must not contain '%'.
  - Link a cell (display only, stored value and sort/filter unchanged): wrap the
    column in %%LINK(expr, 'path')%% where path is a SITE-RELATIVE URL (must start
    with '/', no scheme) and {} is the slot for the cell value — e.g.
    %%LINK(u.id, '/user/view.php?id={}')%% AS profile. To display one column but
    key the link on another, add a key column: %%LINK(display, keycol, 'path')%%,
    where keycol names another selected output column that fills {} — e.g.
    %%LINK(CONCAT(u.firstname, ' ', u.lastname), userid, '/user/view.php?id={}')%%
    AS fullname (with u.id AS userid also selected). Prefer this over building an
    <a href> by hand in a CONCAT: it escapes the value (no XSS) and can never point
    off-site. Note the full closing )%%. expr must not contain '%'.
  - %%WWWROOT%% is the site URL, for building links inside a CONCAT.
  - %%CONTEXT_SYSTEM/USER/COURSECAT/COURSE/MODULE/BLOCK%% are the context-level
    constants (e.g. %%CONTEXT_COURSE%% = 50) — prefer these over the literal
    number when filtering context.contextlevel.
  - Course scope: use %%COURSEID%% (bound course id) and %%COURSECONTEXT%% (its
    context row id) ONLY when the question is clearly about a single course;
    otherwise filter courses explicitly. When the question refers to "this
    course" (or "the current course", "this course's", or similar deixis
    meaning the course in scope), add a WHERE clause pinning the relevant
    course-id column to %%COURSEID%% — e.g. WHERE c.id = %%COURSEID%%, or on a
    table carrying a courseid column WHERE courseid = %%COURSEID%%. Join to
    course only if no course-id column is already reachable in the query.
RULES;
    }

    /**
     * Replace `{tablename}` with the prefixed table name, `%%WWWROOT%%` with the site URL,
     * `%%COURSEID%%` with the query's course scope, and the portable date/time tokens `%%NOW%%`
     * and `%%TIMESTAMP(expr)%%` with their dialect for the live database. The Moodle DML layer
     * normally resolves `{table}` for parameterised queries but DDL statements bypass that path.
     * `%%WWWROOT%%` lets authors embed absolute links (e.g. in a CONCAT building an <a href>)
     * without hard-coding the site address. `%%COURSEID%%` bakes the bound course id into the VIEW
     * so a course-scoped query filters to that course (the VIEW is static, so the id is fixed at
     * publish time).
     *
     * The date tokens let one saved query run on both MySQL/MariaDB and PostgreSQL without the
     * dialect-specific date functions the validator otherwise blocks: `%%NOW%%` → the current Unix
     * epoch (int), `%%TIMESTAMP(expr)%%` → `expr` (an epoch column) cast to a datetime/timestamp.
     *
     * @param string $sql
     * @param int $courseid Course id substituted for %%COURSEID%% (0 when site-wide / dry-run).
     * @return string
     */
    public static function resolve_placeholders(string $sql, int $courseid = 0): string {
        global $CFG, $DB;
        $sql = str_ireplace('%%WWWROOT%%', $CFG->wwwroot, $sql);
        $sql = str_ireplace('%%COURSEID%%', (string) $courseid, $sql);

        // Token %%COURSECONTEXT%% — the bound course's context *row* id (mdl_context.id), not the context
        // level (which is always CONTEXT_COURSE = 50). The id varies per course, so it cannot be
        // hard-coded; resolve it from the course scope. Site-wide queries (courseid 0) have no course
        // context, so the token resolves to 0 there (mirrors %%COURSEID%%; the form blocks publishing
        // a course-context query without a scope).
        if (stripos($sql, '%%COURSECONTEXT%%') !== false) {
            $contextid = $courseid > 0 ? \context_course::instance($courseid)->id : 0;
            $sql = str_ireplace('%%COURSECONTEXT%%', (string) $contextid, $sql);
        }

        // Tokens %%CONTEXT_*%% — Moodle context-level constants (e.g. %%CONTEXT_COURSE%% → 50). These read
        // far more clearly in SQL than the bare magic number when filtering mdl_context.contextlevel.
        // Distinct from %%COURSECONTEXT%% above, which resolves to a specific context *row* id; these
        // are the fixed level constants and need no course scope.
        foreach (self::context_level_tokens() as $token => $level) {
            $sql = str_ireplace($token, (string) $level, $sql);
        }

        // Token %%NOW%% — current Unix time, expanded to the dialect of the live database.
        $postgres = $DB->get_dbfamily() === 'postgres';
        $sql = str_ireplace('%%NOW%%', $postgres ? 'EXTRACT(EPOCH FROM now())::int' : 'UNIX_TIMESTAMP()', $sql);

        // Token %%EPOCH(datetime)%% — a datetime literal/expression → Unix epoch int, in the live dialect.
        // String literals get Postgres's explicit TIMESTAMP cast so the value reads as a datetime;
        // other expressions are wrapped in parens to preserve precedence. (Use %%NOW%% for "now".)
        $sql = preg_replace_callback(
            '/%%EPOCH\(\s*(.+?)\s*\)%%/i',
            static function (array $m) use ($postgres): string {
                $arg = $m[1];
                if (!$postgres) {
                    return "UNIX_TIMESTAMP({$arg})";
                }
                if (preg_match("/^'(?:[^']|'')*'$/", $arg)) {
                    return "EXTRACT(EPOCH FROM TIMESTAMP {$arg})::int";
                }
                return "EXTRACT(EPOCH FROM ({$arg}))::int";
            },
            $sql
        ) ?? $sql;

        // Token %%GROUP_CONCAT([DISTINCT ]expr[, sep])%% — aggregate a column into a delimited string, in
        // the live dialect: MySQL/MariaDB GROUP_CONCAT([DISTINCT ]expr SEPARATOR sep), Postgres
        // string_agg([DISTINCT ](expr)::text, sep). The optional leading DISTINCT keyword sits in the same
        // position for both engines. The separator is an optional single-quoted literal (may itself contain
        // a comma); it defaults to ',' to match MySQL's native default. Postgres string_agg requires text
        // input, so expr is cast to text (harmless on already-text columns); MySQL coerces implicitly. Pure
        // SQL rewrite — the result introspects as plain text, so no columnsmeta / display callback.
        $sql = preg_replace_callback(
            '/%%GROUP_CONCAT\(\s*(DISTINCT\s+)?(' . self::TOKEN_EXPR . ')\s*(?:,\s*(\'(?:[^\']|\'\')*\'))?\s*\)%%/i',
            static function (array $m) use ($postgres): string {
                $distinct = $m[1] ?? '';
                $expr     = $m[2];
                $sep      = ($m[3] ?? '') !== '' ? $m[3] : "','";
                return $postgres
                    ? "string_agg({$distinct}({$expr})::text, {$sep})"
                    : "GROUP_CONCAT({$distinct}{$expr} SEPARATOR {$sep})";
            },
            $sql
        ) ?? $sql;

        // Token %%TIMESTAMP(expr[, format])%% — emit the *raw epoch* expression (no DB date function, so
        // the column stays an integer that sorts chronologically). The publish path types it as a
        // timestamp and applies the optional display format as a Report Builder callback; see
        // self::timestamp_columns(). The format argument is therefore dropped from the SQL here.
        $sql = preg_replace_callback(
            '/%%TIMESTAMP\(\s*(' . self::TOKEN_EXPR . ')\s*(?:,[^)]*)?\)%%/i',
            static fn(array $m): string => '(' . $m[1] . ')',
            $sql
        ) ?? $sql;

        // Token %%CASE(expr, mode)%% — emit the *raw text* expression. The column keeps its original
        // value (so it sorts and filters on the untransformed text); the requested case (upper /
        // lower / title / sentence) is applied per-viewer as a Report Builder display callback. The
        // mode argument is therefore dropped from the SQL here; see self::case_columns().
        $sql = preg_replace_callback(
            '/%%CASE\(\s*(' . self::TOKEN_EXPR . ')\s*(?:,[^)]*)?\)%%/i',
            static fn(array $m): string => '(' . $m[1] . ')',
            $sql
        ) ?? $sql;

        // Token %%LINK(display[, keycol], 'path')%% — emit the *raw* display expression. The column
        // keeps its original value (so it sorts and filters on the untransformed value); the
        // site-relative link target is applied per-viewer as a Report Builder display callback that
        // wraps the value in an <a href>. Both the optional key-column argument (a bare identifier
        // naming another output column that fills the path's {} slot) and the path literal are
        // dropped from the SQL here; see self::link_columns().
        $sql = preg_replace_callback(
            '/%%LINK\(\s*(' . self::TOKEN_EXPR . ')\s*(?:,\s*[A-Za-z_][A-Za-z0-9_]*\s*)?,'
                . '\s*\'(?:[^\']|\'\')*\'\s*\)%%/i',
            static fn(array $m): string => '(' . $m[1] . ')',
            $sql
        ) ?? $sql;

        return preg_replace_callback(
            '/\{([a-z0-9_]+)\}/i',
            static fn(array $m): string => $CFG->prefix . $m[1],
            $sql
        ) ?? $sql;
    }

    /**
     * Find the output columns produced by `%%TIMESTAMP(expr[, format])%%` tokens in a saved query,
     * mapping each to its requested display format.
     *
     * Used at publish time to type these columns as timestamps (the resolved SQL emits a bare epoch
     * integer, which would otherwise introspect as an int) and to carry the optional format into
     * `columnsmeta` so the Report Builder entity can render it via a callback while still sorting on
     * the underlying epoch.
     *
     * The output column name is the alias when present — both `… AS foo` and the implicit `… foo`
     * form (SQL lets the `AS` keyword be omitted) — otherwise the trailing identifier of a simple
     * `a.b` / `b` expression. Tokens whose expression is too complex to name without an alias
     * (e.g. `timecreated + 3600` with no alias) are skipped — they cannot be matched to an
     * introspected column anyway.
     *
     * @param string $sql Raw saved SQL (before placeholder resolution).
     * @return array<string, string> Lower-cased output column name => neutral format ('' if none).
     */
    public static function timestamp_columns(string $sql): array {
        $pattern = '/%%TIMESTAMP\(\s*(' . self::TOKEN_EXPR . ')\s*(?:,\s*([^)]*?)\s*)?\)%%'
            . self::ALIAS_SUFFIX . '/i';
        if (!preg_match_all($pattern, $sql, $matches, PREG_SET_ORDER)) {
            return [];
        }
        $columns = [];
        foreach ($matches as $m) {
            $expr   = $m[1];
            $format = isset($m[2]) ? trim($m[2]) : '';
            $alias  = $m[4] ?? '';
            if ($alias !== '') {
                $name = $alias;
            } else if (preg_match('/([A-Za-z0-9_]+)\s*$/', $expr, $im)) {
                // Bare column expression — name the view column after its trailing identifier.
                $name = $im[1];
            } else {
                continue;
            }
            $columns[strtolower($name)] = $format;
        }
        return $columns;
    }

    /** Case modes a %%CASE()%% token may request. */
    private const CASE_MODES = ['upper', 'lower', 'title', 'sentence'];

    /**
     * Regex fragment matching a token's `expr` argument. A run of characters that are not
     * a paren, comma or %, plus single-level balanced parens so nested calls survive — e.g.
     * %%TIMESTAMP(UNIX_TIMESTAMP() - 86400)%%. Stops at the top-level comma (the optional
     * format / mode separator) and at the closing `)%%`. `%` is excluded because expr may
     * not contain it (the validator's token scan stops at %).
     */
    private const TOKEN_EXPR = '(?:[^(),%]|\([^()%]*\))+?';

    /**
     * Regex fragment matching an optional output-column alias directly after a `…)%%` token, used to
     * name the view column a token produces. Matches both the explicit `AS foo` form and the implicit
     * `foo` form (SQL lets a select item be aliased with the `AS` keyword omitted), with an optional
     * quote/backtick wrapper. A negative lookahead skips a following clause keyword (e.g. `FROM`) so a
     * token with no alias does not swallow the next word as its alias.
     *
     * Assumes exactly two capture groups precede it in the full pattern, so the alias name is group 4
     * (`$m[4]`) and its opening quote — needed for the closing-quote backreference `\3` — is group 3.
     */
    private const ALIAS_SUFFIX = '(?:\s+(?:AS\s+)?(?!(?:FROM|WHERE|GROUP|ORDER|HAVING|LIMIT|UNION)\b)'
        // phpcs:ignore moodle.Strings.ForbiddenStrings.Found
        . '(["`]?)([A-Za-z0-9_]+)\3)?';

    /**
     * Same as {@see self::ALIAS_SUFFIX} but assumes exactly **three** capture groups precede it, so the
     * opening-quote group is 4 (`\4`) and the alias name is group 5 (`$m[5]`). Used by the 3-argument
     * `%%LINK(display, keycol, 'path')%%` form, which captures one more group (the key column) than the
     * two-group tokens (CASE/2-arg LINK) that use ALIAS_SUFFIX.
     */
    private const ALIAS_SUFFIX_G3 = '(?:\s+(?:AS\s+)?(?!(?:FROM|WHERE|GROUP|ORDER|HAVING|LIMIT|UNION)\b)'
        // phpcs:ignore moodle.Strings.ForbiddenStrings.Found
        . '(["`]?)([A-Za-z0-9_]+)\4)?';

    /**
     * Find the output columns produced by `%%CASE(expr, mode)%%` tokens in a saved query, mapping
     * each to its requested case mode.
     *
     * Mirrors {@see self::timestamp_columns()}: the resolved SQL emits the bare text expression, so
     * the transform is recorded in `columnsmeta` and applied by the Report Builder entity as a
     * display callback (the stored value stays the original text, so sort/filter act on it). The
     * output column name is the alias when present (`… AS foo` or the implicit `… foo` form), else
     * the trailing identifier of a simple `a.b` / `b` expression; tokens too complex to name without
     * an alias are skipped. An unknown or
     * missing mode is ignored, leaving the column as plain text.
     *
     * @param string $sql Raw saved SQL (before placeholder resolution).
     * @return array<string, string> Lower-cased output column name => case mode.
     */
    public static function case_columns(string $sql): array {
        $pattern = '/%%CASE\(\s*(' . self::TOKEN_EXPR . ')\s*,\s*([A-Za-z]+)\s*\)%%'
            . self::ALIAS_SUFFIX . '/i';
        if (!preg_match_all($pattern, $sql, $matches, PREG_SET_ORDER)) {
            return [];
        }
        $columns = [];
        foreach ($matches as $m) {
            $expr  = $m[1];
            $mode  = strtolower($m[2]);
            $alias = $m[4] ?? '';
            if (!in_array($mode, self::CASE_MODES, true)) {
                continue;
            }
            if ($alias !== '') {
                $name = $alias;
            } else if (preg_match('/([A-Za-z0-9_]+)\s*$/', $expr, $im)) {
                $name = $im[1];
            } else {
                continue;
            }
            $columns[strtolower($name)] = $mode;
        }
        return $columns;
    }

    /**
     * Find the output columns produced by `%%LINK(expr, 'path')%%` tokens in a saved query, mapping
     * each to its site-relative link path.
     *
     * Mirrors {@see self::case_columns()}: the resolved SQL emits the bare expression, so the link
     * target is recorded in `columnsmeta` and applied by the Report Builder entity as a display
     * callback (the stored value stays the original, so sort/filter act on it). The output column
     * name is the alias when present (`… AS foo` or the implicit `… foo` form), else the trailing
     * identifier of a simple `a.b` / `b` expression; tokens too complex to name without an alias are
     * skipped.
     *
     * The path must be **site-relative** — it has to start with `/` and must not contain a scheme
     * (`://`). Absolute/external targets are rejected (skipped, leaving the column a plain value) so
     * a link can never point off-site: the callback wraps every path in a {@see \moodle_url}, which
     * prefixes the site address, closing off open-redirect / phishing. A literal `{}` in the path is
     * the substitution slot for the (url-encoded) cell value; a path with no slot links every row to
     * the same page.
     *
     * The optional **key column** — `%%LINK(display, keycol, 'path')%%` — names another output column
     * whose value fills the `{}` slot, so the visible column can show one thing (e.g. a full name) while
     * the link keys on another (e.g. the id): `%%LINK(CONCAT(u.firstname,' ',u.lastname), userid,
     * '/user/view.php?id={}')%%`. Without it the display value fills `{}` (the two-argument form).
     *
     * @param string $sql Raw saved SQL (before placeholder resolution).
     * @return array<string, array{path: string, keycol: ?string}> Lower-cased output column name =>
     *     site-relative path and the optional lower-cased key-column name (null = fill {} from own value).
     */
    public static function link_columns(string $sql): array {
        if (!preg_match_all(self::link_pattern(), $sql, $matches, PREG_SET_ORDER)) {
            return [];
        }
        $columns = [];
        foreach ($matches as $m) {
            $expr   = $m[1];
            $keycol = $m[2] ?? '';
            $path   = str_replace("''", "'", $m[3]);
            $alias  = $m[5] ?? '';
            // Site-relative only: must start with '/' and carry no scheme.
            if (!preg_match('#^/#', $path) || strpos($path, '://') !== false) {
                continue;
            }
            if ($alias !== '') {
                $name = $alias;
            } else if (preg_match('/([A-Za-z0-9_]+)\s*$/', $expr, $im)) {
                $name = $im[1];
            } else {
                continue;
            }
            $columns[strtolower($name)] = [
                'path'   => $path,
                'keycol' => $keycol !== '' ? strtolower($keycol) : null,
            ];
        }
        return $columns;
    }

    /**
     * The `%%LINK(display[, keycol], 'path')%%` match pattern, shared by {@see self::link_columns()}
     * and {@see self::link_token_problems()} so the accept and explain paths can never disagree about
     * what a token looks like.
     *
     * Capture groups: 1 = display expression, 2 = optional key column, 3 = path literal,
     * 4 = the alias's opening quote (for the backreference), 5 = the alias name.
     *
     * @return string
     */
    private static function link_pattern(): string {
        return '/%%LINK\(\s*(' . self::TOKEN_EXPR . ')\s*(?:,\s*([A-Za-z_][A-Za-z0-9_]*)\s*)?,'
            . '\s*\'((?:[^\']|\'\')*)\'\s*\)%%' . self::ALIAS_SUFFIX_G3 . '/i';
    }

    /**
     * Explain each `%%LINK()%%` token that {@see self::link_columns()} will skip.
     *
     * A skipped token is not an error — the query still publishes and the column still shows its
     * value — but it silently loses its link, which looks like the token being broken. This reports
     * the two reasons a token is dropped so the editor can warn the author instead:
     *
     *  - `offsite` — the path is not site-relative (no leading `/`, or it carries a `://` scheme).
     *    Rejected so a report cell can never link off-site; the value is the offending path.
     *  - `unnamed` — the output column cannot be named: no alias, and the display expression has no
     *    trailing identifier to fall back on. The value is the display expression.
     *
     * @param string $sql Raw saved SQL (before placeholder resolution).
     * @return array<int, array{reason: string, value: string}> One entry per skipped token, in
     *     source order; empty when every token resolves to a link.
     */
    public static function link_token_problems(string $sql): array {
        if (!preg_match_all(self::link_pattern(), $sql, $matches, PREG_SET_ORDER)) {
            return [];
        }
        $problems = [];
        foreach ($matches as $m) {
            $expr  = $m[1];
            $path  = str_replace("''", "'", $m[3]);
            $alias = $m[5] ?? '';
            if (!preg_match('#^/#', $path) || strpos($path, '://') !== false) {
                $problems[] = ['reason' => 'offsite', 'value' => $path];
            } else if ($alias === '' && !preg_match('/([A-Za-z0-9_]+)\s*$/', $expr)) {
                $problems[] = ['reason' => 'unnamed', 'value' => trim($expr)];
            }
        }
        return $problems;
    }

    /**
     * Inspect the view's columns, post-filtering sensitive column names according to the admin
     * denylist.
     *
     * On the Postgres family this cannot use {@see \moodle_database::get_columns()}: Moodle core's
     * pgsql implementation is hard-filtered to `relkind = 'r'` (ordinary tables), so it returns
     * nothing for a VIEW. We introspect `information_schema.columns` instead, which lists view
     * columns on both Postgres and MySQL. Other families keep the native get_columns() path.
     *
     * The returned objects expose a `meta_type` property so callers can map types uniformly.
     *
     * @param string $viewname View name without the Moodle prefix.
     * @return array<string, object> Keyed by column name; each value has a `meta_type` property.
     */
    public static function columns(string $viewname): array {
        global $DB;
        if ($DB->get_dbfamily() === 'postgres') {
            $columns = self::pg_view_columns($viewname);
        } else {
            // MySQL/MariaDB fold result-set column aliases to lowercase, but Report Builder derives
            // each column's SQL alias from the (case-preserving) column name. A mixed-case name such
            // as `Course_Shortname` therefore yields a select alias `c1_Course_Shortname` that the
            // driver returns as `c1_course_shortname`, so RB's case-sensitive value lookup misses and
            // the column renders blank. Lowercasing the keys keeps the alias and the returned name in
            // sync; the unquoted field reference still resolves because MySQL column names are
            // case-insensitive. (Postgres identifiers are case-sensitive, so its path is left intact.)
            $columns = [];
            foreach ($DB->get_columns($viewname, false) as $name => $info) {
                $columns[strtolower($name)] = $info;
            }
        }
        $deny = self::denylist();
        if ($deny) {
            $columns = array_filter(
                $columns,
                static fn(string $name): bool => !in_array(strtolower($name), $deny, true),
                ARRAY_FILTER_USE_KEY
            );
        }
        return $columns;
    }

    /**
     * Return the first view column name that is not a plain SQL identifier, or null if all are valid.
     *
     * An unaliased expression such as `SELECT count(*) ...` becomes a VIEW column named `count(*)`,
     * which Report Builder cannot reference (it derives its select alias from the column name and
     * rejects anything that isn't `\w+` with "Complex columns must have an alias"). Detecting it here
     * lets callers surface a clear "add an AS alias" message instead of a raw coding_exception.
     *
     * @param array $columns Column map as returned by {@see self::columns()}.
     * @return string|null Offending column name, or null when every name is a valid identifier.
     */
    public static function first_unaliased_column(array $columns): ?string {
        foreach (array_keys($columns) as $name) {
            if (!preg_match('/^\w+$/', (string) $name)) {
                return (string) $name;
            }
        }
        return null;
    }

    /**
     * Introspect a VIEW's columns on Postgres via information_schema.
     *
     * @param string $viewname View name without the Moodle prefix.
     * @return array<string, object> Keyed by column name; each value has a `meta_type` property.
     */
    private static function pg_view_columns(string $viewname): array {
        global $DB, $CFG;
        $fullname = $CFG->prefix . $viewname;
        $sql = "SELECT column_name, data_type
                  FROM information_schema.columns
                 WHERE table_schema = current_schema()
                   AND table_name = ?
              ORDER BY ordinal_position";
        $rows = $DB->get_records_sql($sql, [$fullname]);
        $columns = [];
        foreach ($rows as $row) {
            $columns[$row->column_name] = (object) ['meta_type' => self::pg_meta_type($row->data_type)];
        }
        return $columns;
    }

    /**
     * Map a Postgres information_schema `data_type` to a Moodle meta_type char, mirroring the codes
     * {@see \report_sql\local\query::map_db_type()} consumes.
     *
     * @param string $datatype
     * @return string One of Moodle's meta_type chars: I, N, L, X.
     */
    private static function pg_meta_type(string $datatype): string {
        return match (strtolower($datatype)) {
            'smallint', 'integer', 'bigint' => 'I',
            'numeric', 'decimal', 'real', 'double precision' => 'N',
            'boolean' => 'L',
            default => 'X',
        };
    }

    /**
     * Return lowercased denylist of sensitive column names.
     *
     * @return string[]
     */
    private static function denylist(): array {
        $raw = (string) get_config('report_sql', 'denycolumns');
        if ($raw === '') {
            return [];
        }
        $items = preg_split('/[\s,]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return array_map('strtolower', $items);
    }
}
