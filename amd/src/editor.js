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
 * CodeMirror 6 SQL editor with schema-aware autocomplete for report_sql.
 *
 * ES6 source — `grunt amd` compiles this to amd/build/editor.min.js.
 *
 * @module     report_sql/editor
 * @copyright  2026 Marcus Green
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {
    EditorState,
    EditorView,
    acceptCompletion,
    basicSetup,
    keymap,
    sql,
    MySQL,
} from './codemirror-lazy';
import {format as formatSql} from './sql-formatter-lazy';
import {get_string as getString, get_strings as getStrings} from 'core/str';
import Ajax from 'core/ajax';
import {
    hasQuestionmarkInString,
    rewriteQuestionmarks,
    hasAliasWithSpaces,
    rewriteAliasSpaces,
} from './sqlfix';

/**
 * Initialise a CodeMirror 6 SQL editor replacing the textarea with the given id.
 *
 * @param {string} targetid - The id of the textarea element to replace.
 */
export const init = (targetid) => {
    const textarea = document.getElementById(targetid);
    if (!textarea) {
        return;
    }

    // The schema + FK map are large and expensive to build, so they are fetched lazily over AJAX
    // (server-side MUC cached) rather than embedded in the page. The editor is built once the data
    // arrives; on failure it still builds with keyword-only autocomplete.
    const parse = (value) => {
        try {
            return value ? JSON.parse(value) : {};
        } catch (e) {
            return {};
        }
    };

    Ajax.call([{methodname: 'report_sql_get_schema', args: {}}])[0]
        .then((data) => {
            buildEditor(textarea, parse(data.schema), parse(data.fkmap));
            return data;
        })
        .catch(() => {
            buildEditor(textarea, {}, {});
        });
};

/**
 * Build the CodeMirror editor and wire up autocomplete, formatting and submit validation.
 *
 * @param {HTMLTextAreaElement} textarea - The textarea being replaced.
 * @param {Object} schema - Map of table name to column names.
 * @param {Object} fkMap - Foreign-key map keyed by table then column.
 */
