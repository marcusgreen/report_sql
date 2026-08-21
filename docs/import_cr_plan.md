# Plan: Import configurable_reports → report_sql

An admin feature to import `block_configurable_reports` (Configurable Reports, "CR")
SQL report instances into `report_sql` as drafts.

## What maps, what doesn't

**Source** (`block_configurable_reports` table):
- SQL lives only in `type='sql'` reports. The SQL is stored in the `components`
  column, which is `serialize(urlencode_recursive(...))`; the query text sits at the
  `customsql` component's `querysql`. Decode with CR's own `cr_unserialize()`
  (`blocks/configurable_reports/locallib.php`) — it handles the
  `O:6:"object"`→`stdClass` rewrite and the urlencode quirks.
- Non-SQL types (`timeline`, `categories`, `courses`, `users`) carry **no SQL** and
  cannot be converted. Skip them and report why.

**Target** (`classes/local/transfer.php` JSON model): `name`, `description` (← CR
`summary`), `querysql`, `courseid`, `visible`, `chartmeta`. An imported source lands
as a **draft**, is re-validated, is owned by the importer, and must be re-published.
Reuse this path — do not build a second importer.

## Token gap (the real work)

CR and RS use overlapping but different `%%…%%` vocabularies:

| CR token | RS handling |
|---|---|
| `%%WWWROOT%%` | same — supported as-is |
| `%%COURSEID%%` | same, but RS requires the query to carry a course scope |
| `%%STARTTIME%%` / `%%ENDTIME%%` | no equivalent (CR time-range filter bounds) → rewrite or strip |
| `%%USERID%%` | **rejected** by RS validator (RS scopes by a user-id *column* instead) |
| `%%CATEGORYID%%` | no equivalent |
| `%%FILTER_*%%` / `%%FILTER_VAR%%` | RS uses Report Builder filters, not inline SQL → strip |
| `%%DEBUG%%` | strip |
| literal `?` | CR escapes to `[[QUESTIONMARK]]`; RS validator likely rejects raw `?` |
| column-magic `%%…%%` | CR regex-strips leftovers → may leave broken SQL |

Plus: CR SQL is usually **MySQL-only** (`DATE_FORMAT`, `UNIX_TIMESTAMP`, etc.). The RS
validator warns on those (`MYSQL_DATE_FUNCTIONS`) and steers authors to the portable
`%%TIMESTAMP()%%` / `%%EPOCH()%%` tokens.

## Proposed build

1. **Admin page** `import_cr.php` — register as an `admin_externalpage` inside the
   `if ($hassiteconfig)` guard, cap `report/sql:author`. Lists `type='sql'`
   CR reports with checkboxes; non-SQL rows greyed out with the reason.
2. **`classes/local/cr_import.php`** — new converter:
   - `discover()` → read `block_configurable_reports`, decode `components`, pull `querysql`.
   - `translate_tokens($sql)` → deterministic CR→RS token map (table above).
   - `to_source($rec)` → emit the `transfer.php` source array, then hand off to
     `transfer::import()` (reuse its validation + draft creation).
3. **Per-report status** after conversion: `clean` / `needs-fix` / `unconvertible`.
4. **Tests** `tests/cr_import_test.php` — token translation, `components` decode,
   non-SQL skip, dialect-warning passthrough.

## "Logically broken" — what to do

Detect at convert time via the existing `validator::validate()` (static) plus an
optional live dry-run (`get_records_sql(... LIMIT 1)`). Three buckets:

- **Unconvertible** (non-SQL type, empty SQL) → skip, list in the summary. No fix
  possible.
- **Mechanically fixable** (known token swaps, MySQL date functions,
  `[[QUESTIONMARK]]`) → auto-rewrite, import as draft, flag "review before publish".
- **Genuinely broken** (bad columns, removed `mdl_log`, dropped tables, dialect SQL
  with no clean mapping) → import as a **draft anyway** (never silently drop), with the
  validator error attached, then offer repair.

