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

namespace report_sql\reportbuilder\source;

use core_reportbuilder\datasource;
use report_sql\local\query;
use report_sql\reportbuilder\local\entities\adhoc_view;

/**
 * Datasource that exposes an ad-hoc SQL query (backed by a database VIEW) to Reportbuilder.
 *
 * One Reportbuilder report is created per saved query at publish time. The report's id is mapped
 * back to the query id via plugin config (`queryid_for_report_<reportid>`).
 *
 * Intentionally placed outside the `reportbuilder\datasource` namespace so Moodle's auto-discovery
 * does not surface it in the "new report" source dropdown — reports are created exclusively by the
 * plugin's own publish workflow.
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class adhoc_query extends datasource {
    /**
     * Get the datasource display name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('reportsource', 'report_sql');
    }

    /**
     * Resolve the bound query and wire up its view as the report's main table.
     */
    protected function initialise(): void {
        $reportid = (int) $this->get_report_persistent()->get('id');
        $queryid  = (int) get_config('report_sql', 'queryid_for_report_' . $reportid);
        if ($queryid <= 0) {
            // Report exists without a backing query (e.g. listing in admin UI before publish).
            // Use a no-op single-column placeholder so RB validation passes.
            $this->initialise_placeholder();
            return;
        }

        try {
            $query = query::get($queryid);
        } catch (\dml_missing_record_exception $e) {
            $this->initialise_placeholder();
            return;
        }

        $viewname = $query->viewname();
        $meta     = $query->columns_meta();
        if (!$viewname || !$meta) {
            $this->initialise_placeholder();
            return;
        }

        // Per-user filter: scope every row to the viewing user. The chosen column is a physical
        // column of the view (validated at save against columnsmeta), so referencing it here is
        // safe. The column is also withheld from the entity entirely: once filtered, its value
        // always equals the viewer's own id, so offering it as a column or filter is pure noise.
        // (Unless it is the only column — an entity must expose at least one.)
        $useridcolumn = $query->useridcolumn();
        $peruser = $useridcolumn !== '' && array_key_exists($useridcolumn, $meta);
        $visiblemeta = ($peruser && count($meta) > 1)
            ? array_diff_key($meta, [$useridcolumn => true])
            : $meta;

        $entity = new adhoc_view($viewname, $visiblemeta, $query->name());
        $alias  = $entity->get_table_alias($viewname);
        $this->set_main_table($viewname, $alias);
        $this->add_entity($entity);
        $this->add_all_from_entity($entity->get_entity_name());

        if ($peruser) {
            global $USER;
            $param = \core_reportbuilder\local\helpers\database::generate_param_name();
            $this->add_base_condition_sql("{$alias}.{$useridcolumn} = :{$param}", [$param => (int) $USER->id]);
        }

        // Teacher-course filter: limit rows to courses the viewer teaches. The column stays visible
        // in output (a teacher may teach several courses), so it is not stripped from the entity.
        $coursecolumn = $query->coursecolumn();
        if ($coursecolumn !== '' && array_key_exists($coursecolumn, $meta)) {
            global $DB, $USER;
            $courseids = query::teacher_course_ids((int) $USER->id);
            if (!$courseids) {
                // The viewer teaches no courses, so the report returns no rows.
                $this->add_base_condition_sql('1 = 0');
            } else {
                [$insql, $params] = $DB->get_in_or_equal(
                    $courseids,
                    SQL_PARAMS_NAMED,
                    \core_reportbuilder\local\helpers\database::generate_param_name('_')
                );
                $this->add_base_condition_sql("{$alias}.{$coursecolumn} {$insql}", $params);
            }
        }
    }

    /**
     * Fall-back initialisation when no backing query is resolvable.
     */
    private function initialise_placeholder(): void {
        $this->set_main_table('user', 'u');
        $this->annotate_entity('placeholder', new \lang_string('reportsource', 'report_sql'));
        $this->add_column((new \core_reportbuilder\local\report\column(
            'placeholder',
            new \lang_string('reportsource', 'report_sql'),
            'placeholder'
        ))->add_field('u.id')->set_type(\core_reportbuilder\local\report\column::TYPE_INTEGER));
    }

    /**
     * Get the default columns shown on a new report.
     *
     * @return array Up to six default column identifiers.
     */
    public function get_default_columns(): array {
        return array_slice(array_map(
            static fn(string $name): string => adhoc_view::ENTITY . ':' . $name,
            $this->known_column_names()
        ), 0, 6);
    }

    /**
     * Reproduce the bound query's ORDER BY as the report's default column sorting.
     *
     * Report Builder never carries a view's internal ORDER BY (MySQL drops it and RB re-selects with
     * no ORDER BY of its own), so without this the query's ordering is lost on publish. Only ORDER BY
     * terms that map to a default column are honoured — see {@see query::order_by_sorting()} for the
     * mapping rules — and the ORDER BY order is preserved (multi-column sort).
     *
     * @return int[] array [column identifier => SORT_ASC/SORT_DESC]
     */
    public function get_default_column_sorting(): array {
        $reportid = (int) $this->get_report_persistent()->get('id');
        $queryid  = (int) get_config('report_sql', 'queryid_for_report_' . $reportid);
        if ($queryid <= 0) {
            return [];
        }
        try {
            $query = query::get($queryid);
        } catch (\dml_missing_record_exception $e) {
            return [];
        }

        // Only columns that are actually default columns may appear here (add_default_columns()
        // throws otherwise), so intersect the ORDER BY against the default set, keyed by name.
        $defaults = [];
        foreach ($this->get_default_columns() as $uid) {
            $name = substr($uid, strlen(adhoc_view::ENTITY) + 1);
            $defaults[strtolower($name)] = $uid;
        }

        $sorting = [];
        foreach (query::order_by_sorting($query->sql()) as $name => $direction) {
            if (isset($defaults[$name])) {
                $sorting[$defaults[$name]] = $direction;
            }
        }
        return $sorting;
    }

    /**
     * Get the default filters shown on a new report.
     *
     * @return array Up to four default filter identifiers.
     */
    public function get_default_filters(): array {
        return array_slice(array_map(
            static fn(string $name): string => adhoc_view::ENTITY . ':' . $name,
            $this->known_column_names()
        ), 0, 4);
    }

    /**
     * Get the default conditions (none).
     *
     * @return array
     */
    public function get_default_conditions(): array {
        return [];
    }

    /**
     * List the bound query's column names.
     *
     * @return string[] Column names from the bound query, or empty array on placeholder mode.
     */
    private function known_column_names(): array {
        $reportid = (int) $this->get_report_persistent()->get('id');
        $queryid  = (int) get_config('report_sql', 'queryid_for_report_' . $reportid);
        if ($queryid <= 0) {
            return [];
        }
        try {
            $query = query::get($queryid);
        } catch (\dml_missing_record_exception $e) {
            return [];
        }
        // Hide the per-user filter column from defaults, mirroring initialise().
        $meta = $query->columns_meta();
        $useridcolumn = $query->useridcolumn();
        if ($useridcolumn !== '' && array_key_exists($useridcolumn, $meta) && count($meta) > 1) {
            unset($meta[$useridcolumn]);
        }
        return array_keys($meta);
    }
}