const buildEditor = (textarea, schema, fkMap) => {
    // Bare table name — server auto-wraps it in {} on save, so the user never types braces.
    const tables = Object.keys(schema).map(name => ({label: name}));

    // SQL keywords that can follow FROM/JOIN and must not be treated as aliases.
    const aliasSkip = new Set([
        'where', 'on', 'set', 'inner', 'outer', 'left', 'right',
        'cross', 'full', 'group', 'order', 'having', 'limit',
        'union', 'except', 'intersect', 'using',
    ]);

    /**
     * Parse FROM/JOIN clauses and return a map of alias → {table, cols}.
     *
     * @param {string} docText
     * @returns {Object}
     */
    function parseAliases(docText) {
        const map = {};
        const re = /\b(?:FROM|JOIN)\s+\{?(\w+)\}?\s+(?:AS\s+)?(\w+)/gi;
        let m;
        while ((m = re.exec(docText)) !== null) {
            const raw = m[1].toLowerCase();
            const alias = m[2].toLowerCase();
            if (aliasSkip.has(alias)) {
                continue;
            }
            const resolvedTable = Object.keys(schema).find(k => k.toLowerCase() === raw) ?? raw;
            const cols = schema[resolvedTable];
            if (cols) {
                map[alias] = {table: resolvedTable, cols};
            }
        }
        return map;
    }

    /**
     * CompletionSource that resolves alias.column completions live from the doc.
     * FK columns are annotated with "→ reftable.refcol" in the detail field.
     *
     * @param {CompletionContext} context
     * @returns {CompletionResult|null}
     */
    function aliasCompletionSource(context) {
        const before = context.matchBefore(/\w+\.\w*/);
        if (!before) {
            return null;
        }
        const dotIdx = before.text.indexOf('.');
        const alias = before.text.slice(0, dotIdx).toLowerCase();
        if (schema[alias] !== undefined) {
            return null; // Real table name — built-in source handles it.
        }
        const aliasMap = parseAliases(context.state.doc.toString());
        const entry = aliasMap[alias];
        if (!entry || !entry.cols.length) {
            return null;
        }
        const tableFkMap = fkMap[entry.table] || {};
        return {
            from: before.from + dotIdx + 1,
            // Foreign-key columns are boosted so they group at the top of the list.
            options: entry.cols.map(col => {
                const fk = tableFkMap[col];
                return {
                    label: col,
                    type: 'property',
                    ...(fk ? {detail: `→ ${fk.reftable}.${fk.refcol}`, boost: 1} : {}),
                };
            }),
            validFor: /^\w*$/,
        };
    }

    const heightTheme = EditorView.theme({
        "&": {height: "260px"},
        ".cm-scroller": {overflow: "auto"},
    });

    const state = EditorState.create({
        doc: textarea.value,
        extensions: [
            basicSetup,
            sql({dialect: MySQL, schema: schema, tables: tables, upperCaseKeywords: true}),
            MySQL.language.data.of({autocomplete: aliasCompletionSource}),
            MySQL.language.data.of({autocomplete: timestampFormatCompletionSource}),
            MySQL.language.data.of({autocomplete: tokenCompletionSource}),
            keymap.of([
                {key: "Tab", run: acceptCompletion},
                {key: "Shift-Ctrl-f", run: () => {
                    reformatEditor();
                    return true;
                }},
            ]),
            EditorView.lineWrapping,
            heightTheme,
            EditorView.updateListener.of((update) => {
                if (update.docChanged) {
                    textarea.value = update.state.doc.toString();
                }
            }),
        ],
    });

    const container = document.createElement('div');
    container.className = 'codemirror-sql-editor';
    container.style.width = '100%';
    textarea.parentNode.insertBefore(container, textarea);
    textarea.style.display = 'none';

    const view = new EditorView({state, parent: container});

    initTimestampHover(view);

    // Let other modules (e.g. the Test-query "click-to-wrap" date buttons) replace the whole editor
    // contents. Setting textarea.value alone would not reach CodeMirror, so we dispatch a real doc
    // change and let the updateListener mirror it back to the hidden textarea.
    textarea.rsReplaceContent = (newSql) => {
        view.dispatch({changes: {from: 0, to: view.state.doc.length, insert: newSql}});
        serverValidated = false;
        errorBanner.style.display = 'none';
        warningBanner.style.display = 'none';
    };

    const errorBanner = document.createElement('div');
    errorBanner.className = 'alert alert-danger mt-1';
    errorBanner.style.display = 'none';
    container.after(errorBanner);

    const warningBanner = document.createElement('div');
    warningBanner.className = 'alert alert-warning mt-1';
    warningBanner.style.display = 'none';
    errorBanner.after(warningBanner);

    const toolbar = document.createElement('div');
    toolbar.className = 'd-flex justify-content-end mb-1';
    const formatBtn = document.createElement('button');
    formatBtn.type = 'button';
    formatBtn.className = 'btn btn-outline-secondary btn-sm';
    formatBtn.textContent = 'Format SQL';
    formatBtn.title = 'Shift+Ctrl+F';
    getString('formatsql', 'report_sql')
        .then(s => {
            formatBtn.textContent = s;
            return s;
        })
        .catch(() => null);
    getString('formatsqltooltip', 'report_sql')
        .then(s => {
            formatBtn.title = s;
            return s;
        })
        .catch(() => null);
    toolbar.appendChild(formatBtn);
    container.before(toolbar);

    /**
     * Reformat the editor contents to standard SQL layout (clauses on their own
     * lines, indented lists, upper-case keywords). Moodle {table} placeholders
     * are declared as a custom parameter type so the parser accepts them.
     */
    function reformatEditor() {
        const current = view.state.doc.toString();
        if (!current.trim()) {
            return;
        }
        try {
            const formatted = formatSql(current, {
                language: 'mysql',
                keywordCase: 'upper',
                functionCase: 'upper',
                dataTypeCase: 'upper',
                tabWidth: 4,
                paramTypes: {custom: [
                    {regex: '\\{[A-Za-z0-9_]+\\}'},
                    // Reserved %%TOKEN%% placeholders (%%WWWROOT%%, %%COURSEID%%,
                    // %%COURSECONTEXT%%, %%NOW%%, %%CONTEXT_*%%, %%TIMESTAMP(expr[, format])%%). Treat each as a
                    // single opaque parameter so the formatter does not split, space, or upper-case
                    // their contents. Inner text never contains % so [^%]+ matches the whole token.
                    {regex: '%%[^%]+%%'},
                ]},
            });
            view.dispatch({changes: {from: 0, to: view.state.doc.length, insert: formatted}});
            errorBanner.style.display = 'none';
        } catch (e) {
            // Parse failure (incomplete SQL etc.) — leave the text untouched.
            errorBanner.className = 'alert alert-danger mt-1';
            errorBanner.textContent = 'Could not format SQL: ' + e.message;
            errorBanner.style.display = '';
        }
    }
    formatBtn.addEventListener('click', reformatEditor);

    const denyKeywords = [
        'INSERT', 'UPDATE', 'DELETE', 'DROP', 'TRUNCATE', 'ALTER', 'CREATE',
        'GRANT', 'REVOKE', 'REPLACE', 'CALL', 'LOAD', 'HANDLER', 'LOCK',
        'UNLOCK', 'RENAME', 'COMMIT', 'ROLLBACK', 'SAVEPOINT', 'USE',
        'EXEC', 'EXECUTE', 'INTO', 'OUTFILE', 'COPY', 'VACUUM', 'MERGE',
    ];

    /**
     * Remove SQL comments and string literals so keyword scanning cannot be fooled
     * by embedded denylist words inside quoted values or comment blocks.
     *
     * @param {string} sql - Raw SQL text.
     * @returns {string} SQL with comments replaced by spaces and string contents blanked.
     */
    function stripCommentsAndStrings(sql) {
        sql = sql.replace(/\/\*[\s\S]*?\*\//g, ' ');
        sql = sql.replace(/--[^\n]*/g, ' ');
        sql = sql.replace(/#[^\n]*/g, ' ');
        sql = sql.replace(/'(?:[^']|'')*'/g, "''");
        sql = sql.replace(/"(?:[^"]|"")*"/g, '""');
        return sql;
    }

    /**
     * Client-side static SQL validation — mirrors the server-side denylist in
     * classes/local/sql/validator.php so obvious errors surface instantly without
     * a round-trip.
     *
     * @param {string} sql - SQL text from the editor.
     * @returns {string|null} Error message string, or null if validation passes.
     */
    function validateSql(sql) {
        sql = sql.trim();
        if (!sql) {
            return 'SQL is required.';
        }
        const stripped = stripCommentsAndStrings(sql);
        if (stripped.replace(/;[\s]*$/, '').includes(';')) {
            return 'Only a single statement is allowed (no semicolons).';
        }
        if (!/^\s*(SELECT|WITH)\b/i.test(stripped)) {
            return 'Query must start with SELECT or WITH.';
        }
        // A ? inside a string literal (e.g. a view.php?id= URL) is read as a bound parameter by
        // Moodle's DB layer. Surface it here so the convert link appears without a server round-trip.
        if (hasQuestionmarkInString(sql)) {
            return questionmarkMsg;
        }
        for (const kw of denyKeywords) {
            // REPLACE(...) the string function is legitimate; only block the REPLACE statement.
            const pattern = kw === 'REPLACE' ? '\\bREPLACE\\b(?!\\s*\\()' : '\\b' + kw + '\\b';
            if (new RegExp(pattern, 'i').test(stripped)) {
                return 'Keyword not allowed: ' + kw + '.';
            }
        }
        return null;
    }

    // Convert-link label and the ?-rejection message, preloaded so the error banner can be built
    // synchronously inside the submit handler. English fallbacks cover the pre-load race.
    let convertLabel = 'Convert ? to CHAR(63) automatically';
    let aliasSpacesLabel = 'Replace spaces in the column alias with underscores automatically';
    let compiledSqlLabel = 'Compiled SQL (what actually ran)';
    let questionmarkMsg = 'SQL contains a ? character, which the database layer treats as a query'
        + ' parameter placeholder.';
    getStrings([
        {key: 'convertquestionmark', component: 'report_sql'},
        {key: 'convertaliasspaces', component: 'report_sql'},
        {key: 'compiledsql', component: 'report_sql'},
        {key: 'errquestionmark', component: 'report_sql'},
    ]).then(([convert, aliasspaces, compiled, err]) => {
        convertLabel = convert;
        aliasSpacesLabel = aliasspaces;
        compiledSqlLabel = compiled;
        questionmarkMsg = err;
        return null;
    }).catch(() => null);


    /**
     * Show a danger message in the error banner. When the SQL carries a fixable pattern — a ? inside
     * a string literal, or a column alias containing spaces — append a "…automatically" link that
     * rewrites it in place via the editor.
     *
     * @param {string} msg - The error message.
     * @param {string} sql - The SQL that produced it (drives the optional convert link).
     * @param {string} [compiled] - The compiled SQL the DB actually ran (placeholders/braces
 *     resolved). When present it is shown in a collapsible block (open by default) so the error's
 *     line numbers, which refer to the compiled text and not the author's source, can be lined up.
     */
    function showError(msg, sql, compiled) {
        errorBanner.className = 'alert alert-danger mt-1';
        errorBanner.textContent = msg;
        const addFixLink = (label, rewrite) => {
            errorBanner.appendChild(document.createElement('br'));
            const link = document.createElement('button');
            link.type = 'button';
            link.className = 'btn btn-link p-0 align-baseline';
            link.textContent = label;
            link.addEventListener('click', () => {
                // The rsReplaceContent call pushes the rewrite into CodeMirror, clears serverValidated
                // and hides the banner, so the next submit re-checks the converted SQL.
                textarea.rsReplaceContent(rewrite(textarea.value));
            });
            errorBanner.appendChild(link);
        };
        if (sql && hasQuestionmarkInString(sql)) {
            addFixLink(convertLabel, rewriteQuestionmarks);
        }
        if (sql && hasAliasWithSpaces(sql)) {
            addFixLink(aliasSpacesLabel, rewriteAliasSpaces);
        }
        if (compiled && compiled.trim() && compiled.trim() !== sql.trim()) {
            errorBanner.appendChild(compiledSqlDetails(compiled, compiledSqlLabel));
        }
        errorBanner.style.display = '';
    }

    /**
     * If the AI generation field is present, pre-fill it with the broken SQL and
     * a description of the error so the user can ask the AI to fix it.
     *
     * @param {string} sql - The SQL that failed validation.
     * @param {string} errorMsg - The validation error message.
     */
    function feedAiField(sql, errorMsg) {
        const aiField = document.getElementById('rs-ai-question');
        if (!aiField) {
            return;
        }
        aiField.value = 'Fix this SQL error: ' + errorMsg + '\n\n' + sql;
        aiField.scrollIntoView({behavior: 'smooth', block: 'nearest'});
    }

    // Submit the AI generate form when Enter is pressed in the question field (with text in it).
    // Shift+Enter still inserts a newline for multi-line questions.
    const aiQuestion = document.getElementById('rs-ai-question');
    if (aiQuestion && aiQuestion.form) {
        aiQuestion.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey && aiQuestion.value.trim() !== '') {
                e.preventDefault();
                aiQuestion.form.requestSubmit();
            }
        });

        // Generation is a slow server round-trip to the LLM. Show a spinner and disable the
        // button on submit so the user sees that something is happening (covers click + Enter).
        aiQuestion.form.addEventListener('submit', () => {
            // The AI box is a separate form from the main mform, so the SQL field would not post
            // with it. Copy the live editor value into the AI form's hidden input so a prompt that
            // refers to the existing SQL ("add a column to this") reaches the server.
            const sqlField = document.getElementById('rs-ai-currentsql');
            if (sqlField) {
                sqlField.value = textarea.value;
            }
            const btn = document.getElementById('rs-ai-generate');
            if (btn) {
                const label = btn.dataset.generating || btn.textContent;
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"'
                    + ' aria-hidden="true"></span>' + label;
            }
        });
    }

    let serverValidated = false;

    if (textarea.form) {
        textarea.form.addEventListener('submit', (e) => {
            // Cancel must always return to the index page, even with empty/invalid SQL.
            if (e.submitter && e.submitter.name === 'cancel') {
                return;
            }

            textarea.value = view.state.doc.toString();

            const staticErr = validateSql(textarea.value);
            if (staticErr) {
                e.preventDefault();
                showError(staticErr, textarea.value);
                container.scrollIntoView({behavior: 'smooth', block: 'nearest'});
                return;
            }

            if (serverValidated) {
                errorBanner.style.display = 'none';
                return;
            }

            // Preserve the clicked submit button so the programmatic resubmit below carries its
            // name/value (e.g. "saveandpublish"). requestSubmit() without a submitter drops it,
            // which would silently downgrade "Save and publish" to a plain draft save.
            const submitter = e.submitter;

            e.preventDefault();
            errorBanner.className = 'alert alert-info mt-1';
            errorBanner.textContent = 'Checking query…';
            errorBanner.style.display = '';

            const payload = JSON.stringify([{
                index: 0,
                methodname: 'report_sql_validate_sql',
                args: {sql: textarea.value},
            }]);

            fetch(M.cfg.wwwroot + '/lib/ajax/service.php?sesskey=' + M.cfg.sesskey, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: payload,
            })
            .then(r => r.json())
            .then(data => {
                const result = Array.isArray(data) ? data[0] : data;
                if (result.error) {
                    const msg = result.error.message || JSON.stringify(result.error);
                    showError(msg, textarea.value);
                    feedAiField(textarea.value, msg);
                } else if (result.data && !result.data.ok) {
                    const msg = result.data.error || 'Query validation failed.';
                    showError(msg, textarea.value, result.data.compiledsql);
                    feedAiField(textarea.value, msg);
                } else {
                    serverValidated = true;
                    errorBanner.style.display = 'none';
                    const warns = result.data && result.data.warnings;
                    if (warns && warns.length) {
                        warningBanner.textContent = warns.join('\n');
                        warningBanner.style.display = '';
                    } else {
                        warningBanner.style.display = 'none';
                    }
                    textarea.form.requestSubmit(submitter);
                }
                return null;
            })
            .catch(() => {
                serverValidated = true;
                textarea.form.requestSubmit(submitter);
            });
        });

        view.dom.addEventListener('keydown', () => {
            serverValidated = false;
            errorBanner.style.display = 'none';
            warningBanner.style.display = 'none';
        });
    }
};

