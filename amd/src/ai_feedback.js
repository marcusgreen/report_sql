/**
 * Watches for SQL validation errors and pre-fills the AI question box.
 *
 * @module     report_sql/ai_feedback
 * @copyright  2026 Marcus Green
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
export const init = () => {
    const aiField = document.getElementById('rs-ai-question');
    if (!aiField) {
        return;
    }

    // The SQL editor hides the original textarea and syncs its value on every change.
    // Watch for the alert-danger banner becoming visible via a style mutation.
    const observer = new MutationObserver((mutations) => {
        for (const mutation of mutations) {
            if (mutation.type !== 'attributes') {
                continue;
            }
            const el = mutation.target;
            if (!el.classList.contains('alert-danger')) {
                continue;
            }
            if (el.style.display === 'none' || !el.textContent.trim()) {
                continue;
            }
            const sqlTextarea = document.getElementById('id_querysql');
            if (!sqlTextarea || !sqlTextarea.value.trim()) {
                continue;
            }
            aiField.value = 'Fix this SQL error: ' + el.textContent.trim()
                + '\n\n' + sqlTextarea.value.trim();
            aiField.scrollIntoView({behavior: 'smooth', block: 'nearest'});
        }
    });

    observer.observe(document.body, {
        attributes: true,
        attributeFilter: ['style', 'class'],
        subtree: true,
    });
};
