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

namespace report_sql\hook\output;

use core\hook\output\before_standard_top_of_body_html_generation;

/**
 * Injects a "New SQL report" action onto core's Report Builder index page.
 *
 * Core's /reportbuilder/index.php renders a fixed "New report" button and exposes no
 * pluggable action slot, so this hook adds a sibling button. It emits the button plus a
 * tiny inline script that relocates it next to core's "New report" control; if that control
 * is not present (permission, markup change) the button is left where the hook rendered it
 * rather than lost.
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class before_standard_top_of_body_html {
    /**
     * Add the "New SQL report" button to the Report Builder index page.
     *
     * @param before_standard_top_of_body_html_generation $hook
     */
    public static function callback(before_standard_top_of_body_html_generation $hook): void {
        global $PAGE;

        // Only on the Report Builder custom-reports index, and only for authors.
        if (
            $PAGE->url === null ||
            !$PAGE->url->compare(new \moodle_url('/reportbuilder/index.php'), URL_MATCH_BASE)
        ) {
            return;
        }
        if (!has_capability('report/sql:author', \context_system::instance())) {
            return;
        }

        $button = \html_writer::link(
            new \moodle_url('/report/sql/edit.php'),
            get_string('addnew', 'report_sql'),
            [
                'class' => 'btn btn-secondary ms-2',
                'id' => 'report-sql-newreport',
                // Hidden until the relocating script places it, so it never flashes at page top.
                'style' => 'display:none;',
            ]
        );

        // Relocate the button next to core's "New report" action. No external AMD module for
        // one line of DOM work; guarded so a missing anchor leaves the button visible in place.
        $script = <<<'JS'
(function() {
    var move = function() {
        var btn = document.getElementById('report-sql-newreport');
        if (!btn) {
            return;
        }
        var newreport = document.querySelector('[data-action="report-create"]');
        if (newreport && newreport.parentNode) {
            newreport.parentNode.insertBefore(btn, newreport.nextSibling);
        }
        btn.style.display = '';
    };
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', move);
    } else {
        move();
    }
})();
JS;

        $hook->add_html($button . \html_writer::tag('script', $script));
    }
}