## Repair: PHP vs AI

| | **PHP processing** | **AI assist (via `local_sqlchat`)** |
|---|---|---|
| How | Regex / token map + `validator` rules, deterministic | `chat_engine::ask("fix this error: …", contextid)` → `tool_ai_bridge` → `core_ai_subsystem`; the same path the edit form's AI box already uses |
| Strengths | Free, instant, reproducible, no data leaves the site, testable | Handles novel breakage: dialect rewrites, schema drift (`mdl_log`→`logstore_standard_log`), `SELECT *` duplicate-column splits |
| Weaknesses | Only fixes patterns you coded; brittle on unseen SQL | Costs tokens, non-deterministic, needs `aigenerate` enabled + plugin installed, sends schema + SQL to the provider |
| Gate | always available | only when `get_config('report_sql','aigenerate')` **and** `get_capability_info('local/sqlchat:use')` |

**Recommendation: layered, not either/or.**

1. PHP token translation runs **always** (cheap, covers the ~80% common cases — the
   token table above).
2. If still invalid after the PHP pass → show the validator error in the import UI with
   a **"Fix with AI"** button, shown only when `aigenerate` is on **and**
   `local_sqlchat` is present (mirror the edit-form gating in `classes/local/roles.php`
   and `classes/form/createrole_form.php`).
3. AI returns candidate SQL → re-run `validator` → land as a draft. **Never
   auto-publish AI output**; the author reviews it on the edit form (which already shows
   the LLM prompt via the `ai:prompt` string).

This keeps the deterministic, offline-safe path as the default and treats AI as an
opt-in escalation — consistent with how the plugin already treats `local_sqlchat` as an
optional dependency.

---

# Phase 1 — Conservative importer (ship this first)

Principle: **no transformation, no AI, no guessing.** Decode each CR report, run it
through the existing `validator`, import only the ones that pass untouched, and reject
everything else with a printed reason. Authors fix rejects by hand. Zero clever
rewrites = zero silent corruption.

## Accept criteria (ALL must hold)

A CR report is imported only if:

1. `type === 'sql'` — has actual SQL.
2. `querysql` decodes non-empty from `components`.
3. Contains **only** tokens RS already supports (`%%WWWROOT%%`, `%%COURSEID%%`,
   `%%COURSECONTEXT%%`, `%%NOW%%`, `%%CONTEXT_*%%`, `%%TIMESTAMP()%%`, `%%EPOCH()%%`).
4. `%%COURSEID%%` present ⇒ the CR report has a real `courseid` to bind as scope;
   otherwise reject.
5. Passes `validator::validate()` static check.
6. Passes the live dry-run (`get_records_sql(... LIMIT 1)`).

Imported reports land as **drafts** (`transfer.php` already does this) — the author
still reviews and publishes. Nothing is auto-published.

## Reject (don't touch, just explain)

| Reject reason | Why the conservative pass rejects it |
|---|---|
| Non-SQL type (`timeline`, `categories`, `users`, `courses`) | No SQL to import. Not fixable. |
| Unsupported token (`%%USERID%%`, `%%CATEGORYID%%`, `%%STARTTIME/ENDTIME%%`, `%%FILTER_*%%`, `%%DEBUG%%`) | Rewriting changes meaning. The author must decide intent. |
| Literal `?` / `[[QUESTIONMARK]]` | CR escape artifact; safe handling is non-trivial. Reject. |
| Column-magic `%%…%%` leftovers | CR strips these at runtime; the result may be broken SQL. Don't guess. |
| MySQL-only date functions (`DATE_FORMAT`, `UNIX_TIMESTAMP`, …) | Not cross-DB. Translating to `%%TIMESTAMP%%`/`%%EPOCH%%` is interpretation, not conversion. |
| Fails the static validator | Denylist / multi-statement / unknown token. Hard reject. |
| Fails the live dry-run | Bad columns, dropped tables (`mdl_log`), dialect errors. Reject. |

