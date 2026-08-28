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

use core_reportbuilder\local\models\report as report_model;
use core_reportbuilder\local\helpers\report as reporthelper;
use report_sql\local\sql\validator;
use report_sql\local\sql\view;

/**
 * Saved ad-hoc query CRUD + lifecycle.
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class query {
    /** @var string Database table holding saved queries. */
    public const TABLE = 'report_sql_query';

    /** @var string Database table holding one row per report view (usage audit). */
    public const TABLE_VIEW = 'report_sql_queryview';

    /** @var string Status: saved but not published. */
    public const STATUS_DRAFT = 'draft';
    /** @var string Status: view + Report Builder report are live. */
    public const STATUS_PUBLISHED = 'published';

    /** @var string Audience token: derive the audience automatically from scope/visibility. */
    public const AUDIENCE_DEFAULT = 'default';
    /** @var string Audience token: all site users. */
    public const AUDIENCE_ALLUSERS = 'allusers';
    /** @var string Audience token: participants enrolled in the report's course. */
    public const AUDIENCE_COURSEPARTICIPANT = 'courseparticipant';
    /** @var string Audience token: users holding a chosen role in the course. */
    public const AUDIENCE_COURSEROLE = 'courserole';
    /** @var string Audience token: members of chosen cohorts. */
    public const AUDIENCE_COHORT = 'cohort';
    /** @var string Audience token: nobody (owner + reportbuilder:viewall only). */
    public const AUDIENCE_NONE = 'none';

    /** @var \stdClass */
    private \stdClass $record;

    /**
     * @var bool True while {@see tear_down()} is deleting bound reports, so the report_deleted
     * observer ({@see on_report_deleted()}) ignores our own deletions and only heals external ones.
     */
    private static bool $tearingdown = false;

    /**
     * Wrap a query database record.
     *
     * @param \stdClass $record The query database record.
     */
    private function __construct(\stdClass $record) {
        $this->record = $record;
    }

    /**
     * Whether the plugin is enabled (admin Enable SQL Report setting; defaults to on).
     *
     * @return bool
     */
    public static function is_plugin_enabled(): bool {
        $enabled = get_config('report_sql', 'enabled');
        // Absent config (fresh install before the setting is saved) counts as enabled.
        return $enabled === false || (int) $enabled === 1;
    }

    /**
     * Load a query by id.
     *
     * @param int $id
     * @return self
     */
    public static function get(int $id): self {
        global $DB;
        $rec = $DB->get_record(self::TABLE, ['id' => $id], '*', MUST_EXIST);
        return new self($rec);
    }

    /**
     * Load a query record by id.
     *
     * @param int $id
     * @return \stdClass
     */
    public static function get_record(int $id): \stdClass {
        return self::get($id)->record;
    }

    /**
     * Load the query that owns a given Report Builder report, or null if none.
     *
     * The authoritative report → query binding is the `queryid_for_report_<reportid>` config key
     * (a query can own several reports — the data report and the single-cell chart report — each
     * with its own key). This resolves either kind of report back to its query. Used by the inline
     * embed external function ({@see \report_sql\external\get_embed}) and the filter, both
     * of which key on report id (matching the RB view URL) rather than query id.
     *
     * @param int $reportid Report Builder report id.
     * @return self|null The owning query, or null if the report is unknown / its record is gone.
     */
    public static function from_report_id(int $reportid): ?self {
        global $DB;
        if ($reportid <= 0) {
            return null;
        }
        $queryid = (int) get_config('report_sql', 'queryid_for_report_' . $reportid);
        if ($queryid <= 0) {
            return null;
        }
        $rec = $DB->get_record(self::TABLE, ['id' => $queryid]);
        return $rec ? new self($rec) : null;
    }

    /**
     * Get the query id.
     *
     * @return int
     */
    public function id(): int {
        return (int) $this->record->id;
    }

    /**
     * Get the underlying query record.
     *
     * @return \stdClass
     */
    public function record(): \stdClass {
        return $this->record;
    }

    /**
     * Whether the current user is allowed to modify (edit / delete / publish) this query.
     *
     * Queries owned by a site administrator are locked to site administrators: no capability
     * (author / approve / viewall) lets a non-admin change an admin-created query. Queries owned
     * by non-admins keep the normal owner/viewall rules enforced by the caller.
     *
     * @return bool
     */
    public function can_modify(): bool {
        global $USER;
        return !is_siteadmin($this->record->ownerid) || is_siteadmin($USER);
    }

    /**
     * Throw unless the current user may modify this query (see {@see can_modify()}).
     *
     * @throws \required_capability_exception
     */
    public function require_can_modify(): void {
        if (!$this->can_modify()) {
            throw new \required_capability_exception(
                \context_system::instance(),
                'report/sql:author',
                'nopermissions',
                ''
            );
        }
    }

    /**
     * Get the query name.
     *
     * @return string
     */
    public function name(): string {
        return (string) $this->record->name;
    }

    /**
     * Get the query SQL.
     *
     * @return string
     */
    public function sql(): string {
        return (string) $this->record->querysql;
    }

    /**
     * Get the query status (draft|published).
     *
     * @return string
     */
    public function status(): string {
        return (string) $this->record->status;
    }

    /**
     * Get the database view name, or null if not published.
     *
     * @return string|null
     */
    public function viewname(): ?string {
        return $this->record->viewname ?: null;
    }

    /**
     * Get the bound Report Builder report id, or null if not published.
     *
     * @return int|null
     */
    public function reportid(): ?int {
        return $this->record->reportid ? (int) $this->record->reportid : null;
    }

    /**
     * Get the course id this query is scoped to (0 = site-wide).
     *
     * @return int
     */
    public function courseid(): int {
        return (int) ($this->record->courseid ?? 0);
    }

    /**
     * Whether the published report is listed in the plugin UI.
     *
     * @return bool
     */
    public function visible(): bool {
        return (int) ($this->record->visible ?? 1) === 1;
    }

    /**
     * Output column whose value is matched against the viewing user's id to scope the report to
     * "rows about me". Empty string means no per-user filter.
     *
     * @return string
     */
    public function useridcolumn(): string {
        return (string) ($this->record->useridcolumn ?? '');
    }

    /**
     * Output column holding a course id. When set, the report shows only rows whose course the
     * viewing user teaches (editingteacher/teacher role at the course context). Empty = no filter.
     *
     * @return string
     */
    public function coursecolumn(): string {
        return (string) ($this->record->coursecolumn ?? '');
    }

    /**
     * Output column holding a course id. When set and the report is shown in a block on a course
     * page, rows are limited to that page's course (the block passes its current course id into
     * {@see fetch_rows_for_viewer()}). Empty = no page-course filter. Block-only: the standalone
     * RB report viewer has no "current page" and ignores this.
     *
     * @return string
     */
    public function pagecoursecolumn(): string {
        return (string) ($this->record->pagecoursecolumn ?? '');
    }

    /**
     * Course ids the given user teaches: courses where they have an **active enrolment** AND hold an
     * editingteacher or teacher role at the course context. Requiring the enrolment (not just the
     * role assignment) means a suspended/expired enrolment, or a role assigned without enrolment,
     * does not count. Used by the datasource to scope rows to "courses I teach".
     *
     * @param int $userid
     * @return int[] Distinct course ids; empty if the user teaches nothing.
     */
    public static function teacher_course_ids(int $userid): array {
        global $DB;

        $roles = get_archetype_roles('editingteacher') + get_archetype_roles('teacher');
        $roleids = array_map('intval', array_keys($roles));
        if (!$roleids) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal($roleids, SQL_PARAMS_NAMED);
        $params['userid']       = $userid;
        $params['courselevel']  = CONTEXT_COURSE;
        $params['enrolenabled'] = ENROL_INSTANCE_ENABLED;
        $params['ueactive']     = ENROL_USER_ACTIVE;
        $params['now']          = time();

        // Teacher role at the course context, plus an active (enabled instance, active, in-window)
        // enrolment in that same course.
        $sql = "SELECT DISTINCT c.id
                  FROM {course} c
                  JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = :courselevel
                  JOIN {role_assignments} ra
                        ON ra.contextid = ctx.id AND ra.userid = :userid AND ra.roleid {$insql}
                  JOIN {enrol} e ON e.courseid = c.id AND e.status = :enrolenabled
                  JOIN {user_enrolments} ue
                        ON ue.enrolid = e.id AND ue.userid = :userid2 AND ue.status = :ueactive
                 WHERE (ue.timestart = 0 OR ue.timestart <= :now)
                   AND (ue.timeend = 0 OR ue.timeend >= :now2)";

        $params['userid2'] = $userid;
        $params['now2']    = $params['now'];

        return array_map('intval', $DB->get_fieldset_sql($sql, $params));
    }

    /**
     * Menu of published queries as id => name, ordered by name. Used by block_reportsources to offer
     * a report picker. Lists every published query; per-report view access is still enforced at
     * render time by {@see current_user_can_view_report()}.
     *
     * @return array<int, string>
     */
    public static function published_menu(): array {
        global $DB;
        $records = $DB->get_records_menu(
            self::TABLE,
            ['status' => self::STATUS_PUBLISHED],
            'name ASC',
            'id, name'
        );
        return array_map(static fn($name): string => format_string($name), $records);
    }

    /**
     * Whether the current user may view the published report's data (table or chart). Mirrors the
     * core Report Builder gate used by the report viewer: plugin managers always, everyone else only
     * when core RB's context + audience admit them. Shared by chart.php and block_reportsources so
     * every surface honours the same access as /reportbuilder/view.php.
     *
     * @return bool
     */
    public function current_user_can_view_report(): bool {
        $rec = $this->record();
        if ($rec->status !== self::STATUS_PUBLISHED) {
            return false;
        }
        $syscontext = \context_system::instance();
        if (
            has_capability('report/sql:author', $syscontext) ||
            has_capability('report/sql:approve', $syscontext) ||
            has_capability('report/sql:viewall', $syscontext)
        ) {
            return true;
        }
        if (empty($rec->reportid)) {
            return false;
        }
        $model = \core_reportbuilder\local\models\report::get_record(['id' => (int) $rec->reportid]);
        return $model && \core_reportbuilder\permission::can_view_report($model);
    }

    /**
     * Fetch the published view's rows scoped exactly as the RB report shows them to the current user:
     * only the denylist-stripped published columns, with the per-user (useridcolumn) and teacher-course
     * (coursecolumn) filters applied. The single fetch+filter path shared by chart.php and the block,
     * so no surface can leak rows the report table would hide. A filter naming a missing column fails
     * closed (throws). Does not check view access — call {@see current_user_can_view_report()} first.
     *
     * When $pagecourseid is supplied (the block passes its host page's course id) and the query
     * carries a pagecoursecolumn, rows are additionally limited to that course. This is the only
     * page-context-aware filter; chart.php and the RB report viewer leave it at 0 (no effect).
     *
     * @param int $rowlimit Maximum rows to return; 0 means no limit (capped at 5000).
     * @param int $pagecourseid Course id of the page hosting the block; 0 disables the page-course filter.
     * @return array<int, array<string, mixed>> Result rows as associative arrays.
     */
    public function fetch_rows_for_viewer(int $rowlimit = 0, int $pagecourseid = 0): array {
        global $DB, $USER;

        $rec = $this->record();
        if ($rec->status !== self::STATUS_PUBLISHED || empty($rec->viewname)) {
            return [];
        }
        $meta = $this->columns_meta();
        if (!$meta) {
            return [];
        }

        $wheres = [];
        $params = [];

        $useridcolumn = $this->useridcolumn();
        if ($useridcolumn !== '') {
            if (!array_key_exists($useridcolumn, $meta)) {
                throw new \moodle_exception('errchartnotconfigured', 'report_sql');
            }
            $wheres[] = "{$useridcolumn} = :rs_uid";
            $params['rs_uid'] = (int) $USER->id;
            // Hide the filter column from output: after filtering its value is always the viewer's id.
            if (count($meta) > 1) {
                unset($meta[$useridcolumn]);
            }
        }

        $coursecolumn = $this->coursecolumn();
        if ($coursecolumn !== '') {
            if (!array_key_exists($coursecolumn, $meta)) {
                throw new \moodle_exception('errchartnotconfigured', 'report_sql');
            }
            $courseids = self::teacher_course_ids((int) $USER->id);
            if (!$courseids) {
                // The viewer teaches no courses, so no rows are returned.
                $wheres[] = '1 = 0';
            } else {
                [$insql, $inparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'rs_c');
                $wheres[] = "{$coursecolumn} {$insql}";
                $params += $inparams;
            }
            // The course column stays visible: a teacher may teach many courses.
        }

        // Page-course filter: when shown in a block on a course page, scope rows to that course.
        // The block supplies $pagecourseid (its host page's course id); chart.php / the RB viewer
        // leave it 0 so this is a no-op there.
        $pagecoursecolumn = $this->pagecoursecolumn();
        if ($pagecoursecolumn !== '' && $pagecourseid > 0) {
            if (!array_key_exists($pagecoursecolumn, $meta)) {
                throw new \moodle_exception('errchartnotconfigured', 'report_sql');
            }
            $wheres[] = "{$pagecoursecolumn} = :rs_pc";
            $params['rs_pc'] = $pagecourseid;
            // On a single course page every row is that course, so hide the now-constant column.
            // Only reached when $pagecourseid > 0 (a real course page); chart.php and the RB report
            // viewer pass 0, so there the column stays visible because rows span many courses.
            if (count($meta) > 1) {
                unset($meta[$pagecoursecolumn]);
            }
        }

        $select = $wheres ? implode(' AND ', $wheres) : '';
        $fields = implode(', ', array_keys($meta));
        $limit  = $rowlimit > 0 ? min(5000, $rowlimit) : 0;

        $rows = [];
        try {
            $rs = $DB->get_recordset_select($rec->viewname, $select, $params, '', $fields, 0, $limit);
            foreach ($rs as $row) {
                $rows[] = (array) $row;
            }
            $rs->close();
        } catch (\dml_exception $e) {
            // Never surface raw DB errors to ordinary viewers (they can leak SQL/table names).
            debugging($e->getMessage(), DEBUG_DEVELOPER);
            throw new \moodle_exception('errchartdata', 'report_sql');
        }
        return $rows;
    }

    /**
     * The strftime format for an output column when it is a %%TIMESTAMP%% (epoch) column, else null.
     * Lets chart labels reuse the same date formatting as the data report. Matched case-insensitively
     * like {@see self::column_textcase()}.
     *
     * @param string $col Output column name.
     * @return string|null strftime format, or null when the column is not a timestamp column.
     */
    public function column_dateformat(string $col): ?string {
        foreach ($this->columns_meta() as $name => $meta) {
            if (strcasecmp((string) $name, $col) === 0) {
                if (($meta['type'] ?? '') === 'timestamp') {
                    return chart_presenter::strftime_format((string) ($meta['dateformat'] ?? ''));
                }
                return null;
            }
        }
        return null;
    }

    /**
     * Decoded column metadata cached on save (introspected from the live VIEW).
     *
     * @return array<string, array{type:string,label:string}>
     */
    public function columns_meta(): array {
        $raw = $this->record->columnsmeta ?: '[]';
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * The %%CASE() mode recorded for an output column, or '' if none. Column names in columnsmeta
     * preserve their published casing, so match case-insensitively. Used to give chart labels the
     * same case transform as the data report.
     *
     * @param string $col Output column name.
     * @return string ''|upper|lower|title|sentence.
     */
    public function column_textcase(string $col): string {
        foreach ($this->columns_meta() as $name => $meta) {
            if (strcasecmp((string) $name, $col) === 0) {
                return (string) ($meta['textcase'] ?? '');
            }
        }
        return '';
    }

    /**
     * Coerce a submitted column choice to a valid value: it must name one of the columns in the
     * supplied columnsmeta JSON, otherwise the filter is cleared (returns null). Used for both the
     * per-user (useridcolumn) and teacher-course (coursecolumn) filters.
     *
     * @param string $choice Raw submitted column name.
     * @param string|null $columnsmeta JSON column metadata of the published query.
     * @return string|null Validated column name, or null for no filter.
     */
    private static function valid_column_choice(string $choice, ?string $columnsmeta): ?string {
        $choice = clean_param($choice, PARAM_ALPHANUMEXT);
        if ($choice === '') {
            return null;
        }
        $meta = json_decode((string) ($columnsmeta ?: '[]'), true);
        return (is_array($meta) && array_key_exists($choice, $meta)) ? $choice : null;
    }

    /**
     * Remove saved Reportbuilder column/filter/condition instances of the given view column from
     * the bound report. Used when a column becomes the per-user filter: the datasource no longer
     * exposes it, so saved instances referencing it would be orphaned.
     *
     * @param int $reportid Bound Reportbuilder report id.
     * @param string $columnname View column name (entity-local, without the entity prefix).
     */
    private static function purge_report_column(int $reportid, string $columnname): void {
        $unique = \report_sql\reportbuilder\local\entities\adhoc_view::ENTITY . ':' . $columnname;

        $columns = \core_reportbuilder\local\models\column::get_records(
            ['reportid' => $reportid, 'uniqueidentifier' => $unique]
        );
        foreach ($columns as $column) {
            \core_reportbuilder\local\helpers\report::delete_report_column($reportid, (int) $column->get('id'));
        }

        $filters = \core_reportbuilder\local\models\filter::get_records(
            ['reportid' => $reportid, 'uniqueidentifier' => $unique]
        );
        foreach ($filters as $filter) {
            if ($filter->get('iscondition')) {
                \core_reportbuilder\local\helpers\report::delete_report_condition($reportid, (int) $filter->get('id'));
            } else {
                \core_reportbuilder\local\helpers\report::delete_report_filter($reportid, (int) $filter->get('id'));
            }
        }
    }

    /**
     * Save (create or update) a query record. Validates the SQL before storing.
     *
     * @param \stdClass $data Form data: id?, name, description, querysql.
     * @return int Query id.
     */
    public static function save(\stdClass $data): int {
        global $DB, $USER;

        $sql = validator::validate((string) $data->querysql);
        $now = time();

        // The edit form submits the description as an editor array {text, format, itemid}. Other
        // callers (importers, tests) may still pass a plain string. Resolve both; when an editor
        // draft id is present, embedded files are saved once the record id is known (see the
        // save_description_files() calls before each return below).
        $descdraftid = null;
        if (isset($data->description) && is_array($data->description)) {
            $desctext   = (string) ($data->description['text'] ?? '');
            $descformat = (int) ($data->description['format'] ?? FORMAT_HTML);
            $descdraftid = isset($data->description['itemid']) ? (int) $data->description['itemid'] : null;
        } else {
            $desctext   = (string) ($data->description ?? '');
            $descformat = (int) ($data->descriptionformat ?? FORMAT_HTML);
        }

        $record = (object) [
            'name'         => (string) $data->name,
            'description'  => $desctext,
            'descriptionformat' => $descformat,
            'querysql'     => $sql,
            'courseid'     => (int) ($data->courseid ?? 0),
            'visible'      => isset($data->visible) ? (int) (bool) $data->visible : 1,
            'audiencemeta' => report_visibility::build_audiencemeta($data),
            'timemodified' => $now,
        ];

        if (isset($data->chart_type)) {
            $allowedtypes = ['none', 'bar', 'line', 'pie', 'doughnut'];
            $record->chartmeta = json_encode([
                'type'     => in_array($data->chart_type, $allowedtypes, true) ? $data->chart_type : 'none',
                'xcol'     => clean_param((string) ($data->chart_xcol ?? ''), PARAM_ALPHANUMEXT),
                'ycol'     => clean_param((string) ($data->chart_ycol ?? ''), PARAM_ALPHANUMEXT),
                'rowlimit' => max(1, min(5000, (int) ($data->chart_rowlimit ?? 200))),
                'labelsize' => max(11, min(48, (int) ($data->chart_labelsize ?? 16))),
                'datalabels' => !empty($data->chart_datalabels),
                'multicolour' => !empty($data->chart_multicolour),
                'showdata' => !empty($data->chart_showdata),
            ]);
        }

        if (!empty($data->id)) {
            $record->id = (int) $data->id;
            $existing = $DB->get_record(self::TABLE, ['id' => $record->id], '*', MUST_EXIST);
            // Admin-created queries are locked to site admins regardless of capability.
            if (is_siteadmin($existing->ownerid) && !is_siteadmin($USER)) {
                throw new \required_capability_exception(
                    \context_system::instance(),
                    'report/sql:author',
                    'nopermissions',
                    ''
                );
            }
            // SQL change while published: drop view + report so they get rebuilt on next publish.
            // A transaction here would be illusory — tear_down() issues DROP VIEW via
            // change_database_structure(), and DDL implicitly commits on MySQL. So demote the
            // record to draft *first*, then tear down: if the teardown fails partway, the record
            // already reads draft rather than claiming published over a destroyed view/report.
            if ($existing->status === self::STATUS_PUBLISHED && $existing->querysql !== $sql) {
                $record->status        = self::STATUS_DRAFT;
                $record->viewname      = null;
                $record->reportid      = null;
                $record->chartreportid = null;
                $record->columnsmeta   = null;
                // Old column names no longer apply once the view is rebuilt.
                $record->useridcolumn = null;
                $record->coursecolumn = null;
                $record->pagecoursecolumn = null;
                $DB->update_record(self::TABLE, $record);
                self::save_description_files((int) $record->id, $descdraftid, $desctext, $descformat);
                self::tear_down((int) $existing->id, $existing);
                \report_sql\event\query_updated::create_and_trigger($record->id, $record->name);
                return $record->id;
            }
            // The per-user column is picked from the live view's columns, so only accept it for an
            // already-published query and only when it names one of that query's output columns.
            $record->useridcolumn = self::valid_column_choice(
                (string) ($data->useridcolumn ?? ''),
                $existing->columnsmeta
            );
            // Teacher-course filter column. Unlike the per-user column it stays visible in output
            // (a teacher may teach many courses), so no report instances need purging.
            $record->coursecolumn = self::valid_column_choice(
                (string) ($data->coursecolumn ?? ''),
                $existing->columnsmeta
            );
            // Page-course filter column (block-only). Stays visible in output, no purging needed.
            $record->pagecoursecolumn = self::valid_column_choice(
                (string) ($data->pagecoursecolumn ?? ''),
                $existing->columnsmeta
            );
            // The datasource stops offering a per-user filter column; purge any saved instances
            // of it from the report so they don't linger as stale config.
            if (
                $record->useridcolumn !== null
                    && $existing->status === self::STATUS_PUBLISHED && !empty($existing->reportid)
            ) {
                self::purge_report_column((int) $existing->reportid, $record->useridcolumn);
            }
            $DB->update_record(self::TABLE, $record);
            self::save_description_files((int) $record->id, $descdraftid, $desctext, $descformat);
            // Audience/visibility edits on an already-published report take effect immediately.
            if ($existing->status === self::STATUS_PUBLISHED && !empty($existing->reportid)) {
                report_visibility::apply(self::get_record($record->id), (int) $existing->reportid);
            }
            \report_sql\event\query_updated::create_and_trigger($record->id, $record->name);
            return $record->id;
        }

        $record->ownerid     = (int) $USER->id;
        $record->status      = self::STATUS_DRAFT;
        $record->timecreated = $now;
        $newid = $DB->insert_record(self::TABLE, $record);
        self::save_description_files($newid, $descdraftid, $desctext, $descformat);
        \report_sql\event\query_created::create_and_trigger($newid, $record->name);
        return $newid;
    }

    /**
     * Persist images embedded in a query description.
     *
     * The editor uploads embedded images into a draft file area; this moves them to the query's
     * permanent area (system context, itemid = query id) and rewrites the stored description so its
     * embedded-file placeholder tokens resolve. No-op when the caller passed a plain-string
     * description (no
     * draft id) — e.g. an importer or a programmatic save.
     *
     * @param int $id Query id (must already exist).
     * @param int|null $draftid Editor draft item id, or null for a non-editor caller.
     * @param string $text Raw description text as submitted (still holds @@PLUGINFILE@@ tokens).
     * @param int $format FORMAT_* constant for the description.
     */
    private static function save_description_files(int $id, ?int $draftid, string $text, int $format): void {
        global $CFG, $DB;

        if ($draftid === null) {
            return;
        }

        // The save() method runs from entry points that may not have included the plugin's lib.php,
        // where the shared editor-options helper lives.
        require_once($CFG->dirroot . '/report/sql/lib.php');

        $newtext = file_save_draft_area_files(
            $draftid,
            \context_system::instance()->id,
            'report_sql',
            'description',
            $id,
            report_sql_description_editor_options(),
            $text
        );

        $DB->set_field(self::TABLE, 'description', $newtext, ['id' => $id]);
        $DB->set_field(self::TABLE, 'descriptionformat', $format, ['id' => $id]);
    }

    /**
     * Move a draft to published: create the VIEW, register a Reportbuilder report, cache column meta.
     */
    public function publish(): void {
        global $DB;

        $this->require_can_modify();

        $viewname = view::create_or_replace($this->id(), $this->sql(), $this->courseid());
        $columns  = view::columns($viewname);

        // An unaliased expression (e.g. `SELECT count(*) ...`) yields a VIEW column named `count(*)`,
        // which Report Builder cannot build a column for. Fail early with a clear message.
        if (($badcol = view::first_unaliased_column($columns)) !== null) {
            $errkey = preg_match('/\s/', $badcol) ? 'erraliasspaces' : 'errcolumnnoalias';
            throw new \moodle_exception($errkey, 'report_sql', '', $badcol);
        }

        $meta = self::build_columnsmeta($columns, $this->sql(), $viewname);

        // Register the Reportbuilder report (idempotent). create_report() will set ->type for us.
        // We create with defaults disabled because the datasource cannot resolve its columns until
        // the queryid_for_report_<id> mapping is in place.
        $reportid = $this->record->reportid ? (int) $this->record->reportid : null;
        if (!$reportid) {
            $reportmodel = reporthelper::create_report((object) [
                'name'   => $this->name(),
                'source' => \report_sql\reportbuilder\source\adhoc_query::class,
            ], false);
            $reportid = (int) $reportmodel->get('id');
        }

        // Persist hint *before* hydrating defaults so the datasource can introspect the bound query.
        set_config('queryid_for_report_' . $reportid, $this->id(), 'report_sql');

        $update = (object) [
            'id'           => $this->id(),
            'status'       => self::STATUS_PUBLISHED,
            'viewname'     => $viewname,
            'reportid'     => $reportid,
            'columnsmeta'  => json_encode($meta),
            'timemodified' => time(),
        ];
        $DB->update_record(self::TABLE, $update);

        // Hydrate default columns / filters / conditions now that the datasource can resolve them.
        $reportpersistent = report_model::get_record(['id' => $reportid], MUST_EXIST);
        $datasourceclass = \report_sql\reportbuilder\source\adhoc_query::class;
        /** @var \core_reportbuilder\datasource $datasource */
        $datasource = new $datasourceclass($reportpersistent);
        $existingcolumns = \core_reportbuilder\local\models\column::get_records(['reportid' => $reportid]);
        if (!$existingcolumns) {
            $datasource->add_default_columns();
            $datasource->add_default_filters();
            $datasource->add_default_conditions();
        }

        report_visibility::apply($this->record, $reportid);

        $this->record = $DB->get_record(self::TABLE, ['id' => $this->id()], '*', MUST_EXIST);

        // Keep a companion single-cell chart report in sync with the query's chart config: create /
        // reuse it when a chart type is set, drop it when the author selects "No chart".
        $chartmeta = $this->record->chartmeta ? json_decode($this->record->chartmeta, true) : [];
        $charttype = is_array($chartmeta) ? (string) ($chartmeta['type'] ?? 'none') : 'none';
        if ($charttype !== '' && $charttype !== 'none') {
            $this->create_chart_report();
        } else {
            $this->delete_chart_report();
        }

        \report_sql\event\query_published::create_and_trigger($this->id(), $this->name());
    }

    /**
     * Drop view + RB report, set status to draft.
     */
    public function unpublish(): void {
        global $DB;

        $this->require_can_modify();
        self::tear_down($this->id(), $this->record);
        $DB->update_record(self::TABLE, (object) [
            'id'           => $this->id(),
            'status'       => self::STATUS_DRAFT,
            'viewname'     => null,
            'reportid'     => null,
            'columnsmeta'  => null,
            'timemodified' => time(),
        ]);
        $this->record = $DB->get_record(self::TABLE, ['id' => $this->id()], '*', MUST_EXIST);

        \report_sql\event\query_unpublished::create_and_trigger($this->id(), $this->name());
    }

    /**
     * Create an additional (blank) Report Builder report bound to this published view.
     *
     * Unlike publish(), this does not recreate the view or touch columnsmeta.
     * The caller is responsible for redirecting to /reportbuilder/edit.php.
     *
     * @return int New report id.
     * @throws \moodle_exception If the query is not published.
     */
    public function create_additional_report(): int {
        $this->require_can_modify();
        if ($this->status() !== self::STATUS_PUBLISHED || !$this->viewname()) {
            throw new \moodle_exception('invalidoperation', 'report_sql');
        }

        $reportmodel = reporthelper::create_report((object) [
            'name'   => $this->name(),
            'source' => \report_sql\reportbuilder\source\adhoc_query::class,
        ], false);
        $reportid = (int) $reportmodel->get('id');

        set_config('queryid_for_report_' . $reportid, $this->id(), 'report_sql');

        report_visibility::apply($this->record, $reportid);

        return $reportid;
    }

    /**
     * Create (or reuse) the companion single-cell chart report bound to this published query.
     *
     * The report uses the {@see \report_sql\reportbuilder\source\chart_query} source: one
     * row, one column, rendering the query's whole dataset as an SVG image. It is bound with the
     * same `queryid_for_report_<rid>` config key as the data report, so {@see bound_report_ids()},
     * {@see tear_down()} and course-deletion cleanup already sweep it. Idempotent: an existing chart
     * report is reused (visibility re-applied) rather than duplicated on re-publish.
     *
     * @return int Chart report id.
     */
    public function create_chart_report(): int {
        $chartsource = \report_sql\reportbuilder\source\chart_query::class;

        $reportid = $this->chart_report_id();
        if (!$reportid) {
            $reportmodel = reporthelper::create_report((object) [
                'name'   => get_string('chartreportname', 'report_sql', $this->name()),
                'source' => $chartsource,
            ], false);
            $reportid = (int) $reportmodel->get('id');

            // Bind before hydrating defaults so the datasource can resolve its bound query.
            set_config('queryid_for_report_' . $reportid, $this->id(), 'report_sql');
        }

        // Ensure the chart column exists — added on first create, and healing any report left
        // column-less by an earlier bug (so a re-publish repairs it rather than staying empty).
        if (!\core_reportbuilder\local\models\column::get_records(['reportid' => $reportid])) {
            $reportpersistent = report_model::get_record(['id' => $reportid], MUST_EXIST);
            /** @var \core_reportbuilder\datasource $datasource */
            $datasource = new $chartsource($reportpersistent);
            $datasource->add_default_columns();
        }

        $this->store_chart_report_id($reportid);
        report_visibility::apply($this->record, $reportid);

        return $reportid;
    }

    /**
     * Id of the bound chart report ({@see chart_query} source), or 0 if none exists.
     *
     * Public so UI surfaces (chart.php, the block) can deep-link to the RB chart report at
     * `/reportbuilder/view.php?id=<id>` — where the chart can be scheduled, exported or embedded.
     *
     * Prefers the denormalised `chartreportid` column (fast, and usable as an RB base field); falls
     * back to the authoritative `queryid_for_report_<rid>` binding scan when the column is not yet
     * populated (e.g. a query published before the column existed, not re-published since).
     *
     * @return int
     */
    public function chart_report_id(): int {
        $stored = (int) ($this->record->chartreportid ?? 0);
        if ($stored > 0 && report_model::record_exists($stored)) {
            return $stored;
        }

        $chartsource = \report_sql\reportbuilder\source\chart_query::class;
        foreach (self::bound_report_ids($this->id()) as $rid) {
            $report = report_model::get_record(['id' => $rid]);
            if ($report && $report->get('source') === $chartsource) {
                return $rid;
            }
        }
        return 0;
    }

    /**
     * Persist the chart report id on the query record (denormalised for base-field / UI use).
     *
     * @param int $reportid 0 clears the binding.
     */
    private function store_chart_report_id(int $reportid): void {
        global $DB;
        $value = $reportid > 0 ? $reportid : null;
        if ((int) ($this->record->chartreportid ?? 0) === (int) $reportid) {
            return;
        }
        $DB->set_field(self::TABLE, 'chartreportid', $value, ['id' => $this->id()]);
        $this->record->chartreportid = $value;
    }

    /**
     * Delete the bound chart report if one exists (used when the author selects "No chart").
     */
    private function delete_chart_report(): void {
        $rid = $this->chart_report_id();
        if (!$rid) {
            $this->store_chart_report_id(0);
            return;
        }
        try {
            reporthelper::delete_report($rid);
        } catch (\dml_exception | \moodle_exception $e) {
            debugging('report_sql: failed to delete chart report ' . $rid . ': ' . $e->getMessage());
        }
        unset_config('queryid_for_report_' . $rid, 'report_sql');
        $this->store_chart_report_id(0);
    }

    /**
     * Permanently delete a query and its backing artefacts.
     */
    public function delete(): void {
        global $DB;
        $this->require_can_modify();
        // Snapshot id/name before the record is gone so the event can still describe it.
        $queryid = $this->id();
        $name = $this->name();
        self::tear_down($queryid, $this->record);
        // Drop any images embedded in the description so they don't linger as orphaned files.
        get_file_storage()->delete_area_files(
            \context_system::instance()->id,
            'report_sql',
            'description',
            $queryid
        );
        // The query record is going away, so its view-history rows would be orphaned; drop them.
        // (Unpublish/republish keep the record and go through tear_down only, so history survives.)
        $DB->delete_records(self::TABLE_VIEW, ['queryid' => $queryid]);
        $DB->delete_records(self::TABLE, ['id' => $queryid]);
        \report_sql\event\query_deleted::create_and_trigger($queryid, $name);
    }

    /**
     * Duplicate this query as a new draft owned by the current user.
     *
     * Copies the SQL, description, course scope, visibility and chart
     * settings. The copy starts as a draft: no VIEW, report or column metadata
     * is carried over (those are rebuilt on publish).
     *
     * @return int New query id.
     */
    public function duplicate(): int {
        global $CFG, $DB, $USER;

        // Only the owner (or a report/sql:viewall holder — which includes site
        // admins) may copy a query, so an author cannot clone another user's
        // private/draft SQL by id. Mirrors the gate in delete.php / edit.php.
        if (
            (int) $this->record->ownerid !== (int) $USER->id &&
            !has_capability('report/sql:viewall', \context_system::instance())
        ) {
            throw new \required_capability_exception(
                \context_system::instance(),
                'report/sql:viewall',
                'nopermissions',
                ''
            );
        }

        require_once($CFG->dirroot . '/report/sql/lib.php');

        // Clone any images embedded in the description: prepare a draft area from this query's
        // description files (rewriting URLs back to @@PLUGINFILE@@ tokens), then save that draft
        // against the new query id so the copy owns its own file copies rather than sharing the
        // original's (whose @@PLUGINFILE@@ tokens point at this query's item id).
        $context = \context_system::instance();
        $descdraftid = 0;
        $desctext = file_prepare_draft_area(
            $descdraftid,
            $context->id,
            'report_sql',
            'description',
            $this->id(),
            report_sql_description_editor_options(),
            (string) ($this->record->description ?? '')
        );

        $now = time();
        $copy = (object) [
            'name'         => get_string('copyof', 'report_sql', $this->name()),
            'description'  => $desctext,
            'descriptionformat' => (int) ($this->record->descriptionformat ?? FORMAT_HTML),
            'querysql'     => $this->sql(),
            'courseid'     => $this->courseid(),
            'visible'      => (int) ($this->record->visible ?? 1),
            'chartmeta'    => $this->record->chartmeta ?: null,
            'ownerid'      => (int) $USER->id,
            'status'       => self::STATUS_DRAFT,
            'viewname'     => null,
            'reportid'     => null,
            'columnsmeta'  => null,
            'timecreated'  => $now,
            'timemodified' => $now,
        ];

        $newid = $DB->insert_record(self::TABLE, $copy);
        self::save_description_files($newid, $descdraftid, $desctext, (int) $copy->descriptionformat);
        \report_sql\event\query_created::create_and_trigger($newid, $copy->name);
        return $newid;
    }

    /**
     * Record that one of this query's published reports was opened.
     *
     * Called from {@see \report_sql\observer::report_viewed} on the hot path (every
     * site-wide report view reaches the observer, but only bound plugin reports reach here), so this
     * is a single unqualified insert with no extra lookups.
     *
     * @param int $queryid    The owning query.
     * @param int $reportid   The reportbuilder_report actually opened.
     * @param int $userid     The viewing user.
     * @param int $timeviewed Event time (epoch seconds).
     */
    public static function record_view(int $queryid, int $reportid, int $userid, int $timeviewed): void {
        global $DB;
        $DB->insert_record(self::TABLE_VIEW, (object) [
            'queryid'    => $queryid,
            'reportid'   => $reportid,
            'userid'     => $userid,
            'timeviewed' => $timeviewed,
        ]);
    }

    /**
     * Tear down view + reportbuilder report for a record.
     *
     * @param int $queryid
     * @param \stdClass $record
     */
    private static function tear_down(int $queryid, \stdClass $record): void {
        // reporthelper::delete_report() fires \core_reportbuilder\event\report_deleted, which our own
        // observer listens to. Flag the reentry so on_report_deleted() ignores deletions we initiate
        // here (unpublish / SQL-change republish / healing) and only reacts to external ones.
        self::$tearingdown = true;
        try {
            // Delete all Report Builder reports bound to this query via the queryid config.
            foreach (self::bound_report_ids($queryid) as $rid) {
                $cfgname = 'queryid_for_report_' . $rid;
                try {
                    $report = report_model::get_record(['id' => $rid]);
                    if ($report) {
                        reporthelper::delete_report($rid);
                    }
                    unset_config($cfgname, 'report_sql');
                } catch (\dml_exception | \moodle_exception $e) {
                    debugging('report_sql: failed to delete report ' . $rid . ': ' . $e->getMessage());
                }
            }

            if (!empty($record->viewname)) {
                try {
                    view::drop($queryid);
                } catch (\moodle_exception $e) {
                    debugging('report_sql: failed to drop view: ' . $e->getMessage());
                }
            }
        } finally {
            self::$tearingdown = false;
        }
    }

    /**
     * Heal a query whose bound Report Builder report was deleted directly through core's
     * /reportbuilder/index.php (outside this plugin), which otherwise leaves a dangling reportid /
     * chartreportid and the "View report" link fataling with core RB's invalid-report error.
     *
     * Called from {@see \report_sql\observer::report_deleted}. No-op for a deletion this plugin
     * itself initiated (guarded by {@see self::$tearingdown}) or a report that is not one of ours.
     *
     * @param int $reportid Id of the just-deleted Report Builder report.
     */
    public static function on_report_deleted(int $reportid): void {
        global $DB;

        if (self::$tearingdown || $reportid <= 0) {
            return; // Our own deletion, or nothing to do.
        }
        $query = self::from_report_id($reportid);
        if (!$query) {
            return; // Not bound to any of our queries.
        }

        // Drop the now-orphaned binding for the deleted report.
        unset_config('queryid_for_report_' . $reportid, 'report_sql');
        $record = $query->record;

        if ((int) $record->chartreportid === $reportid) {
            // The companion chart report was deleted: clear its denormalised id, leave the data
            // report and publish state intact (a re-publish regenerates the chart from chartmeta).
            $DB->set_field(self::TABLE, 'chartreportid', null, ['id' => $query->id()]);
            return;
        }

        if ((int) $record->reportid === $reportid) {
            // The primary data report was deleted out from under us: demote the query to draft and
            // sweep any remaining bound reports (chart / additional) + the orphan view, so nothing
            // dangles. A re-publish rebuilds everything from the saved SQL.
            self::tear_down($query->id(), $record);
            $DB->update_record(self::TABLE, (object) [
                'id'            => $query->id(),
                'status'        => self::STATUS_DRAFT,
                'viewname'      => null,
                'reportid'      => null,
                'chartreportid' => null,
                'columnsmeta'   => null,
                'timemodified'  => time(),
            ]);
            \report_sql\event\query_unpublished::create_and_trigger($query->id(), $query->name());
            return;
        }

        // An additional report (create_additional_report): the binding is already cleared above and
        // the primary report is untouched, so there is nothing further to heal.
    }

    /**
     * Ids of every Report Builder report bound to a query.
     *
     * A query owns one report per {@see query::create_additional_report()} call; each binding lives
     * only in {config_plugins} as queryid_for_report_<rid>, so this lookup — not the query record's
     * single reportid field — is the authoritative list of bound reports. Public so
     * {@see report_visibility::on_course_deleted()} can sweep every bound report of a deleted course.
     *
     * @param int $queryid
     * @return int[] Report ids.
     */
    public static function bound_report_ids(int $queryid): array {
        global $DB;

        $rows = $DB->get_records_sql(
            "SELECT name FROM {config_plugins} WHERE plugin = ? AND " . $DB->sql_like('name', '?') . " AND value = ?",
            ['report_sql', 'queryid\_for\_report\_%', (string) $queryid]
        );
        $ids = [];
        foreach ($rows as $row) {
            $ids[] = (int) str_replace('queryid_for_report_', '', $row->name);
        }
        return $ids;
    }

    /**
     * Map a Moodle {@see \database_column_info::$meta_type} char to a Reportbuilder column type
     * token used by the datasource.
     *
     * Moodle meta_type chars: R=autoinc, I=int, N=numeric/float, C=char, X=text, B=blob, L=bool,
     * T=timestamp, D=decimal/date.
     *
     * @param string $metatype
     * @return string One of: int, float, bool, timestamp, text.
     */
    private static function map_db_type(string $metatype): string {
        return match (strtoupper($metatype)) {
            'R', 'I' => 'int',
            'N', 'D' => 'float',
            'L'      => 'bool',
            'T'      => 'timestamp',
            default  => 'text',
        };
    }

    /**
     * Build the column metadata array (frozen into `columnsmeta` at publish) from an introspected
     * VIEW's columns plus the saved SQL. Shared by {@see publish()} and the inline preview, so both
     * type columns identically.
     *
     * Timestamp columns (%%TIMESTAMP()%%) resolve to a bare epoch integer in the view, so
     * introspection alone would type them as int. Recover the intended timestamp type — and any
     * requested display format — from the saved SQL tokens, keyed by output column name.
     *
     * Case columns (%%CASE(expr, mode)%%) keep their introspected text type but carry a `textcase`
     * transform applied at display time (the stored value stays the original text so sort/filter
     * act on it); recover the mode from the saved SQL the same way.
     *
     * Low-cardinality text columns get an `enum` flag when $viewname is supplied and the
     * `enumfilterthreshold` setting is non-zero: the live view is probed for each plain-text column's
     * distinct-value count, and columns with between 2 and the threshold distinct values are flagged so
     * the entity renders a dropdown filter instead of a free-text one (see {@see adhoc_view::build_filters()}).
     * The flag is frozen into columnsmeta at publish time like the rest of the meta.
     *
     * @param array $columns Column map from {@see view::columns()} (has meta_type).
     * @param string $sql Raw saved SQL (before placeholder resolution), for timestamp-token recovery.
     * @param string|null $viewname Live view name (without prefix) to probe for enum candidates; null skips.
     * @return array Column meta keyed by column name (type, label, dateformat?, textcase?, enum?).
     */
    public static function build_columnsmeta(array $columns, string $sql, ?string $viewname = null): array {
        $tsformats = view::timestamp_columns($sql);
        $casemodes = view::case_columns($sql);

        $meta = [];
        foreach ($columns as $name => $info) {
            $key = strtolower($name);
            if (array_key_exists($key, $tsformats)) {
                $meta[$name] = [
                    'type'       => 'timestamp',
                    'label'      => $name,
                    'dateformat' => $tsformats[$key],
                ];
            } else {
                $meta[$name] = [
                    'type'  => self::map_db_type((string) $info->meta_type),
                    'label' => $name,
                ];
                if (array_key_exists($key, $casemodes)) {
                    $meta[$name]['textcase'] = $casemodes[$key];
                }
            }
        }

        if ($viewname !== null) {
            self::flag_enum_columns($meta, $viewname);
        }
        return $meta;
    }

    /**
     * Flag plain-text columns whose distinct-value count is within the `enumfilterthreshold` setting,
     * so the entity gives them a dropdown filter. Timestamp columns and %%CASE%% text-transform columns
     * are skipped (the latter sort/filter on the original value but a dropdown of raw values would be
     * confusing next to a transformed display). Degrades silently: any probe error leaves the column
     * as a free-text filter. A row-count guard (`enumrowceiling`) skips detection entirely for views
     * larger than the ceiling, so publishing a big report does not pay N per-column distinct scans.
     *
     * @param array $meta Column meta, modified in place (adds `enum => true`).
     * @param string $viewname Live view name (without prefix).
     */
    private static function flag_enum_columns(array &$meta, string $viewname): void {
        global $DB;

        $threshold = (int) get_config('report_sql', 'enumfilterthreshold');
        if ($threshold <= 0) {
            return;
        }

        // Row-count guard: one bounded probe before the N per-column distinct scans. When the view has
        // more than the ceiling rows, skip enum detection (all text columns stay free-text) so a large
        // report does not pay N full-column scans at publish. The count is itself capped at ceiling + 1
        // and has no DISTINCT/ORDER BY, so the DB can stream and stop early — the guard's own cost stays
        // bounded even on an unindexed join view. 0 disables the guard (always probe).
        $ceiling = (int) get_config('report_sql', 'enumrowceiling');
        if ($ceiling > 0) {
            try {
                $rows = $DB->count_records_sql(
                    "SELECT COUNT(*) FROM (
                        SELECT 1 FROM {{$viewname}} LIMIT " . ($ceiling + 1) . "
                    ) rs_enumguard"
                );
            } catch (\dml_exception $e) {
                return;
            }
            if ($rows > $ceiling) {
                return;
            }
        }

        foreach ($meta as $name => $info) {
            if (($info['type'] ?? 'text') !== 'text' || isset($info['textcase'])) {
                continue;
            }
            try {
                // Cap the scan: we only care whether the distinct count is <= threshold, so stop after
                // threshold + 1 distinct values. A result of threshold + 1 means "more than threshold"
                // (not a dropdown); anything less is the true distinct count. Bounds worst-case cost to
                // threshold + 1 rows instead of a full-column distinct scan.
                $distinct = $DB->count_records_sql(
                    "SELECT COUNT(*) FROM (
                        SELECT DISTINCT {$name} FROM {{$viewname}}
                         WHERE {$name} IS NOT NULL
                         LIMIT " . ($threshold + 1) . "
                    ) rs_enum"
                );
            } catch (\dml_exception $e) {
                continue;
            }
            if ($distinct >= 2 && $distinct <= $threshold) {
                $meta[$name]['enum'] = true;
            }
        }
    }

    /**
     * Parse the top-level ORDER BY of the saved SQL into an ordered map of output column name to sort
     * direction, so the generated report can reproduce the query's ordering. Report Builder never
     * carries a view's internal ORDER BY (MySQL drops it, and RB re-selects with no ORDER BY of its
     * own), so this recovered ordering is fed back as the report's default column sorting — see
     * {@see \report_sql\reportbuilder\source\adhoc_query::get_default_column_sorting()} and
     * the inline preview.
     *
     * Only bare column references can be honoured: RB sorts on output columns, so an ORDER BY term
     * must resolve to a column the report exposes. Alias/table prefixes are stripped and the token is
     * lowercased to match {@see build_columnsmeta()} keys; expression terms (containing parens) and
     * ordinal positions (ORDER BY 1) are skipped — they cannot be mapped to a column here. A term
     * that targets a source column rather than the output alias (e.g. `ORDER BY c.id` where the
     * column is aliased `AS courseid`) simply will not match and is dropped.
     *
     * @param string $sql Raw saved SQL (before placeholder resolution).
     * @return array<string, int> Ordered [lowercased column name => SORT_ASC|SORT_DESC].
     */
    public static function order_by_sorting(string $sql): array {
        if (!preg_match('/\bORDER\s+BY\b(.*)$/is', $sql, $m)) {
            return [];
        }
        // ORDER BY is the last clause; trim any trailing LIMIT/OFFSET/FETCH tail.
        $tail = preg_split('/\b(?:LIMIT|OFFSET|FETCH)\b/i', $m[1])[0];
        $sorting = [];
        foreach (explode(',', $tail) as $term) {
            $term = trim($term);
            if ($term === '' || strpos($term, '(') !== false) {
                continue; // Skip expression terms — no output column to sort on.
            }
            $parts = preg_split('/\s+/', $term);
            $token = preg_replace('/^\{?\w+\}?\./', '', $parts[0]); // Strip {table}. or alias. prefix.
            $token = strtolower(trim($token, '`"[]{}'));
            if ($token === '' || ctype_digit($token)) {
                continue; // Skip ordinal positions (ORDER BY 1) and empties.
            }
            // A trailing DESC (any part) flips direction; ASC / NULLS FIRST|LAST are the default.
            $desc = false;
            foreach (array_slice($parts, 1) as $part) {
                if (strcasecmp($part, 'DESC') === 0) {
                    $desc = true;
                    break;
                }
            }
            $sorting[$token] = $desc ? SORT_DESC : SORT_ASC;
        }
        return $sorting;
    }

    /**
     * List queries visible to the current user, optionally scoped to a course.
     *
     * - viewall (system): all queries.
     * - author (system): own queries plus published queries; scoped by courseid when supplied.
     * - view (system or course): published + visible queries; if a course is given, only those
     *   bound to that course or to the site (courseid = 0).
     * - viewown (course): same as view, restricted to the supplied course.
     *
     * @param int $courseid Course id to scope to. 0 means site-wide list (only callable with system caps).
     * @return \stdClass[]
     */
    public static function visible_to_current_user(int $courseid = 0): array {
        global $DB, $USER;

        $syscontext = \context_system::instance();
        $coursecontext = $courseid ? \context_course::instance($courseid) : $syscontext;

        if (has_capability('report/sql:viewall', $syscontext)) {
            if ($courseid) {
                return $DB->get_records_select(
                    self::TABLE,
                    'courseid = :c OR courseid = 0',
                    ['c' => $courseid],
                    'timemodified DESC'
                );
            }
            return $DB->get_records(self::TABLE, null, 'timemodified DESC');
        }

        if (has_capability('report/sql:author', $syscontext)) {
            $params = ['u' => $USER->id, 'p' => self::STATUS_PUBLISHED, 'v' => 1];
            $where  = '(ownerid = :u OR (status = :p AND visible = :v))';
            if ($courseid) {
                $where .= ' AND (courseid = :c OR courseid = 0)';
                $params['c'] = $courseid;
            }
            return $DB->get_records_select(self::TABLE, $where, $params, 'timemodified DESC');
        }

        // Course-level viewer (teacher) — must supply courseid and have view/viewown there.
        if (
            $courseid && (
            has_capability('report/sql:view', $coursecontext) ||
            has_capability('report/sql:viewown', $coursecontext)
            )
        ) {
            return $DB->get_records_select(
                self::TABLE,
                'status = :p AND visible = :v AND (courseid = :c OR courseid = 0)',
                ['p' => self::STATUS_PUBLISHED, 'v' => 1, 'c' => $courseid],
                'timemodified DESC'
            );
        }

        // System view fallback (e.g. admins without viewall): published + visible only, site-wide.
        if (has_capability('report/sql:view', $syscontext)) {
            return $DB->get_records_select(
                self::TABLE,
                'status = :p AND visible = :v AND courseid = 0',
                ['p' => self::STATUS_PUBLISHED, 'v' => 1],
                'timemodified DESC'
            );
        }

        return [];
    }
}
