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
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use report_sql\local\sql\analyser;

/**
 * Gives an author advisory feedback on a report source (date columns, row count, indexes).
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class test_query extends external_api {
    /**
     * Describe the parameters accepted by execute().
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'sql'      => new external_value(PARAM_RAW, 'SQL to analyse'),
            'courseid' => new external_value(PARAM_INT, 'Bound course id (0 = site-wide)', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Run the analyser and return its feedback.
     *
     * @param string $sql
     * @param int $courseid
     * @return array{ok: bool, error: string, rowcount: int, datecolumns: string[],
     *     casecolumns: array<array{col: string, mode: string}>, suggestions: string[],
     *     warnings: string[], indexinfo: string[]}
     */
    public static function execute(string $sql, int $courseid = 0): array {
        ['sql' => $sql, 'courseid' => $courseid] =
            self::validate_parameters(self::execute_parameters(), ['sql' => $sql, 'courseid' => $courseid]);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('report/sql:author', $context);

        return analyser::analyse($sql, $courseid);
    }

    /**
     * Describe the return structure of execute().
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'ok'          => new external_value(PARAM_BOOL, 'True if the SQL analysed cleanly'),
            'error'       => new external_value(PARAM_TEXT, 'Error message, empty on success'),
            'rowcount'    => new external_value(PARAM_INT, 'Rows the report would return, -1 if uncountable'),
            'datecolumns' => new external_multiple_structure(
                new external_value(PARAM_TEXT, 'Date-like output column name'),
                'Integer columns that look like stored timestamps (click-to-wrap in %%TIMESTAMP()%%)',
                VALUE_DEFAULT,
                []
            ),
            'casecolumns' => new external_multiple_structure(
                new external_single_structure([
                    'col'  => new external_value(PARAM_TEXT, 'Output column name'),
                    'mode' => new external_value(PARAM_ALPHA, 'Case mode: upper or lower'),
                ]),
                'Columns using raw UPPER()/LOWER() in SQL (click-to-wrap in %%CASE()%%)',
                VALUE_DEFAULT,
                []
            ),
            'suggestions' => new external_multiple_structure(
                new external_value(PARAM_TEXT, 'Suggestion'),
                'Advisory suggestions',
                VALUE_DEFAULT,
                []
            ),
            'warnings'    => new external_multiple_structure(
                new external_value(PARAM_TEXT, 'Warning'),
                'Performance warnings',
                VALUE_DEFAULT,
                []
            ),
            'indexinfo'   => new external_multiple_structure(
                new external_value(PARAM_TEXT, 'Index line'),
                'Per-table index / row-count lines',
                VALUE_DEFAULT,
                []
            ),
        ]);
    }
}
