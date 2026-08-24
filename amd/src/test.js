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
 * "Test" button — asks the test_query external for advisory feedback on the
 * current SQL (date columns, row count, indexes / full scans) and renders it.
 *
 * Date-like columns are rendered as click-to-wrap controls: clicking one rewrites
 * that column's select-list expression in place, wrapping it in %%TIMESTAMP(...)%%.
 * Columns using raw UPPER()/LOWER() in SQL get the same treatment, switching to
 * %%CASE(expr, upper|lower)%% so the transform is display-only.
 *
 * @module     report_sql/test
 * @copyright  2026 Marcus Green
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import {get_string as getString} from 'core/str';
import Ajax from 'core/ajax';
import {exception as displayException} from 'core/notification';
import {
    hasQuestionmarkInString,
    rewriteQuestionmarks,
    hasAliasWithSpaces,
    rewriteAliasSpaces,
} from './sqlfix';

/**
 * Wire the Test button to the analyser endpoint.
 *
 * @param {string} btnid - Test button element id.
 * @param {string} sqlid - SQL textarea element id.
 * @param {string} courseid - Course select element id (may be absent).
 * @param {string} resultid - Results container element id.
 */
export const init = (btnid, sqlid, courseid, resultid) => {
    const btn = document.getElementById(btnid);
    const sqlField = document.getElementById(sqlid);
    const results = document.getElementById(resultid);
    if (!btn || !sqlField || !results) {
        return;
    }

    btn.addEventListener('click', async() => {
        const sql = sqlField.value.trim();
        if (!sql) {
            return;
        }
        const courseField = document.getElementById(courseid);
        const course = courseField ? parseInt(courseField.value, 10) || 0 : 0;

        btn.disabled = true;
        results.textContent = await getString('testquery', 'report_sql');
        try {
            const data = await Ajax.call([{
                methodname: 'report_sql_test_query',
                args: {sql: sql, courseid: course},
            }])[0];
            await render(results, data, sqlField);
        } catch (error) {
            displayException(error);
        } finally {
            btn.disabled = false;
        }
    });
};

/**
 * Render the feedback into the results container.
 *
 * @param {HTMLElement} container - Results element.
 * @param {object} data - Response from test_query.
 * @param {HTMLTextAreaElement} sqlField - SQL textarea (source of / target for the edit).
 */
const render = async(container, data, sqlField) => {
    container.innerHTML = '';

    if (!data.ok) {
        const box = alertBox('alert-danger', data.error);
        // Offer the same one-click auto-fixes as the submit-time validation banner (editor.js):
        // a ? inside a string literal, or a column alias containing spaces.
        const sql = sqlField.value;
        if (hasQuestionmarkInString(sql)) {
            await appendFixLink(box, sqlField, 'convertquestionmark', rewriteQuestionmarks);
        }
        if (hasAliasWithSpaces(sql)) {
            await appendFixLink(box, sqlField, 'convertaliasspaces', rewriteAliasSpaces);
        }
        // Show the compiled SQL (placeholders/{table} resolved) the analyser actually ran, so the
        // author can line the DB error's line/column numbers up against it rather than their source.
        const compiled = data.compiledsql || '';
        if (compiled.trim() && compiled.trim() !== sql.trim()) {
            box.appendChild(await compiledSqlDetails(compiled));
        }
        container.appendChild(box);
        // When "Generate SQL with AI" is enabled, feed the error back into the AI
        // question box, mirroring how the submit-time validation banner does it
        // (see ai_feedback.js). The Test error is appended (a childList change) so
        // that module's attribute-only observer never sees it — prefill directly.
        prefillAiQuestion(data.error, sql);
        return;
    }

    if (data.rowcount >= 0) {
        const count = await getString('checkrowcount', 'report_sql', data.rowcount);
        container.appendChild(alertBox('alert-info', count));
    }

    const datecols = data.datecolumns || [];
    if (datecols.length) {
        container.appendChild(await dateColumnsAlert(datecols, sqlField));
    }

    const casecols = data.casecolumns || [];
    if (casecols.length) {
        container.appendChild(await caseColumnsAlert(casecols, sqlField));
    }

    appendList(container, data.suggestions, 'alert-warning');
    appendList(container, data.warnings, 'alert-warning');
    appendList(container, data.indexinfo, 'alert-secondary');

    if (!datecols.length && !casecols.length && !data.suggestions.length && !data.warnings.length) {
        const ok = await getString('checkallgood', 'report_sql');
        container.appendChild(alertBox('alert-success', ok));
    }
};

