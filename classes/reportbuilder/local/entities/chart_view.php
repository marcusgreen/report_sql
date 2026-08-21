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

namespace report_sql\reportbuilder\local\entities;

use core_reportbuilder\local\entities\base;
use core_reportbuilder\local\report\column;
use lang_string;
use report_sql\local\query;

/**
 * Report Builder entity exposing a saved query's chart as a single image cell.
 *
 * Unlike {@see adhoc_view}, which surfaces one column per view column, this entity has exactly one
 * column ("chart"). The bound report is scoped to a single query row by {@see chart_query}, so the
 * column's callback runs once and renders the whole dataset as one SVG image (see
 * {@see \report_sql\local\chart_svg}).
 *
 * The dataset itself is *not* read from the column's `$row` — a Report Builder column callback only
 * ever receives one row, and a chart aggregates many. Instead the callback fetches the rows itself
 * via {@see query::fetch_rows_for_viewer()}, which applies the same per-viewer scoping (per-user /
 * teacher-course) as the data report, so the chart can never show rows the viewer may not see.
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class chart_view extends base {
    /** @var string Internal entity name. */
    public const ENTITY = 'adhocchart';

    /**
     * Fix the entity name so column unique identifiers are `adhocchart:chart` (matching
     * {@see chart_query::get_default_columns()}); the base class would otherwise derive it from the
     * class name (`chart_view`), and the mismatched identifier makes add_default_columns() fail.
     */
    public function __construct() {
        $this->set_entity_name(self::ENTITY);
    }

    /**
     * Get the tables used by this entity: the plugin's own query table (one row per query).
     *
     * @return array
     */
    protected function get_default_tables(): array {
        return [query::TABLE];
    }

    /**
     * Get the default title for this entity.
     *
     * @return lang_string
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('chartcolumn', 'report_sql');
    }

    /**
     * Add this entity's single chart column.
     *
     * @return base
     */
    public function initialise(): base {
        $alias = $this->get_table_alias(query::TABLE);
        $this->add_column((new column(
            'chart',
            new lang_string('chartcolumn', 'report_sql'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_field("{$alias}.id")
            ->set_type(column::TYPE_TEXT)
            // A chart image cannot be meaningfully sorted (mirrors core's user-picture column).
            ->set_is_sortable(false)
            ->add_callback(static function ($value, \stdClass $row): string {
                return self::render_chart_cell((int) $value);
            }));
        return $this;
    }

    /**
     * Render the chart for a query as an inline `<img>` holding a base64 SVG data URI.
     *
     * Safe to embed unescaped: Report Builder does not escape column-callback output (the callback
     * owns safety), an SVG inside an `<img>` cannot execute script, and
     * {@see \report_sql\local\chart_svg} XML-escapes
     * every label/title it draws. Returns an empty string (no image) when the query is missing, not
     * published, or has no chart configured.
     *
     * @param int $queryid
     * @return string HTML.
     */
    public static function render_chart_cell(int $queryid): string {
        if ($queryid <= 0) {
            return '';
        }
        try {
            $q = query::get($queryid);
        } catch (\dml_missing_record_exception $e) {
            return '';
        }
        $rec = $q->record();

        $chartmeta = $rec->chartmeta ? json_decode($rec->chartmeta, true) : [];
        $type = (string) ($chartmeta['type'] ?? 'none');
        if ($type === '' || $type === 'none') {
            return '';
        }
        $xcol = (string) ($chartmeta['xcol'] ?? '');
        $ycol = (string) ($chartmeta['ycol'] ?? '');
        $rowlimit = max(1, min(5000, (int) ($chartmeta['rowlimit'] ?? 200)));
        $labelsize = max(11, min(48, (int) ($chartmeta['labelsize'] ?? 16)));

        // Viewer-scoped fetch: same per-user / teacher-course row filtering as the data report.
        $rows = $q->fetch_rows_for_viewer($rowlimit);
        [$labels, $values] = query::chart_series($rows, $xcol, $ycol, $q->column_textcase($xcol), $q->column_dateformat($xcol));

        $title = format_string($rec->name);

        // Delegate to the shared renderer so the chart report, the block, and any future surface all
        // draw the chart identically (image + optional data table). See query::chart_figure_html().
        return query::chart_figure_html($type, $labels, $values, $xcol, $ycol, [
            'labelsize'   => $labelsize,
            'datalabels'  => !empty($chartmeta['datalabels']),
            'multicolour' => !empty($chartmeta['multicolour']),
            'showdata'    => !empty($chartmeta['showdata']),
            'title'       => $title,
            'alt'         => $title,
        ]);
    }
}
