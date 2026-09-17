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

use core_reportbuilder\system_report;
use lang_string;
use report_sql\reportbuilder\local\entities\adhoc_view;

/**
 * Ephemeral system report used to preview an unsaved ad-hoc query inline in the edit form.
 *
 * Unlike a published report, this is never persisted: it is built on the fly by
 * {@see \report_sql_output_fragment_preview()} over a throwaway per-user preview VIEW
 * (see {@see \report_sql\local\sql\view::create_or_replace_preview()}), reusing the same
 * {@see adhoc_view} entity as the real datasource so the columns type and format identically.
 *
 * The preview is read-only and non-interactive — first page only, no filters, no download — so no
 * paging AJAX round-trip is exercised (the first page is server-rendered in {@see output()}).
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class preview extends system_report {
    /**
     * Initialise the report from the fragment parameters (view name + frozen column metadata).
     */
    protected function initialise(): void {
        $viewname = $this->get_parameter('viewname', '', PARAM_ALPHANUMEXT);
        $columnsmeta = json_decode($this->get_parameter('columnsmeta', '[]', PARAM_RAW), true) ?: [];
        $title = $this->get_parameter('title', '', PARAM_TEXT);
        $sortcolumn = $this->get_parameter('sortcolumn', '', PARAM_ALPHANUMEXT);
        $sortdir = (int) $this->get_parameter('sortdir', SORT_ASC, PARAM_INT);

        $entity = new adhoc_view($viewname, $columnsmeta, $title);
        $entityname = $entity->get_entity_name();
        $alias = $entity->get_table_alias($viewname);

        $this->set_main_table($viewname, $alias);
        $this->add_entity($entity);

        // Show every column the query produces, in order — this mirrors add_default_columns() on the
        // real datasource, so the preview reflects the report an author would get on publish.
        $columns = array_map(
            static fn(string $name): string => "{$entityname}:{$name}",
            array_keys($columnsmeta)
        );
        $this->add_columns_from_entities($columns);

        // Preview is non-interactive: strip sorting from every column so no header sort links render.
        // Also badge each indexed header with the analyser's index status (bare source-table columns
        // only — expression columns carry no badge, see analyser::column_index_status()). Unindexed
        // columns are left alone: badging every column that is *not* indexed made the common case
        // (most columns unindexed) the noisy one, so only the indexed exception is marked.
        $columnindex = json_decode($this->get_parameter('columnindex', '[]', PARAM_RAW), true) ?: [];
        $indexed = [];
        foreach ($columnindex as $entry) {
            if (!empty($entry['indexed'])) {
                $indexed[strtolower((string) ($entry['col'] ?? ''))] = true;
            }
        }
        foreach ($this->get_columns() as $column) {
            $column->set_is_sortable(false);
            $colname = strtolower((string) preg_replace('/^.*:/', '', $column->get_unique_identifier()));
            if (isset($indexed[$colname])) {
                $badge = \html_writer::tag('i', '', [
                    'class'       => 'fa fa-bolt text-success ms-1',
                    'title'       => get_string('indexedcolumn', 'report_sql'),
                    'aria-hidden' => 'true',
                ]);
                // Routed through the generic '{$a}' passthrough string (see adhoc_view::raw_title()),
                // rather than a new lang string per badge, since the markup is fixed and only the
                // column name varies.
                $column->set_title(new lang_string('reportsourceheader', 'report_sql', $column->get_title() . $badge));
            }
        }

        // Reproduce the query's ORDER BY (primary term only) so the preview rows match the order the
        // published report will show — RB never carries a view's internal ORDER BY. This is a *default*
        // sort: flexible_table applies the default sort column even though it is non-sortable (so no
        // header icon appears), because get_sort_columns() only requires the column to be defined.
        $sortuid = $sortcolumn !== '' ? "{$entityname}:{$sortcolumn}" : '';
        if ($sortuid !== '' && $this->get_column($sortuid) !== null) {
            $this->set_initial_sort_column($sortuid, $sortdir === SORT_DESC ? SORT_DESC : SORT_ASC);
        }

        // Preview only: first 5 rows, no interactivity, no export.
        $this->set_default_per_page(5);
        $this->set_downloadable(false);
    }

    /**
     * Only report-source authors may preview a query.
     *
     * @return bool
     */
    protected function can_view(): bool {
        return has_capability('report/sql:author', $this->get_context());
    }
}