/**
 * Build a warning alert listing the date-like columns, each as a button that wraps its
 * select-list expression in %%TIMESTAMP(...)%%.
 *
 * @param {string[]} cols - Date-like output column names.
 * @param {HTMLTextAreaElement} sqlField - SQL textarea to rewrite.
 * @return {Promise<HTMLElement>}
 */
const dateColumnsAlert = async(cols, sqlField) => {
    const div = document.createElement('div');
    div.className = 'alert alert-warning mb-1 py-1';
    div.setAttribute('role', 'alert');

    const introkey = cols.length === 1 ? 'checkdatecolumnsintroone' : 'checkdatecolumnsintro';
    const intro = await getString(introkey, 'report_sql');
    div.appendChild(document.createTextNode(intro + ' '));

    cols.forEach((col, i) => {
        if (i > 0) {
            div.appendChild(document.createTextNode(', '));
        }
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-link btn-sm p-0 align-baseline';
        btn.textContent = col;
        btn.addEventListener('click', () => {
            applyTimestamp(sqlField, col, btn).catch(() => {
                // GetString rejection on the fallback path — nothing actionable, don't leak it.
            });
        });
        div.appendChild(btn);
    });

    const outro = await getString('checkdatecolumnsoutro', 'report_sql');
    div.appendChild(document.createTextNode(' ' + outro));
    return div;
};

/**
 * Wrap the given column's expression in %%TIMESTAMP()%% and push it back into the editor.
 *
 * @param {HTMLTextAreaElement} sqlField - SQL textarea (and CodeMirror mirror target).
 * @param {string} col - Output column name to wrap.
 * @param {HTMLButtonElement} btn - The clicked button (disabled once applied).
 */
const applyTimestamp = async(sqlField, col, btn) => {
    const current = sqlField.value;
    const updated = wrapTimestamp(current, col);
    if (updated === null) {
        // Could not locate the column's expression (e.g. SELECT *) — tell the user rather than
        // silently doing nothing.
        btn.disabled = true;
        btn.classList.add('text-muted');
        btn.title = await getString('checkdatecolumnsmanual', 'report_sql');
        return;
    }
    if (typeof sqlField.rsReplaceContent === 'function') {
        sqlField.rsReplaceContent(updated);
    } else {
        sqlField.value = updated;
    }
    // Mark this column as done — the button is no longer actionable.
    btn.disabled = true;
    btn.classList.remove('btn-link');
    btn.classList.add('text-success');
    btn.textContent = '✓ ' + col;
};

/**
 * Build a warning alert listing columns that apply UPPER()/LOWER() in SQL, each as a button
 * that rewrites its select-list expression to %%CASE(expr, upper|lower)%%.
 *
 * @param {Array<{col: string, mode: string}>} cols - Raw-case output columns and their mode.
 * @param {HTMLTextAreaElement} sqlField - SQL textarea to rewrite.
 * @return {Promise<HTMLElement>}
 */
const caseColumnsAlert = async(cols, sqlField) => {
    const div = document.createElement('div');
    div.className = 'alert alert-warning mb-1 py-1';
    div.setAttribute('role', 'alert');

    const introkey = cols.length === 1 ? 'checkcasecolumnsintroone' : 'checkcasecolumnsintro';
    const intro = await getString(introkey, 'report_sql');
    div.appendChild(document.createTextNode(intro + ' '));

    cols.forEach((entry, i) => {
        if (i > 0) {
            div.appendChild(document.createTextNode(', '));
        }
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-link btn-sm p-0 align-baseline';
        btn.textContent = entry.col;
        btn.addEventListener('click', () => {
            applyCase(sqlField, entry.col, entry.mode, btn).catch(() => {
                // GetString rejection on the fallback path — nothing actionable, don't leak it.
            });
        });
        div.appendChild(btn);
    });

    const outro = await getString('checkcasecolumnsoutro', 'report_sql');
    div.appendChild(document.createTextNode(' ' + outro));
    return div;
};

