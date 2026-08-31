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
 * Library callbacks for the SQL Report plugin.
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Block access to a plugin page when the admin has disabled the plugin.
 *
 * Call at the top of every entry-point script (after require_login). Site admins are exempt so
 * they can still reach pages to re-enable or inspect. Published Report Builder reports are core RB
 * objects and are intentionally left reachable.
 *
 * @return void
 */
function report_sql_require_enabled(): void {
    if (\report_sql\local\query::is_plugin_enabled() || is_siteadmin()) {
        return;
    }
    throw new \moodle_exception('plugindisabled', 'report_sql');
}

/**
 * Editor options for the query description field.
 *
 * Single source of truth shared by the edit form (element definition + file prepare) and
 * {@see query::save()} (file save). The description is a rich-text field that accepts embedded
 * images only. Its file area lives at system context (constant regardless of the query's course
 * scope), so re-scoping a query never orphans its embedded images.
 *
 * @return array
 */
function report_sql_description_editor_options(): array {
    return [
        'maxfiles'       => EDITOR_UNLIMITED_FILES,
        'maxbytes'       => 0,
        'trusttext'      => false,
        'subdirs'        => 0,
        'accepted_types' => ['web_image'],
        'context'        => context_system::instance(),
    ];
}

/**
 * Serve images embedded in a query description.
 *
 * The description file area is at system context, itemid = query id. Access mirrors who may see the
 * query in the plugin UI: {@see query::visible_to_current_user()} is the single source of truth, so
 * an embedded image is served only to a user who could open the query whose description holds it.
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param context $context
 * @param string $filearea
 * @param array $args First element is the query id (itemid), remainder is the file path + name.
 * @param bool $forcedownload
 * @param array $options
 * @return bool False to fall through to a 404.
 */
function report_sql_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []): bool {
    global $DB;

    if ($context->contextlevel != CONTEXT_SYSTEM || $filearea !== 'description') {
        return false;
    }

    require_login();

    $itemid = (int) array_shift($args);
    $query = $DB->get_record(\report_sql\local\query::TABLE, ['id' => $itemid]);
    if (!$query) {
        return false;
    }

    // Reuse the plugin's own visibility rule: the id must be among the queries this user may see.
    $visible = \report_sql\local\query::visible_to_current_user((int) $query->courseid);
    if (!array_key_exists($itemid, $visible)) {
        return false;
    }

    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';

    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'report_sql', 'description', $itemid, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        return false;
    }

    send_stored_file($file, null, 0, $forcedownload, $options);
    return true;
}

/**
 * Legacy global navigation hook. Kept for any theme that still renders the
 * flat navigation drawer; Boost (Moodle 4.x+) does not.
 *
 * @param global_navigation $navigation
 */
function report_sql_extend_navigation(global_navigation $navigation): void {
    global $USER;
    if (!\report_sql\local\query::is_plugin_enabled()) {
        return;
    }
    if (isloggedin() && !isguestuser()) {
        if (
            has_capability('report/sql:author', context_system::instance(), $USER) ||
            has_capability('report/sql:view', context_system::instance(), $USER)
        ) {
            $node = $navigation->add(
                get_string('reportsources', 'report_sql'),
                new moodle_url('/report/sql/index.php'),
                navigation_node::TYPE_CUSTOM,
                null,
                'report_sql'
            );
            $node->showinflatnavigation = true;
        }
    }
}

/**
 * Add a "SQL Report" link under the course Reports menu (Course → More →
 * Reports) for users with author or view capability in the course context.
 *
 * @param navigation_node $parentnode
 * @param stdClass $course
 * @param context_course $context
 */