/**
 * Build a <details> block (open by default) showing the compiled SQL in a scrollable <pre>.
 * The DB error
 * line/column numbers refer to this compiled text (placeholders and {table} braces resolved), not to
 * the author's source, so surfacing it lets them line the numbers up. Line breaks are preserved and
 * the text is set via textContent (no HTML injection).
 *
 * @param {string} compiled - The compiled SQL string.
 * @param {string} label - Translated summary label.
 * @returns {HTMLElement} A <details> element.
 */
const compiledSqlDetails = (compiled, label) => {
    const details = document.createElement('details');
    details.className = 'mt-2';
    details.open = true;
    const summary = document.createElement('summary');
    summary.textContent = label;
    details.appendChild(summary);
    const pre = document.createElement('pre');
    pre.className = 'mt-1 mb-0 p-2 bg-light border rounded';
    pre.style.cssText = 'max-height:16rem;overflow:auto;white-space:pre-wrap;';
    pre.textContent = compiled;
    details.appendChild(pre);
    return details;
};

/** Default display format applied when a %%TIMESTAMP()%% token gives no format. */
const TIMESTAMP_DEFAULT_FORMAT = 'dd-mmm-yyyy';

/** Matches a whole %%TIMESTAMP(...)%% token — the inner text never contains a % so [^%]* is safe. */
const TIMESTAMP_TOKEN_RE = /%%TIMESTAMP[^%]*%%/gi;