/**
 * Switch the given column's UPPER()/LOWER() call to %%CASE(expr, mode)%% and push it back
 * into the editor.
 *
 * @param {HTMLTextAreaElement} sqlField - SQL textarea (and CodeMirror mirror target).
 * @param {string} col - Output column name to rewrite.
 * @param {string} mode - Case mode (upper|lower).
 * @param {HTMLButtonElement} btn - The clicked button (disabled once applied).
 */
const applyCase = async(sqlField, col, mode, btn) => {
    const updated = wrapCase(sqlField.value, col, mode);
    if (updated === null) {
        btn.disabled = true;
        btn.classList.add('text-muted');
        btn.title = await getString('checkcasecolumnsmanual', 'report_sql');
        return;
    }
    if (typeof sqlField.rsReplaceContent === 'function') {
        sqlField.rsReplaceContent(updated);
    } else {
        sqlField.value = updated;
    }
    btn.disabled = true;
    btn.classList.remove('btn-link');
    btn.classList.add('text-success');
    btn.textContent = '✓ ' + col;
};

/**
 * Append a "…automatically" fix link to an error alert. Clicking it rewrites the SQL in place
 * (through CodeMirror when present, else the textarea) so the next Test / submit re-checks it.
 *
 * @param {HTMLElement} alertDiv - The danger alert to append to.
 * @param {HTMLTextAreaElement} sqlField - SQL textarea (and CodeMirror mirror target).
 * @param {string} strkey - Lang string key for the link label.
 * @param {function(string): string} rewrite - Maps the current SQL to its fixed form.
 */
const appendFixLink = async(alertDiv, sqlField, strkey, rewrite) => {
    const label = await getString(strkey, 'report_sql');
    alertDiv.appendChild(document.createElement('br'));
    const link = document.createElement('button');
    link.type = 'button';
    link.className = 'btn btn-link btn-sm p-0 align-baseline';
    link.textContent = label;
    link.addEventListener('click', () => {
        const updated = rewrite(sqlField.value);
        if (typeof sqlField.rsReplaceContent === 'function') {
            sqlField.rsReplaceContent(updated);
        } else {
            sqlField.value = updated;
        }
    });
    alertDiv.appendChild(link);
};

/**
 * Append one alert per line to the container.
 *
 * @param {HTMLElement} container
 * @param {string[]} lines
 * @param {string} cls - Bootstrap alert class.
 */
const appendList = (container, lines, cls) => {
    (lines || []).forEach((line) => container.appendChild(alertBox(cls, line)));
};

/**
 * Build a Bootstrap alert div with text content (no HTML injection).
 *
 * @param {string} cls - Bootstrap alert class.
 * @param {string} text
 * @return {HTMLElement}
 */
const alertBox = (cls, text) => {
    const div = document.createElement('div');
    div.className = 'alert ' + cls + ' mb-1 py-1';
    div.setAttribute('role', 'alert');
    div.textContent = text;
    return div;
};

/**
 * Build a <details> block (open by default) showing the compiled SQL in a scrollable <pre>.
 * The DB error's
 * line/column numbers refer to this compiled text (placeholders and {table} braces resolved), not to
 * the author's source. Text is set via textContent (no HTML injection); line breaks are preserved.
 *
 * @param {string} compiled - The compiled SQL string.
 * @return {Promise<HTMLElement>} A <details> element.
 */
