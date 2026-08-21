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

namespace report_sql\local;

/**
 * Read-only aggregates over the report-view audit table for a single query (Phase 2 usage panel).
 *
 * Every method is keyed by query id and reads {@see query::TABLE_VIEW}; nothing here writes. The
 * caller is responsible for the access check (view history is manager-level data).
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class usagestats {
    /**
     * Headline totals for a query's report views.
     *
     * @param int $queryid
     * @return \stdClass {views:int, viewers:int, firstviewed:?int, lastviewed:?int}
     */
    public static function summary(int $queryid): \stdClass {
        global $DB;
        $row = $DB->get_record_sql(
            "SELECT COUNT(1) AS views, COUNT(DISTINCT userid) AS viewers,
                    MIN(timeviewed) AS firstviewed, MAX(timeviewed) AS lastviewed
               FROM {" . query::TABLE_VIEW . "}
              WHERE queryid = :queryid",
            ['queryid' => $queryid]
        );
        return (object) [
            'views'       => (int) ($row->views ?? 0),
            'viewers'     => (int) ($row->viewers ?? 0),
            'firstviewed' => $row->firstviewed !== null ? (int) $row->firstviewed : null,
            'lastviewed'  => $row->lastviewed !== null ? (int) $row->lastviewed : null,
        ];
    }

    /**
     * The users who open the query's reports most often.
     *
     * @param int $queryid
     * @param int $limit Maximum rows.
     * @return \stdClass[] Each {userid:int, views:int, lastviewed:int, fullname:string}, busiest first.
     */
    public static function top_viewers(int $queryid, int $limit = 10): array {
        global $DB;
        $rows = $DB->get_records_sql(
            "SELECT userid, COUNT(1) AS views, MAX(timeviewed) AS lastviewed
               FROM {" . query::TABLE_VIEW . "}
              WHERE queryid = :queryid
           GROUP BY userid
           ORDER BY views DESC, MAX(timeviewed) DESC",
            ['queryid' => $queryid],
            0,
            $limit
        );
        return self::attach_fullnames($rows);
    }

    /**
     * View totals broken down by the individual report (a query may own several).
     *
     * @param int $queryid
     * @return \stdClass[] Each {reportid:int, views:int, lastviewed:int}, busiest first.
     */
    public static function per_report(int $queryid): array {
        global $DB;
        $rows = $DB->get_records_sql(
            "SELECT reportid, COUNT(1) AS views, MAX(timeviewed) AS lastviewed
               FROM {" . query::TABLE_VIEW . "}
              WHERE queryid = :queryid
           GROUP BY reportid
           ORDER BY views DESC",
            ['queryid' => $queryid]
        );
        foreach ($rows as $row) {
            $row->reportid = (int) $row->reportid;
            $row->views = (int) $row->views;
            $row->lastviewed = (int) $row->lastviewed;
        }
        return array_values($rows);
    }

    /**
     * The most recent individual opens.
     *
     * @param int $queryid
     * @param int $limit Maximum rows.
     * @return \stdClass[] Each {userid:int, reportid:int, timeviewed:int, fullname:string}, newest first.
     */
    public static function recent(int $queryid, int $limit = 20): array {
        global $DB;
        $rows = $DB->get_records_sql(
            "SELECT id, userid, reportid, timeviewed
               FROM {" . query::TABLE_VIEW . "}
              WHERE queryid = :queryid
           ORDER BY timeviewed DESC, id DESC",
            ['queryid' => $queryid],
            0,
            $limit
        );
        foreach ($rows as $row) {
            $row->reportid = (int) $row->reportid;
            $row->timeviewed = (int) $row->timeviewed;
        }
        return self::attach_fullnames($rows);
    }

    /**
     * Daily view counts across a trailing window, one bucket per day (zero-filled), for a trend chart.
     *
     * Days are bucketed in PHP against the viewing user's midnight so the result is portable across
     * MySQL/MariaDB and PostgreSQL without dialect-specific date functions.
     *
     * @param int $queryid
     * @param int $days Number of days back to include (inclusive of today).
     * @return \stdClass {labels:string[], counts:int[]} oldest day first.
     */
    public static function daily_counts(int $queryid, int $days = 30): \stdClass {
        global $DB;
        $days = max(1, $days);
        $start = usergetmidnight(time()) - ($days - 1) * DAYSECS;

        $counts = array_fill(0, $days, 0);
        $rows = $DB->get_records_select(
            query::TABLE_VIEW,
            'queryid = :queryid AND timeviewed >= :start',
            ['queryid' => $queryid, 'start' => $start],
            '',
            'id, timeviewed'
        );
        foreach ($rows as $row) {
            $idx = (int) floor((usergetmidnight((int) $row->timeviewed) - $start) / DAYSECS);
            if ($idx >= 0 && $idx < $days) {
                $counts[$idx]++;
            }
        }

        $labels = [];
        for ($i = 0; $i < $days; $i++) {
            $labels[] = userdate($start + $i * DAYSECS, get_string('strftimedateshort', 'langconfig'));
        }
        return (object) ['labels' => $labels, 'counts' => array_values($counts)];
    }

    /**
     * Attach a display fullname to grouped/detail rows carrying a userid.
     *
     * Deleted or unknown users fall back to a generic label so the panel never breaks on a missing
     * user record.
     *
     * @param \stdClass[] $rows Rows each holding a numeric userid.
     * @return \stdClass[] The same rows (re-indexed), each with int userid/views/lastviewed where
     *                     present and a fullname string.
     */
    private static function attach_fullnames(array $rows): array {
        global $DB;
        if (!$rows) {
            return [];
        }
        $userids = [];
        foreach ($rows as $row) {
            $userids[(int) $row->userid] = true;
        }
        $users = $DB->get_records_list('user', 'id', array_keys($userids));

        $out = [];
        foreach ($rows as $row) {
            $row->userid = (int) $row->userid;
            if (isset($row->views)) {
                $row->views = (int) $row->views;
            }
            if (isset($row->lastviewed)) {
                $row->lastviewed = (int) $row->lastviewed;
            }
            $row->fullname = isset($users[$row->userid])
                ? fullname($users[$row->userid])
                : get_string('deleteduser', 'moodle');
            $out[] = $row;
        }
        return $out;
    }
}
