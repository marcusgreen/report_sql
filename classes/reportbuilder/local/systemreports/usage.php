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

use core_reportbuilder\local\report\action;
use core_reportbuilder\system_report;
use lang_string;
use report_sql\local\query;
use report_sql\reportbuilder\local\entities\query as query_entity;
use moodle_url;
use pix_icon;

/**
 * System report showing how often each published report source has been opened.
 *
 * Reads the report_sql_queryview audit table (via the entity's viewcount/lastviewed
 * correlated-subquery columns), giving free sorting, filtering, paging and export. Restricted to
 * viewall holders because report-view history is manager-level data.
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class usage extends system_report {
    /**
     * Initialise the report: main table, entity, columns, filters, action.
     */
    protected function initialise(): void {
        $entity = new query_entity();
        $entityname = $entity->get_entity_name();
        $alias = $entity->get_table_alias('report_sql_query');

        $this->set_main_table('report_sql_query', $alias);
        $this->add_entity($entity);

        // Consumed by the row actions below.
        $this->add_base_fields("{$alias}.id, {$alias}.reportid");

        $this->add_columns_from_entities([
            "{$entityname}:name",
            "{$entityname}:owner",
            "{$entityname}:course",
            "{$entityname}:viewcount",
            "{$entityname}:uniqueviewers",
            "{$entityname}:lastviewed",
        ]);

        $this->add_filters_from_entities([
            "{$entityname}:name",
            "{$entityname}:owner",
            "{$entityname}:course",
            "{$entityname}:viewcount",
            "{$entityname}:uniqueviewers",
            "{$entityname}:lastviewed",
        ]);

        // Only published queries have a report and can accrue views.
        $this->add_base_condition_simple("{$alias}.status", query::STATUS_PUBLISHED);

        // Drill into this source's usage detail (who / trend / recent).
        $this->add_action((new action(
            new moodle_url('/report/sql/query_usage.php', ['id' => ':id']),
            new pix_icon('i/stats', ''),
            [],
            false,
            new lang_string('usage:detaillabel', 'report_sql')
        )));

        // Open the live report.
        $this->add_action((new action(
            new moodle_url('/reportbuilder/view.php', ['id' => ':reportid']),
            new pix_icon('i/report', ''),
            [],
            false,
            new lang_string('runreport', 'report_sql')
        ))->add_callback(static function (\stdClass $row): bool {
            return !empty($row->reportid);
        }));

        // Default sort: busiest report sources first.
        $this->set_initial_sort_column("{$entityname}:viewcount", SORT_DESC);
        $this->set_default_per_page(50);
        $this->set_downloadable(true, get_string('usage:title', 'report_sql'));
    }

    /**
     * Only managers (viewall) may see report-view history.
     *
     * @return bool
     */
    protected function can_view(): bool {
        return has_capability('report/sql:viewall', $this->get_context());
    }
}
