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

namespace report_sql\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use report_sql\local\query;
use report_sql\output\embed_renderer;

/**
 * Hydrate an inline report embed for the current viewer.
 *
 * Backs filter_reportsources' [[reportsource:ID]] marker. The filter emits only an inert placeholder
 * (the filtered-text cache is keyed on text + context, not user, so server-rendering per-viewer rows
 * there would leak one viewer's rows to all). This function is called per request, per user, and does
 * the two things the cache cannot: the access gate ({@see query::current_user_can_view_report()}) and
 * the per-viewer row scoping ({@see query::fetch_rows_for_viewer()}).
 *
 * No existence oracle: an unknown report and a report the viewer may not see both return empty HTML.
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_embed extends external_api {
    /** Row cap for an inline embed (kept modest — an embed is a summary, not the full report). */
    private const ROW_LIMIT = 200;

    /**
     * Describe the parameters accepted by execute().
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'reportid'     => new external_value(PARAM_INT, 'Report Builder report id (as in the RB view URL)'),
            'mode'         => new external_value(PARAM_ALPHA, 'Display mode: auto|table|chart', VALUE_DEFAULT, 'auto'),
            'pagecourseid' => new external_value(PARAM_INT, 'Host page course id (0 = none)', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Resolve the report to its query, gate access per user, and render the scoped rows.
     *
     * @param int $reportid
     * @param string $mode
     * @param int $pagecourseid
     * @return array{html: string}
     */
    public static function execute(int $reportid, string $mode = 'auto', int $pagecourseid = 0): array {
        ['reportid' => $reportid, 'mode' => $mode, 'pagecourseid' => $pagecourseid] =
            self::validate_parameters(
                self::execute_parameters(),
                ['reportid' => $reportid, 'mode' => $mode, 'pagecourseid' => $pagecourseid]
            );

        // Session/login gate only; the real per-report access is core RB's context + audience below.
        self::validate_context(\context_system::instance());

        $query = query::from_report_id($reportid);
        if ($query === null) {
            // Unknown report — no existence oracle, same empty result as a no-access hit.
            return ['html' => ''];
        }

        if (!$query->current_user_can_view_report()) {
            return ['html' => ''];
        }

        $mode = in_array($mode, ['auto', 'table', 'chart'], true) ? $mode : 'auto';

        try {
            $rows = $query->fetch_rows_for_viewer(self::ROW_LIMIT, $pagecourseid);
        } catch (\moodle_exception $e) {
            // Misconfigured filter column fails closed: never surface raw rows or DB errors.
            return ['html' => ''];
        }

        return ['html' => embed_renderer::render($query, $rows, $mode)];
    }

    /**
     * Describe the return structure of execute().
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'html' => new external_value(PARAM_RAW, 'Rendered embed HTML, empty when unknown or not viewable'),
        ]);
    }
}
