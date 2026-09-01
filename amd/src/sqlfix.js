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
 * Client-side detectors + rewriters for the two auto-fixable SQL mistakes surfaced by both the
 * submit-time validation (editor.js) and the Test-query feedback (test.js): a ? inside a string
 * literal, and a column alias containing spaces. Shared so the two entry points never drift.
 *
 * @module     report_sql/sqlfix
 * @copyright  2026 Marcus Green
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// A %%...%% token span. A ? inside a token — e.g. the path of
// %%LINK(u.id, '/user/view.php?id={}')%% — never reaches the DML layer: view::resolve_placeholders
// rewrites the token away before the SQL runs, so the server-side validator exempts token spans and
// these detectors must too. Mirrors the mask in validator::validate().
const TOKEN_RE = /%%[^%\n]*%%/g;

// A quoted string literal, single or double quoted (MySQL treats both as strings).
const STRING_RE = /'(?:[^']|'')*'|"(?:[^"]|"")*"/g;

/**
 * True when the SQL has a literal ? inside a quoted string literal (single or double quoted —
 * MySQL treats both as strings) — the case the auto-convert can fix (e.g. a URL such as
 * view.php?id=). A ? inside a %%...%% token is exempt (the token is resolved away before the SQL
 * runs). A bare ? elsewhere is a real bound-parameter marker and is left for the server to reject.
 *
 * @param {string} sql
 * @returns {boolean}
 */
export const hasQuestionmarkInString = (sql) => {
    const masked = sql.replace(TOKEN_RE, '');
    return /'(?:[^']|'')*\?(?:[^']|'')*'/.test(masked) || /"(?:[^"]|"")*\?(?:[^"]|"")*"/.test(masked);
};

/**
 * Rewrite each quoted string literal (single or double quoted) containing a ? into a
 * CONCAT(..., CHAR(63), ...) chain, mirroring import_helper::rewrite_questionmarks so links keep
 * working without tripping Moodle's positional-parameter handling. Only ? inside string literals
 * is touched; the rebuilt chain uses single-quoted parts regardless of the original quote.
 *
 * @param {string} sql
 * @returns {string}
 */
export const rewriteQuestionmarks = (sql) =>
    sql.replace(new RegExp(TOKEN_RE.source + '|' + STRING_RE.source, 'g'), (match) => {
        // Token spans are matched first (and returned untouched) so a literal inside a token —
        // the path of %%LINK(...)%% — is never rebuilt into a CONCAT the token parser cannot read.
        if (match.startsWith('%%') || !match.includes('?')) {
            return match;
        }
        const parts = match.slice(1, -1).split('?');
        return 'CONCAT(' + parts.map(p => "'" + p + "'").join(', CHAR(63), ') + ')';
    });

// A quoted alias containing whitespace, introduced by AS — the case the server rejects with
// erraliasspaces (a VIEW column name cannot contain spaces for Report Builder to reference it).
// Only AS-introduced quoted names are matched so string literals never false-positive.
const ALIAS_SPACE_RE = /\bAS\s+(["'`])([^"'`]*\s[^"'`]*)\1/gi;

/**
 * True when the SQL declares a column alias containing whitespace (e.g. AS "enrollment plugins").
 *
 * @param {string} sql
 * @returns {boolean}
 */
export const hasAliasWithSpaces = (sql) => {
    ALIAS_SPACE_RE.lastIndex = 0;
    return ALIAS_SPACE_RE.test(sql);
};

/**
 * Rewrite each AS-introduced quoted alias containing whitespace into an unquoted underscore alias,
 * mirroring the erraliasspaces guidance (SELECT firstname AS first_name). The quotes are dropped
 * because an underscore identifier needs none.
 *
 * @param {string} sql
 * @returns {string}
 */
export const rewriteAliasSpaces = (sql) =>
    sql.replace(ALIAS_SPACE_RE, (full, quote, name) => 'AS ' + name.trim().replace(/\s+/g, '_'));
