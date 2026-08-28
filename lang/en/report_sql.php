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

/**
 * English language strings for the SQL Report plugin.
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['actions'] = 'Actions';
$string['addnew'] = 'New SQL report';
$string['createfeaturesnote'] = 'Save this query as a draft, then publish it to unlock more options — charts and per-user and per-course filters — which need the published report\'s columns to configure.';
$string['ai:copied'] = 'Copied';
$string['ai:copy'] = 'Copy';
$string['ai:generate'] = 'Generate SQL';
$string['ai:generatedname'] = 'Generated query';
$string['ai:generating'] = 'Generating…';
$string['ai:heading'] = 'Generate SQL with AI';
$string['ai:heading_help'] = 'Describe the data you want in plain English, then click **Generate SQL**. The AI writes a SELECT query into the SQL editor below.

For example: "Show all students enrolled in more than 3 courses".

You can also refer to the SQL already in the editor — prompts like "add a column to this", "also show the email address", or "fix this error" use your current query as the starting point rather than building a new one from scratch.

In particular, starting your prompt with the word **also** pulls in your existing SQL and builds on it — for example "also show the user\'s last login" adds to the current query instead of replacing it.

Always review the generated SQL before saving — the AI can make mistakes.';
$string['ai:latency'] = 'Generated in {$a} s — review the SQL before saving.';
$string['ai:placeholder'] = 'e.g. Show all students enrolled in more than 3 courses';
$string['ai:prompt'] = 'Prompt sent to the LLM';
$string['ai:question'] = 'Describe the data you want';
$string['ai:sqldescription'] = 'Selects {$a->columns} from {$a->tables}.';
$string['ai:sqldescriptionnocols'] = 'Report over {$a}.';
$string['ai:sqlname'] = '{$a} report';
$string['audienceallusers'] = 'All site users';
$string['audiencecohort'] = 'Members of cohorts';
$string['audiencecohorts'] = 'Cohorts';
$string['audiencecourseparticipant'] = 'Course participants';
$string['audiencecourseparticipantdesc'] = 'Users with an active enrolment in the report\'s course.';
$string['audiencecourserole'] = 'Users with a role in the course';
$string['audiencecourseroledesc'] = 'Users holding one of the chosen roles in the report\'s course (or an ancestor context).';
$string['audiencedefault'] = 'Automatic (based on course and visibility)';
$string['audiencenone'] = 'Nobody (only you and site managers)';
$string['audienceroles'] = 'Roles';
$string['audiencesettings'] = 'Who can view the report';
$string['audiencetype'] = 'Audience';
$string['audiencetype_help'] = 'Controls who can open the published Report Builder report.

* **Automatic** — derived from the settings above: a course-scoped report is shown to that course\'s participants, a site-wide report to all users, and a hidden report only to you and site managers.
* **Course participants / Users with a role in the course** — require a course scope to be set above.
* **All site users**, **Members of cohorts**, **Nobody** — apply site-wide.

You can refine the audience further on the Audiences tab in Report Builder, but re-publishing the report resets it to this choice.';
$string['bulkactions'] = 'Bulk actions';
$string['cachedef_schema'] = 'Database schema and foreign-key map for editor autocomplete';
$string['chartbar'] = 'Bar chart';
$string['chartcolumn'] = 'Chart';
$string['chartdatalabels'] = 'Show value labels';
$string['chartdatalabels_help'] = 'Draws each plotted value as a number on top of its bar or beside its line point, so exact figures can be read straight off the chart. Applies to bar and line charts only (pie / doughnut show values in the legend). Suppressed automatically when a series has too many points to label without overlap. Off by default.';
$string['chartdatalabelslabel'] = 'Print each value on the bar / line chart';
$string['chartdoughnut'] = 'Doughnut chart';
$string['chartdownloadpng'] = 'Download PNG';
$string['chartexportcsv'] = 'Export CSV';
$string['chartlabelsize'] = 'Label text size';
$string['chartlabelsize_help'] = 'Font size (in points) for the chart\'s category labels — the pie legend and the bar / line x-axis labels.';
$string['chartlabelsizeoption'] = '{$a} pt';
$string['chartline'] = 'Line chart';
$string['chartmulticolour'] = 'Multi-colour bars';
$string['chartmulticolour_help'] = 'Gives each bar its own colour from a colour-blind-friendly palette instead of one shared colour, so categories are easier to tell apart. Bar charts only (pie and doughnut are already coloured per slice; a line is a single series). Off by default.';
$string['chartmulticolourlabel'] = 'Colour each bar differently';
$string['chartnone'] = 'No chart';
$string['chartpie'] = 'Pie chart';
$string['chartprint'] = 'Print';
$string['chartpublishrequired'] = 'Publish this query to configure its chart.';
$string['chartpublishrequiredsee'] = 'See the option below.';
$string['chartreportname'] = '{$a} (chart)';
$string['chartrowlimit'] = 'Chart row limit';
$string['chartrowlimit_help'] = 'Maximum rows to plot. Keep small (≤ 200) for readable charts.';
$string['chartsettings'] = 'Chart settings';
$string['chartshowdata'] = 'Show data table';
$string['chartshowdata_help'] = 'Renders the chart\'s label and value pairs as a table below the chart image on the chart report. Provides a text alternative for screen readers and lets viewers read exact numbers. Off by default.';
$string['chartshowdatalabel'] = 'Show the plotted values as a table beneath the chart';
$string['charttype'] = 'Chart type';
$string['chartxcol'] = 'Label column (X axis / slices)';
$string['chartxcol_help'] = 'Column whose values label each bar, point, or pie slice.';
$string['chartycol'] = 'Value column (Y axis)';
$string['chartycol_help'] = 'Column whose values are plotted. Must contain numeric data.';
$string['checkallgood'] = 'No issues found. The query looks good.';
$string['checkcasecolumnsintro'] = 'These columns apply UPPER()/LOWER() in SQL:';
$string['checkcasecolumnsintroone'] = 'This column applies UPPER()/LOWER() in SQL:';
$string['checkcasecolumnsmanual'] = 'Could not locate this column\'s expression automatically — switch it to %%CASE()%% by hand.';
$string['checkcasecolumnsoutro'] = 'Click the name to switch it to %%CASE()%% so the case is applied on display while the column still sorts and filters on the original value (and stays portable across databases).';
$string['checkdatecolumnsintro'] = 'These columns look like dates:';
$string['checkdatecolumnsintroone'] = 'This column looks like a date:';
$string['checkdatecolumnsmanual'] = 'Could not locate this column\'s expression automatically — wrap it in %%TIMESTAMP()%% by hand.';
$string['checkdatecolumnsoutro'] = 'Click the name to wrap its expression in %%TIMESTAMP()%% so it displays as a formatted, sortable date.';
$string['checkdistinctlarge'] = 'SELECT DISTINCT over {$a} rows must sort and de-duplicate the whole result, which is slow at this size. Consider GROUP BY on indexed columns, or drop DISTINCT if the joins already yield unique rows.';
$string['checkfullscan'] = 'Full table scan on "{$a->table}" (~{$a->rows} rows), no index used. This report may be slow — add a WHERE filter on an indexed column. Indexed columns: {$a->indexed}.';
$string['checklargeresult'] = 'This query returns {$a} rows. Large results render slowly — add a filter or a LIMIT.';
$string['checkleadingwildcard'] = 'A LIKE pattern starts with a wildcard ("%…" or "_…"). A leading wildcard stops the database using an index on that column, forcing a full scan. Anchor the pattern ("abc%") where you can.';
$string['checknonsargable'] = 'A function wraps a column in the WHERE clause (e.g. DATE(col) or LOWER(col)). This is non-sargable — the database cannot use an index on that column. Filter the bare column instead (e.g. a range comparison, or compare a stored epoch).';
$string['checkquery'] = 'Test query';
$string['checkquery_help'] = 'Runs your SQL against the database without saving or publishing, then reports back. It checks that the query is valid and executes, counts the rows it returns, and flags likely performance issues — full table scans, missing indexes, non-sargable filters, large or DISTINCT result sets — plus date columns you may want to wrap in %%TIMESTAMP()%%.

This is advisory only: it never changes your data and is not required before you save or publish.';
$string['checkrowcount'] = 'Rows returned: {$a}.';
$string['checkselectsubquery'] = 'A subquery in the SELECT list is evaluated once per returned row, multiplying work on a large result. A JOIN or a WITH (CTE) is usually faster.';
$string['checksortindex'] = 'The report sorts by {$a->sortcol}, which is not indexed, so the database orders the whole result. Sorting by an indexed column is faster — indexed columns available: {$a->indexed}.';
$string['compiledsql'] = 'Compiled SQL (what actually ran)';
$string['confirmdeletemany'] = 'Are you sure you want to delete these {$a} report source(s)? This drops each backing view and report and cannot be undone.';
$string['convertaliasspaces'] = 'Replace spaces in the column alias with underscores automatically';
$string['convertquestionmark'] = 'Convert ? inside quotes to CHAR(63) automatically';
$string['copyof'] = 'Copy of {$a}';
$string['copysuccess'] = 'Report source copied. You are now editing the copy.';
$string['coursecolumn'] = 'Restrict to courses the viewer teaches';
$string['coursecolumn_help'] = 'Optionally scope this report so each viewer sees only rows for courses they teach. Pick the output column holding a course id; at view time the report shows only rows where that column is one of the courses where the viewer has an editing teacher or teacher role.

A viewer who teaches no courses sees no rows. This lets you publish a single report to a wide audience (for example all staff) while each teacher still sees only their own courses. Leave as "Choose a column…" for no teacher-course filter.';
$string['coursescope'] = 'Course scope';
$string['coursescope_help'] = 'The course this report belongs to. Leave empty for a site-wide report.

The course determines two things when the report is published: the context its "View report" permission is checked in, and its default audience (course participants for a course-scoped report, all users for a site-wide one).

Change this to re-scope a query — for example an imported draft that was set site-wide because its original course did not exist on this site. You can only choose courses you are allowed to view reports in.';
$string['createrole:aigenerate'] = 'Include "AI SQL generation"';
$string['createrole:aigenerate_desc'] = 'Also grant local/sqlchat:use, so holders can use the AI question box to generate SQL. Only shown when the local_sqlchat plugin is installed. Leave unticked if authors should write SQL themselves.';
$string['createrole:approve'] = 'Include "Approve and publish"';
$string['createrole:approve_desc'] = 'Also grant report/sql:approve, so holders can publish and unpublish report sources themselves. Leave unticked if a separate approver should publish their drafts.';
$string['createrole:author'] = 'Author report sources';
$string['createrole:author_desc'] = 'Always included: report/sql:author lets holders write and save report sources (the purpose of the role). Also always granted are moodle/reportbuilder:view, moodle/reportbuilder:viewall and moodle/reportbuilder:editall, so holders can open and edit any published report at /reportbuilder/view.php regardless of its audience or owner.';
$string['createrole:create'] = 'Create role';
$string['createrole:done'] = 'The "Report author" role was created. Assign people to it below.';
$string['createrole:exists'] = 'A "Report author" role already exists. Submitting this form will update its capabilities to match your selection below.';
$string['createrole:intro'] = 'This creates a system-level role bundling the report-source capabilities, so you can let trusted non-administrators author reports without making them full site managers. Choose which capabilities to include, then create the role and assign people to it.';
$string['createrole:linklabel'] = 'Create the "Report author" role';
$string['createrole:title'] = 'Create the "Report author" role';
$string['createrole:updated'] = 'The "Report author" role capabilities were updated. Assign people to it below.';
$string['createrole:viewall'] = 'Include "View all report sources"';
$string['createrole:viewall_desc'] = 'Also grant report/sql:viewall, so holders can see and manage everyone\'s report sources, not only their own.';
$string['createrole:warning'] = 'Authoring a report means writing an arbitrary SQL SELECT, which can read almost any table in the database (only a small denylist such as config, sessions and password tables is blocked). This role is therefore effectively a site-wide data-read grant. Assign it only to people you would trust with direct read access to the database, and confirm any sensitive columns are covered by the column denylist in the plugin settings.';
$string['crimport:colname'] = 'Report';
$string['crimport:colnotes'] = 'Changes applied';
$string['crimport:colreason'] = 'Reason';
$string['crimport:coltype'] = 'Type';
$string['crimport:importableheading'] = 'Importable reports';
$string['crimport:importselected'] = 'Import selected';
$string['crimport:intro'] = 'These are the SQL reports found in the Configurable Reports block. Importable reports translate cleanly and will be created as drafts owned by you, ready to publish. Rejected reports use features that cannot be converted automatically — port those by hand.';
$string['crimport:linklabel'] = 'Import from Configurable Reports';
$string['crimport:noneimportable'] = 'No Configurable Reports SQL reports could be translated automatically. See the rejected list below for why.';
$string['crimport:noneselected'] = 'No reports were selected.';
$string['crimport:noteclean'] = 'No changes needed';
$string['crimport:notedatefn'] = 'Rewrote MySQL date function(s) to portable %%TIMESTAMP%% / %%EPOCH%% / %%NOW%% tokens';
$string['crimport:notenativedate'] = 'Kept native MySQL date function(s) {$a} — they run on this MySQL/MariaDB database, but the imported report will not be portable to PostgreSQL';
$string['crimport:noteqmark'] = 'Rewrote literal ? in a string to chr(63)';
$string['crimport:notequotes'] = 'Converted "double-quoted" string literals to \'single-quoted\'';
$string['crimport:notetoken'] = 'Substituted Configurable Reports token {$a}';
$string['crimport:reasondatefn'] = 'Uses MySQL-only date function {$a} that has no portable equivalent';
$string['crimport:reasonfilter'] = 'Uses an interactive filter token {$a}; rebuild this as a Report Builder filter after importing';
$string['crimport:reasonnosql'] = 'No SQL could be decoded from this report';
$string['crimport:reasonnotsql'] = 'Not a SQL report (type: {$a})';
$string['crimport:reasontoken'] = 'Uses an unsupported token {$a}';
$string['crimport:reasonuserid'] = 'Uses {$a}; use the "Restrict to viewing user" setting on the imported draft instead';
$string['crimport:rejectedheading'] = 'Rejected reports';
$string['crimport:title'] = 'Import from Configurable Reports';
$string['crimport:title_help'] = 'Imports the SQL reports stored in the Configurable Reports block (block_configurable_reports) as draft report sources.

Each report is decoded and run through a fixed translation: MySQL date functions become portable %%TIMESTAMP%% / %%EPOCH%% / %%NOW%% tokens, double-quoted strings become single-quoted, and a literal ? in a string is rebuilt with chr(63). Reports using features that cannot be converted (such as %%USERID%% or interactive %%FILTER%% tokens) are listed as rejected with a reason.

Imported reports land as drafts owned by you and must be published before they go live. No AI is used — every conversion is a fixed rule.';
$string['crimport:unavailable'] = 'The Configurable Reports block (block_configurable_reports) is not installed, so there is nothing to import.';
$string['customsqlimport:intro'] = 'These are the queries found in the Ad-hoc Database Queries report (report_customsql). Importable queries translate cleanly and will be created as drafts owned by you, ready to publish. Rejected queries use features that cannot be converted automatically — port those by hand.';
$string['customsqlimport:linklabel'] = 'Import from Ad-hoc Database Queries';
$string['customsqlimport:noneimportable'] = 'No Ad-hoc Database Queries could be translated automatically. See the rejected list below for why.';
$string['customsqlimport:noteescape'] = 'Substituted customsql escape token(s) (%%Q%% / %%C%% / %%S%%) with their literal characters';
$string['customsqlimport:reasonparam'] = 'Uses the interactive named parameter {$a}; rebuild this as a Report Builder filter after importing';
$string['customsqlimport:title'] = 'Import from Ad-hoc Database Queries';
$string['customsqlimport:title_help'] = 'Imports the queries stored in the Ad-hoc Database Queries report (report_customsql) as draft report sources.

Each query is run through a fixed translation: MySQL date functions become portable %%TIMESTAMP%% / %%EPOCH%% / %%NOW%% tokens, double-quoted strings become single-quoted, customsql escape tokens (%%Q%% / %%C%% / %%S%%) become their literal characters, and a literal ? in a string is rebuilt with chr(63). Queries using features that cannot be converted (such as %%USERID%% or interactive :named parameters) are listed as rejected with a reason.

Imported queries land as drafts owned by you and must be published before they go live. customsql has no per-course scope, so every draft starts site-wide. No AI is used — every conversion is a fixed rule.';
$string['customsqlimport:unavailable'] = 'The Ad-hoc Database Queries report (report_customsql) is not installed, so there is nothing to import.';
$string['delete'] = 'Delete';
$string['deleteselected'] = 'Delete selected';
$string['deleteselecthelp'] = 'Tick the report sources to delete. Deleting drops each backing database view and report and cannot be undone.';
$string['description'] = 'Description';
$string['duplicate'] = 'Duplicate';
$string['edit'] = 'Edit';
$string['editreport'] = 'Edit in report builder';
$string['embedcodecopied'] = 'Embed code copied';
$string['embedcodecopy'] = 'Copy embed code';
$string['entityquery'] = 'Report source';
$string['erraliasspaces'] = 'The column alias "{$a}" contains spaces. Column aliases in SQL Report cannot have spaces — use an underscore or camel case instead, e.g. SELECT firstname AS first_name FROM user. You can rename the column with a space after publishing, using Report Builder.';
$string['erraudiencecohortsempty'] = 'Choose at least one cohort.';
$string['erraudiencecourse'] = 'This audience applies to a course. Choose a course scope above before selecting it.';
$string['erraudiencerolesempty'] = 'Choose at least one role.';
$string['errchartdata'] = 'The report data for this chart could not be loaded. Contact the report owner if this persists.';
$string['errchartnotconfigured'] = 'No chart is configured for this query. Edit the query to add chart settings.';
$string['errchartnotpublished'] = 'This query is not published. Publish it first before viewing the chart.';
$string['errcolumnnoalias'] = 'The column "{$a}" is an expression with no name. Give every calculated or aggregate column an alias, e.g. SELECT count(*) AS total FROM course.';
$string['errcourseidplaceholder'] = 'The SQL uses %%COURSEID%%, so this report needs a fixed course scope. Choose a course above before saving — or, to show each course its own data in a block, remove the %%COURSEID%% filter from the SQL, output the course id column, and set "Restrict to the course the block is on" instead.';
$string['errcreateview'] = 'Could not create database view: {$a}';
$string['errdeniedcolumn'] = 'Disallowed column: {$a}';
$string['errdeniedkeyword'] = 'Disallowed keyword: {$a}';
$string['errdeniedtable'] = 'Disallowed table: {$a}';
$string['errdropview'] = 'Could not drop database view: {$a}';
$string['errduplicatecolumn'] = 'Joined tables share duplicate column names (e.g. both have "id"). Replace SELECT * with explicit column aliases: SELECT u.id AS userid, fp.id AS postid, ...';
$string['errimportempty'] = 'The export file contains no report sources.';
$string['errimportformat'] = 'This file is not a valid SQL Report export.';
$string['errjoinnoon'] = 'A JOIN is missing its ON (or USING) condition. Each JOIN needs a join condition, e.g. JOIN {user_enrolments} ue ON ue.userid = u.id';
$string['errmultistatement'] = 'Multiple statements are not allowed.';
$string['errnodeleteselection'] = 'Select at least one report source to delete.';
$string['errnoexportselection'] = 'Select at least one report source to export.';
$string['errnoimportselection'] = 'Select at least one report source to import.';
$string['errnotselect'] = 'Only SELECT queries are allowed.';
$string['errparse'] = 'The SQL could not be parsed: {$a}';
$string['errpgsqldatefn'] = 'PostgreSQL-only function {$a} is not supported by MySQL. Use a cross-database equivalent.';
$string['errplaceholder'] = 'The SQL contains an unfilled placeholder "{$a}". Replace it with a real value before saving — e.g. change "l.userid = ##" to "l.userid = 2".';
$string['errplaceholderuserid'] = 'The SQL contains "{$a}", which is not a supported placeholder. There is no per-viewer placeholder because the report runs from a fixed database view. To restrict the report to the rows for whoever opens it, remove "{$a}" from the SQL, select the user-id column in the "Restrict to viewing user" field at the end of this form, and the per-user filter is applied automatically at run time.';
$string['errqualifiedtable'] = 'Schema-qualified table reference "{$a}" is not allowed. Reports may only read the site\'s own tables using Moodle\'s {tablename} syntax; cross-schema or cross-database references (e.g. information_schema.columns) are blocked.';
$string['errquestionmark'] = 'SQL contains a ? character, which the database layer treats as a query parameter placeholder. If ? appears inside a URL string, replace it with CHAR(63) — e.g. CONCAT(\'…/view.php\', CHAR(63), \'id=\', course.id).';
$string['event:querycreated'] = 'Ad-hoc query created';
$string['event:querydeleted'] = 'Ad-hoc query deleted';
$string['event:querypublished'] = 'Ad-hoc query published';
$string['event:queryunpublished'] = 'Ad-hoc query unpublished';
$string['event:queryupdated'] = 'Ad-hoc query updated';
$string['export'] = 'Export';
$string['exportselected'] = 'Export selected';
$string['exportselecthelp'] = 'Tick the report sources to include in the export file, then download the JSON.';
$string['filterpublishrequired'] = 'Publish this query first to enable the per-user and per-course filters.';
$string['focuschart'] = 'When I click Save and publish, reopen this form so I can configure the chart';
$string['focusfilter'] = 'When I click Save and publish, reopen this form so I can configure the per-user and per-course filters';
$string['formatsql'] = 'Format SQL';
$string['formatsqltooltip'] = 'Reformat SQL to standard layout (Shift+Ctrl+F)';
$string['import'] = 'Import';
$string['importdemoted'] = 'Set to site-wide because their course was not found on this site. Edit each draft and set its Course scope before publishing: {$a}.';
$string['importdone'] = 'Imported {$a} report source(s) as drafts.';
$string['importfile'] = 'Export file';
$string['importselected'] = 'Import selected';
$string['importselecthelp'] = 'Tick the report sources to import. Each is created as a new draft owned by you and must be published before use.';
$string['importskipped'] = 'Skipped (failed SQL validation): {$a}.';
$string['importupload'] = 'Upload and choose';
$string['importuploadhelp'] = 'Upload a JSON file previously produced by the Export action. You will then choose which report sources to import.';
$string['install:createrole'] = 'Optionally create a "Report author" role so non-administrators can author reports. Review the security implications first: {$a}';
$string['install:loadsamples'] = 'SQL Report ships sample SQL reports you can load to get started: {$a}';
$string['install:privilegefail'] = 'SQL Report installed, but the database user cannot create or drop views. Publishing queries will fail until the grants are fixed. Error: {$a}';
$string['install:privilegeok'] = 'SQL Report: the database user can create and drop views.';
$string['lastmodified'] = 'Last modified';
$string['name'] = 'Name';
$string['noqueries'] = 'No report sources yet.';
$string['norows'] = 'No data to display.';
$string['owner'] = 'Owner';
$string['pagecoursecolumn'] = 'Restrict to the course the block is on';
$string['pagecoursecolumn_help'] = 'Applies only when this report is shown through the SQL Report block on a course page. Pick the output column holding a course id; the block then shows only rows for the course of the page it sits on, so one block (or a block added to every course) shows each course its own data.

Off a course page (Dashboard or the site front page) no page-course filter is applied. The standalone report viewer also ignores this, since it has no "current course". Leave as "Choose a column…" for no page-course filter.';
$string['plugindisabled'] = 'SQL Report is currently disabled by the site administrator.';
$string['pluginexplained'] = 'About report sources';
$string['pluginexplained_help'] = 'This plugin lets you write a SQL SELECT query and publish it as a fully-configurable Report Builder report — no PHP required.

When you publish a query, the plugin creates a database VIEW from your SQL, reads its columns, and registers a Report Builder datasource pointing at that view. You can then build, filter and share the report like any other Report Builder report.

Only SELECT queries are allowed, and a denylist blocks access to sensitive tables. Editing the SQL of a published query rebuilds the view and report on the next publish.';
$string['pluginname'] = 'SQL Report';
$string['preview'] = 'Preview first 5 rows';
$string['preview_help'] = 'Renders your current SQL as a real Report Builder report, inline and without saving or publishing. Columns are typed and formatted exactly as they would be on publish, so this is a quick way to see what the report will look like. Only the first 5 rows are shown.';
$string['previewheading'] = 'Preview result';
$string['previewloading'] = 'Building preview…';
$string['privacy:metadata:query'] = 'Saved report sources authored by users.';
$string['privacy:metadata:query:ownerid'] = 'User who authored the query.';
$string['privacy:metadata:query:querysql'] = 'The SQL of the query.';
$string['privacy:metadata:query:timecreated'] = 'When the query was created.';
$string['privacy:metadata:queryview'] = 'A log of which published report sources each user has opened.';
$string['privacy:metadata:queryview:timeviewed'] = 'When the report source was opened.';
$string['privacy:metadata:queryview:userid'] = 'User who opened the report source.';
$string['publish'] = 'Publish';
$string['queries'] = 'Saved SQL reports';
$string['querysql'] = 'SQL (SELECT only)';
$string['querysql_help'] = 'A single SELECT or WITH...SELECT statement. Use Moodle table syntax (e.g. {course}). The plugin creates a database VIEW from this query and exposes its columns as a Reportbuilder source.

Always alias tables (e.g. FROM {user} u) since {user} resolves to mdl_user at runtime.

Wrap a text column in %%CASE(expr, mode)%% to display it in upper, lower, title or sentence case (e.g. %%CASE(u.lastname, upper)%%). The stored value is unchanged, so the column still sorts and filters on the original text, and the transform works the same on MySQL/MariaDB and PostgreSQL.

For the Moodle database schema see <a href="https://www.examulator.com/er/output/index.html" target="_blank">examulator.com/er</a>.

For sample queries and inspiration see <a href="https://docs.moodle.org/502/en/ad-hoc_contributed_reports" target="_blank">Moodle ad-hoc contributed reports</a>.';
$string['reportsource'] = 'Report source';
$string['reportsourceheader'] = '{$a}';
$string['reportsources'] = 'SQL Reports';
$string['repository:intro'] = 'These shared report sources come from the remote repository {$a}. Tick the ones you want and import them. Each is created as a draft owned by you that you must publish before use.';
$string['repository:introsingle'] = 'These shared report sources come from the remote repository {$a}. Choose one to import. It is created as a draft owned by you, named with a "Sample:" prefix, that you must publish before use.';
$string['repository:linklabel'] = 'Import from shared repository';
$string['repository:none'] = 'No shared report sources could be read from the repository {$a}. Check the URL and that the repository contains report source export files.';
$string['repository:noneselected'] = 'No report sources were selected.';
$string['repository:refresh'] = 'Refresh from repository';
$string['repository:title'] = 'Shared repository report sources';
$string['repository:titlesingle'] = 'Import a shared report source';
$string['repository:unconfigured'] = 'No shared repository is configured. Set one in the plugin settings (Shared report source repository) first.';
$string['roledescription'] = 'Create, edit and publish report sources (report_sql) site-wide. NOTE: authoring allows arbitrary SQL SELECT against the database, so this role grants effectively site-wide data read. Assign only to trusted report builders.';
$string['rolename'] = 'Report author';
$string['runreport'] = 'Open report';
$string['samples:coldesc'] = 'Description';
$string['samples:colname'] = 'Name';
$string['samples:colselect'] = 'Import';
$string['samples:duplicates'] = 'Skipped (already present): {$a}.';
$string['samples:import'] = 'Import';
$string['samples:importselected'] = 'Import selected';
$string['samples:intro'] = '{$a} sample report sources are bundled with this plugin. Tick the ones you want and import them. Each is created as a draft owned by you that you must publish before use.';
$string['samples:introsingle'] = '{$a} sample report sources are bundled with this plugin. Choose one to import. It is created as a draft owned by you, named with a "Sample:" prefix, that you must publish before use.';
$string['samples:linklabel'] = 'Load sample SQL reports';
$string['samples:none'] = 'No bundled sample report sources were found.';
$string['samples:noneselected'] = 'No samples were selected.';
$string['samples:previewsql'] = 'Show SQL';
$string['samples:samplelinklabel'] = 'Load sample SQL report';
$string['samples:sampleprefix'] = 'Sample: {$a}';
$string['samples:selectall'] = 'Select all';
$string['samples:selectnone'] = 'Select none';
$string['samples:title'] = 'Load sample SQL reports';
$string['samples:titlesingle'] = 'Load sample SQL report';
$string['saveandpublish'] = 'Save and publish';
$string['savedandpublished'] = 'Changes saved and report published';
$string['savedpublishfailed'] = 'Changes saved, but publishing failed: {$a}';
$string['schedule'] = 'Schedule emails';
$string['selectcolumn'] = '(select column)';
$string['settings:aigenerate'] = 'AI SQL generation';
$string['settings:aigenerate_desc'] = 'Show an AI question box on the query edit form. Requires the local_sqlchat plugin to be installed and configured.';
$string['settings:denycolumns'] = 'Sensitive column denylist';
$string['settings:denycolumns_desc'] = 'Comma, space or new-line separated list of column names that will be stripped from any introspected SELECT result.';
$string['settings:denytables'] = 'Table denylist';
$string['settings:denytables_desc'] = 'Comma, space or new-line separated list of table names that may never be queried. Seeded with the plugin\'s built-in list of protected tables (config, sessions, tokens, password history, and similar). This list is fully editable — removing an entry allows that table to be queried, so edit with care.';
$string['settings:enumfilterthreshold'] = 'Dropdown filter threshold';
$string['settings:enumfilterthreshold_desc'] = 'When a text column has this many distinct values or fewer (measured at publish time), its report filter is rendered as a dropdown of those values instead of a free-text box. Set to 0 to disable and keep all text columns as free-text filters.';
$string['settings:enumrowceiling'] = 'Dropdown filter row ceiling';
$string['settings:enumrowceiling_desc'] = 'Skip dropdown-filter detection when a published view has more than this many rows, so a large report does not pay a per-column distinct scan at publish time (all its text columns stay free-text). Set to 0 to always probe regardless of size. Only relevant when the dropdown filter threshold is non-zero.';
$string['settings:enabled'] = 'Enable SQL Report';
$string['settings:enabled_desc'] = 'When unticked the plugin is disabled: its entry is removed from the Reports menu and its pages (list, edit, run, chart) are blocked. Published reports created through Report Builder are unaffected.';
$string['settings:sharedrepository'] = 'Shared report source repository';
$string['settings:sharedrepository_desc'] = 'GitHub repository URL holding shared report source export files. Authors can browse it and import its report sources as drafts. Leave blank to disable the shared repository browser.';
$string['settings:sharedrepositoryenabled'] = 'Enable shared report source repository';
$string['settings:sharedrepositoryenabled_desc'] = 'Allow authors to browse and import from the shared report source repository configured below. Disabled by default; while off, no request is made to the remote repository and its browse page and links are hidden.';
$string['settings:showlastmodified'] = 'Show last modified column';
$string['settings:showlastmodified_desc'] = 'Show a sortable "Last modified" column in the report sources list.';
$string['settings:syntaxhighlight'] = 'SQL syntax highlight and autocomplete';
$string['settings:syntaxhighlight_desc'] = 'Enable a CodeMirror 6 SQL editor on the query form. Suggests SQL keywords plus Moodle table and column names from the live database.';
$string['settings:viewretaindays'] = 'View history retention (days)';
$string['settings:viewretaindays_desc'] = 'How many days to keep the report-view audit history. Older rows are removed by a scheduled task. Set to 0 to keep the history forever.';
$string['sql:approve'] = 'Approve and publish report sources';
$string['sql:author'] = 'Author SQL report sources';
$string['sql:view'] = 'Run published report sources';
$string['sql:viewall'] = 'View all report sources regardless of audience';
$string['sql:viewown'] = 'Run report sources in own course';
$string['status'] = 'Status';
$string['status_draft'] = 'Draft';
$string['status_published'] = 'Published';
$string['strftimeviewdate'] = '%d/%m/%y, %H:%M';
$string['summaryreport'] = 'Summary report';
$string['task:purgeviews'] = 'Purge old report-view history';
$string['testquery'] = 'Testing…';
$string['testview:fail'] = 'The database user cannot create or drop views. Error: {$a}';
$string['testview:grantshint'] = 'Grant the Moodle database user CREATE VIEW and DROP privileges on the schema (e.g. on MySQL/MariaDB: GRANT CREATE VIEW, DROP ON moodle.* TO \'mdluser\'@\'host\';).';
$string['testview:linklabel'] = 'Run database view privilege test';
$string['testview:ok'] = 'The database user can create and drop views. Publishing queries should work.';
$string['testview:title'] = 'Database view privilege test';
$string['timecreated'] = 'Time created';
$string['tokenhintcase'] = 'Text case transform: upper | lower | title | sentence (display only)';
$string['tokenhintcontextblock'] = 'CONTEXT_BLOCK level constant (80)';
$string['tokenhintcontextcourse'] = 'CONTEXT_COURSE level constant (50)';
$string['tokenhintcontextcoursecat'] = 'CONTEXT_COURSECAT level constant (40)';
$string['tokenhintcontextmodule'] = 'CONTEXT_MODULE level constant (70)';
$string['tokenhintcontextsystem'] = 'CONTEXT_SYSTEM level constant (10)';
$string['tokenhintcontextuser'] = 'CONTEXT_USER level constant (30)';
$string['tokenhintcoursecontext'] = "Bound course's context row id";
$string['tokenhintcourseid'] = 'Bound course id (0 = site-wide)';
$string['tokenhintepoch'] = 'Datetime literal/expression to Unix epoch integer';
$string['tokenhintnow'] = 'Current time as a Unix epoch integer';
$string['tokenhinttimestamp'] = 'Epoch column to date; optional format, e.g. dd/mm/yyyy';
$string['tokenhintwwwroot'] = 'Site URL (wwwroot)';
$string['tourdesc'] = 'A short guided tour of the report sources list page.';
$string['tourname'] = 'SQL Report tour';
$string['tourstep1content'] = 'Start here to create a report source. Write a SQL <em>SELECT</em> query, then publish it to build a fully configurable Report Builder report — no PHP required.';
$string['tourstep1title'] = 'Create a report source';
$string['tourstep2content'] = 'Every report source you have saved is listed here, with its owner and status. Sort or filter any column to find one quickly.';
$string['tourstep2title'] = 'Your report sources';
$string['tourstep3content'] = 'The status shows whether a report source is still a <strong>Draft</strong> or has been <strong>Published</strong> as a live report.';
$string['tourstep3title'] = 'Draft or published';
$string['tourstep4content'] = 'Edit your query here, or publish a draft to build its live Report Builder report. Unpublishing takes a live report back offline.';
$string['tourstep4title'] = 'Edit and publish';
$string['tourstep5content'] = 'This menu holds the rest of the actions: edit in Report Builder, view the chart, schedule email delivery, copy the embed code, duplicate and delete.';
$string['tourstep5title'] = 'More actions';
$string['tsfmtdd'] = 'Day, 2 digits (05)';
$string['tsfmtddd'] = 'Weekday, short (Mon)';
$string['tsfmtdddd'] = 'Weekday, full (Monday)';
$string['tsfmthelpintro'] = 'Add an optional format as a second argument, e.g. <code>%%TIMESTAMP(u.timecreated, dd/mm/yyyy)%%</code>. Without one, dates show as <code>{$a}</code>. Separators like / - . : and spaces pass through.';
$string['tsfmthelptitle'] = 'Date display format';
$string['tsfmthelptokens'] = 'Format tokens';
$string['tsfmthh'] = 'Hour, 24-clock (17)';
$string['tsfmtmi'] = 'Minutes (20)';
$string['tsfmtmm'] = 'Month, 2 digits (06)';
$string['tsfmtmmm'] = 'Month name, short (Jun)';
$string['tsfmtmmmm'] = 'Month name, full (June)';
$string['tsfmtmon'] = 'Month name, short (Jun)';
$string['tsfmtmonth'] = 'Month name, full (June)';
$string['tsfmtss'] = 'Seconds (09)';
$string['tsfmtyy'] = 'Year, 2 digits (26)';
$string['tsfmtyyyy'] = 'Year, 4 digits (2026)';

$string['unpublish'] = 'Unpublish';




$string['usage:detaillabel'] = 'Usage detail';
$string['usage:detailtitle'] = 'Report usage: {$a}';
$string['usage:firstviewed'] = 'First viewed';
$string['usage:intro'] = 'How often each published report source has been opened. Every open of a report is recorded; sort, filter and export the list as needed. History older than the configured retention window is removed automatically.';
$string['usage:lastviewed'] = 'Last viewed';
$string['usage:linklabel'] = 'Report usage';
$string['usage:nodata'] = 'This report source has not been opened yet.';
$string['usage:perreport'] = 'Views by report';
$string['usage:recent'] = 'Recent opens';
$string['usage:report'] = 'Report';
$string['usage:reportn'] = 'Report {$a}';
$string['usage:reportsdeleted'] = 'Deleted reports';
$string['usage:title'] = 'Report usage';
$string['usage:topviewers'] = 'Top viewers';
$string['usage:trend'] = 'Views over the last 30 days';
$string['usage:uniqueviewers'] = 'Unique viewers';
$string['usage:views'] = 'Views';
$string['usage:when'] = 'When';
$string['userdocs'] = 'User documentation';
$string['useridcolumn'] = 'Restrict to viewing user';
$string['useridcolumn_help'] = 'Optionally scope this report so each person sees only rows that belong to them. Pick the output column holding a user id; at view time the report shows only rows where that column equals the id of the logged-in user. Leave as "Choose a column…" to show all rows to everyone in the audience.';
$string['useridfilter'] = 'Per-user filter';
$string['viewchart'] = 'View chart';
$string['visible'] = 'Visible';
$string['visible_help'] = 'Controls whether this published report appears in the query listing page. When unchecked, users with the view capability cannot see it. The underlying database view and report still exist — administrators and authors with the viewall capability can still see it.

For finer-grained access control, use the Audiences feature in Report Builder after publishing: open the report, go to the Audience tab, and restrict by cohort, role, or individual user.';
$string['warnmysqldatefn'] = 'MySQL-only function {$a} may not work on PostgreSQL. Use a cross-database equivalent.';