const compiledSqlDetails = async(compiled) => {
    const details = document.createElement('details');
    details.className = 'mt-2';
    details.open = true;
    const summary = document.createElement('summary');
    summary.textContent = await getString('compiledsql', 'report_sql');
    details.appendChild(summary);
    const pre = document.createElement('pre');
    pre.className = 'mt-1 mb-0 p-2 bg-light border rounded';
    pre.style.cssText = 'max-height:16rem;overflow:auto;white-space:pre-wrap;';
    pre.textContent = compiled;
    details.appendChild(pre);
    return details;
};

/**
 * When "Generate SQL with AI" is enabled the edit form renders an AI question box
 * (#rs-ai-question). Pre-fill it with the Test-query error and the offending SQL,
 * using the same "Fix this SQL error: …" framing as ai_feedback.js so the two paths
 * (submit-time validation banner and Test button) feed the AI identically. No-op
 * when AI is disabled (the field is absent).
 *
 * @param {string} error - The DB / validation error message from test_query.
 * @param {string} sql - The current SQL from the editor textarea.
 */
const prefillAiQuestion = (error, sql) => {
    const aiField = document.getElementById('rs-ai-question');
    if (!aiField || !error || !sql.trim()) {
        return;
    }
    aiField.value = 'Fix this SQL error: ' + error.trim() + '\n\n' + sql.trim();
    aiField.scrollIntoView({behavior: 'smooth', block: 'nearest'});
};

/**
 * Blank a quoted string/identifier span starting at `i`, honouring doubled-closer escapes.
 *
 * @param {string} sql - Source SQL.
 * @param {string[]} out - Output char array being blanked in place.
 * @param {number} i - Offset of the opening quote/bracket.
 * @return {number} Offset just past the closing quote.
 */
const blankQuotedSpan = (sql, out, i) => {
    const n = sql.length;
    // The closer is the matching bracket for '[', else the same char; both allow a doubled
    // closer as an escape ('' "" `` ]]).
    const close = sql[i] === '[' ? ']' : sql[i];
    out[i] = ' ';
    i++;
    while (i < n) {
        const doubled = sql[i] === close && sql[i + 1] === close;
        if (doubled) {
            // Doubled closer is an escape, not the terminator.
            out[i] = ' ';
            out[i + 1] = ' ';
            i += 2;
            continue;
        }
        const terminator = sql[i] === close;
        out[i] = ' ';
        i++;
        if (terminator) {
            break;
        }
    }
    return i;
};

/**
 * Length-preserving mask of SQL comments and string literals: every character inside a
 * line/block comment or a '…'/"…" literal is replaced by a space, so keyword and comma
 * scans below can use offsets that still map 1:1 onto the original SQL.
 *
 * @param {string} sql
 * @return {string} Same-length SQL with comments/strings blanked to spaces.
 */
const maskSql = (sql) => {
    const out = sql.split('');
    const n = sql.length;
    let i = 0;
    while (i < n) {
        const two = sql.substr(i, 2);
        if (two === '/*') {
            let j = sql.indexOf('*/', i + 2);
            j = (j === -1) ? n : j + 2;
            for (let k = i; k < j; k++) {
                out[k] = ' ';
            }
            i = j;
        } else if (two === '--' || sql[i] === '#') {
            let j = sql.indexOf('\n', i);
            j = (j === -1) ? n : j;
            for (let k = i; k < j; k++) {
                out[k] = ' ';
            }
            i = j;
        } else if (sql[i] === "'" || sql[i] === '"' || sql[i] === '`' || sql[i] === '[') {
            // Quoted string ('…', "…") or quoted identifier (`…` MySQL, […] MSSQL). Blank the whole
            // span so keywords/commas quoted inside it (e.g. AS `from`) don't fool the region scan.
            i = blankQuotedSpan(sql, out, i);
        } else {
            i++;
        }
    }
    return out.join('');
};

/**
 * Whether the identifier starting at `i` in `mask` begins on a word boundary.
 *
 * @param {string} mask
 * @param {number} i
 * @return {boolean}
 */
const wordStart = (mask, i) => i === 0 || !/\w/.test(mask[i - 1]);

