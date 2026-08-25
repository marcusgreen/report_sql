# Importing from Ad-hoc Database Queries (`report_customsql`)

This page describes, in detail, how **SQL Report** migrates queries from the
**Ad-hoc database queries** report (`report_customsql`) into SQL Report drafts.

For the short overview and the sibling **Configurable reports** importer, see
[Importing from other SQL report plugins](userdocs.md#importing-from-other-sql-report-plugins)
in the user docs.

## Where it is

**Site admin → Reports → SQL Report → Import from Ad-hoc Database Queries**
(`import_customsql.php`). The page only appears when `report_customsql` is
installed — i.e. its `report_customsql_queries` table exists. If the plugin is
absent you get a warning and a link back.

## What it reads

Every row of `report_customsql_queries`, ordered by display name. For each row
it takes the plain-text `querysql` column (customsql stores SQL as a plain
column — there is no serialised blob to decode, unlike Configurable reports) and
runs it through a deterministic, **AI-free** translation. Every transformation
is a fixed rule; anything the rules cannot map faithfully is **rejected** rather
than guessed.

## The translation pipeline

Each query is classified as **importable** or **rejected**:

1. **Shared rewrites** (via `import_helper`, same as the Configurable reports
   importer):
   - double-quote → single-quote string literals
   - MySQL date functions (`FROM_UNIXTIME` / `DATE_FORMAT` / `UNIX_TIMESTAMP`)
     → portable [`%%TIMESTAMP%%` / `%%EPOCH%%` / `%%NOW%%`](userdocs.md#placeholders) tokens
   - a literal `?` inside a string → `CHAR(63)` (so RS does not read it as a
     bound parameter)
2. **customsql-specific token rewrites** (`rewrite_tokens()`):
   - `%%STARTTIME%%` → `0` and `%%ENDTIME%%` → a far-future epoch
     (`2145938400`) — the neutral time-range bounds used when no report period
     is chosen
   - the customsql escape tokens `%%Q%%` → `?`, `%%C%%` → `:`, `%%S%%` → `;`
     (characters that cannot be typed literally in the customsql editor; they
     only occur inside string literals, so substituting the literal character is
     faithful)
   - `%%WWWROOT%%` and `%%COURSEID%%` are shared with SQL Report and kept as-is
3. **Static validation** (`validator::validate()`) — denylist, `SELECT`-only,
   single-statement, supported-token and literal-`?` checks.
4. **Live dry-run** — runs the translated SQL with a single-row fetch and a
   `CREATE` / `DROP VIEW`, catching bad/dropped tables, missing columns, dialect
   errors and duplicate output-column names.

A query that clears all four steps is **importable**. Any note produced along
the way (e.g. "token replaced", "date function converted") is shown against the
row so you can review what changed.

## What gets rejected

- **Interactive named `:param` placeholders** — customsql prompts the user for
  these at run time; Report Sources has no equivalent. Detected outside string
  literals (so embedded colons / Postgres casts do not false-positive) and
  rejected. Rebuild them as a **Report Builder filter** after importing.
- **`%%USERID%%`** (and `%%USERIDS%%`) — per-viewer values a static view cannot
  provide. Use an audience or a per-user filter column instead.
- **Any other unknown `%%…%%` token** — rejected and named.
- **MySQL-only date functions with no portable equivalent** (`DATEDIFF`,
  `DATE_ADD`/`SUB`, `STR_TO_DATE`, …) — kept on MySQL/MariaDB (they run
  natively; the live dry-run is the real gate) but a **fatal reject** on
  PostgreSQL, where there is no equivalent.
- Anything that fails static validation or the live dry-run (denylisted table,
  missing column, syntax error, duplicate output columns).

Each rejection prints its reason so you can port that SQL by hand.

## After import

`report_customsql` has **no per-course scope and no visibility flag**, so every
imported query lands **site-wide** (`courseid 0`, visible) as a fresh **draft
owned by you**. Nothing is published automatically.

Before publishing, set the
[course scope](userdocs.md#the-edit-form-field-by-field) and
[audience](userdocs.md#who-can-view-the-report-audiences) exactly as you would
for any other draft — the importer deliberately does not guess who should see a
migrated report.

## Re-runs are safe

Import is idempotent in intent: the page re-discovers and re-classifies on every
load and never trusts a submitted id blindly. Re-importing a query simply
creates another draft; nothing in `report_customsql` is modified or deleted.
