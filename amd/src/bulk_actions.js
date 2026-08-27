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
 * Wires the actionable report's select-all checkboxes to the bulk-action bar: collects the checked
 * rows' subject ids into the form's hidden `subjectids` field and enables Apply only when a row is
 * selected. Adapted from core_admin/bulk_user_actions.
 *
 * @module     report_sql/bulk_actions
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import * as tableEvents from 'core_table/local/dynamic/events';

const SELECTORS = {
    checkedTargets: '[data-togglegroup="report-select-all"][data-toggle="target"]:checked',
    anyCheckbox: 'input[type="checkbox"][data-togglegroup="report-select-all"]',
};

/**
 * Initialise the bulk-action wiring.
 *
 * @param {object} args
 * @param {string} args.reportwrapperid Id of the element wrapping the system report table.
 * @param {string} args.formid Id of the bulk-action form.
 */
export const init = ({reportwrapperid, formid}) => {
    const report = document.getElementById(reportwrapperid);
    const form = document.getElementById(formid);
    if (!report || !form) {
        return;
    }

    const hidden = form.querySelector('[name="subjectids"]');
    const apply = form.querySelector('[name="apply"]');

    const refresh = () => {
        const ids = [...report.querySelectorAll(SELECTORS.checkedTargets)].map((cb) => cb.value);
        if (hidden) {
            hidden.value = ids.join(',');
        }
        if (apply) {
            apply.disabled = ids.length === 0;
        }
    };

    // Any checkbox toggle (a row or the master toggler) in the report.
    report.addEventListener('change', (e) => {
        if (e.target.matches(SELECTORS.anyCheckbox)) {
            refresh();
        }
    });

    // Report Builder re-renders the table on paging / sort / filter — recompute against the new rows.
    document.addEventListener(tableEvents.tableContentRefreshed, refresh);

    refresh();
};
