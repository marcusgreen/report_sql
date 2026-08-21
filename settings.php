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
 * Admin settings for the SQL Report plugin.
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// The index page lives under Site administration → Reports. Register it OUTSIDE the
// $hassiteconfig guard so the node's own capability governs visibility — otherwise the
// guard (moodle/site:config) hides it from non-admins who only hold the author capability.
// Only show it while the plugin is enabled, mirroring core report-plugin disable behaviour.
if (\report_sql\local\query::is_plugin_enabled()) {
    $ADMIN->add('reports', new admin_externalpage(
        'report_sql_index',
        get_string('reportsources', 'report_sql'),
        new moodle_url('/report/sql/index.php'),
        'report/sql:author',
        false
    ));
}

// Usage overview (read-only, viewall-gated). Registered outside the $hassiteconfig guard for the
// same reason as the index — its own capability governs visibility. Hidden from the tree; reached
// via the link on the index page.
$ADMIN->add('reports', new admin_externalpage(
    'report_sql_usage',
    get_string('usage:title', 'report_sql'),
    new moodle_url('/report/sql/usage.php'),
    'report/sql:viewall',
    true
));

// Sample browser (import bundled sample report views as drafts). Registered outside the
// $hassiteconfig guard with the author capability so any author — not only site admins — can
// browse and import samples. Hidden from the tree; reached via the link on the index page.
$ADMIN->add('reports', new admin_externalpage(
    'report_sql_samples',
    get_string('samples:title', 'report_sql'),
    new moodle_url('/report/sql/samples.php'),
    'report/sql:author',
    true
));

if ($hassiteconfig) {
    // $settings is the admin_settingpage core pre-creates for this report plugin and, after this
    // file is included, adds under Site administration → Plugins → Report plugins → SQL Report.
    // Do NOT create our own or add it to the 'reports' category — that would make the config page
    // show as a Reports link instead of a plugin settings page.
    $settings->add(new admin_setting_configcheckbox(
        'report_sql/enabled',
        get_string('settings:enabled', 'report_sql'),
        get_string('settings:enabled_desc', 'report_sql'),
        1
    ));

    $settings->add(new admin_setting_configtextarea(
        'report_sql/denycolumns',
        get_string('settings:denycolumns', 'report_sql'),
        get_string('settings:denycolumns_desc', 'report_sql'),
        'password,passwordhash,password_hash,secret,client_secret,sesskey,sid,apikey,api_key,' .
        'token,accesstoken,refreshtoken,sharekey,salt,hash,signature,privatekey,private_key,' .
        'clientid,client_id',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configcheckbox(
        'report_sql/syntaxhighlight',
        get_string('settings:syntaxhighlight', 'report_sql'),
        get_string('settings:syntaxhighlight_desc', 'report_sql'),
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'report_sql/showlastmodified',
        get_string('settings:showlastmodified', 'report_sql'),
        get_string('settings:showlastmodified_desc', 'report_sql'),
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'report_sql/aigenerate',
        get_string('settings:aigenerate', 'report_sql'),
        get_string('settings:aigenerate_desc', 'report_sql'),
        0
    ));

    // Retention window (days) for the report-view audit rows; 0 keeps them forever. Enforced by the
    // purge_views scheduled task.
    $settings->add(new admin_setting_configtext(
        'report_sql/viewretaindays',
        get_string('settings:viewretaindays', 'report_sql'),
        get_string('settings:viewretaindays_desc', 'report_sql'),
        '365',
        PARAM_INT
    ));

    $settings->add(new admin_setting_description(
        'report_sql/testviewlink',
        get_string('testview:title', 'report_sql'),
        html_writer::link(
            new moodle_url('/report/sql/testview.php'),
            get_string('testview:linklabel', 'report_sql')
        )
    ));

    $settings->add(new admin_setting_description(
        'report_sql/sampleslink',
        get_string('samples:title', 'report_sql'),
        html_writer::link(
            new moodle_url('/report/sql/samples.php'),
            get_string('samples:linklabel', 'report_sql')
        )
    ));

    $settings->add(new admin_setting_description(
        'report_sql/createrolelink',
        get_string('createrole:title', 'report_sql'),
        html_writer::link(
            new moodle_url('/report/sql/createrole.php'),
            get_string('createrole:linklabel', 'report_sql')
        )
    ));

    $settings->add(new admin_setting_description(
        'report_sql/importcrlink',
        get_string('crimport:title', 'report_sql') .
            $OUTPUT->help_icon('crimport:title', 'report_sql'),
        html_writer::link(
            new moodle_url('/report/sql/import_cr.php'),
            get_string('crimport:linklabel', 'report_sql')
        )
    ));

    $settings->add(new admin_setting_description(
        'report_sql/importcustomsqllink',
        get_string('customsqlimport:title', 'report_sql') .
            $OUTPUT->help_icon('customsqlimport:title', 'report_sql'),
        html_writer::link(
            new moodle_url('/report/sql/import_customsql.php'),
            get_string('customsqlimport:linklabel', 'report_sql')
        )
    ));

    $ADMIN->add('reports', new admin_externalpage(
        'report_sql_testview',
        get_string('testview:title', 'report_sql'),
        new moodle_url('/report/sql/testview.php'),
        'moodle/site:config',
        true
    ));

    $ADMIN->add('reports', new admin_externalpage(
        'report_sql_createrole',
        get_string('createrole:title', 'report_sql'),
        new moodle_url('/report/sql/createrole.php'),
        'moodle/site:config',
        true
    ));

    $ADMIN->add('reports', new admin_externalpage(
        'report_sql_importcr',
        get_string('crimport:title', 'report_sql'),
        new moodle_url('/report/sql/import_cr.php'),
        'moodle/site:config',
        true
    ));

    $ADMIN->add('reports', new admin_externalpage(
        'report_sql_importcustomsql',
        get_string('customsqlimport:title', 'report_sql'),
        new moodle_url('/report/sql/import_customsql.php'),
        'moodle/site:config',
        true
    ));
}
