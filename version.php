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
 * Ad-hoc SQL reports backed by the Reportbuilder API.
 *
 * @package     report_sql
 * @copyright   2026 Marcus Green
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'report_sql';
$plugin->release   = '0.1.22';
$plugin->version   = 2026090400;
$plugin->requires  = 2024100700; // Moodle 4.5 LTS — the Reportbuilder API this plugin uses is stable from 4.5.
$plugin->maturity  = MATURITY_ALPHA;
$plugin->supported = [405, 502];
