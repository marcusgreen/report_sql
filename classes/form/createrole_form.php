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

/**
 * Confirm form for creating the optional "Report author" role.
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_sql\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Lets the admin choose whether the role also gets :approve and :viewall before creating it.
 */
class createrole_form extends \moodleform {
    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;

        // The :author capability is always included (the role's purpose), so it is shown as fixed text only.
        $mform->addElement(
            'static',
            'authornote',
            get_string('createrole:author', 'report_sql'),
            get_string('createrole:author_desc', 'report_sql')
        );

        $mform->addElement(
            'advcheckbox',
            'approve',
            get_string('createrole:approve', 'report_sql'),
            get_string('createrole:approve_desc', 'report_sql'),
            null,
            [0, 1]
        );
        $mform->setDefault('approve', 1);

        $mform->addElement(
            'advcheckbox',
            'viewall',
            get_string('createrole:viewall', 'report_sql'),
            get_string('createrole:viewall_desc', 'report_sql'),
            null,
            [0, 1]
        );
        $mform->setDefault('viewall', 1);

        // Only offer the AI capability when local_sqlchat is installed (its capability exists).
        if (get_capability_info('local/sqlchat:use')) {
            $mform->addElement(
                'advcheckbox',
                'aigenerate',
                get_string('createrole:aigenerate', 'report_sql'),
                get_string('createrole:aigenerate_desc', 'report_sql'),
                null,
                [0, 1]
            );
            $mform->setDefault('aigenerate', 1);
        }

        $this->add_action_buttons(true, get_string('createrole:create', 'report_sql'));
    }
}