/** Neutral display-format tokens accepted in %%TIMESTAMP(expr, format)%% (see view::strftime_format). */
const TIMESTAMP_FORMAT_TOKENS = [
    'dd', 'mm', 'mmm', 'mmmm', 'mon', 'month', 'yyyy', 'yy', 'ddd', 'dddd', 'hh', 'mi', 'ss',
];

/** Cached promise of the completion options (label + i18n gloss) for the format tokens. */
let timestampFormatOptions = null;

/**
 * Lazily build (once) the autocomplete option list for the format tokens, annotating each with its
 * translated gloss (the same tsfmt* strings the hover tooltip uses).
 *
 * @returns {Promise<Array<Object>>}
 */
const loadTimestampFormatOptions = () => {
    if (!timestampFormatOptions) {
        timestampFormatOptions = getStrings(
            TIMESTAMP_FORMAT_TOKENS.map(t => ({key: 'tsfmt' + t, component: 'report_sql'}))
        )
            .then(glosses => TIMESTAMP_FORMAT_TOKENS.map((t, i) => ({
                label: t,
                type: 'keyword',
                detail: glosses[i],
            })))
            .catch(() => {
                // Reset so a later attempt can retry, but still offer bare labels this time.
                timestampFormatOptions = null;
                return TIMESTAMP_FORMAT_TOKENS.map(t => ({label: t, type: 'keyword'}));
            });
    }
    return timestampFormatOptions;
};

