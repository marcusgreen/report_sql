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

namespace report_sql\local\sql;

/**
 * Verifies that the configured DB user can CREATE/DROP views.
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class privilege_check {
    /** @var string Probe view name (without prefix). */
    public const PROBE_NAME = 'report_sql_probe';

    /**
     * Try to create then drop a throwaway view. Cleans up regardless of result.
     *
     * @return array{ok: bool, error: string} ok true if both DDLs succeeded.
     */
    public static function probe(): array {
        global $DB, $CFG;

        $fullname = $CFG->prefix . self::PROBE_NAME;

        // Best-effort cleanup of any leftover from a prior failed probe.
        try {
            $DB->change_database_structure("DROP VIEW IF EXISTS {$fullname}");
        } catch (\Throwable $e) {
            // Drop-failure tolerated only if the view doesn't exist.
            debugging('report_sql probe cleanup ignored: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        try {
            $DB->change_database_structure("CREATE OR REPLACE VIEW {$fullname} AS SELECT 1 AS x");
        } catch (\dml_exception $e) {
            return ['ok' => false, 'error' => self::detail($e)];
        }

        try {
            $DB->change_database_structure("DROP VIEW IF EXISTS {$fullname}");
        } catch (\dml_exception $e) {
            return ['ok' => false, 'error' => self::detail($e)];
        }

        return ['ok' => true, 'error' => ''];
    }

    /**
     * Pull the underlying DB error string out of a Moodle dml_exception.
     *
     * @param \dml_exception $e
     * @return string
     */
    private static function detail(\dml_exception $e): string {
        $err = $e->error ?? '';
        if ($err !== '') {
            return $err;
        }
        return $e->debuginfo ?: $e->getMessage();
    }
}
