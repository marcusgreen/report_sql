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

/**
 * Tests for the neutral-to-strftime date-format mapper shared by the RB entity and the block.
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \report_sql\local\chart_presenter::strftime_format
 */
final class adhoc_view_test extends \advanced_testcase {
    /**
     * Invoke the public static strftime_format() mapper (now the single source on chart_presenter).
     *
     * @param string $neutral
     * @return string
     */
    private function map(string $neutral): string {
        return chart_presenter::strftime_format($neutral);
    }

    /**
     * A neutral display format is translated to the strftime codes userdate() expects, with
     * longest-token precedence and separators passed through untouched.
     */
    public function test_strftime_format_translates_neutral_tokens(): void {
        $this->assertSame('%d/%m/%Y', $this->map('dd/mm/yyyy'));
        $this->assertSame('%a %d %b %Y', $this->map('ddd dd Mon yyyy'));
        $this->assertSame('%d-%b-%y', $this->map('dd-Mon-yy'));
        $this->assertSame('%B %Y', $this->map('Month yyyy'));
        $this->assertSame('%H:%M:%S', $this->map('hh:mi:ss'));
        // Excel-style aliases (MySQL DATE_FORMAT %b/%M/%W): mmm=Jun, mmmm=June, dddd=Monday.
        $this->assertSame('%d %b %Y', $this->map('dd mmm yyyy'));
        $this->assertSame('%d %B %Y', $this->map('dd mmmm yyyy'));
        $this->assertSame('%A %d %B %Y', $this->map('dddd dd mmmm yyyy'));
        // An empty format yields the dd-mmm-yyyy default.
        $this->assertSame('%d-%b-%Y', $this->map(''));
        $this->assertSame('%d-%b-%Y', $this->map('   '));
    }

    /**
     * The format is case-insensitive (DD/MM/YYYY behaves like dd/mm/yyyy).
     */
    public function test_strftime_format_is_case_insensitive(): void {
        $this->assertSame('%d/%m/%Y', $this->map('DD/MM/YYYY'));
    }

    /**
     * render_link() wraps the value in a site-relative <a href>, substituting the url-encoded value
     * into the path's {} slot, escaping both the URL and the visible text, and returning nothing for
     * an empty value.
     */
    public function test_render_link_builds_escaped_site_relative_link(): void {
        global $CFG;
        $this->resetAfterTest();

        $html = \report_sql\reportbuilder\local\entities\adhoc_view::render_link('42', '/user/view.php?id={}');
        $this->assertStringContainsString('href="' . $CFG->wwwroot . '/user/view.php?id=42"', $html);
        $this->assertStringContainsString('>42</a>', $html);

        // The visible text is escaped; the value is url-encoded into the path.
        $xss = \report_sql\reportbuilder\local\entities\adhoc_view::render_link('a<b>', '/x.php?q={}');
        $this->assertStringNotContainsString('<b>', $xss);
        $this->assertStringContainsString('a%3Cb%3E', $xss);

        // An empty value renders no dangling link.
        $this->assertSame('', \report_sql\reportbuilder\local\entities\adhoc_view::render_link('', '/x.php?id={}'));
    }

    /**
     * render_link()'s optional third argument (the 3-arg %%LINK(display, keycol, 'path')%% form) fills
     * the {} slot from the key rather than the visible text, so the cell shows one value and links on
     * another. The empty-value guard still tests the visible text, not the key.
     */
    public function test_render_link_keys_link_on_separate_value(): void {
        global $CFG;
        $this->resetAfterTest();

        $html = \report_sql\reportbuilder\local\entities\adhoc_view::render_link(
            'Ada Lovelace',
            '/user/view.php?id={}',
            '42'
        );
        $this->assertStringContainsString('href="' . $CFG->wwwroot . '/user/view.php?id=42"', $html);
        $this->assertStringContainsString('>Ada Lovelace</a>', $html);

        // Empty visible text renders nothing even when a key is supplied.
        $this->assertSame('', \report_sql\reportbuilder\local\entities\adhoc_view::render_link(
            '',
            '/user/view.php?id={}',
            '42'
        ));
    }
}
