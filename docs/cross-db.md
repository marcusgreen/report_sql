# Cross-database support (MySQL/MariaDB and PostgreSQL)

`report_sql` is designed so that a single saved query runs unchanged on both
MySQL/MariaDB and PostgreSQL. Every place where the two engines differ in
SQL dialect is routed through `$DB->get_dbfamily()`, so authors write one
portable form and the plugin emits the engine-specific spelling at runtime.

## Placeholder tokens

Tokens are substituted in `view::resolve_placeholders()` (the single
substitution point used by both publish and the live AJAX check). The
following tokens exist specifically to paper over dialect differences:

| Token | MySQL/MariaDB | PostgreSQL |
|---|---|---|
| `%%NOW%%` | `UNIX_TIMESTAMP()` | `EXTRACT(EPOCH FROM now())::int` |
| `%%EPOCH(datetime)%%` | `UNIX_TIMESTAMP(arg)` | `EXTRACT(EPOCH FROM <arg>)::int` |
| `%%GROUP_CONCAT([DISTINCT ]expr[, 'sep'])%%` | `GROUP_CONCAT([DISTINCT ]expr SEPARATOR 'sep')` | `string_agg([DISTINCT ](expr)::text, 'sep')` |

Notes:

- `%%EPOCH%%` gives string literals an explicit Postgres `TIMESTAMP` cast, e.g.
  `%%EPOCH('2015-01-01 00:00:00')%%` → `EXTRACT(EPOCH FROM TIMESTAMP '2015-01-01 00:00:00')::int`.
  Other expressions are wrapped in parens. Use `%%NOW%%`, not `%%EPOCH%%`,
  for the current time.