**No AI path. No token remapping. No MySQL→portable rewriting.** Those all belong to
the ambitious plan above — explicitly out of scope for phase 1.

## Build (smaller than the full plan)

1. `import_cr.php` — admin externalpage, inside the `if ($hassiteconfig)` guard, cap
   `report/sql:author`. Two lists: **Importable** (checkboxes) and
   **Rejected** (read-only, each with its reason).
2. `classes/local/cr_import.php`:
   - `discover()` — read `block_configurable_reports`, `cr_unserialize()` the
     components, pull `querysql`.
   - `classify($rec)` — returns `accept` or `reject` + reason. **No mutation.**
   - accepted → build the transfer source array → `transfer::import()`.
3. `tests/cr_import_test.php` — accept clean SQL; reject each rejection category;
   confirm rejects are never written to the DB.

## Trade-off (be honest about it)

- **Wins:** simple, deterministic, no external dependency, no data leaves the site,
  easy to test, impossible to silently corrupt a query.
- **Costs:** likely rejects a large share of real-world CR reports (most use
  `%%USERID%%`, `%%FILTER_*%%`, or MySQL date functions). Authors get a clear reject
  list but must port those manually.

That manual-port burden is exactly what the AI / PHP-rewrite layer above exists to
remove — so treat this as **phase 1**: ship the safe importer, measure the reject list,
then decide whether the rewrite layer is worth building as phase 2.

## Measured against the jleyva report repo

Ran the 20 reports in
[jleyva/moodle-configurable_reports_repository](https://github.com/jleyva/moodle-configurable_reports_repository)
through the phase-1 accept criteria.

**Static pass (criteria 1–4): 35% (7/20).**

| Verdict | Report | Reason (if rejected) |
|---|---|---|
| ✅ | Cohorts by user | clean, cross-DB |
| ✅ | Courses with groups | clean |
| ✅ | Users logged in once | clean |
| ✅ | List of all site users by course enrolment | clean |
| ⚠️ | All badges available, with earned count | passes static, but uses `"double-quote"` string literals → MySQL-only, fails on Postgres |
| ❌ | Most active courses | queries `prefix_log` — `mdl_log` removed in Moodle 4+ (caught by live dry-run) |
| ❌ | Detailed actions per role | queries `prefix_log` — dead table |
| ❌ | All badges issued, by user | MySQL-only `DATE_FORMAT`; literal `?` |
| ❌ | SCORM completed activities | MySQL-only `FROM_UNIXTIME` |
| ❌ | Enrolled users who never logged in | MySQL-only `DATE_FORMAT` |
| ❌ | Enrolled more than 4 weeks | MySQL-only `FROM_UNIXTIME` |
| ❌ | Logged-in users last 120 days | MySQL-only `FROM_UNIXTIME` |
| ❌ | Site-wide grade report (course totals) | MySQL-only `FROM_UNIXTIME` |
| ❌ | Special roles | literal `?` |
| ❌ | Courses defined as using groups | literal `?` |
| ❌ | Student count per course | literal `?` |
| ❌ | User course completion (×2) + with criteria | MySQL-only `DATE_FORMAT`; literal `?` |
| ❌ | User completion / time dedication (×2) | non-sql report type (`users`), no SQL |

**Realistic pass after the live dry-run (criteria 5–6):**
- MySQL Moodle 5 site: ~5/20 = **25%** (`mdl_log` reports drop out).
- Postgres site: ~4/20 = **20%** (the double-quoted badges report also fails).

**Interpretation:** the conservative importer lands roughly **1 in 4** of this repo. The
dominant killers — MySQL date functions, the `?` HTML-link trick, and `mdl_log` schema
drift — are exactly the non-portable patterns phase 1 refuses by design. The reports
that do pass closely match the simple ones already hand-ported into
`samples/samples.json`, confirming phase 1 mainly re-derives the easy cases; the
long tail needs the phase-2 rewrite / AI layer.
