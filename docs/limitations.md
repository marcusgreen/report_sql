# What Report SQL cannot do

This plugin turns a SQL query into a configurable report with no PHP. It handles a lot, but people
often expect features it does not have. This page lists the common surprises and what to do instead.

## Writing and running queries

- **Read-only reports only.** A query can look up data (`SELECT`), but it cannot change anything.
  No inserting, updating or deleting rows, no creating tables, no calling stored procedures.

- **You cannot use `SELECT *` when joining tables.** If two joined tables both have a column with the
  same name (both have an `id`, for example), publishing fails. List and rename the columns instead,
  e.g. `SELECT u.id AS userid, c.id AS courseid`.

- **The report cannot ask the viewer questions each time it runs.** Some older report tools popped up a
  box asking "enter a start date" or "pick a user" before showing results. Report SQL does not do this —
  the query is fixed when you publish it. See [No pop-up prompts](#no-pop-up-prompts) below for what to
  use instead.

- **Some tables are off limits.** For safety, the plugin blocks queries against sensitive tables such as
  site configuration and login sessions. You cannot build a report on those.

## No pop-up prompts

**What people expect.** Older ad-hoc report plugins let a query say "show me everything after this date"
and Moodle would ask the person for the date every time they opened the report. It is natural to expect
Report SQL to do the same — "make the report ask me for a date range / a user / a course each run".

**Why it doesn't.** When you publish, the query is frozen into a fixed database view. There is no slot in
that view for a value the viewer types in later. Queries imported from the old *Ad-hoc Database Queries*
plugin that rely on these prompts are refused during import, and the plugin tells you which one caused it,
rather than importing a broken report.

**What to do instead.** Rebuild the interactivity with the report's own filters:

- Add the column to the report and turn on a **filter** for it. The viewer then picks the date range or
  value in the report itself — same result, nicer interface.
- To always show "just my rows", set the report's **per-user column** so it automatically limits results
  to the person viewing it.
- To scope by course, use the built-in course settings rather than a typed-in course id.

## Charts

- **Chart images do not appear in spreadsheet exports.** A chart report shows the graph on screen and in
  emailed HTML reports. If you export it to CSV, Excel or PDF, the chart cell comes out empty — those
  formats hold data, not pictures. Schedule chart reports as **HTML** email, not as a spreadsheet.

## Editing after publishing

- **Editing the query rebuilds the report.** Column settings are locked in at publish time. If you go back
  and change the SQL, the report is torn down and rebuilt on the next publish, and any per-column tweaks
  you made in Report Builder are lost. Get the query right first, then customise columns.

## Sharing between sites

- **Imported reports arrive as drafts.** When you import a query (from a file, the samples, or an old
  plugin) it lands as a fresh unpublished draft owned by you. You have to publish it again on the new site.
  Not every setting travels with it, so double-check the course scope and filters after importing.

## Who can see the report

- **Hiding a query in this plugin does not hide the report's data.** The plugin's own visibility settings
  only control the plugin's own pages. Who can open the actual report is decided by Report Builder's own
  permission and audience settings, which the plugin sets from the query's course and visibility when you
  publish. If you need to restrict who sees the data, set the audience correctly — do not rely on hiding
  the query in the list.

## Different databases

- **Some queries only work on MySQL/MariaDB.** The plugin translates common date handling so most reports
  run on both MySQL and PostgreSQL. But a few MySQL-only functions have no PostgreSQL equivalent — a report
  using them will import and run on MySQL, and be refused on PostgreSQL. Use the built-in date tokens where
  possible so your report stays portable.

## Database permissions

- **The database user needs permission to create views.** Publishing creates a database view, so the
  Moodle database account must be allowed to create and drop views. On some shared hosting this is turned
  off and publishing will fail. There is a test for this under
  **Site admin → Reports → SQL Report → Run database view privilege test**.
