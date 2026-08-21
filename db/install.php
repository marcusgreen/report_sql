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
 * Post-install hook: verify the DB user can CREATE/DROP views.
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Runs once at first install.
 */
function xmldb_report_sql_install(): void {
    global $DB;

    // Import the bundled user tour for the report views list page.
    \report_sql\local\tour::install();

    // Migrate data from local_adhocreports if it was installed on this site.
    if ($DB->get_manager()->table_exists('local_adhocreports_query')) {
        $oldsource = 'local_adhocreports\\reportbuilder\\datasource\\adhoc_query';
        $newsource = 'report_sql\\reportbuilder\\source\\adhoc_query';

        $oldrecords = $DB->get_records('local_adhocreports_query');
        foreach ($oldrecords as $rec) {
            unset($rec->id);
            // Rewrite view name prefix.
            if ($rec->viewname) {
                $rec->viewname = str_replace('local_adhocreports_v_', 'report_sql_v_', $rec->viewname);
            }
            $newid = $DB->insert_record('report_sql_query', $rec);

            // Migrate queryid_for_report_* config entries.
            if ($rec->reportid) {
                $oldcfg = get_config('local_adhocreports', 'queryid_for_report_' . $rec->reportid);
                if ($oldcfg !== false) {
                    set_config('queryid_for_report_' . $rec->reportid, $newid, 'report_sql');
                }
                // Update reportbuilder_report source class name.
                $DB->set_field(
                    'reportbuilder_report',
                    'source',
                    $newsource,
                    ['id' => $rec->reportid, 'source' => $oldsource]
                );
            }
        }

        // Rename existing DB views to new prefix.
        $existing = $DB->get_records('local_adhocreports_query', null, '', 'id,viewname');
        global $CFG;
        foreach ($existing as $oldrec) {
            if (!$oldrec->viewname) {
                continue;
            }
            $oldview = $CFG->prefix . $oldrec->viewname;
            $newview = $CFG->prefix . str_replace('local_adhocreports_v_', 'report_sql_v_', $oldrec->viewname);
            try {
                $DB->change_database_structure("CREATE OR REPLACE VIEW {$newview} AS SELECT * FROM {$oldview}");
            } catch (\dml_exception $e) {
                debugging('report_sql install: could not recreate view ' . $newview . ': ' . $e->getMessage());
            }
        }
    }

    $result = \report_sql\local\sql\privilege_check::probe();

    if ($result['ok']) {
        \core\notification::success(
            get_string('install:privilegeok', 'report_sql')
        );
    } else {
        \core\notification::error(
            get_string('install:privilegefail', 'report_sql', $result['error'])
        );
    }

    // Offer the bundled sample report views. The web installer runs this hook non-interactively, so
    // we point the installer at a confirm page rather than importing silently.
    $samplesurl = new \moodle_url('/report/sql/samples.php');
    \core\notification::info(
        get_string('install:loadsamples', 'report_sql', $samplesurl->out())
    );

    // Offer (do not auto-create) the optional "Report author" role. Authoring runs arbitrary SQL, so
    // the role is a site-wide data-read grant — it must be an informed, deliberate admin choice. The
    // confirm page carries the warning and lets the admin pick which capabilities to include.
    $createroleurl = new \moodle_url('/report/sql/createrole.php');
    \core\notification::info(
        get_string('install:createrole', 'report_sql', $createroleurl->out())
    );
}
