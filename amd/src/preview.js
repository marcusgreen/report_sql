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
 * "Preview" button — renders the first page of the current (unsaved) SQL as a real Report Builder
 * report, inline, via the Fragment API (see report_sql_output_fragment_preview()).
 *
 * The fragment returns the report HTML plus its queued JS; replaceNodeContents() injects both so
 * the report's own formatting init runs. Preview is first-page-only, so no paging AJAX is exercised.
 *
 * @module     report_sql/preview
 * @copyright  2026 Marcus Green
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import Fragment from 'core/fragment';
import Templates from 'core/templates';
import {get_string as getString} from 'core/str';
import {exception as displayException} from 'core/notification';

/**
 * Read the current (unsaved) chart configuration off the edit form.
 *
 * The chart selects only exist once the query has columns (see definition_after_data()), so this
 * returns an empty object on a fresh draft — the fragment then renders no chart. When a real chart
 * type is chosen, the axis and sizing values are forwarded to the preview fragment.
 *
 * @return {Object} Fragment arg fragments (chart_type, chart_xcol, …), or {} when no chart selected.
 */
const collectChartArgs = () => {
    const type = document.getElementById('id_chart_type');
    if (!type || type.value === '' || type.value === 'none') {
        return {};
    }
    const val = (id) => {
        const el = document.getElementById(id);
        return el ? el.value : '';
    };
    // A checkbox's .value is always "1"; read .checked and send 1/0 so the server sees the real state.
    const checked = (id) => {
        const el = document.getElementById(id);
        return el && el.checked ? 1 : 0;
    };
    // Keys mirror the server fragment/form param names (see lib.php, edit_query_form).
    /* eslint-disable camelcase */
    return {
        chart_type: type.value,
        chart_xcol: val('id_chart_xcol'),
        chart_ycol: val('id_chart_ycol'),
        chart_rowlimit: val('id_chart_rowlimit'),
        chart_labelsize: val('id_chart_labelsize'),
        chart_datalabels: checked('id_chart_datalabels'),
        chart_multicolour: checked('id_chart_multicolour'),
    };
    /* eslint-enable camelcase */
};

/**
 * Wire the Preview button to the fragment renderer.
 *
 * @param {string} btnid - Preview button element id (carries data-contextid).
 * @param {string} sqlid - SQL textarea element id.
 * @param {string} courseid - Course select element id (may be absent).
 * @param {string} regionid - Container the rendered report is injected into.
 * @param {string} detailsid - The <details> wrapper to force open on preview.
 */
export const init = (btnid, sqlid, courseid, regionid, detailsid) => {
    const btn = document.getElementById(btnid);
    const sqlField = document.getElementById(sqlid);
    const region = document.getElementById(regionid);
    if (!btn || !sqlField || !region) {
        return;
    }
    const contextid = parseInt(btn.dataset.contextid, 10);
    const details = detailsid ? document.getElementById(detailsid) : null;

    btn.addEventListener('click', async() => {
        const sql = sqlField.value.trim();
        if (!sql) {
            return;
        }
        const courseField = document.getElementById(courseid);
        const course = courseField ? parseInt(courseField.value, 10) || 0 : 0;

        // Chart config lives on the same form (only present once the query has columns). When a real
        // chart type is selected, pass the axis + sizing choices so the fragment renders the chart
        // above the table — the preview then reflects the *current, unsaved* chart selection.
        const chartArgs = collectChartArgs();

        // Reveal the collapsible region (hidden until the first run) and show a loading note.
        if (details) {
            details.classList.remove('d-none');
            details.open = true;
        }
        btn.disabled = true;
        region.textContent = await getString('previewloading', 'report_sql');

        // Fragment.loadFragment resolves a jQuery Deferred with (html, js); keep the two-arg .then
        // (await would drop the js), then hand both to replaceNodeContents so the report's JS runs.
        const promise = Fragment.loadFragment(
            'report_sql', 'preview', contextid, {sql: sql, courseid: course, ...chartArgs}
        );
        promise.then((html, js) => {
            Templates.replaceNodeContents(region, html, js);
            return;
        }).catch(displayException);
        promise.always(() => {
            btn.disabled = false;
        });
    });
};
