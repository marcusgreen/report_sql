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
use report_sql\local\query;
use report_sql\reportbuilder\local\entities\adhoc_view;
use stdClass;

/**
 * Actionable system report over a published query's VIEW.
 *
 * Unlike the query's *data* report (a datasource report viewed through /reportbuilder/view.php,
 * which has no row-selection UI), this is a {@see system_report} — the only report kind that
 * supports {@see system_report::set_checkbox_toggleall()}. It renders the same columns as the data
 * report (from the query's frozen `columnsmeta`) plus a leading select-all checkbox column keyed on
 * the row's subject id, so a viewer can tick rows and run a built-in bulk operation over them.
 *
 * Mirrors core's users bulk-action report ({@see \core_admin\reportbuilder\local\systemreports\users}):
 * checkboxes are only rendered when the report is created with the `withcheckboxes` parameter (i.e.
 * from the actions host page, alongside a bulk-action form), and the subject id is exposed as a base
 * field so both the checkbox callback and the downstream dispatch can read it.
 *
 * Access is double-gated ({@see can_view()}): the report/sql:actexecute capability AND the data
 * report's own view permission. Rows are scoped per viewer via {@see query::viewer_scope_sql()} as a
 * base condition, so the actions view can never surface a row the data report would hide.
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class query_actions extends system_report {
    /**
     * Initialise the report: main table = the query's VIEW, columns from columnsmeta, subject
     * base field, and (when requested) the select-all checkbox column.
     */
    protected function initialise(): void {
        $queryid = (int) $this->get_parameter('queryid', 0, PARAM_INT);
        $query   = query::get($queryid);

        $viewname = (string) $query->viewname();
        $meta     = $query->columns_meta();

        $entity = new adhoc_view($viewname, $meta, $query->name());
        $alias  = $entity->get_table_alias($viewname);
        $this->set_main_table($viewname, $alias);
        $this->add_entity($entity);

        // The subject of every bulk op (a user id for user ops). Exposed as a base field so the
        // checkbox callback and the dispatch page can both read it. Falls back to useridcolumn for
        // rows saved before an explicit action subject column was chosen.
        $subjectcolumn = $query->action_subjectcolumn() ?: $query->useridcolumn();
        if ($subjectcolumn !== '' && array_key_exists($subjectcolumn, $meta)) {
            $this->add_base_fields("{$alias}.{$subjectcolumn} AS subjectid");
        }

        $columnids = array_map(
            static fn(string $name): string => adhoc_view::ENTITY . ':' . $name,
            array_keys($meta)
        );
        $this->add_columns_from_entities($columnids);

        // Only render checkboxes when a bulk-action form is present (the actions host page passes
        // withcheckboxes=1). Mirrors core admin/user.php gating checkboxes on has_bulk_actions().
        if ($this->get_parameter('withcheckboxes', false, PARAM_BOOL)
                && $subjectcolumn !== '' && array_key_exists($subjectcolumn, $meta)) {
            $this->set_checkbox_toggleall(static function(stdClass $row): ?array {
                if (empty($row->subjectid)) {
                    // No usable subject id → non-selectable row.
                    return null;
                }
                return [$row->subjectid, (string) $row->subjectid];
            });
        }

        // Scope rows exactly as the data report shows them to this viewer (per-user / teacher-course).
        [$where, $params] = $query->viewer_scope_sql($alias);
        $this->add_base_condition_sql($where, $params);

        $this->set_downloadable(false);
    }

    /**
     * Whether the current user may view this actionable report: the report/sql:actexecute capability
     * AND the data report's own view permission (so the actions view never surfaces rows the viewer
     * could not open in the data report).
     *
     * @return bool
     */
    protected function can_view(): bool {
        if (!has_capability('report/sql:actexecute', $this->get_context())) {
            return false;
        }
        $queryid = (int) $this->get_parameter('queryid', 0, PARAM_INT);
        if ($queryid <= 0) {
            return false;
        }
        try {
            $query = query::get($queryid);
        } catch (\moodle_exception $e) {
            return false;
        }
        return $query->current_user_can_view_report();
    }
}
