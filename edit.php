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
 * Create / edit an ad-hoc query.
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use report_sql\form\edit_query_form;
use report_sql\local\query;
use report_sql\local\query_naming;
use report_sql\local\report_visibility;
use report_sql\local\sql\validator;
use report_sql\local\sql\view;

require_login();

$id = optional_param('id', 0, PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);
$aiquestion = optional_param('aiquestion', '', PARAM_RAW_TRIMMED);
$aiaction = optional_param('aiaction', '', PARAM_ALPHA);
// Set when the author reached the edit form via "Save, publish & configure chart" / "…filters":
// expand and jump to that section on the reopened (now published) form. See
// edit_query_form::definition_after_data().
$focuschart = optional_param('focuschart', 0, PARAM_BOOL);
$focusfilter = optional_param('focusfilter', 0, PARAM_BOOL);
// SQL currently in the "SQL (select only)" field, posted alongside an AI generate request so a
// prompt that refers to existing SQL ("add a column to this", "fix this error") can use it as basis.
$aicurrentsql = optional_param('querysql', '', PARAM_RAW);
$context = context_system::instance();
require_capability('report/sql:author', $context);

require_once(__DIR__ . '/lib.php');
report_sql_require_enabled();

// Inherit the admin tree context from the index node (Site administration ▸ Report builder ▸
// SQL Report) so editing highlights the right menu entry and builds a breadcrumb back to the
// listing, rather than a bare Home ▸ heading trail. The edit page's own url/title override the
// node's afterwards; the edit/new leaf is appended once $existing is known.
admin_externalpage_setup(
    'report_sql_index',
    '',
    null,
    new moodle_url('/report/sql/edit.php', ['id' => $id, 'courseid' => $courseid]),
    ['pagelayout' => 'admin']
);
$PAGE->set_heading(get_string('reportsources', 'report_sql'));

$existing = null;
if ($id) {
    $existing = query::get_record($id);
    // Authors edit own queries; viewall can edit anything.
    if (
        (int) $existing->ownerid !== (int) $USER->id &&
        !has_capability('report/sql:viewall', $context)
    ) {
        throw new required_capability_exception($context, 'report/sql:viewall', 'nopermissions', '');
    }
    // Admin-created queries are locked to site admins regardless of capability.
    if (is_siteadmin($existing->ownerid) && !is_siteadmin($USER)) {
        throw new required_capability_exception($context, 'report/sql:author', 'nopermissions', '');
    }
}

// Breadcrumb leaf: the query name when editing, else "New SQL report". The title mirrors it so the
// browser tab and trail agree.
$leaf = $existing ? format_string($existing->name) : get_string('addnew', 'report_sql');
$PAGE->navbar->add($leaf);
$PAGE->set_title($leaf);

// The audience picker offers course-scoped options only when the query is bound to a course.
$formcourseid = $existing ? (int) $existing->courseid : $courseid;
$canpublish = has_capability('report/sql:approve', $context);
$mform = new edit_query_form(null, [
    'courseid' => $formcourseid,
    'canpublish' => $canpublish,
    'focuschart' => $focuschart,
    'focusfilter' => $focusfilter,
]);

// Consolidate form defaults into one object so AI generation can override querysql.
$formdefaults = null;
if ($existing) {
    // Display SQL without {} table braces; auto_brace() re-adds them on save.
    $existing->querysql = validator::strip_braces((string) $existing->querysql);
    // Expand the stored audience choice into the flat form fields.
    foreach (report_visibility::explode_audiencemeta($existing->audiencemeta ?? null) as $key => $value) {
        $existing->$key = $value;
    }
    $formdefaults = $existing;
} else if ($courseid) {
    $formdefaults = (object) ['courseid' => $courseid];
}

$airesult = null;
$aierror  = null;
$aisqlchatavailable = class_exists('\local_sqlchat\api')
    && (bool) get_config('report_sql', 'aigenerate');

if ($aisqlchatavailable && get_config('report_sql', 'syntaxhighlight')) {
    $PAGE->requires->js_call_amd('report_sql/ai_feedback', 'init');
}

