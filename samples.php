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
 * Browse and import the bundled sample report views.
 *
 * Linked from the index page (single mode), the post-install notification and the plugin settings
 * page. Imports the samples shipped in samples/samples.json as fresh drafts owned by the
 * current user, reusing {@see \report_sql\local\transfer::import()}.
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use report_sql\local\transfer;

require_login();

admin_externalpage_setup('report_sql_samples');

$indexurl = new moodle_url('/report/sql/index.php');

$sources = transfer::bundled_samples();

// Single mode (linked from the index page) offers radio buttons so exactly one sample imports;
// the default (settings / post-install) offers checkboxes for a bulk import.
$single = optional_param('single', 0, PARAM_BOOL);

// Handle a selective import of the ticked samples.
if (optional_param('import', 0, PARAM_BOOL) && confirm_sesskey()) {
    $duplicates = [];

    if ($single) {
        // Radio mode: import exactly one sample, whatever it is. Prefix the name so an
        // already-present sample lands as a distinct copy rather than colliding.
        $index = optional_param('selected', -1, PARAM_INT);
        if (!isset($sources[$index])) {
            redirect(
                new moodle_url('/report/sql/samples.php', ['single' => 1]),
                get_string('samples:noneselected', 'report_sql'),
                null,
                \core\output\notification::NOTIFY_WARNING
            );
        }
        $source = $sources[$index];
        $source['name'] = get_string('samples:sampleprefix', 'report_sql', $source['name']);
        $sources = [$source];
        $wanted = [0];
    } else {
        // Checkbox mode posts selected[].
        $selected = optional_param_array('selected', [], PARAM_INT);

        // Server-side guard: never import a sample whose name already exists, even if the client
        // re-enabled the disabled control. Fold those back into the "already present" message.
        $wanted = [];
        foreach ($selected as $index) {
            if (!isset($sources[$index])) {
                continue;
            }
            if (!empty($sources[$index]['duplicate'])) {
                $duplicates[] = (string) $sources[$index]['name'];
                continue;
            }
            $wanted[] = $index;
        }

        if (empty($wanted) && empty($duplicates)) {
            redirect(
                new moodle_url('/report/sql/samples.php'),
                get_string('samples:noneselected', 'report_sql'),
                null,
                \core\output\notification::NOTIFY_WARNING
            );
        }
    }

    $result = transfer::import($sources, $wanted);

    $messages = [get_string('importdone', 'report_sql', $result['imported'])];
    if (!empty($duplicates)) {
        $messages[] = get_string('samples:duplicates', 'report_sql', implode(', ', $duplicates));
    }
    if (!empty($result['demoted'])) {
        $messages[] = get_string('importdemoted', 'report_sql', implode(', ', array_keys($result['demoted'])));
    }
    if (!empty($result['skipped'])) {
        $messages[] = get_string('importskipped', 'report_sql', implode(', ', array_keys($result['skipped'])));
    }

    redirect($indexurl, implode(' ', $messages), null, \core\output\notification::NOTIFY_SUCCESS);
}

// For the display, include samples whose required plugin is missing so the "show all" toggle can
// reveal them (disabled, badged "not installed"). The import handler above keeps the default
// available-only set, so a missing-plugin sample is never actually importable.
$showall = optional_param('showall', 0, PARAM_BOOL);
$sources = transfer::bundled_samples(true);

