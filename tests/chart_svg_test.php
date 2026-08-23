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

namespace report_sql;

use report_sql\local\chart_presenter;
use report_sql\local\chart_svg;

/**
 * Unit tests for the server-side SVG chart builder and the shared chart_series extraction.
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \report_sql\local\chart_svg
 */
final class chart_svg_test extends \advanced_testcase {
    /**
     * Assert a string is a well-formed SVG document (parses as XML with an <svg> root).
     *
     * @param string $svg
     */
    private function assert_valid_svg(string $svg): void {
        $this->assertStringStartsWith('<svg', $svg);
        $prev = libxml_use_internal_errors(true);
        $doc = simplexml_load_string($svg);
        libxml_use_internal_errors($prev);
        $this->assertNotFalse($doc, 'chart_svg output is not well-formed XML: ' . $svg);
        $this->assertSame('svg', $doc->getName());
    }

    /**
     * Every chart type renders well-formed SVG at a sensible size.
     *
     * @return void
     */
    public function test_each_type_is_valid_svg(): void {
        $labels = ['Alpha', 'Beta', 'Gamma'];
        $values = [3.0, 7.5, 2.0];
        foreach (['bar', 'line', 'pie', 'doughnut', 'unknown'] as $type) {
            $svg = chart_svg::render($type, $labels, $values, 'My chart');
            $this->assert_valid_svg($svg);
            $this->assertStringContainsString('My chart', $svg);
        }
    }

    /**
     * Labels are XML-escaped — an HTML/script payload cannot break out of the markup.
     *
     * @return void
     */
    public function test_labels_are_escaped(): void {
        $svg = chart_svg::render('bar', ['<script>alert(1)</script>'], [5.0]);
        $this->assert_valid_svg($svg);
        $this->assertStringNotContainsString('<script>', $svg);
        $this->assertStringContainsString('&lt;script&gt;', $svg);
    }

    /**
     * Empty data renders a valid placeholder SVG rather than crashing.
     *
     * @return void
     */
    public function test_empty_data(): void {
        $this->resetAfterTest();
        foreach (['bar', 'line', 'pie', 'doughnut'] as $type) {
            $svg = chart_svg::render($type, [], []);
            $this->assert_valid_svg($svg);
        }
    }

    /**
     * A single non-zero slice (full circle) does not produce degenerate arc markup.
     *
     * @return void
     */
    public function test_single_slice_pie_and_doughnut(): void {
        foreach (['pie', 'doughnut'] as $type) {
            $svg = chart_svg::render($type, ['Only'], [42.0]);
            $this->assert_valid_svg($svg);
        }
    }

    /**
     * Negative values are handled: bars get a baseline, pie ignores negatives.
     *
     * @return void
     */
    public function test_negative_values(): void {
        $bar = chart_svg::render('bar', ['a', 'b', 'c'], [-4.0, 2.0, -1.0]);
        $this->assert_valid_svg($bar);

        // Pie with only negative/zero values falls back to the placeholder, still valid.
        $pie = chart_svg::render('pie', ['a', 'b'], [-1.0, 0.0]);
        $this->assert_valid_svg($pie);
    }

    /**
     * Mismatched label/value counts are truncated to the shorter length without error.
     *
     * @return void
     */
    public function test_ragged_input(): void {
        $svg = chart_svg::render('bar', ['a', 'b', 'c'], [1.0]);
        $this->assert_valid_svg($svg);
    }

    /**
     * A long series thins its x-axis labels (and line markers) so they do not collide into an
     * unreadable band — at most ~20 are drawn regardless of point count.
     *
     * @return void
     */
    public function test_many_points_thin_labels(): void {
        $labels = [];
        $values = [];
        for ($i = 0; $i < 200; $i++) {
            $labels[] = 'Day ' . $i;
            $values[] = (float) ($i % 17);
        }
        foreach (['bar', 'line'] as $type) {
            $svg = chart_svg::render($type, $labels, $values);
            $this->assert_valid_svg($svg);
            $drawn = substr_count($svg, 'rotate(-40');
            $this->assertLessThanOrEqual(20, $drawn, "$type drew too many x-labels: $drawn");
            $this->assertGreaterThan(0, $drawn);
        }
    }

    /**
     * The datalabels option prints each value as text on a bar / line chart, and only when enabled.
     *
     * @return void
     */
    public function test_data_labels_option(): void {
        $labels = ['a', 'b', 'c'];
        $values = [12.0, 34.0, 56.0];
        foreach (['bar', 'line'] as $type) {
            $off = chart_svg::render($type, $labels, $values, '', ['datalabels' => false]);
            $on  = chart_svg::render($type, $labels, $values, '', ['datalabels' => true]);
            $this->assert_valid_svg($on);
            // A distinctive value string appears as a drawn label only when the option is on.
            $this->assertStringContainsString('>56<', $on, "$type should print value labels when enabled");
            $this->assertStringNotContainsString('>56<', $off, "$type should not print value labels when disabled");
        }
    }

