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

namespace report_sql\output;

use html_table;
use html_writer;
use report_sql\local\query;

/**
 * Shared inline render path for a published report (table or chart).
 *
 * One place builds the HTML that surfaces a report outside the Report Builder viewer:
 * block_reportsources (the companion block) and filter_reportsources (the [[reportsource:ID]]
 * inline embed) both render through here, so a change to the table/chart markup lands on every
 * embed at once. The block augments the chart output with its own "Expand" control; everything
 * else — the timestamp/text-case display transforms, the chart figure, the new-tab link fix —
 * lives here.
 *
 * The caller is responsible for the access gate and row scoping: pass rows already produced by
 * {@see query::fetch_rows_for_viewer()} after {@see query::current_user_can_view_report()}.
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class embed_renderer {
    /**
     * Render the rows for a query, choosing table or chart from the query's chart config and the
     * requested display mode.
     *
     * @param query $query The bound query (drives chart config + display transforms).
     * @param array $rows Rows already scoped for the current viewer.
     * @param string $mode auto|table|chart. auto renders the chart when the query has one configured.
     * @param string $alt Accessible chart image alt text (defaults to the query name).
     * @return string HTML.
     */
    public static function render(query $query, array $rows, string $mode = 'auto', string $alt = ''): string {
        $rec = $query->record();
        $chartmeta = $rec->chartmeta ? json_decode($rec->chartmeta, true) : [];
        $haschart = !empty($chartmeta['type']) && $chartmeta['type'] !== 'none';

        if ($rows && ($mode === 'chart' || ($mode === 'auto' && $haschart))) {
            return self::render_chart(
                $query,
                $rows,
                is_array($chartmeta) ? $chartmeta : [],
                $alt !== '' ? $alt : $query->name()
            );
        }
        return self::render_table($query, $rows);
    }

    /**
     * Render the rows as a simple table, applying the same %%TIMESTAMP() date formatting and
     * %%CASE() text-case transforms the RB data report applies via column callbacks (both are
     * display-only; the stored value is raw).
     *
     * @param query $query The bound query (for column display metadata).
     * @param array $rows
     * @return string
     */
    public static function render_table(query $query, array $rows): string {
        if (!$rows) {
            return html_writer::tag('p', get_string('norows', 'report_sql'), ['class' => 'text-muted']);
        }
        // Per-column display metadata, keyed lower-case so the lookup is case-insensitive against the
        // row keys.
        $meta = array_change_key_case($query->columns_meta());

        $table = new html_table();
        $table->head = array_map('s', array_keys($rows[0]));
        $table->attributes['class'] = 'table table-sm';
        foreach ($rows as $row) {
            $cells = [];
            foreach ($row as $col => $v) {
                $m = $meta[strtolower((string) $col)] ?? [];
                if (($m['type'] ?? '') === 'timestamp') {
                    // Raw epoch → formatted date, using the column's saved format (else the default).
                    $cells[] = ($v === null || $v === '')
                        ? ''
                        : s(userdate((int) $v, query::strftime_format((string) ($m['dateformat'] ?? '')), 99, false));
                } else if (!empty($m['textcase'])) {
                    // Display-only case transform on the raw text.
                    $cells[] = s(query::format_textcase((string) $v, (string) $m['textcase']));
                } else {
                    // Cells may contain author-authored HTML (e.g. a CONCAT'd course link), exactly as
                    // the RB report renders it. format_text() keeps anchors but strips scripts. Filters
                    // are off so plain values pass through.
                    $cells[] = self::open_links_in_new_tab(
                        format_text((string) $v, FORMAT_HTML, ['filter' => false])
                    );
                }
            }
            $table->data[] = $cells;
        }
        return html_writer::table($table);
    }

    /**
     * Render the rows as a chart figure (image + optional data table), using the query's saved
     * chart config. Falls back to a table when the chart has no x/y columns set.
     *
     * @param query $query The bound query (for %%CASE%% label transforms).
     * @param array $rows
     * @param array $chartmeta Decoded chartmeta.
     * @param string $alt Accessible alt text for the chart image.
     * @return string
     */
    public static function render_chart(query $query, array $rows, array $chartmeta, string $alt = ''): string {
        $xcol = (string) ($chartmeta['xcol'] ?? '');
        $ycol = (string) ($chartmeta['ycol'] ?? '');
        if ($xcol === '' || $ycol === '') {
            return self::render_table($query, $rows);
        }

        // Apply the x-column's %%CASE%% transform to the labels, matching the data report and chart.php.
        [$labels, $values] = query::chart_series($rows, $xcol, $ycol, $query->column_textcase($xcol), $query->column_dateformat($xcol));
        $type = (string) $chartmeta['type'];

        // Category/legend label font size, clamped to the same range as the edit form and RB chart report.
        $labelsize = max(11, min(48, (int) ($chartmeta['labelsize'] ?? 16)));

        // Shared server-side SVG chart renderer: no JavaScript, no Chart.js; the <img> holds a base64
        // data URI and cannot execute script.
        return query::chart_figure_html($type, $labels, $values, $xcol, $ycol, [
            'labelsize'   => $labelsize,
            'datalabels'  => !empty($chartmeta['datalabels']),
            'multicolour' => !empty($chartmeta['multicolour']),
            'showdata'    => !empty($chartmeta['showdata']),
            'alt'         => $alt,
        ]);
    }

    /**
     * Force every anchor in cleaned cell HTML to open in a new tab. clean_text() strips author
     * target attributes, so links would otherwise navigate the current tab (losing the host page).
     * rel="noopener noreferrer" closes the reverse-tabnabbing hole a bare target="_blank" opens.
     *
     * @param string $html Cleaned cell HTML.
     * @return string
     */
    public static function open_links_in_new_tab(string $html): string {
        return preg_replace(
            '/<a\b(?![^>]*\btarget=)/i',
            '<a target="_blank" rel="noopener noreferrer"',
            $html
        );
    }
}
