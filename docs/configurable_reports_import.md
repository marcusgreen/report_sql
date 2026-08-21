# Importing from the Configurable Reports block

`report_sql` can import the SQL reports stored in the **Configurable Reports**
block (`block_configurable_reports`) and turn them into draft report sources. Each
imported report is created as a **draft owned by you** and must be published before it
becomes a live Report Builder report.

No AI is involved. Every conversion below is a fixed rule. Anything that cannot be
mapped faithfully is rejected with a reason rather than guessed at, so an imported draft
never silently does the wrong thing.

## Where to find it

**Site administration → Reports → SQL Report → Import from
Configurable Reports**

(There is also a direct link on the plugin settings page.) The page only appears when
the Configurable Reports block is installed; otherwise it shows a notice and nothing to
import.

## How it works

1. The importer reads every Configurable Reports instance and decodes the SQL embedded
   in each one.
2. Only reports of type **SQL** can be imported. Configurable Reports' other types
   (Timeline, Categories, Courses, Users) hold no SQL and are always rejected.
3. Each SQL report is run through a deterministic translation (see below), then through
   the same static validator and live dry-run the edit form uses.
4. The page shows two lists:
   - **Importable** — reports that translated cleanly. Each row has a checkbox (ticked
     by default) and a note of any changes that were applied.
   - **Rejected** — reports that could not be converted, each with a reason.
5. Tick the ones you want and choose **Import selected**. They are created as drafts.
   Review and publish each from the SQL Report list.

## What gets translated automatically

| Configurable Reports construct | Becomes |
|---|---|
| `"double-quoted"` string literals | `'single-quoted'` (portable across MySQL/PostgreSQL) |
| `%%STARTTIME%%` | `0` (Configurable Reports' own no-range default) |
| `%%ENDTIME%%` | `2145938400` (its far-future default) |
| `%%DEBUG%%` | removed |
| `%%WWWROOT%%`, `%%COURSEID%%` | kept (SQL Report supports both) |
| `FROM_UNIXTIME(col)` | `%%TIMESTAMP(col)%%` |
| `FROM_UNIXTIME(col, '%Y-%m-%d')` | `%%TIMESTAMP(col, yyyy-mm-dd)%%` |
| `DATE_FORMAT(FROM_UNIXTIME(col), '%d/%m/%Y')` | `%%TIMESTAMP(col, dd/mm/yyyy)%%` |
| `UNIX_TIMESTAMP()` | `%%NOW%%` |
| `UNIX_TIMESTAMP(expr)` | `%%EPOCH(expr)%%` |
| literal `?` inside a string (e.g. `view.php?id=`) | `CONCAT('view.php', chr(63), 'id=')` |
| Configurable Reports site-course id `1` | site-wide scope (`0`) |

Date-format specifiers are mapped to the neutral vocabulary SQL Report understands:
`%Y→yyyy`, `%y→yy`, `%m→mm`, `%d→dd`, `%H→hh`, `%i→mi`, `%s→ss`, `%M→month`,
`%b→mon`, `%a→ddd`. A format containing a specifier outside this set is rejected rather
than rendered incorrectly.

## Why a report is rejected

| Reason | What to do |
|---|---|
| Not a SQL report (Timeline / Users / etc.) | Nothing — there is no SQL to import. |
| `%%USERID%%` | Import is blocked. Instead, after importing rebuild it manually, or use the **Restrict to viewing user** setting on the draft to scope rows per user. |
| `%%FILTER_*%%` (interactive filters) | Import the query without the filter, then add a **Report Builder filter** on the published report. |
| `%%CATEGORYID%%` or other unknown token | No equivalent exists; rewrite by hand. |
| MySQL-only date function with no clean mapping (`DATEDIFF`, `DATE_ADD`, `DATE_SUB`, `STR_TO_DATE`) | Rewrite using `%%TIMESTAMP%%` / `%%EPOCH%%` / `%%NOW%%` and arithmetic. |
| Unknown date-format specifier | Use a supported specifier (table above). |
| Fails the static validator | Denied table/column, multiple statements, etc. — see the message. |
| Fails the live dry-run | References a table or column that no longer exists on this site (a common cause is the legacy `log` table on newer Moodle), or a query that errors when run. Fix the SQL in Configurable Reports first, or rewrite on import. |

## Notes and limitations

- Imported reports are always **drafts**. Nothing is published automatically, so an
  import can never expose data on its own.
- Only the portable parts of a report travel: name, description (from the Configurable
  Reports summary, stripped to plain text), SQL, course scope and visibility. Charts,
  ownership, schedules and column metadata are not imported.
- A course-scoped report keeps its course only if that course still exists on this site;
  otherwise it is demoted to site-wide and you are told which ones.
- Re-running the importer will create duplicates if you import the same report twice —
  it does not de-duplicate by name (unlike the bundled samples loader).

## For developers

- Translation and classification live in
  `classes/local/cr_import.php` (`discover()`, `classify()`, `convert()`, `import()`).
- The admin page is `import_cr.php`, registered as the `report_sql_importcr`
  external page in `settings.php`.
- Accepted reports are handed to `\report_sql\local\transfer::import()`, so they
  share the standard re-validation, courseid-demotion and draft-creation path.
- Tests: `tests/cr_import_test.php`.
- Design rationale and the measured import rate against the public jleyva report
  repository are in [`import_cr_plan.md`](import_cr_plan.md).
