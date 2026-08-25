# report_sql — Developer documentation

This document explains how `report_sql` works internally, with particular focus on how
it connects a hand-written SQL `SELECT` to a fully-configurable **core Report Builder** report.
It is aimed at developers extending or maintaining the plugin, not at report authors (see
`docs/userdocs.md` for the end-user guide).

---

## 1. What the plugin does, in one sentence

An author writes a SQL `SELECT`, clicks **Publish**, and the plugin creates a database **VIEW**
from that SQL and registers a core Report Builder **report + datasource** pointing at the view —
no PHP required. The resulting report behaves like any other Report Builder report: sortable
columns, filters, conditions, audiences, card/download support, scheduling, etc.

The key idea: **the plugin never renders report data itself.** It only *manufactures* a standard
RB report and gets out of the way. Everything the user sees at `/reportbuilder/view.php?id=<id>`
is core Report Builder driving the plugin's datasource class.

---

## 2. Data model

Two pieces of persistent state:

| Store | Holds |
|---|---|
| `report_sql_query` table | The query record: `name`, `description`, `querysql`, `ownerid`, `status` (`draft\|published\|disabled`), `viewname`, `reportid`, `chartreportid` (companion chart report — see §4.4), `columnsmeta` (JSON), `chartmeta` (JSON), `audiencemeta` (JSON), `courseid` (0 = site-wide), `visible`, `useridcolumn` / `coursecolumn` / `pagecoursecolumn` (per-user / per-course filter columns), `timecreated`, `timemodified` |
| `config_plugins` rows | `queryid_for_report_<reportid> = <queryid>` — the binding between an RB report and the query that backs it. One-to-many: a query can own several reports (see `create_additional_report()` / `bound_report_ids()`), each with its own row |

There is **no** separate audit table. Lifecycle auditing is done through Moodle's standard event
log (`logstore_standard_log`) — see §8.

The `queryid_for_report_<id>` config entries are the foreign-key glue. They are the single source
of truth the datasource uses at runtime to find its VIEW, and they are cleaned up in `tear_down()`.

---

## 3. The publish lifecycle (the heart of the plugin)

All of this lives in `classes/local/query.php → query::publish()`.

```
publish()
 ├─ view::create_or_replace($id, $sql, $courseid)   → CREATE OR REPLACE VIEW mdl_report_sql_v_<id>
 ├─ view::columns($viewname)                          → introspect the new VIEW's columns
 ├─ view::timestamp_columns($sql)                     → recover %%TIMESTAMP()%% columns + formats
 ├─ view::case_columns($sql)                          → recover %%CASE()%% columns + case mode
 ├─ build $meta (columnsmeta)                          → per-column {type,label[,dateformat][,textcase]}
 ├─ reporthelper::create_report(... source: adhoc_query ...)  → a reportbuilder_report row (defaults OFF)
 ├─ set_config('queryid_for_report_<reportid>', $queryid)     → the binding (BEFORE hydrating defaults)
 ├─ persist status=published, viewname, reportid, columnsmeta on the query record
 ├─ $datasource->add_default_columns()/filters()/conditions()  → hydrate RB defaults
 ├─ apply_report_visibility($reportid)                → set RB context + audience
 ├─ create_chart_report() / delete_chart_report()     → sync the companion chart report (§4.4) to chartmeta.type
 └─ event\query_published::create_and_trigger(...)
```

Ordering matters in two places:

1. **`set_config(queryid_for_report_…)` happens before** `add_default_columns()`. The datasource
   resolves its VIEW *from that config key*, so the binding must exist before RB asks the datasource
   what columns it has. The report is created with `defaults = false` precisely because the
   datasource cannot resolve its columns until the mapping is in place.
2. **`apply_report_visibility()` happens last**, once the report and its columns exist.

`query::unpublish()` / editing the SQL while published reverses steps via `tear_down()`, which
iterates `bound_report_ids()` to delete **every** bound RB report — data **and** chart (§4.4) —
cascading their columns/filters/audiences, removes the `queryid_for_report_*` config rows, and
drops the VIEW.

