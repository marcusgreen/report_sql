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

namespace report_sql\local\action;

/**
 * Outcome of running a bulk action over a set of subjects.
 *
 * Accumulates which subject ids were applied and which were skipped (with a human-readable reason,
 * e.g. a permission denial or an API exception). The dispatch page turns this into the summary
 * notification and the `action_applied` audit event.
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class action_result {
    /** @var int[] Subject ids the action was successfully applied to. */
    private array $applied = [];

    /** @var array<int, string> Subject id => reason it was skipped. */
    private array $skipped = [];

    /**
     * Record a subject the action was applied to.
     *
     * @param int $subjectid
     */
    public function mark_applied(int $subjectid): void {
        $this->applied[] = $subjectid;
    }

    /**
     * Record a subject the action was not applied to, with the reason.
     *
     * @param int $subjectid
     * @param string $reason Human-readable, already localised.
     */
    public function mark_skipped(int $subjectid, string $reason): void {
        $this->skipped[$subjectid] = $reason;
    }

    /**
     * @return int[] Applied subject ids.
     */
    public function applied_ids(): array {
        return $this->applied;
    }

    /**
     * @return array<int, string> Skipped subject id => reason.
     */
    public function skipped_reasons(): array {
        return $this->skipped;
    }

    /**
     * @return int Count of subjects the action was applied to.
     */
    public function applied_count(): int {
        return count($this->applied);
    }

    /**
     * @return int Count of subjects skipped.
     */
    public function skipped_count(): int {
        return count($this->skipped);
    }
}