function report_sql_extend_navigation_course(
    navigation_node $parentnode,
    stdClass $course,
    context_course $context
): void {
    global $USER;

    if (!\report_sql\local\query::is_plugin_enabled()) {
        return;
    }
    if (!isloggedin() || isguestuser()) {
        return;
    }
    if (
        !has_capability('report/sql:author', $context, $USER) &&
        !has_capability('report/sql:view', $context, $USER)
    ) {
        return;
    }

    // For report plugins, core calls this hook with the course "Reports" container itself as
    // $parentnode (settings_navigation::load_course_navigation → the 'coursereports' node), so the
    // link is added straight to it — that is what places it in the course Reports section alongside
    // core reports. (The generic course-node navigation pass explicitly skips report plugins.)
    $parentnode->add(
        get_string('reportsources', 'report_sql'),
        new moodle_url('/report/sql/index.php', ['courseid' => $course->id]),
        navigation_node::TYPE_SETTING,
        null,
        'report_sql',
        new pix_icon('i/report', '')
    );
}

/**
 * Fragment: render the first page of an unsaved query as a Report Builder report, for the inline
 * Preview button in the edit form.
 *
 * Validates the SQL (same static denylist as publish), (re)creates the author's throwaway preview
 * VIEW, introspects its columns, then renders an ephemeral system report over it. Nothing is
 * persisted — no reportbuilder_report row, no config binding, no audiences. Returned HTML carries
 * the report's own queued JS via the Fragment API, so the client can inject it live.
 *
 * @param array $args Fragment arguments: 'sql' (string), 'courseid' (int), plus core's 'context'.
 * @return string Rendered report HTML (or an inline error notification).
 */
function report_sql_output_fragment_preview(array $args): string {
    global $USER, $OUTPUT, $PAGE;

    $context = \context_system::instance();
    require_capability('report/sql:author', $context);

    // The preview renders a Report Builder system report, whose flexible_table reads $PAGE->url when
    // it finishes output. In a fragment the URL may be unset, which raises a debugging() notice on
    // Moodle 4.5 (magic_get_url). Set it defensively before rendering.
    if (!$PAGE->has_set_url()) {
        $PAGE->set_url(new \moodle_url('/report/sql/edit.php'));
    }

    $sql = (string) ($args['sql'] ?? '');
    $courseid = (int) ($args['courseid'] ?? 0);

    // Mirror edit.php: an author may not preview a query scoped to a course they cannot access. A
    // courseid that resolves to no course (stale/imported id) is demoted to site-wide.
    if ($courseid > 0) {
        $coursecontext = \context_course::instance($courseid, IGNORE_MISSING);
        if (!$coursecontext) {
            $courseid = 0;
        } else if (
            !has_capability('report/sql:viewall', $context) &&
            !has_capability('report/sql:view', $coursecontext) &&
            !has_capability('report/sql:viewown', $coursecontext)
        ) {
            throw new required_capability_exception($coursecontext, 'report/sql:view', 'nopermissions', '');
        }
    }

    try {
        // Same static denylist as the publish path; errors surface inline instead of crashing.
        $validated = \report_sql\local\sql\validator::validate($sql);
    } catch (\moodle_exception $e) {
        // Validation failed before any view was created — nothing to clean up.
        // Wrap so the alert preserves the SQL's linebreaks/indentation (see .report_sql-pre-wrap).
        return \html_writer::div($OUTPUT->notification($e->getMessage(), 'error'), 'report_sql-pre-wrap');
    }

    // Preview is first-page-only, so output() renders the rows synchronously and the throwaway view
    // is not needed once it returns. Drop it in a finally so every exit path — success, inline error
    // or exception — leaves no residual DB view behind.
    try {
        $viewname = \report_sql\local\sql\view::create_or_replace_preview($USER->id, $validated, $courseid);
        $columns = \report_sql\local\sql\view::columns($viewname);

        // An unaliased expression yields a view column RB cannot build — fail with a clear message.
        if (($badcol = \report_sql\local\sql\view::first_unaliased_column($columns)) !== null) {
            $errkey = preg_match('/\s/', $badcol) ? 'erraliasspaces' : 'errcolumnnoalias';
            return \html_writer::div(
                $OUTPUT->notification(get_string($errkey, 'report_sql', $badcol), 'error'),
                'report_sql-pre-wrap'
            );
        }

        $meta = \report_sql\local\query::build_columnsmeta($columns, $validated);

        // Reproduce the query's ORDER BY so the preview rows appear in the same order the published
        // report will show them (RB never carries a view's internal ORDER BY). A system report sorts
        // on a single initial column, so only the primary ORDER BY term that maps to an output column
        // is applied here; the published report honours the full multi-column ordering.
        $sortcolumn = '';
        $sortdir = SORT_ASC;
        $metakeys = [];
        foreach (array_keys($meta) as $name) {
            $metakeys[strtolower($name)] = $name;
        }
        foreach (\report_sql\local\query::order_by_sorting($validated) as $name => $direction) {
            if (isset($metakeys[$name])) {
                $sortcolumn = $metakeys[$name];
                $sortdir = $direction;
                break;
            }
        }

        $report = \core_reportbuilder\system_report_factory::create(
            \report_sql\reportbuilder\local\systemreports\preview::class,
            $context,
            '',
            '',
            0,
            [
                'viewname'    => $viewname,
                'columnsmeta' => json_encode($meta),
                'title'       => get_string('preview', 'report_sql'),
                'sortcolumn'  => $sortcolumn,
                'sortdir'     => $sortdir,
            ]
        );

        // Reuse the just-built preview view to attach the same advisory summary the Test button
        // shows — row count and performance warnings — so a preview doubles as a lightweight test
        // without standing up a second view. Passing $viewname makes the analyser skip its own
        // dry-run and probe view. Advisory only: any failure degrades to just the rendered rows.
        $summary = report_sql_preview_summary($sql, $courseid, $viewname);

        // When a chart is configured on the (unsaved) form, render it above the table off the same
        // preview view, so the author sees the graph their axis choices produce before publishing.
        $chart = report_sql_preview_chart($args, $viewname, $meta);

        return $summary . $chart . $report->output();
    } catch (\moodle_exception $e) {
        // The view build failed: show the compiled SQL (placeholders/{table} resolved) beside the
        // error so the author can line the DB error's line numbers up against what actually ran.
        $compiled = \report_sql\local\sql\view::compile($validated, $courseid);
        return \html_writer::div($OUTPUT->notification($e->getMessage(), 'error'), 'report_sql-pre-wrap')
            . report_sql_compiled_sql_details($compiled);
    } finally {
        \report_sql\local\sql\view::drop_preview($USER->id);
    }
}

