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
use report_sql\local\schema;

/**
 * Returns the database schema and foreign-key map that drive editor autocomplete.
 *
 * Served lazily so the (large, expensive) schema is fetched once on demand and cached, rather than
 * embedded as a hidden field in every edit-page render.
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_schema extends external_api {
    /**
     * Describe the parameters accepted by execute().
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Return the cached schema and foreign-key map as JSON strings.
     *
     * @return array{schema: string, fkmap: string}
     */
    public static function execute(): array {
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('report/sql:author', $context);

        $data = schema::get();
        $jsonflags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

        return [
            'schema' => json_encode($data['tables'], $jsonflags),
            'fkmap'  => json_encode($data['fkmap'], $jsonflags),
        ];
    }

    /**
     * Describe the return structure of execute().
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'schema' => new external_value(PARAM_RAW, 'JSON map of table name to column names'),
            'fkmap'  => new external_value(PARAM_RAW, 'JSON foreign-key map from install.xml'),
        ]);
    }
}