---

## 4. How the plugin connects to core Report Builder

This is the part most worth understanding. Three classes do the work.

### 4.1 The datasource — `classes/reportbuilder/source/adhoc_query.php`

`adhoc_query extends \core_reportbuilder\datasource`.

**It is deliberately placed in `classes/reportbuilder/source/` and NOT in
`classes/reportbuilder/datasource/`.** Core RB auto-discovers any class under
`\<component>\reportbuilder\datasource` and offers it in the "New report" source picker. By living
one namespace away (`…\reportbuilder\source`), the class stays invisible to that picker, so the
only way a report of this source can exist is via `query::publish()`. **Do not move this file** —
it would leak the datasource into the generic RB UI, where a user could create a report with no
backing query.

At runtime `initialise()` does the wiring:

```php
$reportid = $this->get_report_persistent()->get('id');
$queryid  = get_config('report_sql', 'queryid_for_report_' . $reportid);
$query    = query::get($queryid);
$entity   = new adhoc_view($query->viewname(), $visiblemeta, $query->name());
$this->set_main_table($viewname, $alias);
$this->add_entity($entity);
$this->add_all_from_entity($entity->get_entity_name());
```

So: report id → config lookup → query record → VIEW name → an entity built from `columnsmeta`.
The VIEW becomes the report's **main table**.

**Placeholder fallback.** If the binding config key is missing, the query record is gone, or
`columnsmeta` is empty, `initialise()` falls back to `initialise_placeholder()` — a single dummy
`user.id` column. This exists purely so core RB validation doesn't crash when it instantiates the
datasource for a report that isn't fully wired yet (e.g. during admin listing before publish).

**Row-level filtering** is also applied here as RB *base conditions* (always-on SQL, invisible to
the user):

