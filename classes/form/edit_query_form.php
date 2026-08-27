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

namespace report_sql\form;

use report_sql\local\action\action_registry;
use report_sql\local\sql\validator;
use moodleform;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Edit/create form for an ad-hoc query.
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class edit_query_form extends moodleform {
    /**
     * Form definition.
     */
    protected function definition() {
        global $PAGE;

        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('text', 'name', get_string('name', 'report_sql'), ['size' => 60]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        if (get_config('report_sql', 'syntaxhighlight')) {
            // The editor fetches the (large) schema + FK map lazily over AJAX; see
            // report_sql\external\get_schema and report_sql\local\schema.
            $PAGE->requires->js_call_amd('report_sql/editor', 'init', ['id_querysql']);
        }

        $mform->addElement(
            'textarea',
            'querysql',
            get_string('querysql', 'report_sql'),
            ['rows' => 10, 'cols' => 80, 'class' => 'report-sql-sql']
        );
        $mform->setType('querysql', PARAM_RAW);
        $mform->addRule('querysql', null, 'required', null, 'client');
        $mform->addHelpButton('querysql', 'querysql', 'report_sql');

        // Advisory "Test query" button — analyses date columns, row count and indexes over AJAX
        // (see report_sql\external\test_query). Convenience only, never a publish gate.
        // The inline "Preview" button sits to its right — renders the current (unsaved) SQL as a
        // real Report Builder report via the Fragment API (see
        // report_sql_output_fragment_preview()). Both buttons share one row; their help
        // icons render inline (via help_icon) rather than in the form's label column so each stays
        // next to its own button.
        global $OUTPUT;
        $testbtn = \html_writer::tag(
            'button',
            get_string('checkquery', 'report_sql'),
            ['type' => 'button', 'id' => 'rs-test-btn', 'class' => 'btn btn-secondary']
        );
        $previewbtn = \html_writer::tag(
            'button',
            get_string('preview', 'report_sql'),
            [
                'type'           => 'button',
                'id'             => 'rs-preview-btn',
                'class'          => 'btn btn-secondary',
                'data-contextid' => (string) \context_system::instance()->id,
            ]
        );
        $querybuttons = \html_writer::div(
            $testbtn . $OUTPUT->help_icon('checkquery', 'report_sql')
                . $previewbtn . $OUTPUT->help_icon('preview', 'report_sql'),
            'd-flex align-items-center gap-2'
        );
        $mform->addElement('static', 'querybuttons', '', $querybuttons);
        $PAGE->requires->js_call_amd(
            'report_sql/test',
            'init',
            ['rs-test-btn', 'id_querysql', 'id_courseid', 'rs-test-results']
        );

        // Test results and the preview result region are rendered full-width (raw html elements, not
        // static felements) so the Report Builder table is not squeezed by the form's grid column,
        // which would clip wide column headers. See styles.css (#rs-preview overflow-x).
        $mform->addElement('html', \html_writer::div('', 'mt-2', ['id' => 'rs-test-results']));
        // The preview result lands in a collapsible <details> so it can be tucked away without
        // reloading. Hidden (d-none) until the first Preview run; preview.js reveals it on click.
        $previewregion = \html_writer::start_tag('details', ['id' => 'rs-preview-details', 'class' => 'mt-2 d-none'])
            . \html_writer::tag('summary', get_string('previewheading', 'report_sql'), ['class' => 'h6'])
            . \html_writer::div('', '', ['id' => 'rs-preview', 'class' => 'mt-2'])
            . \html_writer::end_tag('details');
        $mform->addElement('html', $previewregion);
        $PAGE->requires->js_call_amd(
            'report_sql/preview',
            'init',
            ['rs-preview-btn', 'id_querysql', 'id_courseid', 'rs-preview', 'rs-preview-details']
        );

        // Description sits just above the audience header — a free-text note about the report,
        // logically grouped with its scope/visibility settings rather than the SQL editor above.
        $mform->addElement(
            'textarea',
            'description',
            get_string('description', 'report_sql'),
            ['rows' => 3, 'cols' => 80]
        );
        $mform->setType('description', PARAM_TEXT);

        $this->add_audience_elements($mform);

        // Authors get a plain Save (draft); approvers additionally get a one-click Save & publish so
        // they don't have to round-trip through the index page to publish. The capability is also
        // re-checked in edit.php before publishing — the button is convenience, not the gate.
        $buttonarray = [];
        $buttonarray[] = $mform->createElement('submit', 'submitbutton', get_string('savechanges'));
        if (!empty($this->_customdata['canpublish'])) {
            $buttonarray[] = $mform->createElement(
                'submit',
                'saveandpublish',
                get_string('saveandpublish', 'report_sql')
            );
        }
        $buttonarray[] = $mform->createElement('cancel');
        $mform->addGroup($buttonarray, 'buttonar', '', [' '], false);
        $mform->closeHeaderBefore('buttonar');
    }

    /**
     * Add the "who can view the report" audience picker.
     *
     * Course-scoped types (course participants / course roles) are only offered when the query is
     * bound to a course (courseid passed in as custom data). The role and cohort pickers are shown
     * conditionally via hideIf based on the selected type.
     *
     * @param \MoodleQuickForm $mform
     */
    private function add_audience_elements(\MoodleQuickForm $mform): void {
        global $DB;

        $courseid = (int) ($this->_customdata['courseid'] ?? 0);

        $mform->addElement('header', 'audienceheader', get_string('audiencesettings', 'report_sql'));

        // The course-scoped options are always offered so changing the course scope does not require
        // saving and reopening the form to reveal them. Choosing one without a course is rejected in
        // validation() rather than hidden, since the selected course is only known at submit time.
        $typeopts = [
            'default'           => get_string('audiencedefault', 'report_sql'),
            'courseparticipant' => get_string('audiencecourseparticipant', 'report_sql'),
            'courserole'        => get_string('audiencecourserole', 'report_sql'),
            'allusers'          => get_string('audienceallusers', 'report_sql'),
            'cohort'            => get_string('audiencecohort', 'report_sql'),
            'none'              => get_string('audiencenone', 'report_sql'),
        ];

        $mform->addElement(
            'select',
            'audiencetype',
            get_string('audiencetype', 'report_sql'),
            $typeopts
        );
        $mform->setType('audiencetype', PARAM_ALPHA);
        $mform->setDefault('audiencetype', 'default');
        $mform->addHelpButton('audiencetype', 'audiencetype', 'report_sql');

        // A stored courseid may point at a course that no longer exists (course deleted, or a stale
        // id carried in from an import on another site), so fall back to system context for the role
        // display names rather than fatalling on context_course::instance(). The role picker is always
        // built so the courserole audience is usable without saving and reopening the form first.
        $coursecontext = $courseid > 0 ? \context_course::instance($courseid, IGNORE_MISSING) : false;
        $rolecontext = $coursecontext ?: \context_system::instance();
        $roleopts = [];
        foreach (role_fix_names(get_all_roles(), $rolecontext, ROLENAME_BOTH) as $role) {
            $roleopts[$role->id] = $role->localname;
        }
        $mform->addElement(
            'autocomplete',
            'audienceroles',
            get_string('audienceroles', 'report_sql'),
            $roleopts,
            ['multiple' => true]
        );
        $mform->setType('audienceroles', PARAM_INT);
        $mform->hideIf('audienceroles', 'audiencetype', 'neq', 'courserole');

        $cohortopts = $DB->get_records_menu('cohort', null, 'name', 'id, name');
        $mform->addElement(
            'autocomplete',
            'audiencecohorts',
            get_string('audiencecohorts', 'report_sql'),
            $cohortopts,
            ['multiple' => true]
        );
        $mform->setType('audiencecohorts', PARAM_INT);
        $mform->hideIf('audiencecohorts', 'audiencetype', 'neq', 'cohort');

        // Course scope + visibility sit under the audience header: both drive who can open the
        // report (scope sets the context the RB permission is checked in; visibility gates it),
        // so they belong beside the audience picker. Leaving the course empty means site-wide
        // (courseid 0); authors can re-scope an existing query here — e.g. an imported draft that
        // landed site-wide because its original course id does not exist on this site. The chosen
        // course is access-checked on save (see edit.php), so listing all courses here is safe.
        $mform->addElement(
            'course',
            'courseid',
            get_string('coursescope', 'report_sql'),
            ['multiple' => false, 'includefrontpage' => false]
        );
        $mform->setType('courseid', PARAM_INT);
        $mform->setDefault('courseid', 0);
        $mform->addHelpButton('courseid', 'coursescope', 'report_sql');

        $mform->addElement(
            'advcheckbox',
            'visible',
            get_string('visible', 'report_sql'),
            ' ',
            null,
            [0, 1]
        );
        $mform->setDefault('visible', 1);
        $mform->addHelpButton('visible', 'visible', 'report_sql');
    }

    /**
     * Add chart configuration fields once column metadata is available (published queries only).
     */
    public function definition_after_data() {
        global $DB;

        $mform  = $this->_form;
        $idval  = $mform->getElementValue('id');
        $id     = is_array($idval) ? (int) $idval[0] : (int) $idval;
        if (!$id) {
            return;
        }

        $record = $DB->get_record('report_sql_query', ['id' => $id]);
        if (!$record || empty($record->columnsmeta)) {
            if ($record) {
                // The per-user / per-course filters are gated by the same publish check as the chart
                // (their column lists come from the live view), so name them as locked too — matching
                // the published layout, where the filter header precedes the chart header.
                $mform->addElement('header', 'useridfilterheader', get_string('useridfilter', 'report_sql'));
                $mform->addElement(
                    'static',
                    'filter_unpublished_note',
                    '',
                    \html_writer::div(
                        get_string('filterpublishrequired', 'report_sql'),
                        'alert alert-warning',
                        ['role' => 'alert']
                    )
                );
                // Approvers get the same one-tick reopen path for the filter section as for the chart.
                if (!empty($this->_customdata['canpublish'])) {
                    $mform->addElement(
                        'advcheckbox',
                        'focusfilter',
                        '',
                        get_string('focusfilter', 'report_sql')
                    );
                    $mform->setType('focusfilter', PARAM_BOOL);
                    $mform->setDefault('focusfilter', 0);
                }
                $mform->addElement('header', 'chartheader', get_string('chartsettings', 'report_sql'));
                // The "See the option below" hint is appended only for approvers — the checkbox it points to is
                // added just below, and only when they can publish.
                $canpublish = !empty($this->_customdata['canpublish']);
                $note = get_string('chartpublishrequired', 'report_sql')
                    . ($canpublish ? ' ' . get_string('chartpublishrequiredsee', 'report_sql') : '');
                $mform->addElement(
                    'static',
                    'chart_unpublished_note',
                    '',
                    \html_writer::div($note, 'alert alert-warning', ['role' => 'alert'])
                );
                // Approvers get a one-tick path: publish now and reopen here with the chart section
                // unlocked (edit.php reads focuschart on the Save & publish redirect). Authors without
                // approve can't publish, so the option is pointless for them.
                if ($canpublish) {
                    $mform->addElement(
                        'advcheckbox',
                        'focuschart',
                        '',
                        get_string('focuschart', 'report_sql')
                    );
                    $mform->setType('focuschart', PARAM_BOOL);
                    $mform->setDefault('focuschart', 0);
                }
                // Row actions, like the chart, need the published view's columns to configure.
                $mform->addElement('header', 'actionsheader', get_string('actionsettings', 'report_sql'));
                $mform->addElement(
                    'static',
                    'action_unpublished_note',
                    '',
                    \html_writer::div(
                        get_string('actionpublishrequired', 'report_sql'),
                        'alert alert-warning',
                        ['role' => 'alert']
                    )
                );
            }
            return;
        }

        $meta = json_decode($record->columnsmeta, true);
        if (!is_array($meta) || !$meta) {
            return;
        }

        $chartmeta = $record->chartmeta ? json_decode($record->chartmeta, true) : [];
        $colnames  = array_keys($meta);
        $colopts   = array_combine($colnames, $colnames);
        $xopts     = ['' => get_string('selectcolumn', 'report_sql')] + $colopts;

        // Per-user filter: restrict the report to rows whose chosen column matches the viewing
        // user's id. Offered only once published, since the column list comes from the live view.
        $mform->addElement('header', 'useridfilterheader', get_string('useridfilter', 'report_sql'));
        // Arrived via "Save, publish & configure filters": expand so the anchor jump lands on open
        // controls rather than a collapsed header.
        if (!empty($this->_customdata['focusfilter'])) {
            $mform->setExpanded('useridfilterheader', true);
        }
        $mform->addElement(
            'select',
            'useridcolumn',
            get_string('useridcolumn', 'report_sql'),
            $xopts
        );
        $mform->setType('useridcolumn', PARAM_ALPHANUMEXT);
        $mform->setDefault('useridcolumn', $record->useridcolumn ?? '');
        $mform->addHelpButton('useridcolumn', 'useridcolumn', 'report_sql');

        // Teacher-course filter: restrict the report to rows whose course the viewer teaches.
        $mform->addElement(
            'select',
            'coursecolumn',
            get_string('coursecolumn', 'report_sql'),
            $xopts
        );
        $mform->setType('coursecolumn', PARAM_ALPHANUMEXT);
        $mform->setDefault('coursecolumn', $record->coursecolumn ?? '');
        $mform->addHelpButton('coursecolumn', 'coursecolumn', 'report_sql');

        // Page-course filter: when shown in a block on a course page, restrict rows to that course.
        $mform->addElement(
            'select',
            'pagecoursecolumn',
            get_string('pagecoursecolumn', 'report_sql'),
            $xopts
        );
        $mform->setType('pagecoursecolumn', PARAM_ALPHANUMEXT);
        $mform->setDefault('pagecoursecolumn', $record->pagecoursecolumn ?? '');
        $mform->addHelpButton('pagecoursecolumn', 'pagecoursecolumn', 'report_sql');

        $mform->addElement('header', 'chartheader', get_string('chartsettings', 'report_sql'));
        // Arrived via "Save, publish & configure chart": expand the section so the anchor jump lands
        // on open controls rather than a collapsed header.
        if (!empty($this->_customdata['focuschart'])) {
            $mform->setExpanded('chartheader', true);
        }

        $mform->addElement('select', 'chart_type', get_string('charttype', 'report_sql'), [
            'none'     => get_string('chartnone', 'report_sql'),
            'bar'      => get_string('chartbar', 'report_sql'),
            'line'     => get_string('chartline', 'report_sql'),
            'pie'      => get_string('chartpie', 'report_sql'),
            'doughnut' => get_string('chartdoughnut', 'report_sql'),
        ]);
        $mform->setType('chart_type', PARAM_ALPHA);
        $mform->setDefault('chart_type', $chartmeta['type'] ?? 'none');

        // The per-user filter column is hidden from all output (its value always equals the
        // viewer's own id), so don't offer it as a chart axis. Based on the saved choice; a
        // change to the filter select above takes effect after save.
        $chartxopts = $xopts;
        $useridcol = (string) ($record->useridcolumn ?? '');
        if ($useridcol !== '' && count($colopts) > 1) {
            unset($chartxopts[$useridcol]);
        }

        $mform->addElement('select', 'chart_xcol', get_string('chartxcol', 'report_sql'), $chartxopts);
        $mform->setType('chart_xcol', PARAM_ALPHANUMEXT);
        $mform->setDefault('chart_xcol', $chartmeta['xcol'] ?? '');
        $mform->addHelpButton('chart_xcol', 'chartxcol', 'report_sql');

        $mform->addElement('select', 'chart_ycol', get_string('chartycol', 'report_sql'), $chartxopts);
        $mform->setType('chart_ycol', PARAM_ALPHANUMEXT);
        $mform->setDefault('chart_ycol', $chartmeta['ycol'] ?? '');
        $mform->addHelpButton('chart_ycol', 'chartycol', 'report_sql');

        $mform->addElement('text', 'chart_rowlimit', get_string('chartrowlimit', 'report_sql'), ['size' => 6]);
        $mform->setType('chart_rowlimit', PARAM_INT);
        $mform->setDefault('chart_rowlimit', (int) ($chartmeta['rowlimit'] ?? 200));
        $mform->addHelpButton('chart_rowlimit', 'chartrowlimit', 'report_sql');

        $labelsizes = [];
        foreach ([11, 12, 14, 16, 18, 20, 24, 28, 32, 36, 42] as $pt) {
            $labelsizes[$pt] = get_string('chartlabelsizeoption', 'report_sql', $pt);
        }
        $mform->addElement('select', 'chart_labelsize', get_string('chartlabelsize', 'report_sql'), $labelsizes);
        $mform->setType('chart_labelsize', PARAM_INT);
        $mform->setDefault('chart_labelsize', (int) ($chartmeta['labelsize'] ?? 16));
        $mform->addHelpButton('chart_labelsize', 'chartlabelsize', 'report_sql');

        $mform->addElement(
            'advcheckbox',
            'chart_datalabels',
            get_string('chartdatalabels', 'report_sql'),
            get_string('chartdatalabelslabel', 'report_sql')
        );
        $mform->setType('chart_datalabels', PARAM_BOOL);
        $mform->setDefault('chart_datalabels', !empty($chartmeta['datalabels']));
        $mform->addHelpButton('chart_datalabels', 'chartdatalabels', 'report_sql');
        $mform->disabledIf('chart_datalabels', 'chart_type', 'in', ['none', 'pie', 'doughnut']);

        $mform->addElement(
            'advcheckbox',
            'chart_multicolour',
            get_string('chartmulticolour', 'report_sql'),
            get_string('chartmulticolourlabel', 'report_sql')
        );
        $mform->setType('chart_multicolour', PARAM_BOOL);
        $mform->setDefault('chart_multicolour', !empty($chartmeta['multicolour']));
        $mform->addHelpButton('chart_multicolour', 'chartmulticolour', 'report_sql');
        // Bar only: pie/doughnut are already per-slice coloured, a line is a single series.
        $mform->disabledIf('chart_multicolour', 'chart_type', 'in', ['none', 'line', 'pie', 'doughnut']);

        $mform->addElement(
            'advcheckbox',
            'chart_showdata',
            get_string('chartshowdata', 'report_sql'),
            get_string('chartshowdatalabel', 'report_sql')
        );
        $mform->setType('chart_showdata', PARAM_BOOL);
        $mform->setDefault('chart_showdata', !empty($chartmeta['showdata']));
        $mform->addHelpButton('chart_showdata', 'chartshowdata', 'report_sql');

        $this->add_actions_elements($mform, $record, $xopts);
    }

    /**
     * Actionable-report controls: enable bulk row-select, pick the subject column, choose which
     * built-in ops the action bar offers, and configure each op's fixed parameter (role, course,
     * cohort, message). Column-dependent, so built here in definition_after_data() from the live
     * view's columns — same as the per-user filter and chart sections.
     *
     * @param \MoodleQuickForm $mform
     * @param \stdClass $record The saved query record.
     * @param array $xopts Column select options (['' => choose] + column => column).
     */
    private function add_actions_elements(\MoodleQuickForm $mform, \stdClass $record, array $xopts): void {
        global $DB;

        $meta = $record->actionsmeta ? json_decode($record->actionsmeta, true) : [];
        if (!is_array($meta)) {
            $meta = [];
        }

        $mform->addElement('header', 'actionsheader', get_string('actionsettings', 'report_sql'));

        $mform->addElement(
            'advcheckbox',
            'action_enabled',
            get_string('actionenable', 'report_sql'),
            get_string('actionenablelabel', 'report_sql')
        );
        $mform->setType('action_enabled', PARAM_BOOL);
        $mform->setDefault('action_enabled', !empty($meta['enabled']));
        $mform->addHelpButton('action_enabled', 'actionenable', 'report_sql');

        // Which identity the ops act on. v1 ships user ops; course is scaffolded for v1.1.
        $mform->addElement('select', 'action_subject', get_string('actionsubject', 'report_sql'), [
            'user'   => get_string('actionsubjectuser', 'report_sql'),
            'course' => get_string('actionsubjectcourse', 'report_sql'),
        ]);
        $mform->setType('action_subject', PARAM_ALPHA);
        $mform->setDefault('action_subject', $meta['subject'] ?? 'user');
        $mform->disabledIf('action_subject', 'action_enabled', 'notchecked');

        // The output column whose value is the subject id (a user id for user ops).
        $mform->addElement('select', 'action_subjectcolumn', get_string('actionsubjectcolumn', 'report_sql'), $xopts);
        $mform->setType('action_subjectcolumn', PARAM_ALPHANUMEXT);
        $mform->setDefault('action_subjectcolumn', $meta['subjectcolumn'] ?? ($record->useridcolumn ?? ''));
        $mform->addHelpButton('action_subjectcolumn', 'actionsubjectcolumn', 'report_sql');
        $mform->disabledIf('action_subjectcolumn', 'action_enabled', 'notchecked');

        // The ops offered in the action bar (multi-select from the registry).
        $ops = $mform->addElement('select', 'action_ops', get_string('actionops', 'report_sql'), action_registry::menu());
        $ops->setMultiple(true);
        $mform->setDefault('action_ops', $meta['ops'] ?? []);
        $mform->addHelpButton('action_ops', 'actionops', 'report_sql');
        $mform->disabledIf('action_ops', 'action_enabled', 'notchecked');

        // Per-op fixed parameters. Author-time config keeps the runtime action bar a single dropdown.
        $roleoptions = ['' => get_string('choosedots')];
        foreach (role_fix_names(get_all_roles(), \context_system::instance(), ROLENAME_ORIGINAL) as $role) {
            $roleoptions[$role->id] = $role->localname;
        }
        $mform->addElement('select', 'action_roleid', get_string('actionrole', 'report_sql'), $roleoptions);
        $mform->setType('action_roleid', PARAM_INT);
        $mform->setDefault('action_roleid', $meta['params']['roleid'] ?? '');
        $mform->addHelpButton('action_roleid', 'actionrole', 'report_sql');
        $mform->disabledIf('action_roleid', 'action_enabled', 'notchecked');

        $mform->addElement('course', 'action_courseid', get_string('actioncourse', 'report_sql'), [
            'includefrontpage' => false,
        ]);
        $mform->setDefault('action_courseid', $meta['params']['courseid'] ?? ($record->courseid ?: ''));
        $mform->addHelpButton('action_courseid', 'actioncourse', 'report_sql');
        $mform->disabledIf('action_courseid', 'action_enabled', 'notchecked');

        $cohortoptions = ['' => get_string('choosedots')]
            + $DB->get_records_menu('cohort', null, 'name ASC', 'id, name');
        $mform->addElement('select', 'action_cohortid', get_string('actioncohort', 'report_sql'), $cohortoptions);
        $mform->setType('action_cohortid', PARAM_INT);
        $mform->setDefault('action_cohortid', $meta['params']['cohortid'] ?? '');
        $mform->disabledIf('action_cohortid', 'action_enabled', 'notchecked');

        $mform->addElement('textarea', 'action_messagetext', get_string('actionmessagetext', 'report_sql'),
            ['rows' => 3, 'cols' => 50]);
        $mform->setType('action_messagetext', PARAM_TEXT);
        $mform->setDefault('action_messagetext', $meta['params']['messagetext'] ?? '');
        $mform->disabledIf('action_messagetext', 'action_enabled', 'notchecked');
    }

    /**
     * Validate the submitted SQL and course scope.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        $sql = (string) ($data['querysql'] ?? '');
        try {
            validator::validate($sql);
        } catch (\moodle_exception $e) {
            $errors['querysql'] = $e->getMessage();
        }

        // The %%COURSEID%% token is baked into the static VIEW at publish, so the query must carry a
        // course scope to substitute. Reject it site-wide rather than silently bake in courseid 0.
        // The same applies to %%COURSECONTEXT%% (resolves to the course's mdl_context.id).
        $needscourse = stripos($sql, '%%COURSEID%%') !== false || stripos($sql, '%%COURSECONTEXT%%') !== false;
        if ($needscourse && (int) ($data['courseid'] ?? 0) <= 0) {
            $errors['courseid'] = get_string('errcourseidplaceholder', 'report_sql');
        }

        $audiencetype = (string) ($data['audiencetype'] ?? 'default');
        // Course-scoped audiences need a course to resolve against; the options are always shown, so
        // reject the combination here rather than hiding them based on the (submit-time) course value.
        if (
            in_array($audiencetype, ['courseparticipant', 'courserole'], true) &&
            (int) ($data['courseid'] ?? 0) <= 0
        ) {
            $errors['audiencetype'] = get_string('erraudiencecourse', 'report_sql');
        }
        if ($audiencetype === 'courserole' && empty($data['audienceroles'])) {
            $errors['audienceroles'] = get_string('erraudiencerolesempty', 'report_sql');
        }
        if ($audiencetype === 'cohort' && empty($data['audiencecohorts'])) {
            $errors['audiencecohorts'] = get_string('erraudiencecohortsempty', 'report_sql');
        }

        // Actionable-report config is only coherent when a subject column and at least one op are
        // set, and each chosen op has the parameter it needs.
        if (!empty($data['action_enabled'])) {
            $ops = (array) ($data['action_ops'] ?? []);
            if (empty($data['action_subjectcolumn'])) {
                $errors['action_subjectcolumn'] = get_string('erractionsubjectcol', 'report_sql');
            }
            if (!$ops) {
                $errors['action_ops'] = get_string('erractionops', 'report_sql');
            }
            // Enrol/unenrol need a course: the report's own scope, or an explicit target course.
            $needscourse = array_intersect($ops, ['enrol_user', 'unenrol_user']);
            if ($needscourse && (int) ($data['courseid'] ?? 0) <= 0 && empty($data['action_courseid'])) {
                $errors['action_courseid'] = get_string('erractioncourse', 'report_sql');
            }
            if (in_array('cohort_add', $ops, true) && empty($data['action_cohortid'])) {
                $errors['action_cohortid'] = get_string('erractioncohort', 'report_sql');
            }
            if (in_array('message_user', $ops, true) && trim((string) ($data['action_messagetext'] ?? '')) === '') {
                $errors['action_messagetext'] = get_string('erractionmessage', 'report_sql');
            }
        }

        return $errors;
    }
}