// Count the samples hidden behind the toggle (a required plugin is not installed here).
$unavailablecount = 0;
foreach ($sources as $source) {
    if (empty($source['available'])) {
        $unavailablecount++;
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string($single ? 'samples:titlesingle' : 'samples:title', 'report_sql'));

echo html_writer::div(
    $OUTPUT->single_button($indexurl, get_string('back'), 'get'),
    'mb-3'
);

if (empty($sources)) {
    echo $OUTPUT->notification(
        get_string('samples:none', 'report_sql'),
        \core\output\notification::NOTIFY_WARNING
    );
    echo html_writer::link($indexurl, get_string('back'));
    echo $OUTPUT->footer();
    exit;
}

// Build the sample context: one row per sample.
// Checkbox (bulk) mode disables any sample whose name already exists and pre-ticks the rest.
// Radio (single) mode lets you import ANY sample — the import prefixes the name, so an
// already-present sample is imported as a fresh "Sample: ..." copy, never blocked. The first
// row is pre-selected (radios are mutually exclusive).
// Glyph + Bootstrap text-colour class per chart type — same treatment as the query listing entity,
// so a sample that carries a chart config is flagged bar/line/pie at a glance.
$charticons = [
    'bar'      => ['fa-chart-column', 'text-primary'],
    'line'     => ['fa-chart-line', 'text-danger'],
    'pie'      => ['fa-chart-pie', 'text-success'],
    'doughnut' => ['fa-chart-pie', 'text-info'],
];

$rows = [];
$radiochosen = false;
foreach ($sources as $source) {
    $available = !empty($source['available']);

    // A sample needing an uninstalled plugin is hidden unless "show all" is on; when revealed it is
    // shown disabled (its tables are absent, so it can never import here).
    if (!$available && !$showall) {
        continue;
    }

    $isdup = !empty($source['duplicate']);
    if (!$available) {
        $disabled = true;
        $checked = false;
    } else if ($single) {
        $disabled = false;
        $checked = !$radiochosen;
        if ($checked) {
            $radiochosen = true;
        }
    } else {
        $disabled = $isdup;
        $checked = !$isdup;
    }

    // Leading glyph. A chart config wins (bar/line/pie/doughnut); otherwise a heuristic flags
    // aggregate "summary" samples — SQL with GROUP BY or an aggregate function.
    $nameicon = '';
    $charttype = $source['chartmeta']['type'] ?? 'none';
    if ($charttype !== 'none' && isset($charticons[$charttype])) {
        [$faclass, $colourclass] = $charticons[$charttype];
        $nameicon = html_writer::tag('i', '', [
            'class'       => 'fa ' . $faclass . ' ' . $colourclass . ' me-1',
            'title'       => get_string('viewchart', 'report_sql'),
            'aria-hidden' => 'true',
        ]);
    } else if (preg_match('/\bgroup\s+by\b|\b(?:count|sum|avg|min|max)\s*\(/i', (string) $source['querysql'])) {
        $nameicon = html_writer::tag('i', '', [
            'class'       => 'fa fa-calculator text-info me-1',
            'title'       => get_string('summaryreport', 'report_sql'),
            'aria-hidden' => 'true',
        ]);
    }

    // Third-party plugin dependencies get a "Requires ..." badge so an author knows the sample is
    // not core-only. When the sample is revealed-but-unavailable the badge says "(not installed)"
    // and renders as a warning; an installed dependency is an informational badge.
    $requiresnames = [];
    foreach ($source['requiresmeta'] ?? [] as $rm) {
        if (!empty($rm['thirdparty'])) {
            $requiresnames[] = $rm['name'];
        }
    }

    $rows[] = [
        'index'       => $source['index'],
        'name'        => $source['name'],
        'nameicon'    => $nameicon,
        'hasrequires' => !empty($requiresnames),
        'requiresmissing' => !$available,
        'requires'    => $requiresnames
            ? get_string(
                $available ? 'samples:requires' : 'samples:requiresmissingbadge',
                'report_sql',
                implode(', ', $requiresnames)
            )
            : '',
        // SECURITY: intentional 'noclean' — NOT an XSS hole. $source['description'] is never user
        // input; it is read only from the plugin's shipped samples/samples.json (via
        // transfer::bundled_samples()), so it is trusted. Needed so a sample can embed an
        // illustrative data: image, which HTMLPurifier strips. Do not reuse for user-sourced text.
        'description' => format_text($source['description'], FORMAT_HTML, ['para' => false, 'noclean' => true]),
        'querysql'    => $source['querysql'],
        'duplicate'   => $isdup,
        'disabled'    => $disabled,
        'checked'     => $checked,
    ];
}

echo $OUTPUT->render_from_template('report_sql/samples_list', [
    'intro'     => get_string(
        $single ? 'samples:introsingle' : 'samples:intro',
        'report_sql',
        count($sources)
    ),
    'single'    => $single,
    'actionurl' => (new moodle_url('/report/sql/samples.php'))->out(false),
    'cancelurl' => $indexurl->out(false),
    'sesskey'   => sesskey(),
    // "Show all" reveal: offered only when at least one sample needs an uninstalled plugin. The
    // toggle is a small GET form (report_sql/samples AMD submits it on change) that flips ?showall.
    'hasunavailable' => $unavailablecount > 0,
    'showall'        => (bool) $showall,
    'showalllabel'   => get_string('samples:showall', 'report_sql', $unavailablecount),
    'samples' => $rows,
]);

echo $OUTPUT->footer();