if ($aisqlchatavailable && $aiaction === 'generate' && $aiquestion !== '') {
    require_sesskey();
    try {
        // When the question refers to the SQL already in the editor, feed that SQL to the AI as the
        // basis of the prompt. Skip if the SQL is already embedded (the error-fix path appends it
        // client-side) so it isn't duplicated.
        $prompt = $aiquestion;
        $currentsql = trim($aicurrentsql);
        if (
            $currentsql !== '' &&
            query_naming::refers_to_existing_sql($aiquestion) &&
            strpos($aiquestion, $currentsql) === false
        ) {
            $prompt = $aiquestion . "\n\nExisting SQL to use as the basis:\n" . $currentsql;
        }
        // Pass our token rules as the third arg so the AI emits report_sql %%…%%
        // tokens (dates, case, context, …); they resolve when the view is built.
        // local_sqlchat itself knows nothing about these tokens.
        $airesult = \local_sqlchat\api::generate_sql($prompt, $context->id, view::ai_prompt_rules());
        $mergedata = $formdefaults ? (array) $formdefaults : [];
        $mergedata['querysql'] = validator::strip_braces($airesult->sql);
        // Make up a name/description when none exist yet, so the generated query is immediately
        // saveable (name is a required field). A "fix this SQL error" prompt is meaningless as a
        // name, so in that case derive both from the meaning of the generated SQL instead.
        $fromsql = query_naming::is_error_fix_prompt($aiquestion);
        if (trim((string) ($mergedata['name'] ?? '')) === '') {
            $mergedata['name'] = $fromsql
                ? query_naming::from_sql($airesult->sql)
                : query_naming::from_question($aiquestion);
        }
        if (trim((string) ($mergedata['description'] ?? '')) === '') {
            $mergedata['description'] = $fromsql
                ? query_naming::description_from_sql($airesult->sql)
                : $aiquestion;
        }
        $formdefaults = (object) $mergedata;
    } catch (\Throwable $e) {
        $aierror = $e->getMessage();
    }
}

if ($formdefaults !== null) {
    $mform->set_data($formdefaults);
}

$returnurl = new moodle_url(
    '/report/sql/index.php',
    $courseid ? ['courseid' => $courseid] : []
);

