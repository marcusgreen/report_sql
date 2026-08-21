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

namespace report_sql\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\{
    approved_contextlist,
    approved_userlist,
    contextlist,
    userlist,
    writer,
};
use report_sql\local\query;

/**
 * Privacy provider for ad-hoc reports.
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe the personal data stored by this plugin.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('report_sql_query', [
            'ownerid'      => 'privacy:metadata:query:ownerid',
            'querysql'     => 'privacy:metadata:query:querysql',
            'timecreated'  => 'privacy:metadata:query:timecreated',
        ], 'privacy:metadata:query');

        $collection->add_database_table('report_sql_queryview', [
            'userid'     => 'privacy:metadata:queryview:userid',
            'timeviewed' => 'privacy:metadata:queryview:timeviewed',
        ], 'privacy:metadata:queryview');

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the given user.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $list = new contextlist();
        $list->add_system_context();
        return $list;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof \context_system) {
            return;
        }
        $userlist->add_from_sql(
            'ownerid',
            'SELECT DISTINCT ownerid FROM {report_sql_query} WHERE ownerid <> 0',
            []
        );
        $userlist->add_from_sql(
            'userid',
            'SELECT DISTINCT userid FROM {report_sql_queryview} WHERE userid <> 0',
            []
        );
    }

    /**
     * Export all user data for the given approved contexts.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        if (!in_array(\context_system::instance()->id, $contextlist->get_contextids(), true)) {
            return;
        }
        $userid = $contextlist->get_user()->id;

        $queries = $DB->get_records('report_sql_query', ['ownerid' => $userid]);
        if ($queries) {
            writer::with_context(\context_system::instance())
                ->export_data(['Ad-hoc reports', 'Queries'], (object) $queries);
        }

        $views = $DB->get_records('report_sql_queryview', ['userid' => $userid]);
        if ($views) {
            writer::with_context(\context_system::instance())
                ->export_data(['Ad-hoc reports', 'Report views'], (object) $views);
        }
    }

    /**
     * Delete all data for all users in the given context.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if (!$context instanceof \context_system) {
            return;
        }
        self::purge_queries('ownerid <> 0', []);
        $DB->delete_records('report_sql_queryview');
    }

    /**
     * Delete all user data for the given user in the approved contexts.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        if (!in_array(\context_system::instance()->id, $contextlist->get_contextids(), true)) {
            return;
        }
        $userid = $contextlist->get_user()->id;
        self::purge_queries('ownerid = :ownerid', ['ownerid' => $userid]);
        $DB->delete_records('report_sql_queryview', ['userid' => $userid]);
    }

    /**
     * Delete data for multiple users within a single context.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        if (!$userlist->get_context() instanceof \context_system) {
            return;
        }
        $userids = $userlist->get_userids();
        if (!$userids) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        self::purge_queries("ownerid {$insql}", $params);

        [$vinsql, $vparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $DB->delete_records_select('report_sql_queryview', "userid {$vinsql}", $vparams);
    }

    /**
     * Delete or anonymise queries matching the given owner condition.
     *
     * Published queries back a live Report Builder report and a DB view, so deleting
     * them would destroy site reporting infrastructure; they are kept with ownerid
     * cleared instead (the listing shows no owner and ownership checks no longer match).
     * Anything else (drafts, legacy statuses) is deleted outright via query::delete(),
     * whose tear_down also removes any stray view/report artefacts.
     *
     * @param string $where SQL condition on report_sql_query
     * @param array $params parameters for the condition
     */
    private static function purge_queries(string $where, array $params): void {
        global $DB;
        $records = $DB->get_records_select('report_sql_query', $where, $params);
        foreach ($records as $record) {
            if ($record->status === query::STATUS_PUBLISHED) {
                $DB->set_field('report_sql_query', 'ownerid', 0, ['id' => $record->id]);
            } else {
                query::get((int) $record->id)->delete();
            }
        }
    }
}
