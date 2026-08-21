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
 * Base class for ad-hoc query lifecycle events.
 *
 * Each subclass records one transition (created / updated / published / unpublished / deleted) of a
 * saved query, routed to the standard Moodle log (logstore_standard_log) so admins can audit who
 * exposed or altered which query via Site admin → Reports → Logs.
 *
 * The query record lives at system context, so events are raised against context_system. The
 * objectid is the query id; the query name is carried in `other['name']` so a description can be
 * rendered after the record itself is gone (deletes).
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class query_event_base extends \core\event\base {
    /**
     * Build and trigger the event for a query.
     *
     * @param int $queryid Query id (objectid).
     * @param string $name Query name, stored in `other` for the log description.
     * @return void
     */
    public static function create_and_trigger(int $queryid, string $name): void {
        $event = static::create([
            'objectid' => $queryid,
            'context'  => \context_system::instance(),
            'other'    => ['name' => $name],
        ]);
        $event->trigger();
    }

    /**
     * Common init: the object is a query record at "other" education level. Subclasses set crud.
     *
     * @return void
     */
    protected function init() {
        $this->data['objecttable'] = \report_sql\local\query::TABLE;
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Relevant URL: the plugin's query listing.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/report/sql/index.php');
    }

    /**
     * Custom validation.
     *
     * @return void
     */
    protected function validate_data() {
        parent::validate_data();
        if (!isset($this->objectid)) {
            throw new \coding_exception('The \'objectid\' value must be set.');
        }
    }
}
