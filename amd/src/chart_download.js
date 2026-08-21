/**
 * PNG download for rendered Chart.js canvas.
 *
 * @module     report_sql/chart_download
 * @copyright  2026 Marcus Green
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
 * Wire the PNG download button.
 *
 * @param {String} filename base name for the downloaded PNG
 */
export const init = (filename) => {
    const btn = document.getElementById('report-sql-download-png');
    if (!btn) {
        return;
    }
    btn.addEventListener('click', () => {
        const canvas = document.querySelector('.chart-output canvas, canvas');
        if (!canvas) {
            return;
        }
        const link = document.createElement('a');
        link.download = filename + '.png';
        link.href = canvas.toDataURL('image/png');
        link.click();
    });
};
