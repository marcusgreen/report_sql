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
use core_reportbuilder\local\models\audience as audience_model;
use core_reportbuilder\reportbuilder\audience\allusers;
use core_cohort\reportbuilder\audience\cohortmember;
use report_sql\reportbuilder\audience\courseparticipant;
use report_sql\reportbuilder\audience\courserole;

/**
 * Who can open a query's generated Report Builder report — the RB **context + audience** side of
 * access control, kept separate from the plugin's own page-level capability checks on {@see query}.
 *
 * Extracted from {@see query} so the saved-query entity no longer owns the RB visibility concern.
 * The {@see query}::AUDIENCE_* tokens remain the vocabulary; this class reads a query record and
 * drives the two levers of {@see \core_reportbuilder\permission::can_view_report()}.
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_visibility {
    /**
     * Limit who can open the RB report by setting core Report Builder's context + audience.
     *
     * These are the two levers of {@see \core_reportbuilder\permission::can_view_report()}:
     *
     * - Context: course-scoped queries (courseid > 0) place their report in that course context, so
     *   the moodle/reportbuilder:view capability is evaluated there rather than site-wide.
     * - Audience: taken from the query's audiencemeta picker. When that is empty (DEFAULT) the
     *   audience is derived automatically — a hidden query (visible = 0) gets none (owner +
     *   reportbuilder:viewall only); a course-scoped query gets {@see courseparticipant}; a visible
     *   site-wide query gets {@see allusers}.
     *
     * Idempotent: existing audiences are cleared first so re-publishing, toggling visibility or
     * changing the picker does not accumulate duplicates. These reports are created solely by this
     * plugin, so wiping their audiences is safe.
     *
     * @param \stdClass $record The query record (reads courseid, visible, audiencemeta).
     * @param int $reportid The Report Builder report to constrain.
     */
    public static function apply(\stdClass $record, int $reportid): void {
        $courseid = (int) ($record->courseid ?? 0);
        $visible  = (int) ($record->visible ?? 1);

        // Context follows course scope. A courseid pointing at a course that no longer exists (e.g.
        // course deleted after scoping, or a stale id carried in from an older import) degrades to
        // site-wide rather than fatalling on context_course::instance().
        $context = \context_system::instance();
        if ($courseid > 0) {
            $coursecontext = \context_course::instance($courseid, IGNORE_MISSING);
            if ($coursecontext) {
                $context = $coursecontext;
            } else {
                $courseid = 0;
            }
        }
        $reportpersistent = report_model::get_record(['id' => $reportid], MUST_EXIST);
        if ((int) $reportpersistent->get('contextid') !== (int) $context->id) {
            $reportpersistent->set('contextid', $context->id);
            $reportpersistent->save();
        }

        // Reset any audiences this plugin previously attached to the report.
        foreach (audience_model::get_records(['reportid' => $reportid]) as $audience) {
            $audience->delete();
        }

        $meta = $record->audiencemeta ? json_decode($record->audiencemeta, true) : null;
        $type = is_array($meta) ? ($meta['type'] ?? query::AUDIENCE_DEFAULT) : query::AUDIENCE_DEFAULT;

        // Automatic: derive from scope + visibility.
        if ($type === query::AUDIENCE_DEFAULT) {
            if (!$visible) {
                return;
            }
            if ($courseid > 0) {
                // Course-scoped reports default to course staff (teacher / non-editing teacher /
                // manager) rather than every enrolled user, so students do not see them unless the
                // author explicitly chooses "Course participants". Fall back to participants only if
                // the site somehow has no staff roles defined.
                $roles = self::staff_role_ids();
                if ($roles) {
                    courserole::create($reportid, ['courseid' => $courseid, 'roles' => $roles]);
                } else {
                    courseparticipant::create($reportid, ['courseid' => $courseid]);
                }
            } else {
                allusers::create($reportid, []);
            }
            return;
        }

        // Explicit picker choice.
        switch ($type) {
            case query::AUDIENCE_ALLUSERS:
                allusers::create($reportid, []);
                break;
            case query::AUDIENCE_COURSEPARTICIPANT:
                if ($courseid > 0) {
                    courseparticipant::create($reportid, ['courseid' => $courseid]);
                }
                break;
            case query::AUDIENCE_COURSEROLE:
                $roles = array_values(array_filter(array_map('intval', (array) ($meta['roles'] ?? []))));
                if ($courseid > 0 && $roles) {
                    courserole::create($reportid, ['courseid' => $courseid, 'roles' => $roles]);
                }
                break;
            case query::AUDIENCE_COHORT:
                $cohorts = array_values(array_filter(array_map('intval', (array) ($meta['cohorts'] ?? []))));
                if ($cohorts) {
                    cohortmember::create($reportid, ['cohorts' => $cohorts]);
                }
                break;
            case query::AUDIENCE_NONE:
            default:
                // No audience: owner + reportbuilder:viewall only.
                break;
        }
    }

    /**
     * Detach every query scoped to a course that has just been deleted.
     *
     * Called from the {@see \core\event\course_deleted} observer. When a course is deleted its
     * context row goes with it, leaving any report we placed in that course context with a dangling
     * contextid that fatals {@see report_model::get_context()} (and, before that, our course-scoped
     * audiences calling context_course::instance()). For each affected query this:
     *
     * - degrades the query to site-wide scope (courseid = 0) so the plugin UI is consistent;
     * - re-points its published report to the system context, curing the dangling contextid;
     * - clears the report's plugin audiences. The course-scoped audience can never match again, and
     *   silently re-deriving a site-wide audience would *widen* who can open the report (a privilege
     *   escalation), so we degrade to owner + reportbuilder:viewall only and force the picker to NONE.
     *
     * @param int $courseid Id of the deleted course.
     */
    public static function on_course_deleted(int $courseid): void {
        global $DB;

        if ($courseid <= 0) {
            return;
        }

        $records = $DB->get_records(query::TABLE, ['courseid' => $courseid]);
        if (!$records) {
            return;
        }

        $syscontext = \context_system::instance();
        foreach ($records as $rec) {
            $DB->update_record(query::TABLE, (object) [
                'id'           => $rec->id,
                'courseid'     => 0,
                'audiencemeta' => json_encode(['type' => query::AUDIENCE_NONE]),
                'timemodified' => time(),
            ]);

            if ($rec->status !== query::STATUS_PUBLISHED) {
                continue;
            }
            // A query may own several reports (see create_additional_report); every one of them was
            // placed in the now-deleted course context, so detach them all, not just $rec->reportid.
            foreach (query::bound_report_ids((int) $rec->id) as $rid) {
                $report = report_model::get_record(['id' => $rid]);
                if (!$report) {
                    continue;
                }
                if ((int) $report->get('contextid') !== (int) $syscontext->id) {
                    $report->set('contextid', $syscontext->id);
                    $report->save();
                }
                foreach (audience_model::get_records(['reportid' => $rid]) as $audience) {
                    $audience->delete();
                }
            }
        }
    }

    /**
     * Role ids considered "course staff" — those with a teaching or management archetype.
     *
     * Used for the automatic course-scoped audience so students are excluded by default.
     *
     * @return int[]
     */
    private static function staff_role_ids(): array {
        $roleids = [];
        foreach (['editingteacher', 'teacher', 'manager'] as $archetype) {
            foreach (get_archetype_roles($archetype) as $role) {
                $roleids[(int) $role->id] = (int) $role->id;
            }
        }
        return array_values($roleids);
    }

    /**
     * Build the audiencemeta JSON blob from submitted form data.
     *
     * @param \stdClass $data Form data (audiencetype, audienceroles, audiencecohorts).
     * @return string|null JSON string, or null for the automatic default.
     */
    public static function build_audiencemeta(\stdClass $data): ?string {
        $type = (string) ($data->audiencetype ?? query::AUDIENCE_DEFAULT);
        switch ($type) {
            case query::AUDIENCE_ALLUSERS:
                return json_encode(['type' => query::AUDIENCE_ALLUSERS]);
            case query::AUDIENCE_COURSEPARTICIPANT:
                return json_encode(['type' => query::AUDIENCE_COURSEPARTICIPANT]);
            case query::AUDIENCE_COURSEROLE:
                return json_encode([
                    'type'  => query::AUDIENCE_COURSEROLE,
                    'roles' => array_values(array_map('intval', (array) ($data->audienceroles ?? []))),
                ]);
            case query::AUDIENCE_COHORT:
                return json_encode([
                    'type'    => query::AUDIENCE_COHORT,
                    'cohorts' => array_values(array_map('intval', (array) ($data->audiencecohorts ?? []))),
                ]);
            case query::AUDIENCE_NONE:
                return json_encode(['type' => query::AUDIENCE_NONE]);
            default:
                return null;
        }
    }

    /**
     * Expand a stored audiencemeta blob into flat form field values for set_data().
     *
     * @param string|null $json Stored audiencemeta JSON.
     * @return array{audiencetype:string,audienceroles:int[],audiencecohorts:int[]}
     */
    public static function explode_audiencemeta(?string $json): array {
        $meta = $json ? json_decode($json, true) : null;
        return [
            'audiencetype'    => is_array($meta) ? ($meta['type'] ?? query::AUDIENCE_DEFAULT) : query::AUDIENCE_DEFAULT,
            'audienceroles'   => array_map('intval', (array) ($meta['roles'] ?? [])),
            'audiencecohorts' => array_map('intval', (array) ($meta['cohorts'] ?? [])),
        ];
    }
}
