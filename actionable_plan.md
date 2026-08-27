# Actionable SQL Reports — Implementation Plan

**Goal:** Let a published query render its rows with a leading **checkbox column** and an **action bar**. The viewer ticks rows, picks a built-in Moodle operation, clicks Apply — the operation runs over the selected rows' subjects (users or courses).

**Scope (locked):** bulk row-select + action bar; built-in Moodle ops only (enrol / unenrol / suspend / message / add-to-cohort). No per-row buttons, no author-defined SQL/webhook in v1.

---

## Prior art in Moodle core (researched)

Core already has a first-class "select rows → run a bulk op" idiom. **Adopt it wholesale** rather than hand-rolling a table.

| Piece | Core reference |
|---|---|
| Checkbox primitive (master toggler + per-row target, same `togglegroup`) | `\core\output\checkbox_toggleall` — `lib/classes/output/checkbox_toggleall.php:36` |
| RB **system_report** bulk-checkbox API — per-row callback `fn($row) => [$value, $label]`, renders a `selectall` column, gated on a `withcheckboxes` parameter | `reportbuilder/classes/system_report.php:151`; table render `reportbuilder/classes/table/system_report_table.php:114`; caller `admin/classes/reportbuilder/local/systemreports/users.php:70` |
| Host page: render the report **+ a separate bulk-action `moodleform`** whose action posts to a **dispatch page** | `admin/user.php:164` → `admin/user/user_bulk.php` |
| AMD glue: checkboxes tagged `data-togglegroup="report-select-all"`; on change collect `:checked`, write joined ids into a hidden input, submit form when the action `<select>` changes | `admin/amd/src/bulk_user_actions.js:33-67` |
| Same pattern, second example (cohort members) | `cohort/classes/reportbuilder/local/systemreports/cohorts.php` |

Key facts this settles:
- **Only RB `system_report` supports checkboxes — datasource reports viewed via `/reportbuilder/view.php` do not.** The plugin's *data* reports are datasource reports, so they cannot host the checkboxes. The plugin's own **index.php is already a `system_report`** (`classes/reportbuilder/local/systemreports/queries.php`), so the team knows this exact API.
- The selection→form transfer is a solved, accessible, core-maintained mechanism. We clone ~40 lines of `bulk_user_actions.js`, changing only the target form/field names.

## Why a new render page (revised)

Report data is rendered by **core** `/reportbuilder/view.php`, which has no row-selection UI. So the actionable table is a **new plugin-owned `system_report`** built over the query's published VIEW (`report_sql_v_<id>`), rendered on a plugin page `actions.php`. It reuses the columns already described by `columnsmeta` (same source the `adhoc_view` entity reads) and re-applies the per-viewer row scoping from `fetch_rows_for_viewer()` (useridcolumn / coursecolumn) via `add_base_condition_sql()`. Core RB `view.php` stays the read-only path; `actions.php` is the write path.

## Subject of an action

Each built-in op targets a **user** or a **course**. The row's subject id comes from the query's existing identity columns:
- User ops → value of `useridcolumn` (must be set; else those ops are unavailable).
- Course ops → value of `coursecolumn` (or an explicit `actionsubjectcolumn`).

Rows with an empty/invalid subject id are non-selectable.

## Security model (central)

Every apply request is **double-gated**:
1. `report/sql:actexecute` (new plugin capability) at the report's context.
2. The op's **own core capability**, checked **per target context** at execute time (e.g. `enrol/manual:enrol` in each course context, `moodle/user:update` in user context). A subject the viewer lacks rights over is skipped and reported, never silently applied.

Plus: `require_sesskey()` on POST, a **confirm step** for destructive ops (unenrol / suspend), row cap (e.g. max 500 selected), and an audit event per apply.

---

## Task checklist

### 1. Data model ✅
- [x] Add `actionsmeta` (JSON) column to `report_sql_query` in `db/install.xml` (nullable).
- [x] Add `db/upgrade.php` step (2026082700) + bump `version.php` → 0.1.21.
- [x] `actionsmeta` shape: `{enabled: bool, ops: string[], subject: "user"|"course", subjectcolumn: string}`.
- [x] Accessors `query::actions_meta()` + `query::actions_enabled()` + `query::action_subjectcolumn()`; read live, not frozen.

### 2. Capability ✅
- [x] Add `report/sql:actexecute` to `db/access.php` (CONTEXT_SYSTEM, riskbitmask `RISK_DATALOSS | RISK_SPAM`, archetype manager only).
- [x] Lang string `sql:actexecute`.

### 3. Action handler framework (`classes/local/action/`) ✅
- [x] Interface `action_handler`: `key()`, `label()`, `subject_type()`, `required_capability()`, `is_destructive()`, `execute(array $subjectids, context $reportctx, array $params): action_result`.
- [x] `action_result` value object: `mark_applied`/`mark_skipped(reason)` + counts.
- [x] Abstract `base_action` — implements `execute()` **once**: per-subject `target_context()` → `has_capability()` gate (skip+reason if denied) → `apply_one()` with per-subject error isolation. No handler can skip the gate.
- [x] Registry `action_registry::all()`/`instance()`/`for_ops()`/`menu()`.
- [x] Handlers (each wraps a **core API**, never raw SQL):
  - [x] `enrol_user` — manual enrol, cap `enrol/manual:enrol` (shared `manual_enrol_action` base).
  - [x] `unenrol_user` — destructive, cap `enrol/manual:unenrol`.
  - [x] `suspend_user` — `user_update_user` suspended=1 + kill sessions, cap `moodle/user:update`, destructive; refuses siteadmin/self.
  - [x] `message_user` — `message_send()` via new `db/messages.php` `actionmessage` provider, cap `moodle/site:sendmessage`.
  - [x] `cohort_add` — `cohort_add_member()` (idempotent), cap `moodle/cohort:assign`.