/**
 * Locate the top-level (paren depth 0) select list — the offsets between the outer SELECT
 * and its matching FROM. Handles WITH/CTE and select-list subqueries because both sit at a
 * deeper paren level than the outer statement.
 *
 * @param {string} mask - Length-preserving masked SQL (see {@see maskSql}).
 * @return {?{start: number, end: number}} Select-list offsets, or null if not found.
 */
const selectListRegion = (mask) => {
    let depth = 0;
    let selEnd = -1;
    for (let i = 0; i < mask.length; i++) {
        const ch = mask[i];
        if (ch === '(') {
            depth++;
        } else if (ch === ')') {
            depth--;
        } else if (depth === 0) {
            if (selEnd === -1) {
                if (wordStart(mask, i) && /^select\b/i.test(mask.substr(i, 7))) {
                    selEnd = i + 6;
                }
            } else if (wordStart(mask, i) && /^from\b/i.test(mask.substr(i, 5))) {
                return {start: selEnd, end: i};
            }
        }
    }
    return null;
};

/**
 * Split a select list into top-level (paren depth 0) items, as {from, to} offsets into the
 * original SQL.
 *
 * @param {string} mask - Masked SQL.
 * @param {number} start - Select-list start offset.
 * @param {number} end - Select-list end offset (position of FROM).
 * @return {Array<{from: number, to: number}>}
 */
const splitItems = (mask, start, end) => {
    const items = [];
    let depth = 0;
    let from = start;
    for (let i = start; i < end; i++) {
        const ch = mask[i];
        if (ch === '(') {
            depth++;
        } else if (ch === ')') {
            depth--;
        } else if (ch === ',' && depth === 0) {
            items.push({from: from, to: i});
            from = i + 1;
        }
    }
    items.push({from: from, to: end});
    return items;
};

/**
 * Rewrite `sql` so the select-list item producing `col` is wrapped in %%TIMESTAMP(...)%%.
 * The output column name is preserved: `x.timecreated` → `%%TIMESTAMP(x.timecreated)%%`
 * (the token derives its name from the trailing identifier), and `expr AS col` keeps its
 * `AS col`. Idempotent, and returns null when the column's expression cannot be located
 * (e.g. SELECT *, or a function expression with no matching output name).
 *
 * @param {string} sql - Current editor SQL.
 * @param {string} col - Output column name to wrap.
 * @return {?string} Rewritten SQL, or null if the column expression was not found.
 */