if ($mform->is_cancelled()) {
    redirect($returnurl);
} else if ($data = $mform->get_data()) {
    // Prevent an author from scoping a query to a course they have no access to. A courseid that
    // resolves to no course (e.g. stale id from an import) is demoted to site-wide rather than
    // fatalling on context_course::instance().
    if (!empty($data->courseid)) {
        $coursecontext = context_course::instance((int) $data->courseid, IGNORE_MISSING);
        if (!$coursecontext) {
            $data->courseid = 0;
        } else if (
            !has_capability('report/sql:viewall', $context) &&
            !has_capability('report/sql:view', $coursecontext) &&
            !has_capability('report/sql:viewown', $coursecontext)
        ) {
            throw new required_capability_exception($coursecontext, 'report/sql:view', 'nopermissions', '');
        }
    }
    $newid = query::save($data);

    // The "Save and publish" action is a one-click convenience for approvers. The capability is re-checked here
    // (not just on the form button) so a forged submit can't publish. If publishing fails the query
    // is already saved as a draft, so report the failure but keep the saved state.
    if (!empty($data->saveandpublish) && $canpublish) {
        try {
            query::get($newid)->publish();
        } catch (\moodle_exception $e) {
            redirect(
                $returnurl,
                get_string('savedpublishfailed', 'report_sql', $e->getMessage()),
                null,
                \core\output\notification::NOTIFY_ERROR
            );
        }
        // The "Save, publish & configure chart" / "…filters" button reopens this form (now published, that
        // section unlocked) scrolled to and expanded at the relevant header, instead of returning to
        // the index. If both are ticked, expand both and anchor to the filter section (it sits first).
        if (!empty($data->focuschart) || !empty($data->focusfilter)) {
            $params = ['id' => $newid];
            if (!empty($data->focuschart)) {
                $params['focuschart'] = 1;
            }
            if (!empty($data->focusfilter)) {
                $params['focusfilter'] = 1;
            }
            $anchor = !empty($data->focusfilter) ? 'id_useridfilterheader' : 'id_chartheader';
            $publishtarget = new moodle_url('/report/sql/edit.php', $params, $anchor);
        } else {
            $publishtarget = $returnurl;
        }
        redirect(
            $publishtarget,
            get_string('savedandpublished', 'report_sql'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    redirect(
        $returnurl,
        get_string('changessaved'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();
echo $OUTPUT->heading($existing
    ? get_string('edit', 'report_sql') . ': ' . format_string($existing->name)
    : get_string('addnew', 'report_sql'));

// Link to the bundled user documentation so authors can reach it from the editor.
echo html_writer::div(
    html_writer::link(
        new moodle_url('/report/sql/docs.php'),
        get_string('userdocs', 'report_sql'),
        ['target' => '_blank', 'rel' => 'noopener']
    ),
    'mb-3'
);

if ($aisqlchatavailable) {
    echo html_writer::start_div('card mb-3');
    echo html_writer::start_div('card-body');
    echo html_writer::tag(
        'h5',
        get_string('ai:heading', 'report_sql')
            . $OUTPUT->help_icon('ai:heading', 'report_sql'),
        ['class' => 'card-title mt-0']
    );

    if ($aierror) {
        echo $OUTPUT->notification($aierror, 'error');
    }
    if ($airesult) {
        echo html_writer::tag(
            'p',
            get_string('ai:latency', 'report_sql', number_format($airesult->latency_ms / 1000, 2)),
            ['class' => 'text-muted small mb-2']
        );
        if (get_config('local_sqlchat', 'showprompt') && !empty($airesult->prompt)) {
            echo html_writer::start_tag('details', ['class' => 'mt-2']);
            echo html_writer::tag('summary', get_string('ai:prompt', 'report_sql'), ['class' => 'h6']);
            echo html_writer::tag('button', get_string('ai:copy', 'report_sql'), [
                'type' => 'button',
                'class' => 'btn btn-sm btn-outline-secondary mb-2',
                'data-sqlchat-copy' => 'rs-sqlchat-prompt',
                'data-copied-label' => get_string('ai:copied', 'report_sql'),
            ]);
            echo html_writer::tag('pre', s($airesult->prompt), [
                'id' => 'rs-sqlchat-prompt', 'class' => 'bg-light p-2 small',
            ]);
            echo html_writer::end_tag('details');
            echo html_writer::script("
document.querySelectorAll('[data-sqlchat-copy]').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var pre = document.getElementById(btn.getAttribute('data-sqlchat-copy'));
        if (!pre) { return; }
        var text = pre.textContent;
        var flash = function() {
            var orig = btn.textContent;
            btn.textContent = btn.getAttribute('data-copied-label');
            setTimeout(function() { btn.textContent = orig; }, 1500);
        };
        var fallback = function() {
            var ta = document.createElement('textarea');
            ta.value = text;
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.focus();
            ta.select();
            try { document.execCommand('copy'); flash(); } catch (e) { /* noop */ }
            document.body.removeChild(ta);
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(flash, fallback);
        } else {
            fallback();
        }
    });
});
");
        }
    }

    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url->out(false)]);
    echo html_writer::tag(
        'label',
        get_string('ai:question', 'report_sql'),
        ['for' => 'rs-ai-question']
    );
    echo html_writer::tag('textarea', s($aiquestion), [
        'name'        => 'aiquestion',
        'id'          => 'rs-ai-question',
        'rows'        => 2,
        'cols'        => 80,
        'class'       => 'form-control mb-2',
        'placeholder' => get_string('ai:placeholder', 'report_sql'),
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'aiaction', 'value' => 'generate']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    // Carries the SQL currently in the editor so a prompt that refers to it ("add a column to this")
    // can use it as the basis. This AI box is a separate form from the main mform, so the SQL would
    // not otherwise post. Prefilled server-side for the no-JS case; editor.js overwrites it with the
    // live editor value on submit when syntax highlighting is on.
    echo html_writer::empty_tag('input', [
        'type'  => 'hidden',
        'name'  => 'querysql',
        'id'    => 'rs-ai-currentsql',
        'value' => is_object($formdefaults) ? ($formdefaults->querysql ?? '') : '',
    ]);
    if ($id) {
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $id]);
    }
    if ($courseid) {
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'courseid', 'value' => $courseid]);
    }
    echo html_writer::tag('button', get_string('ai:generate', 'report_sql'), [
        'type'            => 'submit',
        'id'              => 'rs-ai-generate',
        'class'           => 'btn btn-secondary',
        'data-generating' => get_string('ai:generating', 'report_sql'),
    ]);
    echo html_writer::end_tag('form');
    echo html_writer::end_div(); // End card-body.
    echo html_writer::end_div(); // End card.
}

$mform->display();
echo $OUTPUT->footer();
