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

use report_sql\local\query;
use report_sql\output\embed_renderer;

/**
 * Covers the shared inline render path used by the block and the [[reportsource:ID]] filter embed.
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \report_sql\output\embed_renderer
 */
final class embed_renderer_test extends \advanced_testcase {
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
     * Publish a query from the given SQL and return the query object.
     *
     * @param string $sql
     * @return query
     */
    private function published(string $sql): query {
        $id = query::save($this->formdata(['querysql' => $sql]));
        query::get($id)->publish();
        return query::get($id);
    }

    public function test_render_table_empty_rows_shows_norows_message(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $query = $this->published('SELECT id FROM {user}');
        $html = embed_renderer::render_table($query, []);

        $this->assertStringContainsString(get_string('norows', 'report_sql'), $html);
        $this->assertStringNotContainsString('<table', $html);
    }

    public function test_render_table_builds_table_with_headers_and_cells(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $query = $this->published('SELECT id, firstname FROM {user}');
        $html = embed_renderer::render_table($query, [
            ['id' => 1, 'firstname' => 'Ada'],
            ['id' => 2, 'firstname' => 'Bob'],
        ]);

        $this->assertStringContainsString('<table', $html);
        $this->assertStringContainsString('firstname', $html);
        $this->assertStringContainsString('Ada', $html);
        $this->assertStringContainsString('Bob', $html);
    }

    public function test_render_table_formats_timestamp_column_from_epoch(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        // Token %%TIMESTAMP%% forces columnsmeta type=timestamp; the cell is a raw epoch rendered via userdate().
        $query = $this->published('SELECT id, %%TIMESTAMP(timecreated, dd/mm/yyyy)%% AS created FROM {user}');
        $epoch = gmmktime(0, 0, 0, 6, 15, 2021); // 15/06/2021 UTC.
        $html = embed_renderer::render_table($query, [['id' => 1, 'created' => $epoch]]);

        // Rendered as a formatted date, not the raw epoch integer.
        $this->assertStringContainsString('15/06/2021', $html);
        $this->assertStringNotContainsString((string) $epoch, $html);
    }

    public function test_render_table_applies_textcase_transform_display_only(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $query = $this->published('SELECT id, %%CASE(firstname, upper)%% AS fname FROM {user}');
        $html = embed_renderer::render_table($query, [['id' => 1, 'fname' => 'ada']]);

        $this->assertStringContainsString('ADA', $html);
    }

    public function test_render_table_renders_link_column_as_anchor(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        // A %%LINK%% column must render as an <a> in the block / embed path, same as the RB report —
        // not as the raw value. Regression: the link branch was missing from render_table().
        $query = $this->published(
            "SELECT %%LINK(username, '/user/profile.php?id={}')%% AS who FROM {user}"
        );
        $html = embed_renderer::render_table($query, [['who' => 'ada']]);

        $this->assertStringContainsString('<a ', $html);
        $this->assertStringContainsString('/user/profile.php?id=ada', $html);
        $this->assertStringContainsString('>ada<', $html);
    }

    public function test_render_table_link_key_column_fills_slot_from_other_column(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        // 3-arg %%LINK(display, keycol, 'path')%%: the visible text is the username, but {} is filled
        // from the userid column in the same row.
        $query = $this->published(
            'SELECT id AS userid, '
            . "%%LINK(username, userid, '/user/view.php?id={}')%% AS who FROM {user}"
        );
        $html = embed_renderer::render_table($query, [['userid' => 42, 'who' => 'ada']]);

        $this->assertStringContainsString('/user/view.php?id=42', $html);
        $this->assertStringContainsString('>ada<', $html);
    }

    public function test_render_table_hides_listed_columns(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $query = $this->published('SELECT id, firstname FROM {user}');
        $html = embed_renderer::render_table($query, [
            ['id' => 1, 'firstname' => 'Ada'],
        ], ['id']);

        // The hidden column's header and cell are gone; the other column is untouched.
        $this->assertStringNotContainsString('>id<', $html);
        $this->assertStringContainsString('firstname', $html);
        $this->assertStringContainsString('Ada', $html);
    }

    public function test_render_table_hide_is_case_insensitive(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $query = $this->published('SELECT id, firstname FROM {user}');
        $html = embed_renderer::render_table($query, [
            ['id' => 1, 'firstname' => 'Ada'],
        ], ['FirstName']);

        $this->assertStringNotContainsString('Ada', $html);
        $this->assertStringContainsString('>id<', $html);
    }

    public function test_render_table_hidden_keycol_still_resolves_link(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        // Hiding the keycol column must not break a %%LINK%% that keys on it — the value is still
        // read from the row, only its own column is dropped from display.
        $query = $this->published(
            'SELECT id AS userid, '
            . "%%LINK(username, userid, '/user/view.php?id={}')%% AS who FROM {user}"
        );
        $html = embed_renderer::render_table($query, [['userid' => 42, 'who' => 'ada']], ['userid']);

        $this->assertStringContainsString('/user/view.php?id=42', $html);
        $this->assertStringContainsString('>ada<', $html);
        $this->assertStringNotContainsString('>userid<', $html);
    }

    public function test_render_auto_falls_back_to_table_without_chart_config(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        // No chartmeta configured → auto mode renders a table.
        $query = $this->published('SELECT id, firstname FROM {user}');
        $html = embed_renderer::render($query, [['id' => 1, 'firstname' => 'Ada']], 'auto');

        $this->assertStringContainsString('<table', $html);
        $this->assertStringNotContainsString('<img', $html);
    }

    public function test_render_chart_falls_back_to_table_without_xy_columns(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $query = $this->published('SELECT id, firstname FROM {user}');
        // Chartmeta with no xcol/ycol → render_chart degrades to a table rather than emitting a broken image.
        $html = embed_renderer::render_chart($query, [['id' => 1, 'firstname' => 'Ada']], ['type' => 'bar']);

        $this->assertStringContainsString('<table', $html);
    }

    public function test_open_links_in_new_tab_adds_target_and_rel(): void {
        $html = embed_renderer::open_links_in_new_tab('<a href="https://example.com/x">x</a>');

        $this->assertStringContainsString('target="_blank"', $html);
        $this->assertStringContainsString('rel="noopener noreferrer"', $html);
    }

    public function test_open_links_in_new_tab_leaves_existing_target_untouched(): void {
        $input = '<a target="_self" href="https://example.com/x">x</a>';
        $html = embed_renderer::open_links_in_new_tab($input);

        // Anchors that already declare a target are not rewritten (single target attribute only).
        $this->assertSame(1, substr_count($html, 'target='));
        $this->assertStringContainsString('target="_self"', $html);
    }
}
