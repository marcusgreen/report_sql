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

use context;
use Throwable;

/**
 * Shared execute loop for bulk-action handlers.
 *
 * Implements the security-critical part once so no concrete handler can forget it: for every
 * subject it (1) resolves the context the operation actually touches, (2) requires the handler's
 * core capability *in that context* — skipping (never silently applying) any subject the operator
 * lacks rights over, and (3) isolates failures so one bad subject cannot abort the batch. Concrete
 * handlers implement only {@see target_context()} and {@see apply_one()}.
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base_action implements action_handler {
    public function subject_type(): string {
        return self::SUBJECT_USER;
    }

    public function is_destructive(): bool {
        return false;
    }

    final public function execute(array $subjectids, context $reportctx, array $params): action_result {
        $result = new action_result();
        $cap = $this->required_capability();

        foreach ($subjectids as $subjectid) {
            $subjectid = (int) $subjectid;
            if ($subjectid <= 0) {
                continue;
            }

            try {
                $targetctx = $this->target_context($subjectid, $reportctx, $params);
            } catch (Throwable $e) {
                $result->mark_skipped($subjectid, $e->getMessage());
                continue;
            }

            // Second gate: the op's own core capability, in the context it actually touches.
            if (!has_capability($cap, $targetctx)) {
                $result->mark_skipped($subjectid, get_string('actionskipnocap', 'report_sql'));
                continue;
            }

            try {
                $this->apply_one($subjectid, $targetctx, $params);
                $result->mark_applied($subjectid);
            } catch (Throwable $e) {
                $result->mark_skipped($subjectid, $e->getMessage());
            }
        }

        return $result;
    }

    /**
     * The context the operation touches for this subject, where {@see required_capability()} is
     * checked. Throw to skip the subject with the exception message as the reason.
     *
     * @param int $subjectid
     * @param context $reportctx
     * @param array $params
     * @return context
     */
    abstract protected function target_context(int $subjectid, context $reportctx, array $params): context;

    /**
     * Apply the operation to a single subject. Called only after the capability gate passes. Throw
     * on failure to record the subject as skipped with the exception message.
     *
     * @param int $subjectid
     * @param context $targetctx The context returned by {@see target_context()}.
     * @param array $params
     */
    abstract protected function apply_one(int $subjectid, context $targetctx, array $params): void;
}
