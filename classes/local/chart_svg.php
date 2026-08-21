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
 * Dependency-free server-side SVG chart builder.
 *
 * Moodle's {@see \core\chart_bar} family only serialises to JSON for client-side Chart.js — there
 * is no server-side rasterizer. This class renders bar / line / pie / doughnut charts straight to
 * SVG markup so a chart can be embedded in a Report Builder column callback, a scheduled export, or
 * an email with no JavaScript. The output is self-contained (explicit colours, no external refs),
 * so it survives being wrapped in a `data:` URI.
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class chart_svg {
    /** @var string[] Default categorical palette (colour-blind friendly, readable light + dark). */
    private const PALETTE = [
        '#1f77b4', '#ff7f0e', '#2ca02c', '#d62728', '#9467bd',
        '#8c564b', '#e377c2', '#7f7f7f', '#bcbd22', '#17becf',
    ];

    /** Fixed width (px) of the pie/doughnut disc area; the legend column is added to the right. */
    private const PIE_PLOT = 360;

    /**
     * Render a chart to SVG markup.
     *
     * @param string $type One of bar|line|pie|doughnut (anything else → bar).
     * @param string[] $labels Category labels (x axis / slice names).
     * @param float[] $values Numeric values, index-aligned with $labels.
     * @param string $title Optional title drawn top-centre.
     * @param array $opts Display options (width, height, axiscolor, textcolor, palette).
     * @return string SVG document markup.
     */
    public static function render(
        string $type,
        array $labels,
        array $values,
        string $title = '',
        array $opts = []
    ): string {
        $width     = max(200, (int) ($opts['width'] ?? 640));
        $height    = max(160, (int) ($opts['height'] ?? 380));
        $axiscolor = (string) ($opts['axiscolor'] ?? '#888888');
        $textcolor = (string) ($opts['textcolor'] ?? '#444444');
        $labelsize = max(8, min(48, (int) ($opts['labelsize'] ?? 16)));
        $datalabels = !empty($opts['datalabels']);
        $multicolour = !empty($opts['multicolour']);
        $palette   = !empty($opts['palette']) && is_array($opts['palette']) ? $opts['palette'] : self::PALETTE;

        // Normalise the two arrays to the same length and drop non-finite values.
        $labels = array_values($labels);
        $values = array_values(array_map(static function ($v): float {
            $v = (float) $v;
            return is_finite($v) ? $v : 0.0;
        }, $values));
        $n = min(count($labels), count($values));
        $labels = array_slice($labels, 0, $n);
        $values = array_slice($values, 0, $n);

        // Pie/doughnut labels live in a right-hand legend. Size the canvas to a fixed plot area plus
        // exactly the legend width the actual longest label needs at this font size — a flat per-size
        // bump reserved empty legend space for short labels, and since the rendered <img> is fluid,
        // that extra width shrank the whole SVG (and its labels) in narrow containers like the block.
        if ($type === 'pie' || $type === 'doughnut') {
            $width = self::PIE_PLOT + self::pie_legend_width($labels, $labelsize);
        }

        $body = '';
        if ($n === 0) {
            $body = self::empty_message($width, $height, $textcolor);
        } else {
            $body = match ($type) {
                'pie', 'doughnut' => self::render_pie(
                    $labels,
                    $values,
                    $type === 'doughnut',
                    $width,
                    $height,
                    $title,
                    $textcolor,
                    $palette,
                    $labelsize
                ),
                default => self::render_cartesian(
                    $type === 'line' ? 'line' : 'bar',
                    $labels,
                    $values,
                    $width,
                    $height,
                    $title,
                    $axiscolor,
                    $textcolor,
                    $palette,
                    $labelsize,
                    $datalabels,
                    $multicolour
                ),
            };
        }

        $titlemarkup = $title !== ''
            ? self::text($width / 2, 20, $title, $textcolor, 14, 'middle', 'font-weight:bold')
            : '';

        return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $width . '" height="' . $height . '" '
            . 'viewBox="0 0 ' . $width . ' ' . $height . '" role="img">'
            . $titlemarkup
            . $body
            . '</svg>';
    }

    /**
     * Render a bar or line chart with a shared axis frame.
     *
     * @param string $kind bar|line
     * @param string[] $labels
     * @param float[] $values
     * @param int $width
     * @param int $height
     * @param string $title
     * @param string $axiscolor
     * @param string $textcolor
     * @param string[] $palette
     * @param int $labelsize Category-label font size in px.
     * @param bool $datalabels Draw each value as a number on/above its bar or point.
     * @param bool $multicolour Bar charts only: give each bar its own palette colour instead of one shared colour.
     * @return string
     */
    private static function render_cartesian(
        string $kind,
        array $labels,
        array $values,
        int $width,
        int $height,
        string $title,
        string $axiscolor,
        string $textcolor,
        array $palette,
        int $labelsize,
        bool $datalabels = false,
        bool $multicolour = false
    ): string {
        $left   = 56;
        $right  = 16;
        $top    = $title !== '' ? 34 : 14;
        // Bar categories are drawn in full (never truncated) at $labelsize px, rotated 40°. Reserve
        // enough bottom margin for the longest label's vertical footprint so nothing is clipped;
        // capped so very long labels cannot swallow the whole plot.
        $bottom = 64;
        if ($kind === 'bar' && $labels) {
            $maxchars = 0;
            foreach ($labels as $label) {
                $maxchars = max($maxchars, \core_text::strlen((string) $label));
            }
            $labelpx = $maxchars * $labelsize * 0.6;    // ~0.6em per glyph at $labelsize px.
            $needed  = (int) ceil($labelpx * 0.643) + 24; // sin(40°) ≈ 0.643, plus tick/pad.
            $bottom  = (int) min($height * 0.55, max($bottom, $needed));
        }
        $plotw  = $width - $left - $right;
        $ploth  = $height - $top - $bottom;
        $x0     = $left;
        $ybot   = $top + $ploth;

        // Value range: always include zero so bars have a baseline, rounded out to "nice" bounds so
        // the horizontal gridlines land on round numbers.
        [$ymin, $ymax, $step] = self::nice_ticks(min(0.0, ...$values), max(0.0, ...$values), 5);
        if ($ymax === $ymin) {
            $ymax = $ymin + 1.0;
        }
        $span = $ymax - $ymin;
        $y = static fn(float $v): float => $ybot - (($v - $ymin) / $span) * $ploth;
        $zeroy = $y(0.0);

        $n    = count($values);
        $band = $plotw / $n;

        // With many categories the rotated x-labels collide into an unreadable band; draw at most
        // ~20, evenly spaced. The same step thins the line chart's per-point markers so a long
        // series does not become a wall of dots.
        $maxlabels = 20;
        $labelstep = (int) max(1, (int) ceil($n / $maxlabels));

        $svg = '';

        // Horizontal gridlines at each nice y-value, drawn faint across the whole plot so any bar or
        // point can be read off without tracing back to the axis; each carries its value label at the
        // left. The value-0 baseline and the left axis are drawn solid on top.
        $steps = (int) round(($ymax - $ymin) / $step);
        for ($k = 0; $k <= $steps; $k++) {
            $t  = $ymin + $k * $step;
            $ty = $y($t);
            $svg .= self::line($x0, $ty, $x0 + $plotw, $ty, $axiscolor, 1, 0.18);
            $svg .= self::text($x0 - 8, $ty + 4, self::num($t), $textcolor, 11, 'end');
        }

        // Axis frame: left (y) and a solid baseline at value 0.
        $svg .= self::line($x0, $top, $x0, $ybot, $axiscolor, 1);
        $svg .= self::line($x0, $zeroy, $x0 + $plotw, $zeroy, $axiscolor, 1);

        if ($kind === 'line') {
            $points = [];
            for ($i = 0; $i < $n; $i++) {
                $px = $x0 + ($i + 0.5) * $band;
                $points[] = self::coord($px) . ',' . self::coord($y($values[$i]));
            }
            $svg .= '<polyline points="' . implode(' ', $points) . '" fill="none" stroke="'
                . self::esc($palette[0]) . '" stroke-width="2"/>';
            for ($i = 0; $i < $n; $i++) {
                if ($i % $labelstep !== 0) {
                    continue;
                }
                $px = $x0 + ($i + 0.5) * $band;
                $svg .= '<circle cx="' . self::coord($px) . '" cy="' . self::coord($y($values[$i]))
                    . '" r="3" fill="' . self::esc($palette[0]) . '"/>';
                if ($datalabels) {
                    $svg .= self::text($px, $y($values[$i]) - 7, self::num($values[$i]), $textcolor, 10, 'middle');
                }
                $svg .= self::xlabel($px, $ybot, $labels[$i], $textcolor, $labelsize);
            }
        } else {
            $bw = $band * 0.7;
            for ($i = 0; $i < $n; $i++) {
                $bx  = $x0 + $i * $band + ($band - $bw) / 2;
                $vy  = $y($values[$i]);
                $bt  = min($vy, $zeroy);
                $bh  = abs($vy - $zeroy);
                // Single data series: bars share one fill colour by default. With multicolour on, each
                // bar takes its own palette colour (cycled), reading each category as distinct — the
                // same categorical use of the palette as pie/doughnut slices.
                $fill = $multicolour ? $palette[$i % count($palette)] : $palette[0];
                $svg .= '<rect x="' . self::coord($bx) . '" y="' . self::coord($bt) . '" width="'
                    . self::coord($bw) . '" height="' . self::coord(max(0.0, $bh)) . '" fill="'
                    . self::esc($fill) . '"/>';
                // Value label above the bar top — gated on a readable bar count so a long series is
                // not swamped with overlapping numbers.
                if ($datalabels && $n <= $maxlabels * 2) {
                    $svg .= self::text($bx + $bw / 2, $bt - 3, self::num($values[$i]), $textcolor, 10, 'middle');
                }
                if ($i % $labelstep === 0) {
                    // Bar categories get larger labels than the line chart's thinned markers, and
                    // are shown in full (the bottom margin above is sized to fit them).
                    $svg .= self::xlabel($x0 + ($i + 0.5) * $band, $ybot, $labels[$i], $textcolor, $labelsize, false);
                }
            }
        }

        return $svg;
    }

    /**
     * Render a pie or doughnut chart with a legend.
     *
     * @param string[] $labels
     * @param float[] $values
     * @param bool $doughnut
     * @param int $width
     * @param int $height
     * @param string $title
     * @param string $textcolor
     * @param string[] $palette
     * @param int $labelsize Legend-label font size in px.
     * @return string
     */
    private static function render_pie(
        array $labels,
        array $values,
        bool $doughnut,
        int $width,
        int $height,
        string $title,
        string $textcolor,
        array $palette,
        int $labelsize
    ): string {
        // Pie slices are non-negative; negatives contribute nothing.
        $slices = [];
        $total  = 0.0;
        foreach ($values as $i => $v) {
            $v = max(0.0, (float) $v);
            if ($v > 0) {
                $slices[] = ['i' => $i, 'label' => (string) $labels[$i], 'value' => $v];
                $total += $v;
            }
        }
        if ($total <= 0) {
            return self::empty_message($width, $height, $textcolor);
        }

        $top    = $title !== '' ? 34 : 10;
        // Legend geometry scales with the label font size. Swatch + gap sit left of the text. The
        // column width is content-aware (sized to the actual longest label) via the same helper
        // render() used to size the canvas, so the legend and the canvas always agree.
        $swatch  = max(10, (int) round($labelsize * 0.9));
        $textoff = $swatch + 6;                           // swatch + gap before the text starts.
        $charw   = $labelsize * 0.6;                      // ~0.6em per glyph.
        $legendw = self::pie_legend_width($labels, $labelsize);
        $cx     = ($width - $legendw) / 2;
        $cy     = $top + ($height - $top - 10) / 2;
        $r      = max(20.0, min($cx, $cy - $top) - 10);
        $ir     = $doughnut ? $r * 0.55 : 0.0;

        $svg = '';

        if (count($slices) === 1) {
            // A single slice is a full circle — arc paths degenerate, so draw a ring/disc.
            $fill = $palette[$slices[0]['i'] % count($palette)];
            if ($doughnut) {
                $svg .= '<path d="' . self::ring_path($cx, $cy, $r, $ir) . '" fill="' . self::esc($fill)
                    . '" fill-rule="evenodd"/>';
            } else {
                $svg .= '<circle cx="' . self::coord($cx) . '" cy="' . self::coord($cy) . '" r="'
                    . self::coord($r) . '" fill="' . self::esc($fill) . '"/>';
            }
        } else {
            $angle = -M_PI / 2;
            foreach ($slices as $slice) {
                $frac = $slice['value'] / $total;
                $end  = $angle + $frac * 2 * M_PI;
                $fill = $palette[$slice['i'] % count($palette)];
                $svg .= '<path d="' . self::slice_path($cx, $cy, $r, $ir, $angle, $end)
                    . '" fill="' . self::esc($fill) . '"/>';
                $angle = $end;
            }
        }

        // Legend down the right edge: colour swatch + "label (value)". Row step, swatch size and
        // the per-row character budget all track $labelsize so bigger text still fits its column.
        $lx = $width - $legendw + 8;
        $ly = $top + 6;
        $rowstep = $labelsize + 8;
        $maxchars = max(6, (int) floor(($legendw - 16 - $textoff) / $charw));
        foreach ($slices as $slice) {
            $fill = $palette[$slice['i'] % count($palette)];
            $svg .= '<rect x="' . self::coord($lx) . '" y="' . self::coord($ly)
                . '" width="' . $swatch . '" height="' . $swatch . '" fill="' . self::esc($fill) . '"/>';
            $suffix = ' (' . self::num($slice['value']) . ')';
            $label  = self::truncate($slice['label'], max(3, $maxchars - \core_text::strlen($suffix))) . $suffix;
            $svg .= self::text($lx + $textoff, $ly + $swatch - 2, $label, $textcolor, $labelsize, 'start');
            $ly += $rowstep;
        }

        return $svg;
    }

    /**
     * Build an SVG path for a pie slice (ir = 0) or a doughnut segment (ir > 0).
     *
     * @param float $cx
     * @param float $cy
     * @param float $r Outer radius.
     * @param float $ir Inner radius (0 for a solid pie slice).
     * @param float $start Start angle in radians.
     * @param float $end End angle in radians.
     * @return string
     */
    private static function slice_path(float $cx, float $cy, float $r, float $ir, float $start, float $end): string {
        $large = ($end - $start) > M_PI ? 1 : 0;
        $ox1 = $cx + $r * cos($start);
        $oy1 = $cy + $r * sin($start);
        $ox2 = $cx + $r * cos($end);
        $oy2 = $cy + $r * sin($end);
        if ($ir <= 0) {
            return 'M ' . self::coord($cx) . ' ' . self::coord($cy)
                . ' L ' . self::coord($ox1) . ' ' . self::coord($oy1)
                . ' A ' . self::coord($r) . ' ' . self::coord($r) . ' 0 ' . $large . ' 1 '
                . self::coord($ox2) . ' ' . self::coord($oy2) . ' Z';
        }
        $ix1 = $cx + $ir * cos($start);
        $iy1 = $cy + $ir * sin($start);
        $ix2 = $cx + $ir * cos($end);
        $iy2 = $cy + $ir * sin($end);
        return 'M ' . self::coord($ox1) . ' ' . self::coord($oy1)
            . ' A ' . self::coord($r) . ' ' . self::coord($r) . ' 0 ' . $large . ' 1 '
            . self::coord($ox2) . ' ' . self::coord($oy2)
            . ' L ' . self::coord($ix2) . ' ' . self::coord($iy2)
            . ' A ' . self::coord($ir) . ' ' . self::coord($ir) . ' 0 ' . $large . ' 0 '
            . self::coord($ix1) . ' ' . self::coord($iy1) . ' Z';
    }

    /**
     * Build a full ring (annulus) path for a single-slice doughnut, using the even-odd fill rule.
     *
     * @param float $cx
     * @param float $cy
     * @param float $r Outer radius.
     * @param float $ir Inner radius.
     * @return string
     */
    private static function ring_path(float $cx, float $cy, float $r, float $ir): string {
        $circle = static fn(float $rad): string =>
            'M ' . self::coord($cx - $rad) . ' ' . self::coord($cy)
            . ' a ' . self::coord($rad) . ' ' . self::coord($rad) . ' 0 1 0 ' . self::coord($rad * 2) . ' 0'
            . ' a ' . self::coord($rad) . ' ' . self::coord($rad) . ' 0 1 0 ' . self::coord(-$rad * 2) . ' 0 Z';
        return $circle($r) . ' ' . $circle($ir);
    }

    /**
     * A rotated x-axis label centred under a band.
     *
     * @param float $cx Band centre x.
     * @param float $ybot Plot bottom y.
     * @param string $label
     * @param string $textcolor
     * @param int $size Font size in px.
     * @param bool $truncate Whether to shorten over-long labels with an ellipsis.
     * @return string
     */
    private static function xlabel(
        float $cx,
        float $ybot,
        string $label,
        string $textcolor,
        int $size = 11,
        bool $truncate = true
    ): string {
        if ($truncate) {
            $label = self::truncate($label);
        }
        return '<text x="' . self::coord($cx) . '" y="' . self::coord($ybot + 14)
            . '" transform="rotate(-40 ' . self::coord($cx) . ' ' . self::coord($ybot + 14) . ')" '
            . 'font-size="' . $size . '" fill="' . self::esc($textcolor) . '" text-anchor="end" '
            . 'font-family="sans-serif">' . self::esc($label) . '</text>';
    }

    /**
     * Centred "no data" placeholder.
     *
     * @param int $width
     * @param int $height
     * @param string $textcolor
     * @return string
     */
    private static function empty_message(int $width, int $height, string $textcolor): string {
        return self::text($width / 2, $height / 2, get_string('errchartdata', 'report_sql'), $textcolor, 13, 'middle');
    }

    /**
     * A styled `<text>` element (content is escaped).
     *
     * @param float $x
     * @param float $y
     * @param string $content
     * @param string $color
     * @param int $size
     * @param string $anchor start|middle|end
     * @param string $style Extra inline style.
     * @return string
     */
    private static function text(
        float $x,
        float $y,
        string $content,
        string $color,
        int $size,
        string $anchor = 'start',
        string $style = ''
    ): string {
        $stylemarkup = $style !== '' ? ' style="' . self::esc($style) . '"' : '';
        return '<text x="' . self::coord($x) . '" y="' . self::coord($y) . '" font-size="' . $size
            . '" fill="' . self::esc($color) . '" text-anchor="' . self::esc($anchor)
            . '" font-family="sans-serif"' . $stylemarkup . '>' . self::esc($content) . '</text>';
    }

    /**
     * A straight line element.
     *
     * @param float $x1
     * @param float $y1
     * @param float $x2
     * @param float $y2
     * @param string $color
     * @param float $w Stroke width.
     * @param float $opacity Stroke opacity (0-1); emitted only when < 1 (e.g. faint gridlines).
     * @return string
     */
    private static function line(
        float $x1,
        float $y1,
        float $x2,
        float $y2,
        string $color,
        float $w,
        float $opacity = 1.0
    ): string {
        $op = $opacity < 1.0 ? ' stroke-opacity="' . self::coord($opacity) . '"' : '';
        return '<line x1="' . self::coord($x1) . '" y1="' . self::coord($y1) . '" x2="' . self::coord($x2)
            . '" y2="' . self::coord($y2) . '" stroke="' . self::esc($color) . '" stroke-width="' . $w . '"' . $op . '/>';
    }

    /**
     * Compute "nice" axis bounds and a round tick step for a value range.
     *
     * Rounds the min down and the max up to multiples of a 1/2/5×10ⁿ step near span/$target, so the
     * gridlines land on human-readable numbers (10, 20, 50, …) instead of raw data extremes. The
     * caller passes a range already widened to include zero, so the returned bounds still bracket
     * zero (the bar/line baseline).
     *
     * @param float $min Range minimum (≤ 0 in practice).
     * @param float $max Range maximum (≥ 0 in practice).
     * @param int $target Desired approximate tick count.
     * @return array{0: float, 1: float, 2: float} [niceMin, niceMax, step]
     */
    private static function nice_ticks(float $min, float $max, int $target): array {
        $span = $max - $min;
        if ($span <= 0) {
            return [$min, $min + 1.0, 1.0];
        }
        $raw  = $span / max(1, $target);
        $mag  = 10 ** floor(log10($raw));
        $norm = $raw / $mag;
        $step = ($norm < 1.5 ? 1 : ($norm < 3 ? 2 : ($norm < 7 ? 5 : 10))) * $mag;
        $nicemin = floor($min / $step) * $step;
        $nicemax = ceil($max / $step) * $step;
        return [$nicemin, $nicemax, $step];
    }

    /**
     * Format a coordinate: fixed 2dp, trailing zeros trimmed, to keep the markup compact.
     *
     * @param float $v
     * @return string
     */
    private static function coord(float $v): string {
        $s = number_format($v, 2, '.', '');
        $s = rtrim(rtrim($s, '0'), '.');
        return ($s === '' || $s === '-0') ? '0' : $s;
    }

    /**
     * Format a data value for display (2dp max, trailing zeros trimmed).
     *
     * @param float $v
     * @return string
     */
    private static function num(float $v): string {
        return self::coord($v);
    }

    /**
     * Truncate an over-long label so it does not overflow the legend / axis.
     *
     * @param string $s
     * @param int $max
     * @return string
     */
    private static function truncate(string $s, int $max = 22): string {
        return \core_text::strlen($s) > $max ? \core_text::substr($s, 0, $max - 1) . '…' : $s;
    }

    /**
     * Width (px) of the pie/doughnut legend column for the given labels at the given font size.
     *
     * Sized to the actual longest label (plus room for the appended " (value)" suffix), not a flat
     * per-size constant — so short labels no longer reserve empty width that, on a fluid <img>,
     * shrinks the whole chart in narrow containers. Floored so a tiny label still gets a usable
     * column and capped so a very long label never dominates the canvas (it truncates instead).
     * Both {@see render()} (to size the canvas) and {@see render_pie()} (to place the legend) call
     * this, so the two always agree.
     *
     * @param string[] $labels
     * @param int $labelsize Legend font size in px.
     * @return int Legend column width in px.
     */
    private static function pie_legend_width(array $labels, int $labelsize): int {
        $charw  = $labelsize * 0.6;                        // ~0.6em per glyph.
        $swatch = max(10, (int) round($labelsize * 0.9));
        $glyphs = 8;                                       // floor: swatch + a short "(n)" suffix.
        foreach ($labels as $lab) {
            // +6 glyphs of headroom for the " (value)" the legend appends to each label.
            $glyphs = max($glyphs, \core_text::strlen((string) $lab) + 6);
        }
        $glyphs = min($glyphs, 28);                        // cap: longer labels truncate, not widen.
        $col = 8 + $swatch + 6 + (int) round($glyphs * $charw) + 8;
        return (int) max(140, min(520, $col));
    }

    /**
     * XML-escape a string for safe inclusion in SVG markup.
     *
     * @param string $s
     * @return string
     */
    private static function esc(string $s): string {
        return htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
