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
 * Sample browser — Select all / Select none for the bundled-sample import picker.
 *
 * Only enabled checkboxes are toggled; already-present samples stay disabled.
 *
 * @module     report_sql/samples
 * @copyright  2026 Marcus Green
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Wire the Select all / Select none buttons to the sample checkboxes.
 */
export const init = () => {
    const form = document.querySelector('[data-region="samples-form"]');
    if (!form) {
        return;
    }

    const setAll = (checked) => {
        form.querySelectorAll('[data-region="sample-checkbox"]').forEach((box) => {
            if (!box.disabled) {
                box.checked = checked;
            }
        });
    };

    form.addEventListener('click', (e) => {
        const trigger = e.target.closest('[data-action]');
        if (!trigger) {
            return;
        }
        if (trigger.dataset.action === 'select-all') {
            e.preventDefault();
            setAll(true);
        } else if (trigger.dataset.action === 'select-none') {
            e.preventDefault();
            setAll(false);
        }
    });
};
