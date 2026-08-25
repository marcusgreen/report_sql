# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this plugin does

`report_sql` lets a Moodle author write a SQL `SELECT` query, click **Publish**, and get a fully-configurable Moodle Report Builder report — no PHP required. Publishing creates a MySQL VIEW backed by the SQL, introspects its columns, and registers a Report Builder datasource pointing at that view.

## Commands

### Tests
```bash
# Run the plugin's PHPUnit suite from the Moodle root
cd /var/www/mdl52/public
vendor/bin/phpunit --filter report_sql report/sql/tests/

# Run a single test class
vendor/bin/phpunit report/sql/tests/sql_validator_test.php

# Run one test method
vendor/bin/phpunit --filter test_invalid report/sql/tests/sql_validator_test.php
```

### JS build
```bash
# From the Moodle root — compiles amd/src/editor.es6.js → amd/build/editor.min.js
grunt amd --root=report/sql
```

### Upgrade / install
After changing `db/install.xml` or adding `db/upgrade.php` steps, run:
```
Site admin → Notifications
```
or via CLI: `php admin/cli/upgrade.php`

## Architecture

### Publish lifecycle

The central flow lives in `classes/local/query.php → query::publish()`:

1. `validator::validate()` — static denylist + keyword check (no live DB)
2. `view::create_or_replace()` — issues `CREATE OR REPLACE VIEW mdl_report_sql_v_<id> AS <sql>`
3. `view::columns()` — calls `$DB->get_columns()` on the new view, strips denylist columns
4. `reporthelper::create_report()` — creates a `reportbuilder_report` row with source `adhoc_query`
5. `set_config('queryid_for_report_<reportid>', $queryid)` — the binding key (see below)
6. `adhoc_query::add_default_columns/filters()` — hydrates RB defaults using the bound query
7. `apply_report_visibility()` — sets the report's RB **context** and **audience** from the query's scope/visibility (see below)
8. `create_chart_report()` / `delete_chart_report()` — when `chartmeta.type` is a real chart, create (or reuse) the companion single-cell **chart report**; when it is `none`, drop it (see [Chart report](#chart-report-graph-rendered-through-report-builder))

Unpublish / SQL edit reverses steps 2-6 via `query::tear_down()` (which deletes every bound report — data **and** chart — cascading their audiences).

### Report Builder binding

The datasource class `classes/reportbuilder/source/adhoc_query.php` is placed **outside** the `reportbuilder\datasource` namespace on purpose — Moodle's auto-discovery would otherwise surface it in the "new report" UI. Data reports on this datasource are created exclusively by `query::publish()` (and `query::create_additional_report()`), never through the RB "new report" UI.

A single query can own **several** RB reports: `publish()` creates the primary one, and `create_additional_report()` creates extras. Each gets its own `queryid_for_report_<rid>` config binding, so the query→reports mapping is one-to-many. `query::bound_report_ids($queryid)` recovers every report id for a query by scanning those config keys (they are the source of truth, not a column on the query record). `tear_down()` and `on_course_deleted()` both iterate `bound_report_ids()` so no report is orphaned.

Separately, the plugin's own **query listing** page (`index.php`) is itself an RB system report — `classes/reportbuilder/local/systemreports/queries.php` with entity `classes/reportbuilder/local/entities/query.php`. That is a `system_report` (built via `system_report_factory::create`), distinct from the per-query *data* reports above.

The datasource resolves its VIEW at runtime via:
```php
$queryid = get_config('report_sql', 'queryid_for_report_' . $reportid);
```

If that config key is absent the datasource falls back to a placeholder (single dummy column) so RB validation doesn't crash.

Column/filter objects are built dynamically in `classes/reportbuilder/local/entities/adhoc_view.php` from `columnsmeta` — a JSON blob cached on the query record at publish time. `query::map_db_type()` maps the introspected Moodle meta_type char: `R/I→int`, `N/D→float`, `L→bool`, `T→timestamp`, everything else `→text`.

**Timestamp columns** (`%%TIMESTAMP()%%`, see [SQL validation](#sql-validation--two-layers)) are *not* typed from introspection — the token resolves to a bare epoch integer, which would read back as `int`. Instead `query::publish()` calls `view::timestamp_columns()` on the saved SQL to recover which output columns came from `%%TIMESTAMP()%%` (keyed by `AS` alias, else the expression's trailing identifier) and their optional display format, and forces `columnsmeta` `type=timestamp` + `dateformat` for them. The entity then renders each timestamp column with a `userdate()` **callback** (`chart_presenter::strftime_format()` translates the neutral format e.g. `dd/mm/yyyy` → strftime `%d/%m/%Y`; empty → `%d-%b-%Y` i.e. `dd-mmm-yyyy`). Because the field stays a raw epoch, the column **sorts chronologically** while displaying the formatted string.

**Text-case columns** (`%%CASE(expr, mode)%%`) use the same recover-from-SQL mechanism as timestamps, but keep their introspected `text` type. `build_columnsmeta()` calls `view::case_columns()` (keyed the same way — `AS` alias, else trailing identifier) to recover the requested `mode` (`upper|lower|title|sentence`) and stashes it as `columnsmeta` `textcase`. The entity renders the column with a case-transform **callback** (`adhoc_view::apply_textcase()`): `upper`/`lower` via `\core_text`, `title` via `mb_convert_case(..., MB_CASE_TITLE)` (= Postgres `INITCAP`, which MySQL lacks), `sentence` = lower then upper-case first letter. The stored value is the **raw text** (the token resolves to `(expr)`), so the column still **sorts and filters on the original**, transform is display-only. Unknown/missing modes are ignored (column stays plain text).

**Enum (dropdown) filters.** A plain-text column with a small, finite set of values gets a **dropdown** filter (core RB `select`) instead of a free-text one. At publish, `build_columnsmeta()` (passed the live `$viewname`) calls `flag_enum_columns()`, which — when the `enumfilterthreshold` setting is non-zero — probes each plain-text column's `COUNT(DISTINCT …)` on the view and stashes `columnsmeta` `enum=true` when the distinct count is between 2 and the threshold (default 30). Timestamp columns and `%%CASE%%` text-transform columns are skipped. The entity's `build_filters()` swaps `text::class`→`select::class` for flagged columns and attaches a `set_options_callback()` (`enum_options_callback()`) that runs one `SELECT DISTINCT` on the view at **filter-render time** — so values added after publish still appear in the dropdown, and the column still filters on the raw value. Degrades to a free-text/empty-options filter on any probe error. Threshold 0 disables the feature.

### Chart report (graph rendered through Report Builder)

Besides the *data* report (the table), a query with a chart configured (`chartmeta.type` ≠ `none`) also gets a **chart report**: a Report Builder report whose **single row / single cell** is the whole graph rendered as an inline SVG image. This makes the chart a first-class RB object — schedulable, exportable, embeddable — alongside the older interactive `chart.php` / block Chart.js render.

- **Server-side SVG.** Moodle's `\core\chart_*` only serialise to JSON for client-side Chart.js — there is no server-side rasterizer. `classes/local/chart_svg.php` (`chart_svg::render($type, $labels, $values, $title)`) is a dependency-free SVG builder (bar / line / pie / doughnut) with **no external refs**, so it survives being wrapped in a `data:` URI. It XML-escapes every label/title, caps drawn x-axis labels (and line markers) to ~20 so a long series stays readable, and handles empty/negative/single-slice/ragged input. `chart_presenter::chart_series($rows, $xcol, $ycol)` is the shared `[labels, values]` extraction used by `chart_svg`, `chart.php`, and the block.

  The pure chart/format **display helpers** — `chart_series`, `chart_figure_html`, `format_textcase`, `strftime_format` (+ `DEFAULT_DATE_FORMAT`) — live in `classes/local/chart_presenter.php`, extracted from `query` so the saved-query entity no longer owns chart rendering. Record-bound accessors that decide *which* transform a column gets (`query::column_dateformat()`, `query::column_textcase()`) stay on `query` and delegate to `chart_presenter`.
- **Source + entity.** `classes/reportbuilder/source/chart_query.php` uses the plugin's **own query table** as its main table, base-conditioned to `id = :queryid` so the report has exactly **one row** (`1 = 0` when unbound). Its single-column entity `classes/reportbuilder/local/entities/chart_view.php` (entity name **`adhocchart`** — set explicitly in the constructor; the column uid is `adhocchart:chart` and must match `chart_query::get_default_columns()`, else `add_default_columns()` throws `invalid_parameter`) exposes one `TYPE_TEXT`, non-sortable `chart` column whose callback returns the `<img data:image/svg+xml;base64,…>` (RB does **not** escape column-callback output — the callback owns safety; an SVG inside an `<img>` cannot run script; mirrors core's user-picture column). The callback **ignores `$row`** and fetches the dataset itself via `query::fetch_rows_for_viewer()` — a column callback only sees one row, but a chart aggregates many, so this is how it aggregates while still applying the same per-viewer (per-user / teacher-course) row scoping as the data report. `chart_query` is kept **outside** the `reportbuilder\datasource` namespace, same as `adhoc_query`, to stay hidden from the RB source picker.
- **Binding & lifecycle.** The chart report is bound with the same `queryid_for_report_<rid>` config key as data reports, so `bound_report_ids()` / `tear_down()` / `on_course_deleted()` already sweep it. Its id is also **denormalised** onto the query record as `chartreportid` (single writer `store_chart_report_id()`), so it can be an RB base field / read without a config scan; `chart_report_id()` prefers that column and falls back to the config-scan for pre-`chartreportid` rows. `create_chart_report()` is idempotent (reuse by source class) and **heals** a report left column-less by adding defaults on the reuse path. `apply_report_visibility()` is called for the chart report too, so its context + audience stay in lockstep with the data report.
- **UI.** The system report's **View chart** kebab action links straight to `/reportbuilder/view.php?id=:chartreportid`; `chart.php` redirects its HTML view to the same report (CSV export still streams from `chart.php`), so on-screen there is one rendering path. The **Schedule** action targets the **chart** report for chart queries (so the emailed report is the graph) and the **data** report otherwise. Caveat: RB tabular export (CSV / Excel / PDF dataformat) strips the `<img>` to an empty cell — the chart report is an **HTML / on-screen** artifact, so scheduled emails must use an HTML format.

### Report visibility (who can open the report)

The plugin's `visible_to_current_user()` only gates the **plugin's own** index/run pages. The actual report data lives at `/reportbuilder/view.php?id=<reportid>`, gated by **core RB** `permission::can_view_report()`:

```
moodle/reportbuilder:view at report context  AND  (viewall  OR  can_edit  OR  user ∈ audience)
```

The RB context + audience logic lives in `classes/local/report_visibility.php` (extracted from `query`): `report_visibility::apply($record, $reportid)` (called from `query::publish()`, `save()`, `create_additional_report()` and `create_chart_report()`), plus `on_course_deleted()`, `build_audiencemeta()`, `explode_audiencemeta()` and the private `staff_role_ids()`. It reads a query record and drives the two core levers from existing query fields — no extra config:

- **Context** — `courseid > 0` places the report in that course context (so `reportbuilder:view` is evaluated there); site-wide queries stay at system context.
- **Audience** — driven by the `audiencemeta` JSON field on the query record (set from the edit form's Audience picker). `audiencemeta.type` is one of:
  - `auto` (the default) — derive from scope + visibility: `visible = 0` → **no audience** (owner + `reportbuilder:viewall` only); `courseid > 0` + visible → **course staff** (`courserole` for the teacher / non-editing teacher / manager archetypes, via `staff_role_ids()`), falling back to `courseparticipant` only if the site defines no staff roles; visible site-wide → `allusers`.
  - explicit picker choices: `allusers`, `courseparticipant`, `courserole` (`roles` from `audiencemeta`), `cohort` (`cohortmember`, `cohorts` from `audiencemeta`), or `none`.

  The `AUDIENCE_*` constants stay on `query` (the vocabulary); `report_visibility::apply()` reads `audiencemeta` and switches on `type`.

The method is **idempotent**: it deletes existing audiences for the report before re-adding, so re-publishing or toggling visibility never accumulates duplicates. These reports are created solely by this plugin, so wiping their audiences is safe.

Two of the audience classes are **custom** — core ships no "enrolled in / has a role in course X" audience — and both are generated programmatically only, never offered in the RB audience UI:
- `courseparticipant` (`classes/reportbuilder/audience/courseparticipant.php`) — active enrolments in a course; `configdata` = `['courseid' => int]`.
- `courserole` (`classes/reportbuilder/audience/courserole.php`) — users holding given roles in a course; `configdata` = `['courseid' => int, 'roles' => int[]]`.

  (`cohortmember` for the `cohort` choice is core's own audience, not custom.)

The edit form's **Audience** picker (`edit_query_form::add_audience_elements()`) always lists every audience type, including the course-scoped ones (Course participants / Users with a role in the course), and always builds the role picker — using the bound course context for role display names when a course is set, otherwise system context. The course-scoped options are no longer conditionally rendered on `courseid`, so changing the course scope no longer requires saving and reopening the form to reveal them. Choosing a course-scoped audience without a course is caught in `validation()` (`erraudiencecourse`) rather than hidden, since the selected course is only known at submit time.

### SQL validation — two layers

**Static** (`classes/local/sql/validator.php`):
- Strips comments and string literals before scanning so embedded `DROP` strings don't evade the denylist
- Enforces SELECT/WITH-only; blocks multi-statement; blocks a table denylist (`config`, `sessions`, etc.)
- `auto_brace()` wraps bare table names in `{}`—users don't need to type braces
- Rejects unknown `%%…%%` tokens via `is_supported_token()`. Supported tokens (`%%WWWROOT%%`, `%%COURSEID%%`, `%%COURSECONTEXT%%`, `%%NOW%%`, `%%CONTEXT_*%%` level constants via `view::context_level_tokens()`, `%%TIMESTAMP(expr[, format])%%`, `%%EPOCH(datetime)%%`, `%%CASE(expr, mode)%%`) are exempt because they are substituted later in `view::resolve_placeholders()`, not at validate time

**Placeholder substitution** (`view::resolve_placeholders()`, the single substitution point — used by both publish and the live AJAX check): `{table}`→prefixed name, `%%WWWROOT%%`→site URL, `%%COURSEID%%`→bound course id, `%%NOW%%`→current epoch int (`UNIX_TIMESTAMP()` on MySQL / `EXTRACT(EPOCH FROM now())::int` on Postgres, chosen by `$DB->get_dbfamily()`), and `%%TIMESTAMP(expr[, format])%%`→the **bare epoch expression** `(expr)` — no DB date function, so the column is portable and sorts chronologically; the date typing and `format` are applied later from `columnsmeta` (see [Report Builder binding](#report-builder-binding)). `expr` cannot contain `%` (the token scan stops at `%`).

`%%EPOCH(datetime)%%` resolves in the same `resolve_placeholders()` pass: a datetime literal/expression → Unix epoch int in the live dialect — `UNIX_TIMESTAMP(arg)` on MySQL, `EXTRACT(EPOCH FROM <arg>)::int` on Postgres. String literals get Postgres's explicit `TIMESTAMP` cast (`%%EPOCH('2015-01-01 00:00:00')%%` → `EXTRACT(EPOCH FROM TIMESTAMP '2015-01-01 00:00:00')::int`); other expressions are wrapped in parens. Native `UNIX_TIMESTAMP()` is **not** rewritten — it stays in the validator's `MYSQL_DATE_FUNCTIONS` warn list, so authors are steered to the token. (Use `%%NOW%%`, not `%%EPOCH%%`, for the current time.)

`%%CASE(expr, mode)%%` resolves in the same pass to the **bare text expression** `(expr)` — the `mode` is dropped from the SQL and re-applied at display time from `columnsmeta` `textcase` (see [Report Builder binding](#report-builder-binding)). `mode` is one of `upper|lower|title|sentence`; `expr` cannot contain `%`.

The display-layer transform (over an SQL-side `UPPER`/`INITCAP` rewrite) does **two** distinct jobs — note that raw `UPPER`/`LOWER` are cross-DB, so **portability is only half the reason**:

1. **Cross-DB portability — only for title/sentence.** `upper`/`lower` are portable in SQL anyway (`UPPER()`/`LOWER()` run on both MySQL and Postgres), so for those two modes the token adds nothing on the portability axis. But `title` and `sentence` have **no portable SQL** — `title` = Postgres-only `INITCAP` (MySQL lacks it), `sentence` = no native function on either DB — so the PHP callback is the only cross-engine path for them.
2. **Sort/filter on the original value — for all four modes, including upper/lower.** The token resolves to bare `(expr)`, so the stored/selected value stays the untransformed text and the RB column **sorts and filters on the original**; the case change is display-only. A raw `UPPER(col)` in the SQL would instead persist the transformed text into the column, so RB would sort/filter on the uppercased string. This job applies even where portability doesn't, which is why the token is used for `upper`/`lower` too.

**Live** (`classes/external/validate_sql.php` AJAX endpoint):
- First runs static validation, then `$DB->get_records_sql("... LIMIT 1")` to catch bad table/column names and row-dependent runtime errors (the single fetched row forces select-list expressions to be evaluated, e.g. `to_char()` on a bigint with a date mask)
- Then issues `CREATE OR REPLACE VIEW ... / DROP VIEW` to catch duplicate column names (a VIEW constraint that the dry-run misses)

The JS editor (`amd/src/editor.es6.js`) mirrors the static denylist client-side and calls the AJAX endpoint on form submit before allowing the form through.

**Advisory analysis** (`classes/external/test_query.php` → `analyser::analyse()` in `classes/local/sql/analyser.php`): the edit form's **Test query** button (`amd/src/test.js`) runs the SQL without saving and returns `{ok, error, rowcount, datecolumns, suggestions, warnings, indexinfo}` — advisory only, never a publish gate. After static validation, `analyse()` runs a live `dry_run()` (`SELECT * FROM (<sql>) rs_dryrun LIMIT 1`) before the advisory probes: a query that will not execute — syntax error, missing table/column, row-dependent select-list error — sets `ok=false` with the cleaned DB message (`validator::clean_error()`) and returns early, so those surface to the author instead of being swallowed as `rowcount=-1`. The remaining probes (row count, date columns, index/scan hints) still degrade silently on error. This mirrors the publish-gate live check in `external/validate_sql.php`, except Test query never issues a `CREATE VIEW`, so duplicate-output-column errors stay a publish-time discovery. `test.js` renders each `datecolumns` entry as a **click-to-wrap** control: `maskSql()`/`selectListRegion()`/`splitItems()`/`wrapTimestamp()` rewrite that column's select-list item in place, wrapping its expression in `%%TIMESTAMP(...)%%` (idempotent; returns null for `SELECT *`; a leading `DISTINCT` on the first item is kept outside the token).

`analyse()` takes an optional third arg `?string $viewname`. When a caller has **already built and executed** a live view for the exact SQL, it passes that view name and `analyse()` skips both its own `dry_run()` (the caller proved the SQL runs) and the throwaway `_chk` probe view in `date_columns()` (it introspects the supplied view instead). The **inline Preview** is the caller: `report_sql_output_fragment_preview()` builds its per-user preview view, renders the rows, then calls `analyser::analyse($sql, $courseid, $viewname)` via `report_sql_preview_summary()` and renders a row-count + performance-warning strip **above** the rendered table — so a preview doubles as a lightweight test off a single view build. Advisory only: a failed analysis pass degrades to just the rendered rows. Preview reuses the `checkrowcount` string; the click-to-wrap date UX stays exclusive to the Test button (the editor is not in scope in the read-only preview).

### Import / export & bundled samples

`classes/local/transfer.php` moves queries as portable JSON (`export()`/`parse()`/`import()`). Only portable fields travel (name, description, SQL, course scope, visibility, chart config); derived state is regenerated, so every import lands as a fresh **draft** owned by the importer and must be re-published. `import()` re-validates each SQL and demotes unknown courseids to site-wide.

The plugin ships sample report views in `samples/samples.json`, loadable two ways, both via `transfer`:
- **CLI** — `cli/import.php` (defaults to `report_sql.json` in the CWD).
- **Browse / post-install** — `samples.php` is a browsable picker (registered as the `report_sql_samples` admin external page, linked from `db/install.php`'s post-install notification, the settings page, and an index-page button). It renders `samples/samples.json` via `templates/samples_list.mustache` (JS `amd/src/samples.js` drives Select all / Select none). Two modes: **checkbox** (bulk import, disables any sample whose name already exists) and **single** (`?single=1`, radio/one-shot import that prefixes the draft name with `Sample:` so it never collides). `transfer::bundled_samples()` is the single read path — it parses the file and annotates each source with a stable `index` and a `duplicate` flag (name already exists). `count_samples()` counts them; `import_samples()` bulk-imports the non-duplicates and is idempotent across repeat clicks / reinstalls.

The shipped samples are cross-DB: date handling uses the `%%TIMESTAMP()%%` / `%%NOW%%` tokens rather than dialect-specific functions, so all of them import and publish on both MySQL/MariaDB and PostgreSQL.

### Legacy-report importers (Configurable Reports & Ad-hoc DB Queries)

Two admin pages migrate SQL reports from older plugins into RS drafts. Both share `classes/local/import_helper.php` (a **trait**) for the deterministic, AI-free SQL translation: double-quote → single-quote, MySQL date functions (`FROM_UNIXTIME`/`DATE_FORMAT`/`UNIX_TIMESTAMP`) → portable `%%TIMESTAMP%%`/`%%EPOCH%%`/`%%NOW%%` tokens, literal `?` in a string → `chr(63)`, plus `validator::validate()` and a live `dry_run()` (single-row fetch + CREATE/DROP VIEW). The only per-source step is `rewrite_tokens()`, which each importer defines and the trait's `convert()` calls via **late static binding** (`static::rewrite_tokens()`). Both feed accepted reports to `transfer::import()`, so they land as fresh drafts owned by the importer.

`rewrite_date_functions()` is **DB-family aware**: after rewriting what it can to portable tokens, any leftover MySQL-only date function (`DATEDIFF`, `DATE_ADD/SUB`, `STR_TO_DATE`, un-mappable `FROM_UNIXTIME` formats, …) is **kept** with a note on a MySQL/MariaDB install (`$DB->get_dbfamily() === 'mysql'`) — it runs natively and the live `dry_run()` is the real gate — but is a **fatal reject** on PostgreSQL etc. where there is no equivalent. So a `DATEDIFF` report imports on MySQL and is rejected on Postgres.

- **Configurable Reports** (`cr_import` → `import_cr.php`, table `block_configurable_reports`): decodes the serialised `components` blob; maps `%%STARTTIME/ENDTIME%%`→`0`/far-future, `%%DEBUG%%`→stripped; rejects `%%USERID%%`, `%%FILTER_*%%`, unknown tokens. Carries CR `courseid`/`visible`.
- **Ad-hoc DB Queries** (`customsql_import` → `import_customsql.php`, table `report_customsql_queries`): reads the plain `querysql` column (no blob); maps the customsql escape tokens `%%Q%%`/`%%C%%`/`%%S%%`→`?`/`:`/`;`; **rejects** interactive named `:param` placeholders (detected on `mask_strings()` output so colons in literals don't false-positive) and `%%USERID%%`. customsql has no course scope, so every draft lands site-wide (`courseid 0`, `visible 1`).

Both link from the settings page (`admin_setting_description` + a hidden `admin_externalpage`, inside `$hassiteconfig`) and reuse the shared `crimport:*` lang strings for conversion notes/reasons; page-level strings are `customsqlimport:*`.

### DB schema

One table — `report_sql_query`. Columns:
- `name`, `description`, `querysql` — the query and its metadata
- `ownerid` — author who created the query
- `status` (`draft|published|disabled`), `viewname`, `reportid` — publish state and RB binding
- `chartreportid` — denormalised id of the companion single-cell **chart report** (FK into `reportbuilder_report`; NULL when no chart configured — see [Chart report](#chart-report-graph-rendered-through-report-builder))
- `columnsmeta` (JSON, frozen at publish), `chartmeta` (JSON chart config), `audiencemeta` (JSON audience picker choice — see [Report visibility](#report-visibility-who-can-open-the-report))
- `courseid` (0 = site-wide), `visible`
- `useridcolumn`, `coursecolumn`, `pagecoursecolumn` — per-user / per-course filter column choices
- `timecreated`, `timemodified`

Auditing is done through Moodle's standard event log (`logstore_standard_log`), not a custom table. `classes/event/` defines five query-lifecycle events (`query_created`, `query_updated`, `query_published`, `query_unpublished`, `query_deleted`), all extending `query_event_base` (→ `\core\event\base`). They are raised at `context_system` with `objectid` = query id and the query name in `other['name']` (so delete descriptions still render after the record is gone), and are triggered from `classes/local/query.php`. Viewable at **Site admin → Reports → Logs**.

**Course deletion** is handled out of band: `db/events.php` subscribes `\core\event\course_deleted` → `observer::course_deleted` → `report_visibility::on_course_deleted()`, which degrades every query scoped to the deleted course back to site-wide (`courseid = 0`), forces its audience to `none` (re-widening to a site-wide audience would be a privilege escalation), and re-points any published reports to the system context — curing the otherwise-dangling `contextid`.

The `queryid_for_report_<id>` config entries in `config_plugins` are the foreign-key glue between RB reports and query records. They are cleaned up in `tear_down()`.

### Capability model

| Capability | Scope | Who |
|---|---|---|
| `author` | system | Write/save queries |
| `approve` | system | Publish/unpublish |
| `viewall` | system | See all queries |
| `view` | system or course | See published queries |
| `viewown` | course | Course-level teacher view |

`query::visible_to_current_user()` implements all five visibility rules — but only for the plugin's own pages. Who can open the generated RB report is enforced separately by core RB's context + audience, set at publish via `apply_report_visibility()` (see [Report visibility](#report-visibility-who-can-open-the-report)).

**Admin tree registration** (`settings.php`): the index `admin_externalpage` (under `reports`) is registered **outside** the `if ($hassiteconfig)` guard with cap `report/sql:author`, so the **Site administration → Reports → SQL Report** menu entry shows for the author role without `moodle/site:config`. The **samples** externalpage (`report_sql_samples`, hidden, under `reports`) is likewise registered outside the guard with cap `report/sql:author`, so any author can browse/import bundled samples. The settings page itself (denylist, AI toggle, etc.) and the admin-only externalpages (testview, createrole) stay **inside** the guard.

## Key constraints

- The plugin's capabilities gate the **plugin UI only**; the RB report viewer (`/reportbuilder/view.php`) is gated by core RB context + audience, set at publish from `courseid`/`visible`. A query hidden at the plugin level but published with a wide audience would still be reachable via RB — keep the two in sync through `apply_report_visibility()`

- DB user needs `CREATE VIEW` and `DROP` privileges — `privilege_check::probe()` tests this; run it from **Site admin → Reports → SQL Report → Run database view privilege test**
- `SELECT *` across joins fails at publish time (duplicate column names in VIEWs); the validator's live check catches this before saving
- The `adhoc_query` datasource class must stay in `classes/reportbuilder/source/` (not `classes/reportbuilder/datasource/`) to stay hidden from RB's source picker
- `columnsmeta` is frozen at publish time; editing SQL while published drops and rebuilds the view+report on next publish
