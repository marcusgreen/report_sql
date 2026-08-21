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

/**
 * True when the SQL has a literal ? inside a quoted string literal (single or double quoted —
 * MySQL treats both as strings) — the case the auto-convert can fix (e.g. a URL such as
 * view.php?id=). A bare ? elsewhere is a real bound-parameter marker and is left for the server
 * to reject.
 *
 * @param {string} sql
 * @returns {boolean}
 */
export const hasQuestionmarkInString = (sql) =>
    /'(?:[^']|'')*\?(?:[^']|'')*'/.test(sql) || /"(?:[^"]|"")*\?(?:[^"]|"")*"/.test(sql);

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
    sql.replace(/'(?:[^']|'')*'|"(?:[^"]|"")*"/g, (literal) => {
        if (!literal.includes('?')) {
            return literal;
        }
        const parts = literal.slice(1, -1).split('?');
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
