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
 * Render the bundled user documentation (docs/userdocs.md) in the browser.
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();
// Only authors (people who can create report views) get the docs page.
require_capability('report/sql:author', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/report/sql/docs.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('userdocs', 'report_sql'));
$PAGE->set_heading(get_string('reportsources', 'report_sql'));

$markdown = file_get_contents(__DIR__ . '/docs/userdocs.md');

// The markdown references images by bare filename (e.g. logo.png); they live in docs/. Rewrite both
// markdown image syntax ![alt](file.png) and raw <img src="file.png"> to absolute plugin URLs so they
// resolve once the doc is served from docs.php rather than the docs/ directory.
$docsbase = (new moodle_url('/report/sql/docs/'))->out(false);
$markdown = preg_replace_callback(
    '/!\[([^\]]*)\]\((?!https?:\/\/)([^)]+)\)/',
    function ($m) use ($docsbase) {
        return '![' . $m[1] . '](' . $docsbase . ltrim($m[2], '/') . ')';
    },
    $markdown
);
$markdown = preg_replace_callback(
    '/<img\b([^>]*?)\bsrc="(?!https?:\/\/)([^"]+)"/i',
    function ($m) use ($docsbase) {
        return '<img' . $m[1] . 'src="' . $docsbase . ltrim($m[2], '/') . '"';
    },
    $markdown
);

echo $OUTPUT->header();
$html = format_text($markdown, FORMAT_MARKDOWN, ['context' => $context, 'noclean' => true]);

// Moodle's markdown renderer emits headings without id attributes, so the in-page table-of-contents
// anchors (GitHub-style #slug links) have nothing to jump to. Inject a GitHub-compatible slug id on
// every heading so the TOC and cross-references work. GitHub's slugger: lowercase, drop anything that
// is not a letter/number/space/hyphen, then spaces -> hyphens; duplicate slugs get a -1, -2 … suffix.
$seen = [];
$slugger = function (string $text) use (&$seen): string {
    $text = html_entity_decode(strip_tags($text), ENT_QUOTES, 'UTF-8');
    $slug = strtolower(trim($text));
    $slug = preg_replace('/[^\p{L}\p{N} \-]+/u', '', $slug); // drop punctuation (keep spaces/hyphens).
    $slug = str_replace(' ', '-', $slug);
    $base = $slug;
    $i = 0;
    while (isset($seen[$slug])) {
        $slug = $base . '-' . (++$i);
    }
    $seen[$slug] = true;
    return $slug;
};
$html = preg_replace_callback(
    '/<h([1-6])>(.*?)<\/h\1>/s',
    function ($m) use ($slugger) {
        return '<h' . $m[1] . ' id="' . s($slugger($m[2])) . '">' . $m[2] . '</h' . $m[1] . '>';
    },
    $html
);

echo $OUTPUT->box($html, 'generalbox rs-docs');
echo $OUTPUT->footer();
