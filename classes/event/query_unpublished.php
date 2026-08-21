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

/**
 * Raised when a published ad-hoc query is unpublished: its database VIEW and Report Builder report
 * are torn down and the query returns to draft.
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class query_unpublished extends query_event_base {
    /**
     * Init: set the crud flag for this event.
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
        return get_string('event:queryunpublished', 'report_sql');
    }

    /**
     * Returns a description of what happened.
     *
     * @return string
     */
    public function get_description() {
        $name = s($this->other['name'] ?? '');
        return "The user with id '{$this->userid}' unpublished the ad-hoc query with id " .
            "'{$this->objectid}' (name '{$name}').";
    }
}