/**
 * CompletionSource offering the neutral date-format tokens (dd, mm, yyyy, …) while the cursor sits
 * inside the format argument of an unclosed %%TIMESTAMP(expr, …)%% token — e.g. typing `dd` inside
 * `%%TIMESTAMP(u.timecreated, dd` completes to a format token.
 *
 * @param {CompletionContext} context
 * @returns {Promise<CompletionResult>|null}
 */
const timestampFormatCompletionSource = (context) => {
    const before = context.state.doc.sliceString(0, context.pos);
    const open = before.lastIndexOf('%%TIMESTAMP(');
    if (open === -1) {
        return null;
    }
    // A '%%' between the opening token and the cursor means the token is already closed — not inside.
    if (before.indexOf('%%', open + 2) !== -1) {
        return null;
    }
    // The format is the second argument, so only fire once a comma separates it from the expression.
    if (!before.slice(open).includes(',')) {
        return null;
    }
    const word = context.matchBefore(/[a-zA-Z]*/);
    if (!word || (word.from === word.to && !context.explicit)) {
        return null;
    }
    return loadTimestampFormatOptions().then(options => ({
        from: word.from,
        options,
        validFor: /^[a-zA-Z]*$/,
    }));
};

/**
 * The reserved %%TOKEN%% placeholders offered by the %% autocomplete. Mirrors the server-side
 * source of truth (validator::is_supported_token / view::context_level_tokens) so the editor never
 * suggests a token publish would reject. `caret` (parameterised tokens only) is the offset from the
 * token start at which to drop the cursor — inside the parentheses — after insertion.
 */
