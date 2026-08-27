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

namespace report_sql\form;

use report_sql\local\action\action_registry;
use moodleform;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Bulk-action bar rendered beneath the actionable report on actions.php.
 *
 * Mirrors core's user bulk-action form ({@see \core_user\form\...}): an op picker plus a hidden
 * `subjectids` field that the page's JS fills from the checked report rows, posting to
 * action_apply.php. The op list is limited to the query's enabled ops.
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class bulk_action_form extends moodleform {
    /**
     * Form definition. Custom data: 'id' (query id), 'ops' (enabled op keys).
     */
    /** @var string Stable DOM id of the rendered <form>, shared with report_sql/bulk_actions JS. */
    public const FORM_ID = 'rs-bulk-action-form';

    protected function definition() {
        $mform = $this->_form;
        $mform->updateAttributes(['id' => self::FORM_ID]);

        $mform->addElement('hidden', 'id', (int) ($this->_customdata['id'] ?? 0));
        $mform->setType('id', PARAM_INT);

        // Filled by report_sql/bulk_actions from the checked rows; PARAM_SEQUENCE = comma-list of ids.
        $mform->addElement('hidden', 'subjectids', '');
        $mform->setType('subjectids', PARAM_SEQUENCE);

        $ops = (array) ($this->_customdata['ops'] ?? []);
        $menu = array_intersect_key(action_registry::menu(), array_flip($ops));

        $group = [];
        $group[] = $mform->createElement('select', 'op', get_string('actionbarlabel', 'report_sql'),
            ['' => get_string('choosedots')] + $menu);
        $group[] = $mform->createElement('submit', 'apply', get_string('actionapply', 'report_sql'));
        $mform->addGroup($group, 'actionbar', get_string('actionbarlabel', 'report_sql'), [' '], false);
        $mform->setType('op', PARAM_ALPHANUMEXT);
    }
}
