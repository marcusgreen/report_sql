# Spike: query execution time + row-count display

**Status:** design spike, not implemented. **Ask:** show viewers how long a report took to generate
and how many total rows exist (beyond the paginated page), the way Metabase/Redash/Jasper do — so a
viewer can judge whether results look complete.

## Key finding

Core Report Builder **already computes the total row count on every render**, but **execution time is
measured nowhere** — and both would display on a page the plugin does not own.

- `base_report_table::query_db()` fetches the page via `$DB->get_counted_recordset_sql()`, a windowed
  `COUNT(*) OVER()` that returns the grand total in the *same* query. Total lands in `$countedsize` →
  `totalrows` → drives the pager. So **the number already exists at zero extra cost**; core just
  renders page *links*, not an "N records" label.
- `query_db()` runs the DB call with no `microtime()` around it and stores no timing. **Execution
  time is not captured anywhere.**

## The ownership wall

The published **data report** renders at core `/reportbuilder/view.php`; its table is core's
`custom_report_table`, not a plugin subclass (RB instantiates its own table for any datasource). So
the plugin has:

- **no seam** to wrap `query_db()` for timing, and
- **no clean seam** to append a footer to that page.

(Same wall as the required-filter half of `spike_runtime_params.md`.)

## Cost by surface

| Want | Where | Cost |
|---|---|---|
| Row count | Preview / Test (plugin owns) | trivial — Preview already prints rowcount (`report_sql_preview_summary`) |
| Exec time | Preview / Test (plugin owns) | small — `microtime()` around the existing probe query |
| Row count | Published report (core view.php) | total already computed; **displaying** it needs a core RB template/renderer change, not plugin-local |
| Exec time | Published report (core view.php) | hard — core owns `query_db`; no timing seam. Needs a wrapper or a core patch |

## Options for the viewer-facing ask

1. **Surface core's already-computed total first.** The total is already in `totalrows`/the pager. Check
   whether a theme-level `report.mustache` / renderer tweak can render an "N records" label — if so,
   row count is a **display toggle**, not new code. Try this before building anything.
2. **Plugin embed/run wrapper.** A plugin-owned page that renders the report and appends a "X rows ·
   Y ms" strip. Costs: re-adds the second view path the design deliberately removed; timing there
   measures the *wrapper's* re-run, not core's actual render; adds an extra count query. Weak
   accuracy, real regression risk. Not recommended.
3. **Upstream core RB.** Add a per-report footer (rows + time) in core Report Builder. Cleanest
   semantically, slowest, out of plugin.

## Recommendation

- **Ship now (cheap, plugin-local):** add **execution time** to the Preview / Test summary strip —
  the row count is already shown there. Wrap the existing probe query in `microtime()` and render
  "… · N ms" beside the row count. This lands the "is this slow / do results look complete?"
  judgment **at authoring time**, which is where the plugin has clean reach and full control.
- **Viewer-facing on the published report:** do **not** build a wrapper just to host a footer. First
  test whether core RB's already-computed total can be surfaced via template/renderer (option 1).
  Real per-viewer **execution time** is a **core Report Builder feature request**, not a plugin-local
  change — flag it upstream rather than fake it in a wrapper.

## Notes

- Admins already get whole-page time + DB query count from Moodle's `$CFG->perfdebug` footer — coarse,
  site-wide, not per-report, but an existing lever for the "how long did it take?" curiosity.
- Row count and execution time are independent deliverables; the row-count half is nearly free
  (already computed), the exec-time half on the viewer page is the genuinely hard part.

## Out of scope

- Wrapping/replacing the core RB view page.
- Per-viewer execution timing on the published report without core RB support.
