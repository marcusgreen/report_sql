# SQL Report

[![Moodle Plugin CI](https://github.com/marcusgreen/report_sql/actions/workflows/ci.yml/badge.svg)](https://github.com/marcusgreen/report_sql/actions/workflows/ci.yml)
[![GitHub Release](https://img.shields.io/github/release/marcusgreen/report_sql.svg)](https://github.com/marcusgreen/report_sql/releases)
[![Moodle Support](https://img.shields.io/badge/Moodle-%3E%3D%205.0-blue)](https://moodle.org/plugins/report_sql)

*Reportbuilder ... the Sequel (Thanks to Adam Jenkins)*

**SQL Report** lets you turn a SQL query into a fully configurable Moodle report — no programming required. Write your query, click **Publish**, and the plugin creates a Report Builder report your colleagues can run, filter, chart, schedule, and export.

📖 **[Read the full user documentation →](docs/userdocs.md)**

> ⚠️ Reporting tools can expose sensitive data. Always test who can see a report before sharing it.

## 📖 Documentation

The [full user documentation](docs/userdocs.md) covers the edit form, writing SQL, placeholders, the SQL editor, AI generation, audiences, charts, scheduled emails, import/export, troubleshooting, and more.

## What it does

- Write a `SELECT` query (or generate one with AI) and publish it as a Report Builder report.
- Choose columns, filters, sorting, and charts through the standard Report Builder UI.
- Control who can open each report via audiences (course participants, roles, cohorts, all users).
- Schedule reports to be emailed on a recurring basis.
- Cross-database placeholders (`%%TIMESTAMP%%`, `%%NOW%%`, `%%COURSEID%%`, `%%CASE%%`, …) keep queries portable across MySQL/MariaDB and PostgreSQL.
- Import and export report views as portable JSON.

## Requirements

- Moodle 5.0 – 5.2
- A database account with permission to create and drop database views.

Check the privilege from **Site admin → Reports → SQL Report → Run database view privilege test**.

## Installation

1. Copy the `sql` folder into `<moodleroot>/report/` (so the plugin lives at `<moodleroot>/report/sql/`).
2. Go to **Site admin → Notifications** to complete the install.
3. Run the privilege test above to confirm everything works.

## Who can do what

| Role | What they can do |
|---|---|
| **Manager** (site-wide) | Write queries, publish/unpublish reports, see all reports |
| **Authenticated user** | Run published reports they have been given access to |
| **Teacher** (within a course) | Run reports published for their course |

Permissions are assigned at **Site admin → Users → Permissions → Define roles**. See the [user documentation](docs/userdocs.md#who-can-do-what-capabilities) for the full capability model.

## License

GNU GPL v3 or later — see [COPYING](https://www.gnu.org/licenses/gpl-3.0.html).
