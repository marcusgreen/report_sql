<?php
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

declare(strict_types=1);

namespace report_sql;

use report_sql\external\get_embed;
use report_sql\local\query;

/**
 * Covers the inline-embed external function that backs filter_reportsources' [[reportsource:ID]] marker.
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \report_sql\external\get_embed
 */
final class get_embed_test extends \advanced_testcase {
    /**
     * Build a minimal valid form-data object for query::save().
     *
     * @param array $extra Extra/override fields.
     * @return \stdClass
     */
    private function formdata(array $extra = []): \stdClass {
        return (object) array_merge([
            'name'     => 'Embed view',
            'querysql' => 'SELECT id FROM {user}',
            'courseid' => 0,
            'visible'  => 1,
        ], $extra);
    }

    /**
     * Drop VIEWs left behind by publish() before the framework's table-oriented reset runs.
     */
    protected function tearDown(): void {
        global $DB;
        $prefix = $DB->get_prefix() . 'report_sql_v_';
        $views = $DB->get_records_sql(
            "SELECT table_name FROM information_schema.views WHERE table_name LIKE ?",
            [$prefix . '%']
        );
        foreach ($views as $view) {
            $name = $view->table_name ?? reset($view);
            $DB->execute('DROP VIEW IF EXISTS ' . $name);
        }
        parent::tearDown();
    }

    /**
     * Save + publish a query as the admin and return its RB report id.
     *
     * @param array $extra Overrides for the form data.
     * @return array{0:query,1:int} [query, reportid]
     */
    private function publish_as_admin(array $extra = []): array {
        $this->setAdminUser();
        $id = query::save($this->formdata($extra));
        query::get($id)->publish();
        return [query::get($id), (int) query::get($id)->reportid()];
    }

    public function test_unknown_report_returns_empty_html(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        // No existence oracle: an unknown report id is indistinguishable from a no-access hit.
        $result = get_embed::execute(987654);
        $this->assertSame('', $result['html']);
    }

    public function test_published_report_renders_for_privileged_viewer(): void {
        $this->resetAfterTest();
        [, $reportid] = $this->publish_as_admin(['querysql' => 'SELECT id, firstname FROM {user}']);

        // Admin holds viewall, so the access gate passes and rows render.
        $result = get_embed::execute($reportid);
        $this->assertStringContainsString('<table', $result['html']);
    }

    public function test_no_access_viewer_gets_empty_html(): void {
        $this->resetAfterTest();

        // Published hidden (visible=0) → auto audience is "none", so only owner/viewall can open it.
        [, $reportid] = $this->publish_as_admin(['visible' => 0]);

        // A plain user with none of the plugin caps and not in any audience is refused — empty, no oracle.
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $result = get_embed::execute($reportid);
        $this->assertSame('', $result['html']);
    }

    public function test_invalid_mode_is_coerced_and_still_renders(): void {
        $this->resetAfterTest();
        [, $reportid] = $this->publish_as_admin(['querysql' => 'SELECT id, firstname FROM {user}']);

        // An out-of-range mode falls back to auto rather than erroring.
        $result = get_embed::execute($reportid, 'bogus');
        $this->assertStringContainsString('<table', $result['html']);
    }
}
