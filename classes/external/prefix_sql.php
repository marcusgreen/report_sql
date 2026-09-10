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
use report_sql\local\sql\validator;

/**
 * Rewrites {table} references in author-supplied SQL to their real, prefixed table names — e.g.
 * {user} becomes mdl_user — so the SQL can be copied out and run directly against the raw database,
 * outside this plugin's placeholder handling.
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class prefix_sql extends external_api {
    /**
     * Describe the parameters accepted by execute().
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'sql' => new external_value(PARAM_RAW, 'SQL to rewrite'),
        ]);
    }

    /**
     * Brace any bare table names (reusing the publish-time auto_brace() detection, which locates real
     * FROM/JOIN table positions rather than aliases or column names) then replace every {table} with
     * the site's real, prefixed table name.
     *
     * @param string $sql
     * @return array{sql: string}
     */
    public static function execute(string $sql): array {
        global $CFG;

        ['sql' => $sql] = self::validate_parameters(self::execute_parameters(), ['sql' => $sql]);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('report/sql:author', $context);

        $braced = validator::auto_brace($sql);
        $prefixed = preg_replace_callback(
            '/\{([A-Za-z0-9_]+)\}/',
            static fn(array $m): string => $CFG->prefix . $m[1],
            $braced
        );

        return ['sql' => $prefixed];
    }

    /**
     * Describe the return structure of execute().
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'sql' => new external_value(PARAM_RAW, 'SQL with {table} references replaced by real, prefixed table names'),
        ]);
    }
}
