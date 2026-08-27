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
 * Capabilities for report_sql.
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [
    // SECURITY NOTE — broad read access by design.
    // report/sql:author lets a holder write and publish an arbitrary SQL SELECT, which
    // is then run against the live Moodle database. Access to tables is governed by a *blocklist*
    // (the 'denytables' admin setting, seeded from validator::DENY_TABLES) and a sensitive-column
    // blocklist (the 'denycolumns' admin setting), not an allowlist. Any table or column not
    // explicitly denied is readable. In practice this means granting this capability is close to
    // granting read access to most of the database — including user emails/idnumbers/auth fields,
    // grades, logs and messages. Grant it only to trusted staff, and extend 'denycolumns' /
    // 'denytables' for any sensitive tables shipped by other installed plugins; note 'denytables'
    // is fully editable, so removing a seeded entry re-exposes that table. The
    // RISK_PERSONAL | RISK_DATALOSS bitmask below reflects this.
    'report/sql:author' => [
        'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS,
        'captype'     => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'  => [
            'manager' => CAP_ALLOW,
        ],
    ],
    'report/sql:approve' => [
        'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS,
        'captype'     => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'  => [
            'manager' => CAP_ALLOW,
        ],
    ],
    'report/sql:view' => [
        'captype'     => 'read',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes'  => [
            'user'           => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'teacher'        => CAP_ALLOW,
        ],
    ],
    'report/sql:viewown' => [
        'captype'     => 'read',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes'  => [
            'editingteacher' => CAP_ALLOW,
            'teacher'        => CAP_ALLOW,
        ],
    ],
    'report/sql:viewall' => [
        'riskbitmask' => RISK_PERSONAL,
        'captype'     => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'  => [
            'manager' => CAP_ALLOW,
        ],
    ],
    // Execute built-in bulk Moodle operations (enrol/unenrol/suspend/message/cohort) over rows
    // selected in an actionable report. High risk: it mutates users and enrolments, and can send
    // messages. Every op is additionally gated by its own core capability, checked per target
    // context at execute time — this cap only admits a holder to the actions UI. Manager only.
    'report/sql:actexecute' => [
        'riskbitmask' => RISK_DATALOSS | RISK_SPAM,
        'captype'     => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'  => [
            'manager' => CAP_ALLOW,
        ],
    ],
];