- *Per-user* (`useridcolumn`): `WHERE <col> = :currentuserid`. The column is also stripped from
  the visible entity (its value would always equal the viewer's own id — noise), unless it is the
  only column.
- *Teacher-course* (`coursecolumn`): `WHERE <col> IN (courses the viewer teaches)`, or `1 = 0`
  when the viewer teaches nothing. This column stays visible (a teacher may teach several courses).

`get_default_columns()` / `get_default_filters()` cap the auto-shown set at 6 / 4 columns.

### 4.2 The entity — `classes/reportbuilder/local/entities/adhoc_view.php`

`adhoc_view extends \core_reportbuilder\local\entities\base`. It turns `columnsmeta` into RB
`column` and `filter`/`condition` objects **dynamically at runtime** — there is no hand-written
column list, because the columns depend entirely on the author's SQL.

For each entry in `columnsmeta`:

- A **column** is created: `->add_field("{alias}.{name}")`, typed via `rb_column_type()`, sortable.
- A **filter** *and* a matching **condition** are created, typed via `rb_filter_class()`.

Type mapping (`adhoc_view::rb_column_type()` / `rb_filter_class()`):

| `columnsmeta` type | RB column type | RB filter class |
|---|---|---|
| `int` | `TYPE_INTEGER` | `number` |
| `float` | `TYPE_FLOAT` | `number` |
| `bool` | `TYPE_BOOLEAN` | `boolean_select` |
| `timestamp` | `TYPE_TIMESTAMP` | `date` |
| everything else | `TYPE_TEXT` | `text` |

Column **titles** are arbitrary author-chosen strings, so they cannot have a lang entry each.
They are routed through one parametrised string, `reportsourceheader = '{$a}'`, via `raw_title()`.

### 4.3 Column metadata — where `columnsmeta` comes from

`columnsmeta` is built in `publish()` and **frozen** on the query record. It is a JSON map of
`columnname → {type, label[, dateformat]}`.

Types are derived two ways:

1. **Introspection.** `view::columns($viewname)` runs `$DB->get_columns()` on the freshly created
   VIEW and reads each column's Moodle `meta_type` char. `query::map_db_type()` maps it:
   `R/I → int`, `N/D → float`, `L → bool`, `T → timestamp`, everything else → `text`.

2. **Timestamp recovery (the exception).** A `%%TIMESTAMP()%%` token resolves to a *bare epoch
   integer* in the VIEW (see §6), so introspection would type it as `int`. To recover the intended
   `timestamp` type and any display format, `publish()` calls `view::timestamp_columns($sql)` on
   the saved SQL, which finds which output columns came from `%%TIMESTAMP()%%` (keyed by `AS`
   alias, else the expression's trailing identifier) and forces `type=timestamp` + `dateformat`.

   Why keep the field as a raw epoch instead of a DB date? Because then the column **sorts
   chronologically** (it is numerically an integer) while *displaying* a formatted date. The entity
   renders it with a `userdate()` **callback** (`adhoc_view::build_columns()`); the strftime format
   comes from `strftime_format()`, which translates a neutral format like `dd/mm/yyyy` →
   `%d/%m/%Y`, defaulting to `dd-mmm-yyyy` when none was given.

3. **Text-case recovery (same pattern).** A `%%CASE(expr, mode)%%` token resolves to the *bare text
   expression* `(expr)` in the VIEW (§6), so the column keeps its introspected `text` type but the
   value is unchanged. `build_columnsmeta()` calls `view::case_columns($sql)` (keyed like
   timestamps — `AS` alias, else trailing identifier) to recover the `mode` and stashes it as
   `columnsmeta` `textcase`. `adhoc_view::build_columns()` attaches a case-transform **callback**
   (`apply_textcase()`): `upper`/`lower` via `\core_text`, `title` via `mb_convert_case(…,
   MB_CASE_TITLE)` (reproduces Postgres `INITCAP`, which MySQL/MariaDB lacks), `sentence` = lower
   then upper-case the first letter. Because the stored value is the raw text, the column still
   **sorts and filters on the original**; only the display changes. Unknown/missing modes are
   dropped, leaving a plain text column.

**Consequence:** editing the SQL after publish does not retype columns on the fly — `columnsmeta`
is regenerated only on the next publish, which drops and rebuilds the VIEW + report.

### 4.4 The chart report — a graph rendered *through* Report Builder

A query with a chart configured (`chartmeta.type` ≠ `none`) gets a **second** RB report alongside
the data (table) report: a report whose **single row / single cell** is the whole graph, rendered
server-side as an inline SVG image. This makes the chart schedulable / exportable / embeddable like
any RB report, alongside the older interactive `chart.php` + block Chart.js render.

Why it is built this way:

- **Server-side SVG (`classes/local/chart_svg.php`).** Moodle's `\core\chart_*` only serialise to
  JSON for client-side Chart.js — there is no server rasterizer. `chart_svg::render($type, $labels,
  $values, $title)` is a dependency-free SVG builder (bar / line / pie / doughnut) with **no
  external refs**, so it survives being base64-wrapped in a `data:` URI. It XML-escapes every
  label/title, caps drawn x-axis labels (and line markers) to ~20 so a long series stays legible,
  and handles empty / negative / single-slice / ragged input. The shared `[labels, values]`
  extraction is `query::chart_series($rows, $xcol, $ycol)`, reused by `chart_svg`, `chart.php` and
  the block.
- **One-row source (`classes/reportbuilder/source/chart_query.php`).** Main table is the plugin's
  **own query table**, base-conditioned to `id = :queryid`, so the report has exactly one row
  (`1 = 0` when unbound). Hidden from the RB source picker the same way as `adhoc_query` (lives in
  `…\reportbuilder\source`, not `…\reportbuilder\datasource`).
- **One-column entity (`classes/reportbuilder/local/entities/chart_view.php`).** Entity name is
  **`adhocchart`**, **set explicitly in the constructor** — the base class would otherwise derive it
  from the class name (`chart_view`), making the column's unique identifier `chart_view:chart`
  instead of the `adhocchart:chart` that `chart_query::get_default_columns()` returns; that mismatch
  makes `add_default_columns()` throw `invalid_parameter` and the report renders with **zero
  columns** ("Nothing to display"). The single `chart` column is `TYPE_TEXT`, non-sortable, and its
  callback returns `<img … data:image/svg+xml;base64,…>` (RB does **not** escape callback output —
  the callback owns safety; an SVG inside `<img>` can't run script; this mirrors core's
  user-picture column). The callback **ignores `$row`** and fetches the dataset itself via
  `query::fetch_rows_for_viewer()` — a column callback only sees one row but a chart aggregates
  many, and going through `fetch_rows_for_viewer()` applies the same per-viewer (per-user /
  teacher-course) row scoping as the data report.
- **Binding, lifecycle, healing.** The chart report shares the `queryid_for_report_<rid>` binding,
  so `bound_report_ids()` / `tear_down()` / `on_course_deleted()` already sweep it. Its id is also
  **denormalised** onto the query record as `chartreportid` (single writer `store_chart_report_id()`)
  so it can be an RB base field and be read without a config scan; `chart_report_id()` prefers that
  column and falls back to a config-scan for pre-`chartreportid` rows. `create_chart_report()` is
  idempotent (reuse by source class) and **heals** a report left column-less by adding defaults on
  the reuse path. `apply_report_visibility()` runs for the chart report too, keeping its context +
  audience in lockstep with the data report.
- **UI wiring.** The system report's **View chart** kebab action links to
  `/reportbuilder/view.php?id=:chartreportid`; `chart.php` redirects its HTML view there (CSV export
  still streams from `chart.php`), so on-screen there is a single rendering path. The **Schedule**
  action targets the **chart** report for chart queries (so the emailed report is the graph) and the
  **data** report otherwise. **Caveat:** RB tabular export (CSV / Excel / PDF dataformat) strips the
  `<img>` to an empty cell, so the chart report is an **HTML / on-screen** artifact — scheduled
  emails of it must use an HTML delivery format.

---

## 5. Report visibility — who can open the generated report

There are **two independent gates**, and keeping them in sync is a core design constraint.

| Gate | Controls | Enforced by |
|---|---|---|
| Plugin pages (index / run / edit) | The plugin's own UI | `query::visible_to_current_user()` using the plugin capabilities |
| The RB report viewer (`/reportbuilder/view.php`) | The actual report data | **core RB** `permission::can_view_report()` |

The report data does **not** live behind the plugin's capabilities. It lives behind core RB:

```
moodle/reportbuilder:view at the report context  AND  (viewall  OR  can_edit  OR  user ∈ audience)
```

`apply_report_visibility()` (`classes/local/query.php:679`) drives the two core RB levers from the
query's own fields — there is no extra config:

- **Context** — `courseid > 0` places the report in that course's context, so `reportbuilder:view`
  is evaluated there. Site-wide queries stay at system context. A stale/deleted course id silently
  degrades to system context rather than fatalling.
- **Audience** — driven by `audiencemeta` (the form's Audience picker) or, when that is the
  automatic default, derived from scope + visibility:
  - `visible = 0` → **no audience** (owner + `reportbuilder:viewall` only)
  - course-scoped + visible → **course staff** (`courserole` for teacher/non-editing teacher/
    manager archetypes), falling back to `courseparticipant` if the site defines no staff roles
  - site-wide + visible → **all users** (`allusers`)
  - explicit picker choices: `allusers`, `courseparticipant`, `courserole`, `cohort`, or `none`

`apply_report_visibility()` is **idempotent**: it deletes existing audiences for the report before
re-adding, so re-publishing or toggling visibility never accumulates duplicates. These reports are
created solely by this plugin, so wiping their audiences is safe.

### Custom audiences

Core ships no "enrolled in course X" or "has role X in course X" audience, so the plugin adds two,
both generated **programmatically only** (never offered in the RB audience UI):

- `classes/reportbuilder/audience/courseparticipant.php` — active enrolments in a course; carries
  `configdata = ['courseid' => int]`.
- `classes/reportbuilder/audience/courserole.php` — users holding given roles in a course; carries
  `configdata = ['courseid' => int, 'roles' => int[]]`.

The `cohort` picker choice uses **core's** `cohortmember` audience (`configdata = ['cohorts' => int[]]`), not a custom class.

> **Critical invariant:** a query hidden at the plugin level but published with a wide audience is
> still reachable through `/reportbuilder/view.php`. Always change visibility through
> `apply_report_visibility()` so both gates stay consistent.

---

## 6. SQL validation and placeholder substitution

Two layers, both rooted in `classes/local/sql/`.

### 6.1 Static validation — `validator.php`

- Strips comments and string literals *before* scanning, so an embedded `'DROP …'` string can't
  evade the denylist.
- Enforces `SELECT`/`WITH`-only; blocks multi-statement; blocks a table denylist (`config`,
  `sessions`, etc.).
- `auto_brace()` wraps bare table names in `{}` so authors don't have to type Moodle braces.
- Rejects unknown `%%…%%` tokens via `is_supported_token()`. Supported tokens are exempt because
  they are substituted later, not at validate time.

### 6.2 Placeholder substitution — `view::resolve_placeholders()`

This is the **single** substitution point, used by both publish and the live AJAX check. It
resolves:

| Token | Becomes |
|---|---|
| `{table}` | prefixed table name (`mdl_table`) |
| `%%WWWROOT%%` | site URL |
| `%%COURSEID%%` | bound course id |
| `%%COURSECONTEXT%%` / `%%CONTEXT_*%%` | context ids / context-level constants (`view::context_level_tokens()`) |
| `%%NOW%%` | current epoch int — `UNIX_TIMESTAMP()` (MySQL) / `EXTRACT(EPOCH FROM now())::int` (Postgres) |
| `%%TIMESTAMP(expr[, format])%%` | the **bare epoch expression** `(expr)` — no DB date function (so it sorts; typing/format applied later from `columnsmeta`) |
| `%%EPOCH(datetime)%%` | epoch int in the live dialect (string literals get an explicit Postgres `TIMESTAMP` cast) |
| `%%CASE(expr, mode)%%` | the **bare text expression** `(expr)` — `mode` (`upper`/`lower`/`title`/`sentence`) is dropped from SQL and re-applied as a display callback from `columnsmeta` `textcase`, so the stored value (and sort/filter) stay on the original text |

The dialect is chosen via `$DB->get_dbfamily()`. `normalise_aliases()` then makes quoted column
aliases identifier-safe (spaces → underscores; lowercases double-quoted aliases on Postgres so RB's
case-folded SQL can reference them).

### 6.3 Live validation — `classes/external/validate_sql.php` (AJAX)

1. Runs static validation.
2. Runs `$DB->get_records_sql("… LIMIT 1")` to catch bad table/column names and runtime errors
   (the single fetched row forces select-list expressions to actually evaluate).
3. Issues `CREATE OR REPLACE VIEW … / DROP VIEW` to catch **duplicate column names** — a VIEW
   constraint the dry-run `LIMIT 1` misses (this is why `SELECT *` across joins fails at publish).

The JS editor (`amd/src/editor.es6.js`) mirrors the denylist client-side and calls this endpoint on
submit before allowing the form through.

### 6.4 Advisory analysis — the **Test query** button

Separate from validation (which gates publish), the edit form's **Test query** button gives the author
non-blocking feedback. It is wired in `edit_query_form.php` (`js_call_amd('report_sql/test',
…)`) and calls the `report_sql_test_query` external
(`classes/external/test_query.php` → `analyser::analyse()` in `classes/local/sql/analyser.php`,
capability `report/sql:author` at system context). The analyser returns `{ok, error,
rowcount, datecolumns, suggestions, warnings, indexinfo}` — a row count, per-table index/scan lines,
performance warnings (full scans, missing indexes, non-sargable filters, large/`DISTINCT` result
sets), and the integer columns that look like stored timestamps.

`amd/src/test.js` renders each date column as a **click-to-wrap** control: clicking rewrites that
column's select-list item in place, wrapping its expression in `%%TIMESTAMP(...)%%`. The rewrite is
purely client-side string surgery on the editor text:

- `maskSql()` blanks comments and string literals length-preservingly so keyword/comma scans map 1:1
  onto the original SQL.
- `selectListRegion()` finds the top-level `SELECT … FROM` (paren-depth 0, so CTEs and select-list
  subqueries are skipped); `splitItems()` breaks it on depth-0 commas.
- `wrapTimestamp()` matches the target column by trailing identifier or `AS` alias, preserves the
  alias, is idempotent (already-wrapped returns unchanged), and returns null when the expression
  can't be located (e.g. `SELECT *`). A leading `DISTINCT` on the first item is kept **outside** the
  token (`DISTINCT %%TIMESTAMP(col)%%`), since it is a statement-level modifier, not part of the
  column expression.

The button never blocks save/publish — it is convenience only.

---

## 7. AI SQL generation (optional, via `local_sqlchat`)

AI generation is **not** implemented in this plugin. It is delegated to the separate
`local_sqlchat` plugin when that is installed. `edit.php` checks `class_exists('\local_sqlchat\api')`
and, on an AI request, calls:

```php
// The third argument is an opaque prompt-rules string. local_sqlchat is token-agnostic
// and appends it verbatim; view::ai_prompt_rules() describes our %%…%% tokens so the AI
// emits them (dates, case, context, …). They resolve in view::resolve_placeholders()
// when the view is built.
$airesult = \local_sqlchat\api::generate_sql(
    $prompt, $context->id, \report_sql\local\sql\view::ai_prompt_rules());
```

`local_sqlchat` builds the prompt (compressed schema + question + our `$extrarules`), sends it to
the configured AI backend via `tool_ai_bridge`, and returns a `result` object (`sql`,
`raw_response`, `prompt`, `latency_ms`). The single source of truth for what tokens the AI may
emit is `view::ai_prompt_rules()`; `local_sqlchat` knows nothing about them. `edit.php` loads the generated SQL into the edit form and, when the
`local_sqlchat/showprompt` admin setting is on, renders the prompt sent to the LLM (for reuse on a
different model). `classes/local/query_naming.php` provides helpers that derive a query name /
description from either the question or the generated SQL.

The plugin's own `local/sqlchat:use` capability is granted to the report-author role (via
`classes/local/roles.php`) only when that plugin is present.

---

## 8. Auditing — standard events

`classes/event/` defines five query-lifecycle events, all extending `query_event_base`
(→ `\core\event\base`):

`query_created`, `query_updated`, `query_published`, `query_unpublished`, `query_deleted`.

- Raised at `context_system` (query records are site-level).
- `objectid` = query id; `objecttable` = `report_sql_query`; `edulevel = LEVEL_OTHER`.
- The query **name** is carried in `other['name']` so a delete event can still render a description
  after the record is gone.
- Triggered from `classes/local/query.php` (save / publish / unpublish / delete / duplicate).
- Viewable at **Site admin → Reports → Logs**.

### Course-deletion observer

Separately from the lifecycle events, `db/events.php` subscribes `\core\event\course_deleted`
→ `classes/observer.php::course_deleted` → `query::on_course_deleted()`. When a course is deleted
its context row goes with it, leaving any report placed in that course context with a dangling
`contextid`. For every query scoped to that course this:

- degrades the query to site-wide (`courseid = 0`) and forces `audiencemeta.type = none` — silently
  re-deriving a site-wide audience would **widen** who can open the report (privilege escalation);
- re-points each of its published reports (all of them — see `bound_report_ids()`) to the system
  context, curing the dangling `contextid`.

---

## 9. Admin tree & capabilities

### Capabilities (`db/access.php`)

| Capability | Scope | Who / what |
|---|---|---|
| `report/sql:author` | system | Write/save queries |
| `report/sql:approve` | system | Publish / unpublish |
| `report/sql:viewall` | system | See all queries |
| `report/sql:view` | system or course | See published queries |
| `report/sql:viewown` | course | Course-level teacher view |

`query::visible_to_current_user()` implements all five rules — **but only for the plugin's own
pages**. Who can open the generated RB report is enforced separately by core RB context + audience
(see §5).

### Admin tree registration (`settings.php`)

Three `admin_externalpage`s (under **Reports**) are registered **outside** the `if
($hassiteconfig)` guard, so they show for the author role *without* `moodle/site:config`:

- the **index** page (`report_sql`), cap `report/sql:author` — the
  **Site administration → Reports → SQL Report** menu entry;
- the **usage overview** (`report_sql_usage`, hidden), cap `report/sql:viewall`;
- the **samples browser** (`report_sql_samples`, hidden), cap `report/sql:author`
  — so any author can browse/import bundled samples (§10).

The settings page (denylist, AI toggle, etc.) and the admin-only externalpages
(`testview`, `createrole`, `importcr`, `importcustomsql` — all `moodle/site:config`) stay **inside**
the guard.

---

## 10. Import / export & bundled samples

`classes/local/transfer.php` moves queries as portable JSON (`export()` / `parse()` / `import()`).
Only portable fields travel (name, description, SQL, course scope, visibility, chart config);
derived state (view name, report id, `columnsmeta`) is regenerated, so every import lands as a fresh
**draft** owned by the importer and must be re-published. `import()` re-validates each SQL and
demotes unknown course ids to site-wide.

Bundled samples (`samples/samples.json`) load two ways, both via `transfer`:

- **CLI** — `cli/import.php`.
- **Browse / post-install** — `samples.php` is a browsable picker (linked from `db/install.php`'s
  notification, the settings page and an index-page button) that renders the file via
  `templates/samples_list.mustache` (`amd/src/samples.js` drives Select all / none). Checkbox mode
  bulk-imports the ticked, non-duplicate rows; single mode (`?single=1`) imports one with a
  `Sample:` name prefix. `transfer::bundled_samples()` is the single read path (annotates each
  source with an `index` + `duplicate` flag); `import_samples()` bulk-imports the non-duplicates
  and is idempotent across reinstalls.

The shipped samples are cross-DB: date handling uses `%%TIMESTAMP()%%` / `%%NOW%%` rather than
dialect-specific functions, so they import and publish on both MySQL/MariaDB and PostgreSQL.

### Legacy-report importers (Configurable Reports & Ad-hoc DB Queries)

Two admin pages migrate SQL reports from older plugins into RS drafts:

- **Configurable Reports** — `import_cr.php` → `classes/local/cr_import.php`, table
  `block_configurable_reports`. Decodes the serialised `components` blob; maps
  `%%STARTTIME/ENDTIME%%` → `0` / far-future, `%%DEBUG%%` → stripped; rejects `%%USERID%%`,
  `%%FILTER_*%%`, unknown tokens. Carries CR `courseid` / `visible`.
- **Ad-hoc DB Queries** — `import_customsql.php` → `classes/local/customsql_import.php`, table
  `report_customsql_queries`. Reads the plain `querysql` column (no blob); maps the customsql escape
  tokens `%%Q%%` / `%%C%%` / `%%S%%` → `?` / `:` / `;`; **rejects** interactive named `:param`
  placeholders and `%%USERID%%`. customsql has no course scope, so every draft lands site-wide.
  Full translation / rejection rules: [import_customsql.md](import_customsql.md).

Both share `classes/local/import_helper.php` (a **trait**) for the deterministic, AI-free SQL
translation: double-quote → single-quote, MySQL date functions
(`FROM_UNIXTIME`/`DATE_FORMAT`/`UNIX_TIMESTAMP`) → portable `%%TIMESTAMP%%` / `%%EPOCH%%` / `%%NOW%%`
tokens, literal `?` in a string → `chr(63)`, plus `validator::validate()` and a live `dry_run()`.
The only per-source step is `rewrite_tokens()`, which each importer defines and the trait's
`convert()` calls via **late static binding** (`static::rewrite_tokens()`). Both feed accepted
reports to `transfer::import()`, so they land as fresh drafts owned by the importer.

`rewrite_date_functions()` is **DB-family aware**: after rewriting what it can to portable tokens,
any leftover MySQL-only date function (`DATEDIFF`, `DATE_ADD/SUB`, `STR_TO_DATE`, …) is **kept** with
a note on a MySQL/MariaDB install (`$DB->get_dbfamily() === 'mysql'`) — it runs natively and the live
`dry_run()` is the real gate — but is a **fatal reject** on PostgreSQL etc. where there is no
equivalent. Both link from the settings page and reuse the shared `crimport:*` lang strings for
conversion notes/reasons.

---

## 11. Operational requirements & gotchas

- **DB privileges.** The DB user needs `CREATE VIEW` and `DROP`. Test via
  `classes/local/sql/privilege_check.php → probe()`, surfaced at
  **Site admin → Reports → SQL Report → Run database view privilege test**.
- **`SELECT *` across joins fails at publish** — duplicate column names are illegal in a VIEW. The
  live validator's CREATE-VIEW step catches this before saving.
- **`columnsmeta` is frozen at publish.** Editing SQL while published drops and rebuilds the
  VIEW + report on the next publish.
- **Never move `adhoc_query.php`** out of `classes/reportbuilder/source/` — see §4.1.
- **Keep the two visibility gates in sync** through `apply_report_visibility()` — see §5.

---

## 12. File map (quick reference)

```
classes/local/query.php                          Publish lifecycle, visibility, tear-down (the core)
classes/local/sql/view.php                       VIEW create/drop, placeholder substitution, introspection
classes/local/sql/validator.php                  Static SQL validation + denylist
classes/local/sql/analyser.php                   Advisory Test-query analysis (row count, index/scan hints, date cols)
classes/local/sql/privilege_check.php            CREATE VIEW / DROP probe
classes/local/transfer.php                       Import/export, bundled samples
classes/local/query_naming.php                   Derive name/description from question or SQL
classes/local/roles.php                          Report-author role creation
classes/local/import_helper.php                  Shared trait: deterministic SQL translation for legacy importers
classes/local/cr_import.php                      Configurable Reports → RS draft conversion
classes/local/customsql_import.php               Ad-hoc DB Queries → RS draft conversion
classes/reportbuilder/source/adhoc_query.php     RB datasource for per-query data reports (hidden from source picker)
classes/reportbuilder/local/entities/adhoc_view.php  Dynamic columns/filters from columnsmeta
classes/reportbuilder/source/chart_query.php     RB datasource for the one-row chart report (§4.4, hidden from picker)
classes/reportbuilder/local/entities/chart_view.php  Single chart column → base64 SVG <img> cell
classes/local/chart_svg.php                      Dependency-free server-side SVG builder (bar/line/pie/doughnut)
classes/reportbuilder/local/systemreports/queries.php  RB system report backing the index.php query listing
classes/reportbuilder/local/entities/query.php   Entity for the query-listing system report
classes/reportbuilder/audience/courseparticipant.php Custom "enrolled in course" audience
classes/reportbuilder/audience/courserole.php    Custom "role in course" audience
classes/external/validate_sql.php                Live AJAX SQL validation
classes/external/test_query.php                  Test-query external → analyser::analyse()
classes/external/get_schema.php                  Schema lookup for the editor
classes/observer.php                             course_deleted → query::on_course_deleted (see db/events.php)
classes/event/*                                  Standard lifecycle events
index.php                                         Query listing (system_report) + publish/unpublish actions
edit.php                                          Edit form + AI generation entry point
usage.php                                         Usage overview page (viewall-gated)
samples.php                                       Bundled-samples browser/import picker
docs.php                                          Renders docs/userdocs.md in the browser
import_cr.php                                     Configurable Reports importer page
import_customsql.php                              Ad-hoc DB Queries importer page
settings.php                                      Admin tree registration + settings
db/access.php                                     Capabilities
db/install.xml                                    report_sql_query schema
```
