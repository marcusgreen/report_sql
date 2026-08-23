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

namespace report_sql\local;

/**
 * Presentation helpers shared by every surface that renders a query's chart or its
 * date/text-case formatted cells: chart.php, the SQL Report block, the inline embed, and the
 * Report Builder data / chart entities.
 *
 * These are pure display transforms with no query record state — extracted from {@see query} so
 * the "saved query entity" no longer carries the chart/formatting rendering concern. Record-bound
 * accessors that decide *which* transform a column gets ({@see query::column_dateformat()},
 * {@see query::column_textcase()}) stay on {@see query} and delegate here.
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class chart_presenter {
    /** Default display format when a %%TIMESTAMP() token gives none: `dd-mmm-yyyy`, e.g. 15-Jun-2026. */
    public const DEFAULT_DATE_FORMAT = '%d-%b-%Y';

    /**
     * Derive chart labels + numeric values from fetched rows.
     *
     * Shared by chart.php, the block, and {@see \report_sql\local\chart_svg} so the x/y
     * extraction lives in one place. Labels are the x column cast to string; values are the y
     * column cast to float (missing → 0).
     *
     * The x column may carry a %%CASE()%% transform. Because charts read the raw VIEW value (not the
     * Report Builder display callback that formats table cells), the case is applied here to the
     * labels so a pie/bar chart matches the data report; pass the mode as $xcase.
     *
     * @param array $rows Rows as associative arrays.
     * @param string $xcol Label (x) column name.
     * @param string $ycol Value (y) column name.
     * @param string $xcase Optional %%CASE()%% mode for the x column (upper|lower|title|sentence).
     * @param string|null $xdateformat Optional strftime format for a timestamp x column (epoch → date label).
     * @return array [labels, values]
     */
    public static function chart_series(
        array $rows,
        string $xcol,
        string $ycol,
        string $xcase = '',
        ?string $xdateformat = null
    ): array {
        $labels = [];
        $values = [];
        foreach ($rows as $row) {
            $raw = $row[$xcol] ?? '';
            if ($xdateformat !== null && $raw !== '' && is_numeric($raw)) {
                // Timestamp x column: format the epoch the same way the data table does, so the
                // axis reads as dates instead of raw Unix seconds. $xdateformat is a strftime string.
                $labels[] = userdate((int) $raw, $xdateformat, 99, false);
            } else {
                $labels[] = self::format_textcase((string) $raw, $xcase);
            }
            $values[] = (float) ($row[$ycol] ?? 0);
        }
        return [$labels, $values];
    }

    /**
     * Apply a %%CASE() display transform to a value. UTF-8-safe and identical on every database (the
     * transform runs in PHP, not SQL) — notably `title` reproduces Postgres INITCAP, which
     * MySQL/MariaDB has no equivalent for; `sentence` lower-cases then upper-cases the first letter.
     * An empty or unknown mode returns the value unchanged. Shared by the Report Builder column
     * callback ({@see \report_sql\reportbuilder\local\entities\adhoc_view}) and the chart
     * label extraction above, so table and chart render the same text.
     *
     * @param string $value Raw column value.
     * @param string $mode ''|upper|lower|title|sentence.
     * @return string
     */
    public static function format_textcase(string $value, string $mode): string {
        if ($value === '' || $mode === '') {
            return $value;
        }
        return match ($mode) {
            'upper' => \core_text::strtoupper($value),
            'lower' => \core_text::strtolower($value),
            'title' => mb_convert_case($value, MB_CASE_TITLE, 'UTF-8'),
            'sentence' => \core_text::strtoupper(\core_text::substr($value, 0, 1))
                . \core_text::strtolower(\core_text::substr($value, 1)),
            default => $value,
        };
    }

    /**
     * Build the chart's display HTML: the SVG chart as an inline `<img>`, plus — when the `showdata`
     * option is set — the accessible label/value table beneath it.
     *
     * Single source of truth for how a chart is rendered, shared by the Report Builder chart report
     * ({@see \report_sql\reportbuilder\local\entities\chart_view}) and the SQL Report
     * block, so a change to chart display appears on every surface at once. The `<img>` holds a
     * base64 SVG data URI (cannot execute script); the data-table cells are all s()-escaped.
     *
     * @param string $type Chart type (bar|line|pie|doughnut).
     * @param string[] $labels Category labels.
     * @param float[] $values Numeric values, index-aligned with $labels.
     * @param string $xcol Label (x) column name — the first data-table header.
     * @param string $ycol Value (y) column name — the second data-table header.
     * @param array $opts Display options (labelsize, datalabels, multicolour, showdata, title, alt).
     * @return string HTML.
     */
    public static function chart_figure_html(
        string $type,
        array $labels,
        array $values,
        string $xcol,
        string $ycol,
        array $opts = []
    ): string {
        $labelsize = max(11, min(48, (int) ($opts['labelsize'] ?? 16)));
        $title = (string) ($opts['title'] ?? '');
        $alt = (string) ($opts['alt'] ?? '');

        $svg = chart_svg::render($type, $labels, $values, $title, [
            'labelsize'   => $labelsize,
            'datalabels'  => !empty($opts['datalabels']),
            'multicolour' => !empty($opts['multicolour']),
        ]);
        $html = \html_writer::img('data:image/svg+xml;base64,' . base64_encode($svg), $alt, [
            'class' => 'report-sql-chart img-fluid',
            'style' => 'max-width:100%;height:auto;',
        ]);

        if (!empty($opts['showdata'])) {
            $head = \html_writer::tag('thead', \html_writer::tag(
                'tr',
                \html_writer::tag('th', s($xcol), ['scope' => 'col'])
                . \html_writer::tag('th', s($ycol), ['scope' => 'col'])
            ));
            $body = '';
            foreach ($labels as $i => $label) {
                $value = $values[$i] ?? 0;
                $body .= \html_writer::tag(
                    'tr',
                    \html_writer::tag('td', s((string) $label))
                    . \html_writer::tag('td', s((string) $value))
                );
            }
            $html .= \html_writer::tag(
                'table',
                $head . \html_writer::tag('tbody', $body),
                ['class' => 'report-sql-chart-data table table-sm table-striped w-auto mt-2']
            );
        }

        return $html;
    }

    /**
     * Translate a neutral display format (e.g. `dd/mm/yyyy`, `ddd dd Mon yyyy`) into the strftime-style
     * format {@see userdate()} expects. Unrecognised characters pass through, so separators like
     * `/ - . :` and spaces are preserved. An empty format yields the default `dd-mmm-yyyy`.
     *
     * Single source shared by the Report Builder column callback
     * ({@see \report_sql\reportbuilder\local\entities\adhoc_view}) and the block table, so a
     * %%TIMESTAMP() column formats identically on every surface.
     *
     * @param string $neutral Neutral format from the %%TIMESTAMP(expr, format)%% token.
     * @return string strftime format.
     */
    public static function strftime_format(string $neutral): string {
        $neutral = trim($neutral);
        if ($neutral === '') {
            return self::DEFAULT_DATE_FORMAT;
        }
        // Longest tokens first so 'month'/'mmmm' beat 'mon'/'mmm'/'mm', 'yyyy' beats 'yy',
        // 'dddd' beats 'ddd' beats 'dd'. 'mmm'/'mmmm'/'dddd' are Excel-style aliases of
        // 'mon'/'month'/(full weekday), matching MySQL DATE_FORMAT %b/%M/%W.
        $map = [
            'mmmm' => '%B', 'month' => '%B', 'dddd' => '%A', 'yyyy' => '%Y',
            'mmm' => '%b', 'mon' => '%b', 'ddd' => '%a',
            'hh' => '%H', 'mi' => '%M', 'ss' => '%S', 'yy' => '%y', 'mm' => '%m', 'dd' => '%d',
        ];
        return (string) preg_replace_callback(
            '/mmmm|month|dddd|yyyy|mmm|mon|ddd|hh|mi|ss|yy|mm|dd/i',
            static fn(array $m): string => $map[strtolower($m[0])],
            $neutral
        );
    }
}