- `%%GROUP_CONCAT%%` is a pure SQL rewrite (no display callback). The
  separator is an optional single-quoted literal (default `','`, matching
  MySQL's native default). Postgres `string_agg` requires text input, so
  `expr` is cast `::text` (harmless when already text); MySQL coerces
  implicitly. Use inside a query with a `GROUP BY`.
- Native `UNIX_TIMESTAMP()` written directly is **not** rewritten — it stays
  on the validator's MySQL-date-function warn list to steer authors to the
  token.

## Display-layer transforms

Two tokens resolve to a bare, portable expression and do the engine-specific
work in PHP at display time rather than in SQL. This keeps the VIEW portable
and lets the column sort/filter on the original value.

- **`%%TIMESTAMP(expr[, format])%%`** resolves to the bare epoch expression
  `(expr)` — no DB date function at all. Date typing and formatting are
  applied later from `columnsmeta` via a `userdate()` callback. Because the
  stored value is a raw epoch int, the column sorts chronologically while
  displaying a formatted string.
- **`%%CASE(expr, mode)%%`** resolves to the bare text expression `(expr)`;
  the case change happens in a PHP display callback. This matters for cross-DB
  support because `title` maps to Postgres-only `INITCAP` (MySQL has no
  equivalent) and `sentence` has no native function on either engine — the
  PHP callback is the only cross-engine path for those two modes. (`upper`/
  `lower` are portable in SQL anyway; the token is still used for them so the
  column sorts/filters on the untransformed value.)

## Type introspection

After creating the VIEW, `view::columns()` calls `$DB->get_columns()` and
`query::map_db_type()` maps the introspected Moodle meta_type char
(`R/I→int`, `N/D→float`, `L→bool`, `T→timestamp`, else `text`). This is
DB-agnostic — the plugin never parses engine-specific column types.

## Bundled samples

Every sample in `samples/samples.json` uses the `%%TIMESTAMP()%%` / `%%NOW%%`
tokens rather than dialect-specific date functions, so all of them import and
publish on both MySQL/MariaDB and PostgreSQL.

## Legacy-report importers

The Configurable Reports and Ad-hoc DB Queries importers share
`import_helper::rewrite_date_functions()`, which is **DB-family aware**.
After rewriting what it can to portable tokens, any leftover MySQL-only date
function (`DATEDIFF`, `DATE_ADD/SUB`, `STR_TO_DATE`, un-mappable
`FROM_UNIXTIME` formats, …) is:

- **kept** on a MySQL/MariaDB install (`$DB->get_dbfamily() === 'mysql'`) —
  it runs natively and the live `dry_run()` is the real gate; but
- a **fatal reject** on PostgreSQL, where there is no equivalent.

So a `DATEDIFF` report imports on MySQL and is rejected on Postgres.

## Server-side chart SVG

`classes/local/chart_svg.php` is a dependency-free SVG builder with no
external references, so the chart report renders identically regardless of DB
engine (it operates on the fetched dataset, not on SQL). Not a dialect
concern, but part of why the whole pipeline is portable.

## What breaks going from MySQL to PostgreSQL

The tokens above cover the paths the plugin *owns*. Hand-written SQL can still
use MySQL-only syntax that runs on MySQL and fails (or silently misbehaves) on
PostgreSQL. The common trap is authoring and validating a query on a MySQL
site, exporting it, then importing on a PostgreSQL site — the query only
breaks at publish time on the PG site.

The plugin's live check (`external/validate_sql.php`) and the Test-query
advisory (`analyser::analyse()`) both run a real `dry_run()` and
`CREATE VIEW`, so they **do** catch every hard error below — but only when
run against a PostgreSQL connection. Only the MySQL date functions get an
early PostgreSQL-specific *warning* (`validator::MYSQL_DATE_FUNCTIONS`); the
rest surface as raw DB errors at publish on PG.

### Hard errors (the query fails to execute on PostgreSQL)

| MySQL construct | PostgreSQL result | Guarded? |
|---|---|---|
| Loose `GROUP BY` (select a non-aggregated column not in `GROUP BY`) | `column must appear in the GROUP BY clause` | no |
| Implicit coercion — `varchar = 3`, `text + int`, string↔date compares | `operator does not exist: character varying = integer` | no |
| Boolean context — `WHERE intcol`, `WHERE 1`, `AND intcol` | `argument of WHERE must be type boolean` | no |
| `IFNULL(a, b)` | no such function → use `COALESCE` | no |
| `IF(cond, a, b)` | no such function → use `CASE WHEN` | no |
| Native `GROUP_CONCAT(...)` (not the `%%GROUP_CONCAT%%` token) | no such function → use the token | no |
| Native date funcs: `DATE_FORMAT`, `FROM_UNIXTIME`, `STR_TO_DATE`, `DATEDIFF`, `DATE_ADD/SUB`, `UNIX_TIMESTAMP(arg)` | no such function → use `%%TIMESTAMP%%`/`%%EPOCH%%`/`%%NOW%%` | warning only (advisory, not blocked) |
| Double-quoted string literal `x = "foo"` | `"foo"` is parsed as an identifier → `column "foo" does not exist` | on import only |
| Backtick-quoted identifier `` `col` `` | syntax error (PG uses double quotes for identifiers) | no |
| `LIMIT x, y` (offset-comma form) | syntax error → use `LIMIT y OFFSET x` | no |
| `CAST(x AS UNSIGNED/SIGNED)` | no `UNSIGNED` type → use `::int` / `CAST(x AS integer)` | no |
| `RAND()`, `CURDATE()`, `YEAR()/MONTH()/DAY()`, `SUBSTRING_INDEX(...)` | no such function | no |
| `#` line comment | not a comment in PG (stripped before the plugin's scan, but reaches the view if present) | mostly stripped |

### Silent-wrong (runs on both, but returns a different answer — the worst kind)

| MySQL | MySQL behaviour | PostgreSQL behaviour |
|---|---|---|
| `5 / 2` integer division | `2.5` (float result) | `2` (integer truncation) |
| `a \|\| b` | logical OR | string concatenation |
| `col LIKE 'foo'` | case-insensitive (default collation) | case-sensitive (use `ILIKE` or `LOWER()`) |
| `a DIV b` | integer division | error (no `DIV` operator) |

### Top three real risks

For typical Moodle report SQL the most frequent breakers are **loose
`GROUP BY`**, **implicit type coercion**, and **boolean context** — none are
caught by static validation; all fail only at PostgreSQL runtime. (Modern
MySQL 5.7+ enables `ONLY_FULL_GROUP_BY` by default, so the `GROUP BY` case is
rarer on new installs but still common in legacy/imported SQL.)
