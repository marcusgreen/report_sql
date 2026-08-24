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
use report_sql\local\sql\validator;
use report_sql\local\sql\view;

/**
 * Validates user-supplied SQL: static checks then a live DB dry-run.
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class validate_sql extends external_api {
    /**
     * Describe the parameters accepted by execute().
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'sql' => new external_value(PARAM_RAW, 'SQL to validate'),
        ]);
    }

    /**
     * Run static validation then a single-row dry-run to catch bad table/column
     * names and row-dependent runtime errors in select-list expressions.
     *
     * @param string $sql
     * @return array{ok: bool, error: string, compiledsql?: string}
     */
    public static function execute(string $sql): array {
        global $DB, $CFG;

        ['sql' => $sql] = self::validate_parameters(self::execute_parameters(), ['sql' => $sql]);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('report/sql:author', $context);

        // Static denylist + SELECT-only check.
        try {
            $validated = validator::validate($sql);
        } catch (\moodle_exception $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        // Live dry-run: validates tables/columns without wrapping the query
        // (wrapping causes false "Duplicate column name" errors when SELECT * joins two tables).
        // Compile exactly as publish does (placeholders resolved + aliases normalised) so the
        // compiledsql we return on error is the string the DB error line numbers refer to.
        $resolved = view::compile($validated);

        // Syntax/table/column check — LIMIT 1 fetches a single row so the select-list
        // expressions are actually evaluated, catching row-dependent runtime errors
        // (e.g. to_char() on a bigint with a date mask) that LIMIT 0 would let through.
        // Wrap as a subquery so the LIMIT cannot be swallowed by a trailing line comment
        // (`... -- note` would otherwise comment out an appended LIMIT, fetching the whole
        // table into memory). Unlike the VIEW-create check below, a row dry-run does not care
        // about duplicate output column names, so wrapping is safe here.
        try {
            $DB->get_records_sql("SELECT * FROM ({$resolved}) rs_dryrun LIMIT 1", []);
        } catch (\dml_exception $e) {
            $detail = $e->error ?: ($e->debuginfo ?: $e->getMessage());
            return ['ok' => false, 'error' => validator::clean_error($detail), 'compiledsql' => $resolved];
        }

        // View-compatibility check — creating a VIEW enforces unique column names,
        // so test that now before the user hits it at publish time.
        // change_database_structure() throws ddl_change_structure_exception (a moodle_exception
        // subclass, not a dml_exception), so we catch the broader moodle_exception here.
        $testview = $CFG->prefix . \report_sql\local\sql\privilege_check::PROBE_NAME . '_col';
        $probeview = \report_sql\local\sql\privilege_check::PROBE_NAME . '_col';
        try {
            $DB->change_database_structure("CREATE OR REPLACE VIEW {$testview} AS {$resolved}");
            // An unaliased expression (e.g. count(*)) becomes a VIEW column Report Builder can't
            // reference; catch it now rather than letting publish fail with a coding_exception.
            $badcol = view::first_unaliased_column(view::columns($probeview));
            $DB->change_database_structure("DROP VIEW IF EXISTS {$testview}");
            if ($badcol !== null) {
                $errkey = preg_match('/\s/', $badcol) ? 'erraliasspaces' : 'errcolumnnoalias';
                return ['ok' => false, 'error' =>
                    get_string($errkey, 'report_sql', $badcol), 'compiledsql' => $resolved];
            }
        } catch (\moodle_exception $e) {
            $detail = $e->debuginfo ?: $e->getMessage();
            if (stripos($detail, 'Duplicate column name') !== false) {
                return ['ok' => false, 'error' =>
                    get_string('errduplicatecolumn', 'report_sql'), 'compiledsql' => $resolved];
            }
            // Any other DDL error (syntax error, multiple statements, etc.) is also fatal.
            return ['ok' => false, 'error' => validator::clean_error($detail), 'compiledsql' => $resolved];
        }

        return ['ok' => true, 'error' => '', 'warnings' => validator::get_warnings()];
    }

    /**
     * Describe the return structure of execute().
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'ok'       => new external_value(PARAM_BOOL, 'True if SQL is valid'),
            'error'    => new external_value(PARAM_TEXT, 'Error message, empty on success'),
            'compiledsql' => new external_value(
                PARAM_RAW,
                'The compiled SQL (placeholders resolved) that produced the error — line numbers match this',
                VALUE_OPTIONAL
            ),
            'warnings' => new external_multiple_structure(
                new external_value(PARAM_TEXT, 'Warning message'),
                'Non-fatal warnings (e.g. portability issues)',
                VALUE_OPTIONAL
            ),
        ]);
    }
}
