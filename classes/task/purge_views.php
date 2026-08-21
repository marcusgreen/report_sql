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

namespace report_sql\task;

use report_sql\local\query;

/**
 * Purge report-view audit rows older than the configured retention window.
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class purge_views extends \core\task\scheduled_task {
    /**
     * Human-readable task name shown in the scheduled-task admin screen.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task:purgeviews', 'report_sql');
    }

    /**
     * Delete view rows older than viewretaindays days. A value of 0 (or unset) keeps rows forever.
     */
    public function execute(): void {
        global $DB;

        $days = (int) get_config('report_sql', 'viewretaindays');
        if ($days <= 0) {
            return; // Retention disabled — keep everything.
        }

        $cutoff = time() - ($days * DAYSECS);
        $DB->delete_records_select(query::TABLE_VIEW, 'timeviewed < :cutoff', ['cutoff' => $cutoff]);
    }
}