/**
 * Build the advisory summary strip shown above the inline preview rows: row count and any
 * performance warnings, from {@see \report_sql\local\sql\analyser::analyse()} run against
 * the caller's already-built preview view.
 *
 * @param string $sql Raw author SQL (re-validated inside analyse()).
 * @param int $courseid Bound course id (0 = site-wide).
 * @param string $viewname Unprefixed name of the live preview view to reuse for introspection.
 * @return string Summary HTML, or '' when the analysis yields nothing worth showing.
 */
function report_sql_preview_summary(string $sql, int $courseid, string $viewname): string {
    $feedback = \report_sql\local\sql\analyser::analyse($sql, $courseid, $viewname);
    if (!$feedback['ok']) {
        // The rows rendered, so a failed advisory pass is not worth surfacing here.
        return '';
    }

    $lines = [];
    if ($feedback['rowcount'] >= 0) {
        // Show the generation time (row-count probe wall-clock) beside the count when measured.
        $lines[] = ($feedback['elapsed'] ?? -1) >= 0
            ? get_string('checkrowcounttimed', 'report_sql',
                ['rows' => $feedback['rowcount'], 'ms' => $feedback['elapsed']])
            : get_string('checkrowcount', 'report_sql', $feedback['rowcount']);
    }
    $lines = array_merge($lines, $feedback['warnings']);
    if (!$lines) {
        return '';
    }

    $items = array_map(static fn(string $line): string => \html_writer::div(s($line)), $lines);
    return \html_writer::div(implode('', $items), 'alert alert-secondary py-1 mb-2', ['role' => 'status']);
}

/**
 * Build a collapsible block showing the compiled SQL (placeholders and {table} braces resolved) that
 * the failed preview view was built from, so an author can line the DB error's line/column numbers
 * up against the SQL that actually ran rather than their un-substituted source.
 *
 * @param string $compiled Compiled SQL from {@see \report_sql\local\sql\view::compile()}.
 * @return string HTML, or '' when there is nothing to show.
 */
