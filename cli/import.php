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
 * Import saved ad-hoc queries from a JSON export file.
 *
 * Wraps {@see \report_sql\local\transfer::parse()} and
 * {@see \report_sql\local\transfer::import()}, the same code path used by
 * the web import UI, so files written by export.php round-trip cleanly. Every source
 * lands as a fresh draft owned by the admin running this script and must be
 * re-published on this site.
 *
 * Looks for report_sql.json in the current directory by default. If that file is
 * absent and no --file was given, the script prompts for a path.
 *
 * Usage (from Moodle root):
 *   php report/sql/cli/import.php                      # read ./report_sql.json
 *   php report/sql/cli/import.php --file=/tmp/out.json # read a named file
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use report_sql\local\transfer;

[$options, $unrecognised] = cli_get_params(
    ['help' => false, 'file' => ''],
    ['h' => 'help']
);

/** @var string Default file looked for in the current directory. */
const IMPORT_FILENAME = 'report_sql.json';

if ($unrecognised) {
    $unrecognised = implode("\n  ", $unrecognised);
    cli_error(get_string('cliunknowoption', 'core_admin', $unrecognised));
}

if ($options['help']) {
    cli_writeln(<<<'HELP'
Import saved ad-hoc queries from a JSON export file.

Reads report_sql.json in the current directory by default. If that file is
missing and no --file is given, you are prompted for a path. Every query is
imported as a fresh draft and must be re-published on this site.

Options:
  -h, --help     Print this help.
  --file=PATH    Path to the export file (default: ./report_sql.json).

Examples:
  php report/sql/cli/import.php
  php report/sql/cli/import.php --file=/tmp/out.json
HELP);
    exit(0);
}

// Resolve the file: explicit --file wins; otherwise the default; otherwise prompt.
$path = $options['file'] !== '' ? $options['file'] : IMPORT_FILENAME;
if (!file_exists($path)) {
    if ($options['file'] !== '') {
        cli_error("File not found: {$path}");
    }
    $path = cli_input("'" . IMPORT_FILENAME . "' not found. Enter path to import file:");
    $path = trim($path);
    if ($path === '' || !file_exists($path)) {
        cli_error("File not found: {$path}");
    }
}

$json = file_get_contents($path);
if ($json === false) {
    cli_error("Could not read {$path}.");
}

// The parse() call throws moodle_exception on a malformed / unrecognised file.
try {
    $sources = transfer::parse($json);
} catch (\moodle_exception $e) {
    cli_error($e->getMessage());
}

if (!$sources) {
    cli_writeln("No queries found in {$path}. Nothing to import.");
    exit(0);
}

// Import every source in the file.
$result = transfer::import($sources, array_keys($sources));

cli_writeln("Imported {$result['imported']} of " . count($sources) . " queries from {$path}.");

foreach ($result['demoted'] as $name => $courseid) {
    cli_writeln("  Demoted to site-wide (course {$courseid} not found): {$name}");
}
foreach ($result['skipped'] as $name => $reason) {
    cli_writeln("  Skipped (validation failed): {$name} - {$reason}");
}