const TOKENS = [
    {label: '%%WWWROOT%%', key: 'tokenhintwwwroot'},
    {label: '%%COURSEID%%', key: 'tokenhintcourseid'},
    {label: '%%COURSECONTEXT%%', key: 'tokenhintcoursecontext'},
    {label: '%%NOW%%', key: 'tokenhintnow'},
    {label: '%%CONTEXT_SYSTEM%%', key: 'tokenhintcontextsystem'},
    {label: '%%CONTEXT_USER%%', key: 'tokenhintcontextuser'},
    {label: '%%CONTEXT_COURSECAT%%', key: 'tokenhintcontextcoursecat'},
    {label: '%%CONTEXT_COURSE%%', key: 'tokenhintcontextcourse'},
    {label: '%%CONTEXT_MODULE%%', key: 'tokenhintcontextmodule'},
    {label: '%%CONTEXT_BLOCK%%', key: 'tokenhintcontextblock'},
    {label: '%%TIMESTAMP()%%', key: 'tokenhinttimestamp', caret: 12},
    {label: '%%EPOCH()%%', key: 'tokenhintepoch', caret: 8},
    {label: '%%CASE()%%', key: 'tokenhintcase', caret: 7},
];

/**
 * Build a single CodeMirror completion option for a token. Parameterised tokens (those with a
 * `caret`) get a custom apply that leaves the cursor between the parentheses so the author types the
 * expression straight away.
 *
 * @param {Object} token - One TOKENS entry.
 * @param {string} gloss - Translated one-line description (may be empty on lang failure).
 * @returns {Object} A CodeMirror Completion.
 */
const makeTokenOption = (token, gloss) => {
    const option = {label: token.label, type: 'constant'};
    if (gloss) {
        option.detail = gloss;
    }
    if (token.caret) {
        option.apply = (view, completion, from, to) => {
            view.dispatch({
                changes: {from, to, insert: token.label},
                selection: {anchor: from + token.caret},
            });
        };
    }
    return option;
};

/** Cached promise of the completion options (label + i18n gloss) for the %%TOKEN%% placeholders. */
let tokenOptions = null;

/**
 * Lazily build (once) the autocomplete option list for the %%TOKEN%% placeholders, annotating each
 * with its translated gloss. On lang failure it resets the cache (so a later attempt retries) and
 * still offers bare labels this time.
 *
 * @returns {Promise<Array<Object>>}
 */
const loadTokenOptions = () => {
    if (!tokenOptions) {
        tokenOptions = getStrings(TOKENS.map(t => ({key: t.key, component: 'report_sql'})))
            .then(glosses => TOKENS.map((t, i) => makeTokenOption(t, glosses[i])))
            .catch(() => {
                tokenOptions = null;
                return TOKENS.map(t => makeTokenOption(t, ''));
            });
    }
    return tokenOptions;
};