function report_sql_compiled_sql_details(string $compiled): string {
    if (trim($compiled) === '') {
        return '';
    }
    $summary = \html_writer::tag('summary', s(get_string('compiledsql', 'report_sql')));
    $pre = \html_writer::tag('pre', s($compiled), [
        'class' => 'mt-1 mb-0 p-2 bg-light border rounded',
        'style' => 'max-height:16rem;overflow:auto;white-space:pre-wrap;',
    ]);
    return \html_writer::tag('details', $summary . $pre, ['class' => 'mt-2', 'open' => 'open']);
}

/**
 * Render the configured chart for the inline preview, above the table.
 *
 * Mirrors {@see \report_sql\reportbuilder\local\entities\chart_view::render_chart_cell()},
 * but reads the axis/type/sizing from the *unsaved* form args and pulls rows straight from the
 * caller's already-built preview view (unscoped — the preview view is the author's own throwaway
 * copy). Returns '' when no real chart type is selected or the chosen axes are not real columns.
 *
 * The SVG is embedded unescaped inside an `<img>` for the same reason as the RB chart column:
 * {@see \report_sql\local\chart_svg} XML-escapes every label/title it draws, and an SVG in
 * an `<img>` cannot execute script.
 *
 * @param array $args Fragment args carrying the chart_* fields.
 * @param string $viewname Unprefixed name of the live preview view to read rows from.
 * @param array $meta Frozen columnsmeta (assoc by column name) for the preview view.
 * @return string Chart HTML, or '' when nothing to render.
 */
function report_sql_preview_chart(array $args, string $viewname, array $meta): string {
    global $DB;

    $type = (string) ($args['chart_type'] ?? 'none');
    if ($type === '' || $type === 'none') {
        return '';
    }
    $xcol = (string) ($args['chart_xcol'] ?? '');
    $ycol = (string) ($args['chart_ycol'] ?? '');
    // Both axes must resolve to real view columns, else there is nothing to plot.
    if ($xcol === '' || $ycol === '' || !array_key_exists($xcol, $meta) || !array_key_exists($ycol, $meta)) {
        return '';
    }
    $rowlimit = max(1, min(5000, (int) ($args['chart_rowlimit'] ?? 200)));
    $labelsize = max(11, min(48, (int) ($args['chart_labelsize'] ?? 16)));
    $datalabels = !empty($args['chart_datalabels']);
    $multicolour = !empty($args['chart_multicolour']);

    // Read straight from the preview view. A raw recordset (not get_records) preserves every row and
    // its order — a grouped chart query has no unique id column to key on.
    $rows = [];
    try {
        $rs = $DB->get_recordset_sql('SELECT * FROM {' . $viewname . '}', null, 0, $rowlimit);
        foreach ($rs as $row) {
            $rows[] = (array) $row;
        }
        $rs->close();
    } catch (\dml_exception $e) {
        // Advisory only: a failed chart pass degrades to just the rendered table.
        debugging($e->getMessage(), DEBUG_DEVELOPER);
        return '';
    }

    $xcase = (string) ($meta[$xcol]['textcase'] ?? '');
    $xdateformat = (($meta[$xcol]['type'] ?? '') === 'timestamp')
        ? \report_sql\local\chart_presenter::strftime_format((string) ($meta[$xcol]['dateformat'] ?? ''))
        : null;
    [$labels, $values] = \report_sql\local\chart_presenter::chart_series($rows, $xcol, $ycol, $xcase, $xdateformat);

    $svg = \report_sql\local\chart_svg::render($type, $labels, $values, '', [
        'labelsize'   => $labelsize,
        'datalabels'  => $datalabels,
        'multicolour' => $multicolour,
    ]);
    $datauri = 'data:image/svg+xml;base64,' . base64_encode($svg);
    $img = \html_writer::img($datauri, get_string('chartcolumn', 'report_sql'), [
        'class' => 'report-sql-chart img-fluid',
        'style' => 'max-width:100%;height:auto;',
    ]);
    return \html_writer::div($img, 'mb-3');
}
