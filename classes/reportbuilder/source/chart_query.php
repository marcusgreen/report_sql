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
use core_reportbuilder\local\helpers\database;
use report_sql\local\query;
use report_sql\reportbuilder\local\entities\chart_view;

/**
 * Datasource that exposes a saved query's chart as a one-row / one-cell Report Builder report.
 *
 * The main table is the plugin's own query table, scoped by a base condition to the single bound
 * query row, so the report has exactly one row. Its only column ({@see chart_view}) renders that
 * query's whole dataset as one SVG image.
 *
 * Like {@see adhoc_query} it is placed outside the `reportbuilder\datasource` namespace on purpose,
 * so Moodle's auto-discovery does not surface it in the "new report" source dropdown — the chart
 * report is created exclusively by {@see query::create_chart_report()} at publish time.
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class chart_query extends datasource {
    /**
     * Get the datasource display name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('chartcolumn', 'report_sql');
    }

    /**
     * Wire the query table as the single-row main table and add the chart column.
     */
    protected function initialise(): void {
        $reportid = (int) $this->get_report_persistent()->get('id');
        $queryid  = (int) get_config('report_sql', 'queryid_for_report_' . $reportid);

        $entity = new chart_view();
        $alias  = $entity->get_table_alias(query::TABLE);
        $this->set_main_table(query::TABLE, $alias);
        $this->add_entity($entity);
        $this->add_all_from_entity($entity->get_entity_name());

        // Scope to exactly the bound query row (one row). With no binding the report shows no rows;
        // the chart column still exists so Report Builder validation passes.
        if ($queryid > 0) {
            $param = database::generate_param_name();
            $this->add_base_condition_sql("{$alias}.id = :{$param}", [$param => $queryid]);
        } else {
            $this->add_base_condition_sql('1 = 0');
        }
    }

    /**
     * Get the default columns shown on a new report: just the chart.
     *
     * @return array
     */
    public function get_default_columns(): array {
        return [chart_view::ENTITY . ':chart'];
    }

    /**
     * Get the default filters (none).
     *
     * @return array
     */
    public function get_default_filters(): array {
        return [];
    }

    /**
     * Get the default conditions (none).
     *
     * @return array
     */
    public function get_default_conditions(): array {
        return [];
    }
}
