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

namespace report_sql\event;

use context;

/**
 * Raised when a bulk action is applied to rows of an actionable report.
 *
 * Records who ran which op over how many subjects and how many were applied vs skipped, at the
 * report's own context, so a manager can audit bulk mutations via Site admin → Reports → Logs.
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class action_applied extends query_event_base {
    /**
     * Build and trigger the event for an applied bulk action.
     *
     * @param int $queryid Query id (objectid).
     * @param string $name Query name.
     * @param context $context Report context the action ran in.
     * @param string $op Op key (e.g. enrol_user).
     * @param int $applied Count of subjects the op was applied to.
     * @param int $skipped Count of subjects skipped.
     * @return void
     */
    public static function create_and_trigger_action(
        int $queryid,
        string $name,
        context $context,
        string $op,
        int $applied,
        int $skipped
    ): void {
        $event = static::create([
            'objectid' => $queryid,
            'context'  => $context,
            'other'    => [
                'name'    => $name,
                'op'      => $op,
                'applied' => $applied,
                'skipped' => $skipped,
            ],
        ]);
        $event->trigger();
    }

    /**
     * Init: this event updates the affected users/enrolments.
     *
     * @return void
     */
    protected function init() {
        parent::init();
        $this->data['crud'] = 'u';
    }

    /**
     * Returns the localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('event:actionapplied', 'report_sql');
    }

    /**
     * Returns a description of what happened.
     *
     * @return string
     */
    public function get_description() {
        $name    = s($this->other['name'] ?? '');
        $op      = s($this->other['op'] ?? '');
        $applied = (int) ($this->other['applied'] ?? 0);
        $skipped = (int) ($this->other['skipped'] ?? 0);
        return "The user with id '{$this->userid}' applied the bulk action '{$op}' from the ad-hoc " .
            "query with id '{$this->objectid}' (name '{$name}') to {$applied} subject(s), " .
            "skipping {$skipped}.";
    }
}
