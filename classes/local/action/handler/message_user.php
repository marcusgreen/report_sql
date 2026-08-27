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

namespace report_sql\local\action\handler;

use context;
use context_system;
use core\message\message;
use moodle_exception;
use report_sql\local\action\base_action;

/**
 * Send a notification message to selected users.
 *
 * The message body is author-configured (params['messagetext']). Delivered through the plugin's
 * 'actionmessage' provider ({@see /report/sql/db/messages.php}) so recipients' notification
 * preferences apply. Capability moodle/site:sendmessage is checked at system context.
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class message_user extends base_action {
    public function key(): string {
        return 'message_user';
    }

    public function label(): string {
        return get_string('actionmessage', 'report_sql');
    }

    public function required_capability(): string {
        return 'moodle/site:sendmessage';
    }

    protected function target_context(int $subjectid, context $reportctx, array $params): context {
        return context_system::instance();
    }

    protected function apply_one(int $subjectid, context $targetctx, array $params): void {
        global $DB, $USER;

        $body = trim((string) ($params['messagetext'] ?? ''));
        if ($body === '') {
            throw new moodle_exception('actionerrnomessage', 'report_sql');
        }
        $userto = $DB->get_record('user', ['id' => $subjectid, 'deleted' => 0], '*', MUST_EXIST);

        $message = new message();
        $message->component         = 'report_sql';
        $message->name              = 'actionmessage';
        $message->courseid          = SITEID;
        $message->userfrom          = $USER;
        $message->userto            = $userto;
        $message->subject           = get_string('actionmessagesubject', 'report_sql');
        $message->fullmessage       = $body;
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml   = text_to_html($body);
        $message->smallmessage      = $body;
        $message->notification      = 1;

        if (!message_send($message)) {
            throw new moodle_exception('actionerrsendfail', 'report_sql');
        }
    }
}