const wrapTimestamp = (sql, col) => {
    const mask = maskSql(sql);
    const region = selectListRegion(mask);
    if (!region) {
        return null;
    }
    const target = col.toLowerCase();
    const items = splitItems(mask, region.start, region.end);
    for (const item of items) {
        const orig = sql.slice(item.from, item.to);
        const maskItem = mask.slice(item.from, item.to);

        // Detect a trailing "AS alias" (alias found via the mask, extracted from the original).
        const asMatch = /\bas\s+([`"[]?\w+[\]`"]?)\s*$/i.exec(maskItem);
        let expr;
        let alias = '';
        if (asMatch) {
            expr = orig.slice(0, asMatch.index);
            alias = orig.slice(asMatch.index).replace(/^\s*as\s+/i, '').trim();
        } else {
            expr = orig;
        }

        // Output name: the alias if present, else the trailing identifier of the expression.
        let outName = alias.replace(/^[`"[]|[\]`"]$/g, '');
        if (!outName) {
            const idMatch = /([A-Za-z_]\w*)\s*$/.exec(expr);
            outName = idMatch ? idMatch[1] : '';
        }
        if (outName.toLowerCase() !== target) {
            continue;
        }
        if (/%%\s*TIMESTAMP/i.test(expr)) {
            return sql; // Already wrapped — no change.
        }

        const lead = expr.match(/^\s*/)[0];
        const trail = orig.match(/\s*$/)[0];
        // A leading DISTINCT is a statement-level modifier on the first select-list item, not
        // part of the column expression — keep it outside the token so it stays valid SQL.
        const distinct = /^\s*DISTINCT\b/i.test(expr) ? 'DISTINCT ' : '';
        const core = expr.replace(/^\s*DISTINCT\b\s*/i, '').trim();
        const wrapped = lead + distinct + '%%TIMESTAMP(' + core + ')%%' + (alias ? ' AS ' + alias : '') + trail;
        return sql.slice(0, item.from) + wrapped + sql.slice(item.to);
    }
    return null;
};

/**
 * Index of the ")" matching the "(" at `open` in `mask`, or -1 if unbalanced. Scans a masked
 * string so parens inside string literals are already blanked.
 *
 * @param {string} mask - Masked SQL (see {@see maskSql}).
 * @param {number} open - Offset of the opening "(".
 * @return {number}
 */
const matchParen = (mask, open) => {
    let depth = 0;
    for (let i = open; i < mask.length; i++) {
        if (mask[i] === '(') {
            depth++;
        } else if (mask[i] === ')') {
            depth--;
            if (depth === 0) {
                return i;
            }
        }
    }
    return -1;
};

/**
 * Rewrite `sql` so the select-list item producing `col` — a raw UPPER()/LOWER() (or MySQL
 * UCASE()/LCASE()) call wrapping a value — becomes %%CASE(innerExpr, mode)%%. The call's inner
 * argument becomes the token expression, so `UPPER(x.name) AS n` → `%%CASE(x.name, upper)%% AS n`.
 * Returns null when the item cannot be located or is not a bare case call spanning the whole
 * expression (e.g. SELECT *, or `UPPER(x) || 'y'`).
 *
 * @param {string} sql - Current editor SQL.
 * @param {string} col - Output column name to rewrite.
 * @param {string} mode - Case mode (upper|lower).
 * @return {?string} Rewritten SQL, or null if the column's case call was not found.
 */
const wrapCase = (sql, col, mode) => {
    const mask = maskSql(sql);
    const region = selectListRegion(mask);
    if (!region) {
        return null;
    }
    const target = col.toLowerCase();
    const items = splitItems(mask, region.start, region.end);
    for (const item of items) {
        const orig = sql.slice(item.from, item.to);
        const maskItem = mask.slice(item.from, item.to);

        // Trailing "AS alias" (found on the mask, extracted from the original).
        const asMatch = /\bas\s+([`"[]?\w+[\]`"]?)\s*$/i.exec(maskItem);
        let expr;
        let alias = '';
        if (asMatch) {
            expr = orig.slice(0, asMatch.index);
            alias = orig.slice(asMatch.index).replace(/^\s*as\s+/i, '').trim();
        } else {
            expr = orig;
        }
        const maskExpr = maskItem.slice(0, expr.length);

        // Output name: the alias if present, else the trailing identifier of the expression.
        let outName = alias.replace(/^[`"[]|[\]`"]$/g, '');
        if (!outName) {
            const idMatch = /([A-Za-z_]\w*)\s*$/.exec(expr);
            outName = idMatch ? idMatch[1] : '';
        }
        if (outName.toLowerCase() !== target) {
            continue;
        }

        // The whole expression must be a single case call (bar an optional leading DISTINCT).
        const fnMatch = /^(\s*(?:DISTINCT\s+)?)(UPPER|LOWER|UCASE|LCASE)\s*\(/i.exec(maskExpr);
        if (!fnMatch) {
            return null;
        }
        const open = fnMatch.index + fnMatch[0].length - 1; // Offset of the "(".
        const close = matchParen(maskExpr, open);
        if (close === -1 || maskExpr.slice(close + 1).trim() !== '') {
            return null; // Unbalanced, or text after the call — not a pure case column.
        }

        const prefix = expr.slice(0, fnMatch.index + fnMatch[1].length); // Leading ws + DISTINCT.
        const innerExpr = expr.slice(open + 1, close).trim();
        const trail = orig.match(/\s*$/)[0];
        const wrapped = prefix + '%%CASE(' + innerExpr + ', ' + mode + ')%%'
            + (alias ? ' AS ' + alias : '') + trail;
        return sql.slice(0, item.from) + wrapped + sql.slice(item.to);
    }
    return null;
};
