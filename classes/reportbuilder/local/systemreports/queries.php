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

declare(strict_types=1);

namespace report_sql\reportbuilder\local\systemreports;

use core_reportbuilder\local\helpers\database;
use core_reportbuilder\local\report\action;
use core_reportbuilder\local\report\column;
use core_reportbuilder\system_report;
use html_writer;
use lang_string;
use report_sql\local\query;
use report_sql\reportbuilder\local\entities\query as query_entity;
use moodle_url;
use pix_icon;

/**
 * System report listing saved ad-hoc queries (report sources), with paging, sorting and filtering.
 *
 * Row visibility mirrors {@see query::visible_to_current_user()} via a SQL base condition, so the
 * report never surfaces a query the current user is not entitled to see on the plugin's own pages.
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class queries extends system_report {
    /**
     * Initialise the report: main table, entity, columns, filters, actions.
     */
    protected function initialise(): void {
        $entity = new query_entity();
        $entityname = $entity->get_entity_name();
        $alias = $entity->get_table_alias('report_sql_query');

        $this->set_main_table('report_sql_query', $alias);
        $this->add_entity($entity);

        // Base fields consumed by the row action URLs and their per-row visibility callbacks below.
        $this->add_base_fields(
            "{$alias}.id, {$alias}.status, {$alias}.reportid, {$alias}.chartreportid, {$alias}.ownerid, {$alias}.chartmeta"
        );

        $columns = [
            "{$entityname}:name",
            "{$entityname}:owner",
            "{$entityname}:course",
            "{$entityname}:status",
            "{$entityname}:visible",
            "{$entityname}:timemodified",
        ];
        // View history is manager-level data, so the usage columns only show for viewall holders.
        if (has_capability('report/sql:viewall', $this->get_context())) {
            $columns[] = "{$entityname}:viewcount";
            $columns[] = "{$entityname}:lastviewed";
        }
        $this->add_columns_from_entities($columns);

        // The most-used actions (Edit query, and the Publish / Unpublish pair) render as inline
        // buttons in their own column; the rest stay in the row's kebab menu (see add_report_actions()).
        $this->add_buttons_column($entityname, $alias);

        // Course filter is redundant when the listing is already scoped to a course via the
        // 'courseid' report parameter, so only offer it on the site-wide listing.
        $courseid = (int) $this->get_parameter('courseid', 0, PARAM_INT);
        $filters = [
            "{$entityname}:name",
            "{$entityname}:owner",
        ];
        if (!$courseid) {
            $filters[] = "{$entityname}:course";
        }
        $filters = array_merge($filters, [
            "{$entityname}:status",
            "{$entityname}:visible",
            "{$entityname}:timemodified",
            "{$entityname}:timecreated",
        ]);
        $this->add_filters_from_entities($filters);

        $this->add_report_actions();

        [$where, $params] = $this->build_visibility_condition();
        $this->add_base_condition_sql($where, $params);

        $this->set_downloadable(true, get_string('reportsources', 'report_sql'));

        // Delegated click handler for the "Copy embed code" kebab action (see add_report_actions()).
        // The module self-initialises on import (no exported entry point), so load it the way core does.
        global $PAGE;
        if ($PAGE instanceof \moodle_page) {
            $PAGE->requires->js_amd_inline("require(['core/copy_to_clipboard']);");
        }
    }

    /**
     * Only users allowed on the plugin's listing pages may view this report.
     *
     * @return bool
     */
    protected function can_view(): bool {
        $context = $this->get_context();
        return has_capability('report/sql:viewall', $context)
            || has_capability('report/sql:author', $context)
            || has_capability('report/sql:view', $context)
            || has_capability('report/sql:viewown', $context);
    }

    /**
     * Build the [$where, $params] pair matching query::visible_to_current_user(). The optional
     * 'courseid' report parameter scopes the listing to a course (site-wide queries always included).
     *
     * @return array{0:string,1:array}
     */
    private function build_visibility_condition(): array {
        global $USER;

        $alias = $this->get_main_table_alias();
        $courseid = (int) $this->get_parameter('courseid', 0, PARAM_INT);
        $syscontext = \context_system::instance();
        $coursecontext = $courseid ? \context_course::instance($courseid) : $syscontext;

        // Report Builder requires all base-condition param names to come from generate_param_name().
        $paramcourse = database::generate_param_name();
        $paramuser = database::generate_param_name();
        $parampub = database::generate_param_name();

        // Course-scope clause reused by several branches (site-wide rows always included).
        $scope = '';
        $scopeparams = [];
        if ($courseid) {
            $scope = " AND ({$alias}.courseid = :{$paramcourse} OR {$alias}.courseid = 0)";
            $scopeparams[$paramcourse] = $courseid;
        }

        // viewall — every query (course-scoped when a course is given).
        if (has_capability('report/sql:viewall', $syscontext)) {
            return $courseid
                ? ["({$alias}.courseid = :{$paramcourse} OR {$alias}.courseid = 0)", [$paramcourse => $courseid]]
                : ['1 = 1', []];
        }

        // author — own queries, plus any published+visible query.
        if (has_capability('report/sql:author', $syscontext)) {
            $where = "({$alias}.ownerid = :{$paramuser}"
                . " OR ({$alias}.status = :{$parampub} AND {$alias}.visible = 1)){$scope}";
            return [$where, [$paramuser => $USER->id, $parampub => query::STATUS_PUBLISHED] + $scopeparams];
        }

        // Course-level viewer (teacher) — needs a course and view/viewown there.
        if (
            $courseid && (
            has_capability('report/sql:view', $coursecontext) ||
            has_capability('report/sql:viewown', $coursecontext)
            )
        ) {
            $where = "{$alias}.status = :{$parampub} AND {$alias}.visible = 1"
                . " AND ({$alias}.courseid = :{$paramcourse} OR {$alias}.courseid = 0)";
            return [$where, [$parampub => query::STATUS_PUBLISHED, $paramcourse => $courseid]];
        }

        // System viewer fallback — published + visible, site-wide only.
        if (has_capability('report/sql:view', $syscontext)) {
            return [
                "{$alias}.status = :{$parampub} AND {$alias}.visible = 1 AND {$alias}.courseid = 0",
                [$parampub => query::STATUS_PUBLISHED],
            ];
        }

        // Nothing visible.
        return ['1 = 0', []];
    }

    /**
     * Add a leading column rendering the most-used actions (Edit query, and the status-exclusive
     * Publish / Unpublish pair) as inline buttons, so they sit outside the kebab menu holding the rest.
     *
     * @param string $entityname entity the column is attached to
     * @param string $alias main-table alias for the button-gating fields
     * @return void
     */
    private function add_buttons_column(string $entityname, string $alias): void {
        $courseid = (int) $this->get_parameter('courseid', 0, PARAM_INT);
        $urlcourse = $courseid ? ['courseid' => $courseid] : [];

        $column = (new column('buttons', new lang_string('actions', 'report_sql'), $entityname))
            ->set_type(column::TYPE_TEXT)
            ->add_fields("{$alias}.id, {$alias}.status, {$alias}.reportid, {$alias}.ownerid")
            ->set_is_sortable(false)
            // Shrink the Actions column to its buttons (see .rs-actions-col) so the freed width
            // goes to the Name column instead of pooling as empty space before the kebab menu.
            ->add_attributes(['class' => 'rs-actions-col'])
            ->add_callback(static function ($value, \stdClass $row) use ($urlcourse): string {
                global $USER;
                $buttons = '';

                // Edit the query. Admin-owned queries are locked to site admins.
                $canmodify = !is_siteadmin($row->ownerid) || is_siteadmin($USER);
                if ($canmodify && has_capability('report/sql:author', \context_system::instance())) {
                    $buttons .= html_writer::link(
                        new moodle_url('/report/sql/edit.php', ['id' => $row->id] + $urlcourse),
                        get_string('edit'),
                        ['class' => 'btn btn-sm btn-secondary me-2']
                    );
                }

                // Publish / Unpublish — a status-exclusive pair, rendered as one inline button so the
                // primary next action (Publish a draft, Unpublish a published query) is never buried
                // in the kebab. Both need the approve capability; admin-owned queries locked to admins.
                if (
                    (!is_siteadmin($row->ownerid) || is_siteadmin($USER))
                    && has_capability('report/sql:approve', \context_system::instance())
                ) {
                    if ($row->status === query::STATUS_DRAFT) {
                        $buttons .= html_writer::link(
                            new moodle_url(
                                '/report/sql/run.php',
                                ['id' => $row->id, 'action' => 'publish', 'sesskey' => sesskey()]
                            ),
                            get_string('publish', 'report_sql'),
                            ['class' => 'btn btn-sm btn-success rs-publish-toggle']
                        );
                    } else if ($row->status === query::STATUS_PUBLISHED) {
                        $buttons .= html_writer::link(
                            new moodle_url(
                                '/report/sql/run.php',
                                ['id' => $row->id, 'action' => 'unpublish', 'sesskey' => sesskey()]
                            ),
                            get_string('unpublish', 'report_sql'),
                            ['class' => 'btn btn-sm btn-warning rs-publish-toggle']
                        );
                    }
                }

                // Hidden clipboard target for the "Copy embed code" kebab action. The action link
                // itself carries no text (RB attr placeholders only match a whole-value :prop, so a
                // mid-string selector like "#...-:id" can't be built there); instead the action's
                // callback points data-clipboard-target at this per-row sr-only node. Only published
                // queries have an RB report id, so drafts emit nothing. The id is the *report* id
                // (as in /reportbuilder/view.php?id=), NOT the query id in the edit.php URL.
                $marker = '';
                if ($row->status === query::STATUS_PUBLISHED && !empty($row->reportid)) {
                    $marker = html_writer::span(
                        s('[[reportsource:' . (int) $row->reportid . ']]'),
                        'sr-only',
                        ['id' => 'rs-embed-marker-' . (int) $row->id]
                    );
                }

                // Keep the action buttons on a single line instead of wrapping in the narrow cell.
                return ($buttons === '' && $marker === '') ? '' : html_writer::div($buttons . $marker, 'text-nowrap');
            });

        $this->add_column($column);
    }

    /**
     * Add the per-row kebab action links, mirroring the plugin's own listing page.
     *
     * Edit query and the Publish / Unpublish pair render as inline buttons (see add_buttons_column());
     * this covers the remainder: Edit in Report Builder, View chart, Schedule emails, New report,
     * Duplicate and Delete. Each is gated by the same capability / status / owner-lock rules the
     * hand-rolled index.php table used.
     *
     * @return void
     */
    private function add_report_actions(): void {
        // Admin-owned queries are locked to site admins (mirrors index.php row guard).
        $canmodifyrow = static function (\stdClass $row): bool {
            global $USER;
            return !is_siteadmin($row->ownerid) || is_siteadmin($USER);
        };

        // Edit the underlying Report Builder report (RB editors only).
        $this->add_action((new action(
            new moodle_url('/reportbuilder/edit.php', ['id' => ':reportid']),
            new pix_icon('i/edit', ''),
            [],
            false,
            new lang_string('editreport', 'report_sql')
        ))->add_callback(static function (\stdClass $row): bool {
            return $row->status === query::STATUS_PUBLISHED && !empty($row->reportid)
                && has_any_capability(
                    ['moodle/reportbuilder:edit', 'moodle/reportbuilder:editall'],
                    \context_system::instance()
                );
        }));

        // View the chart as its Report Builder report (schedulable / exportable / embeddable).
        // Shown once that report exists — created at publish time whenever a chart type is configured.
        $this->add_action((new action(
            new moodle_url('/reportbuilder/view.php', ['id' => ':chartreportid']),
            new pix_icon('i/chartbar', ''),
            [],
            false,
            new lang_string('viewchart', 'report_sql')
        ))->add_callback(static function (\stdClass $row): bool {
            return $row->status === query::STATUS_PUBLISHED && !empty($row->chartreportid);
        }));

        // Deep-link to the report's Schedules tab (RB editors only).
        // RB editors can be scheduled to receive the report by email. Deep-link to the Schedules tab.
        $canschedule = static function (\stdClass $row): bool {
            return $row->status === query::STATUS_PUBLISHED
                && has_any_capability(
                    ['moodle/reportbuilder:edit', 'moodle/reportbuilder:editall'],
                    \context_system::instance()
                );
        };

        // For a chart query, schedule the chart report so the emailed report is the graph, not the
        // underlying table. Shown only when the chart report exists.
        $this->add_action((new action(
            new moodle_url('/reportbuilder/edit.php', ['id' => ':chartreportid'], 'schedules'),
            new pix_icon('i/scheduled', ''),
            [],
            false,
            new lang_string('schedule', 'report_sql')
        ))->add_callback(static function (\stdClass $row) use ($canschedule): bool {
            return $canschedule($row) && !empty($row->chartreportid);
        }));

        // Otherwise schedule the data (table) report.
        $this->add_action((new action(
            new moodle_url('/reportbuilder/edit.php', ['id' => ':reportid'], 'schedules'),
            new pix_icon('i/scheduled', ''),
            [],
            false,
            new lang_string('schedule', 'report_sql')
        ))->add_callback(static function (\stdClass $row) use ($canschedule): bool {
            return $canschedule($row) && empty($row->chartreportid) && !empty($row->reportid);
        }));

        // Publish is an inline button (see add_buttons_column()), paired with Unpublish.

        // Copy the [[reportsource:ID]] embed marker to the clipboard. No visible marker text clutters
        // the listing (the old dedicated column is gone); the marker lives once per row as an sr-only
        // node emitted by add_buttons_column(), and this action's data-clipboard-target points at it.
        // The target selector must be built by the callback: RB's attribute placeholder replacement
        // only matches a whole value (^:prop), so "#rs-embed-marker-:id" cannot be interpolated as a
        // static attribute — the callback sets $row->embedtarget and the attr uses the :embedtarget
        // whole-value placeholder. Shown for published rows only (drafts have no RB report id).
        $this->add_action((new action(
            new moodle_url('#'),
            new pix_icon('t/copy', ''),
            [
                'data-action' => 'copytoclipboard',
                'data-clipboard-target' => ':embedtarget',
                'data-clipboard-success-message' => new lang_string('embedcodecopied', 'report_sql'),
            ],
            false,
            new lang_string('embedcodecopy', 'report_sql')
        ))->add_callback(static function (\stdClass $row): bool {
            if ($row->status !== query::STATUS_PUBLISHED || empty($row->reportid)) {
                return false;
            }
            // Injected onto the (cloned) row so replace_placeholders() can resolve :embedtarget.
            $row->embedtarget = '#rs-embed-marker-' . (int) $row->id;
            return true;
        }));

        // Duplicate the query (any author).
        $this->add_action((new action(
            new moodle_url(
                '/report/sql/run.php',
                ['id' => ':id', 'action' => 'copy', 'sesskey' => sesskey()]
            ),
            new pix_icon('t/copy', ''),
            [],
            false,
            new lang_string('duplicate', 'report_sql')
        ))->add_callback(static function (\stdClass $row): bool {
            return has_capability('report/sql:author', \context_system::instance());
        }));

        // Delete the query.
        $this->add_action((new action(
            new moodle_url('/report/sql/delete.php', ['id' => ':id', 'sesskey' => sesskey()]),
            new pix_icon('t/delete', ''),
            [],
            false,
            new lang_string('delete')
        ))->add_callback(static function (\stdClass $row) use ($canmodifyrow): bool {
            global $USER;
            return $canmodifyrow($row)
                && has_capability('report/sql:author', \context_system::instance())
                && ($row->ownerid == $USER->id
                    || has_capability('report/sql:viewall', \context_system::instance()));
        }));
    }
}