/**
 * CompletionSource offering the reserved %%TOKEN%% placeholders once the cursor sits just after a
 * `%%` opener — typing `%%` offers the whole list, `%%C` narrows to CASE / COURSEID / COURSECONTEXT
 * / CONTEXT_*, `%%T` to TIMESTAMP, and so on. Does not fire inside an already-closed token or in the
 * format argument of %%TIMESTAMP(…)%% (a comma/space/dot breaks the match back to the opener).
 *
 * @param {CompletionContext} context
 * @returns {Promise<CompletionResult>|null}
 */
const tokenCompletionSource = (context) => {
    const before = context.matchBefore(/%%[A-Za-z_()]*/);
    if (!before || before.from === before.to) {
        return null;
    }
    return loadTokenOptions().then(options => ({
        from: before.from,
        options,
        validFor: /^%%[A-Za-z_()]*$/,
    }));
};

/**
 * Attach a hover tooltip that explains the optional display-format argument whenever the pointer
 * is over a %%TIMESTAMP(...)%% token.
 *
 * The prebuilt codemirror-lazy bundle does not export CodeMirror's own hoverTooltip helper, so we
 * drive a lightweight tooltip from a mousemove listener + view.posAtCoords instead.
 *
 * @param {EditorView} view - The CodeMirror editor view.
 */
const initTimestampHover = (view) => {
    const tooltip = document.createElement('div');
    tooltip.className = 'rs-timestamp-hover card shadow-sm p-2';
    tooltip.style.cssText = 'position:fixed;z-index:1080;max-width:24rem;display:none;font-size:.85rem;';
    document.body.appendChild(tooltip);

    // Build the tooltip body once, lazily, from lang strings the first time it is shown.
    let contentReady = false;
    const tokens = TIMESTAMP_FORMAT_TOKENS;
    const populate = () => {
        if (contentReady) {
            return;
        }
        contentReady = true;
        const requests = [
            {key: 'tsfmthelptitle', component: 'report_sql'},
            {key: 'tsfmthelpintro', component: 'report_sql', param: TIMESTAMP_DEFAULT_FORMAT},
            {key: 'tsfmthelptokens', component: 'report_sql'},
            ...tokens.map(t => ({key: 'tsfmt' + t, component: 'report_sql'})),
        ];
        getStrings(requests)
            .then(([title, intro, tokensLabel, ...glosses]) => {
                const rows = tokens.map((t, i) =>
                    `<tr><th class="pe-2 text-nowrap"><code>${t}</code></th><td>${glosses[i]}</td></tr>`
                ).join('');
                tooltip.innerHTML =
                    `<div class="fw-bold mb-1">${title}</div>` +
                    `<div class="mb-2">${intro}</div>` +
                    `<div class="fw-bold mb-1">${tokensLabel}</div>` +
                    `<table class="mb-0"><tbody>${rows}</tbody></table>`;
                return null;
            })
            .catch(() => {
                contentReady = false;
            });
    };

    // Find the %%TIMESTAMP(...)%% token, if any, spanning document position pos.
    const tokenAt = (text, pos) => {
        TIMESTAMP_TOKEN_RE.lastIndex = 0;
        let m;
        while ((m = TIMESTAMP_TOKEN_RE.exec(text)) !== null) {
            if (pos >= m.index && pos <= m.index + m[0].length) {
                return true;
            }
        }
        return false;
    };

    const hide = () => {
        tooltip.style.display = 'none';
    };

    view.dom.addEventListener('mousemove', (e) => {
        const pos = view.posAtCoords({x: e.clientX, y: e.clientY}, false);
        if (pos === null || !tokenAt(view.state.doc.toString(), pos)) {
            hide();
            return;
        }
        populate();
        // Offset from the pointer and clamp to the viewport so the tooltip stays on-screen.
        const pad = 12;
        tooltip.style.display = '';
        const rect = tooltip.getBoundingClientRect();
        let left = e.clientX + pad;
        let top = e.clientY + pad;
        if (left + rect.width > window.innerWidth - pad) {
            left = Math.max(pad, e.clientX - pad - rect.width);
        }
        if (top + rect.height > window.innerHeight - pad) {
            top = Math.max(pad, e.clientY - pad - rect.height);
        }
        tooltip.style.left = left + 'px';
        tooltip.style.top = top + 'px';
    });
    view.dom.addEventListener('mouseleave', hide);
    view.scrollDOM.addEventListener('scroll', hide);
};