    /**
     * A long bar/line series suppresses value labels (too many to draw without overlap) while still
     * rendering valid SVG.
     *
     * @return void
     */
    public function test_data_labels_suppressed_when_many_points(): void {
        $labels = [];
        $values = [];
        for ($i = 0; $i < 100; $i++) {
            $labels[] = 'p' . $i;
            $values[] = 900.0 + $i; // Distinctive 3-digit values.
        }
        $svg = chart_svg::render('bar', $labels, $values, '', ['datalabels' => true]);
        $this->assert_valid_svg($svg);
        $this->assertStringNotContainsString('>999<', $svg, 'value labels should be suppressed for a long series');
    }

    /**
     * A single-series bar chart uses one fill colour for every bar (no per-bar palette cycling).
     *
     * @return void
     */
    public function test_bars_share_one_colour(): void {
        $svg = chart_svg::render('bar', ['a', 'b', 'c', 'd'], [1.0, 2.0, 3.0, 4.0]);
        $this->assert_valid_svg($svg);
        // Every <rect> bar fill is the first palette colour; the second palette colour is not used.
        $this->assertGreaterThanOrEqual(4, substr_count($svg, 'fill="#1f77b4"'));
        $this->assertStringNotContainsString('#ff7f0e', $svg);
    }

    /**
     * A bar/line chart draws faint horizontal gridlines (stroke-opacity) at round y-values.
     *
     * @return void
     */
    public function test_gridlines_present(): void {
        $svg = chart_svg::render('bar', ['a', 'b'], [0.0, 100.0]);
        $this->assert_valid_svg($svg);
        $this->assertStringContainsString('stroke-opacity', $svg);
        // Nice bounds round the max out to 100, so that tick label is drawn.
        $this->assertStringContainsString('>100<', $svg);
    }

    /**
     * chart_series extracts labels (string) and values (float) from row arrays.
     *
     * @return void
     */
    public function test_chart_series(): void {
        $rows = [
            ['name' => 'Alpha', 'total' => '3'],
            ['name' => 'Beta', 'total' => 7.5],
            ['name' => 123, 'total' => null],
        ];
        [$labels, $values] = chart_presenter::chart_series($rows, 'name', 'total');
        $this->assertSame(['Alpha', 'Beta', '123'], $labels);
        $this->assertSame([3.0, 7.5, 0.0], $values);
    }

    /**
     * chart_series tolerates a missing x or y column (label '' / value 0.0).
     *
     * @return void
     */
    public function test_chart_series_missing_columns(): void {
        [$labels, $values] = chart_presenter::chart_series([['a' => 1]], 'nolabel', 'novalue');
        $this->assertSame([''], $labels);
        $this->assertSame([0.0], $values);
    }

    /**
     * chart_series applies a %%CASE() mode to the labels so a chart matches the data report (which
     * transforms the same column in its display callback).
     *
     * @return void
     */
    public function test_chart_series_applies_textcase(): void {
        $rows = [['city' => 'new york', 'n' => 1], ['city' => 'LONDON', 'n' => 2]];
        [$labels] = chart_presenter::chart_series($rows, 'city', 'n', 'title');
        $this->assertSame(['New York', 'London'], $labels);
        // Empty mode leaves labels untouched.
        [$raw] = chart_presenter::chart_series($rows, 'city', 'n', '');
        $this->assertSame(['new york', 'LONDON'], $raw);
    }

    /**
     * format_textcase applies each mode, is UTF-8-safe, and passes through empty/unknown modes.
     *
     * @return void
     */
    public function test_format_textcase_modes(): void {
        $this->assertSame('JOSÉ', chart_presenter::format_textcase('josé', 'upper'));
        $this->assertSame('josé', chart_presenter::format_textcase('JOSÉ', 'lower'));
        $this->assertSame('New York', chart_presenter::format_textcase('new york', 'title'));
        $this->assertSame('Hello world', chart_presenter::format_textcase('hELLO wORLD', 'sentence'));
        $this->assertSame('unchanged', chart_presenter::format_textcase('unchanged', ''));
        $this->assertSame('unchanged', chart_presenter::format_textcase('unchanged', 'bogus'));
        $this->assertSame('', chart_presenter::format_textcase('', 'upper'));
    }
}