- [x] Smoke-tested via Moodle bootstrap: all resolve, labels localise, caps/destructive correct, `for_ops` drops unknowns.
- [ ] Course-subject ops (if `subject: course`) parked for v1.1 — framework supports it (`subject_type`), ship user ops first.

### 4. Edit form ✅
- [x] "Row actions" section in `edit_query_form::add_actions_elements()` (built in `definition_after_data`, column-dependent like the filter/chart sections): enable toggle, subject select, subject-column picker, multi-select ops from `action_registry::menu()`, per-op params (role / course / cohort / message text), all `disabledIf` the enable checkbox (`notchecked`).
- [x] Unpublished queries show a "publish first" locked note (UX parity with chart/filter).
- [x] `validation()`: enabled ⇒ subject column + ≥1 op; enrol ops ⇒ course scope or target course; cohort ⇒ cohort; message ⇒ text.
- [x] `query::build_actionsmeta()` (validates ops against registry + subject column against `columnsmeta`) wired into `save()` published branch → `actionsmeta`.
- [x] Smoke-tested: valid config serialises (drops unknown ops), off/bad-column/no-ops → null.

### 5. Actionable `system_report` + host page (mirror `admin/users.php` + `admin/user.php`)
- [x] New `classes/reportbuilder/local/systemreports/query_actions.php` (a `system_report`) over the query's VIEW — **stub**:
  - [x] Main table = `report_sql_v_<id>` (reuse `adhoc_view` entity built from `columnsmeta`).
  - [x] `add_base_fields()` for the subject id column (needed by the checkbox callback + dispatch).
  - [x] `add_base_condition_sql()` via new `query::viewer_scope_sql($alias)` — re-applies useridcolumn/coursecolumn scoping so the checkbox report can't widen visibility.
  - [x] `set_checkbox_toggleall(fn($row) => [$row->subjectid, $label])`, gated on `withcheckboxes`.
  - [x] `can_view()` = `report/sql:actexecute` **AND** `query::current_user_can_view_report()` (data report's own gate).
- [x] `actions.php` host page: `require_login` + context (course/system) + `require_capability(actexecute)` + `current_user_can_view_report()`; builds the system_report `withcheckboxes=1`, renders it in `#rs-actions-report`, renders `bulk_action_form` (op `<select>` limited to enabled ops + hidden `subjectids`) posting to `action_apply.php`.
- [x] `$PAGE->requires->js_call_amd('report_sql/bulk_actions', 'init', …)`.
- [x] AMD `amd/src/bulk_actions.js` (built via grunt): togglegroup `report-select-all`, checked ids → hidden `subjectids`, Apply disabled until ≥1 selected, rebinds on RB dynamic-table refresh.
- [x] Verified: rendered the report as admin over query 56 — checkbox column present (`report-select-all` + `data-toggle="target"`), viewer-scoping applied.
- Note: `action_apply.php` (the form POST target) is block 6 — Apply is inert until then.

### 6. Dispatch page + confirm (mirror `admin/user/user_bulk.php`)
- [ ] `action_apply.php` — `require_login`, `require_sesskey`, `require_capability('report/sql:actexecute', $reportctx)`, resolve handler from op key, parse `subjectids`, enforce row cap.
- [ ] Confirm interstitial for `is_destructive()` ops (count + op label + Cancel).
- [ ] Dispatch: iterate subject ids, per-subject core-cap check in target context, `execute()`, collect `action_result`.
- [ ] Redirect back to `actions.php` with a summary notification: N applied, M skipped (reasons).

### 7. Audit
- [ ] New event `action_applied` (extends `query_event_base`): `other = {op, count, applied, skipped}`, raised at report context.
- [ ] Trigger from the apply dispatch.

### 8. UI wiring
- [ ] Add "Open actions" kebab item to the `queries` system report (visible only when `actions_enabled()` and viewer holds `actexecute`), linking to `actions.php?id=`.

### 9. Docs + tests
- [ ] PHPUnit: registry resolution, per-subject cap skipping, destructive-confirm gate, `actionsmeta` round-trip in save/import.
- [ ] `transfer.php`: include `actionsmeta` in `export()`/`parse()`/`import()` as a portable field.
- [ ] Update `CLAUDE.md` with an "Actionable reports" architecture section.
- [ ] Behat: tick two rows → enrol → assert enrolment + skipped-row notice.

---

## Open decisions (defer until build)
- Op parameters (enrol → which role? cohort → which cohort?): fixed at author time in `actionsmeta`, or chosen in the action bar at apply time. **Lean:** author-time config, keeps the action bar a single dropdown.
- Async for large selections: synchronous with a row cap in v1; adhoc-task queue in v1.1 if caps bite.
