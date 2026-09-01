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

namespace report_sql\reportbuilder\local\entities;

use core_reportbuilder\local\entities\base;
use core_reportbuilder\local\filters\{boolean_select, date, number, select, text};
use core_reportbuilder\local\report\{column, filter};
use report_sql\local\chart_presenter;
use report_sql\local\query;
use lang_string;

/**
 * Reportbuilder entity that wraps a database VIEW representing a saved ad-hoc query.
 *
 * Columns and filters are constructed at runtime from the cached column metadata stored on the
 * query record (which itself was introspected from the live VIEW at publish time).
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class adhoc_view extends base {
    /** @var string Internal entity name. */
    public const ENTITY = 'adhoc';

    /** @var array<string, array{type:string,label:string,dateformat?:string,textcase?:string,enum?:bool}> */
    private array $columnsmeta;

    /** @var string VIEW name (without Moodle prefix). */
    private string $viewname;

    /** @var string Display title for the entity (defaults to the query name). */
    private string $title;

    /**
     * Build the entity for a given view and its cached column metadata.
     *
     * @param string $viewname
     * @param array  $columnsmeta
     * @param string $title Display title shown as the column-picker group heading.
     */
    public function __construct(string $viewname, array $columnsmeta, string $title = '') {
        $this->viewname = $viewname;
        $this->columnsmeta = $columnsmeta;
        $this->title = $title;
        $this->set_entity_name(self::ENTITY);
    }

    /**
     * Get the tables (the view) used by this entity.
     *
     * @return array
     */
    protected function get_default_tables(): array {
        return [$this->viewname];
    }

    /**
     * Get the default title for this entity.
     *
     * @return lang_string
     */
    protected function get_default_entity_title(): lang_string {
        if ($this->title !== '') {
            return new lang_string('reportsourceheader', 'report_sql', $this->title);
        }
        return new lang_string('reportsource', 'report_sql');
    }

    /**
     * Add this entity's columns, filters and conditions.
     *
     * @return base
     */
    public function initialise(): base {
        foreach ($this->build_columns() as $col) {
            $this->add_column($col);
        }
        foreach ($this->build_filters() as $f) {
            $this->add_filter($f);
            $this->add_condition($f);
        }
        return $this;
    }

    /**
     * Build a Report Builder column for every view column.
     *
     * @return column[]
     */
    private function build_columns(): array {
        $alias = $this->get_table_alias($this->viewname);
        $cols = [];
        foreach ($this->columnsmeta as $name => $meta) {
            $type = $meta['type'] ?? 'text';
            // A linked cell emits <a> HTML from its callback; keep the column TYPE_TEXT so Report
            // Builder does not apply numeric/date formatting to the markup. Sorting still uses the
            // underlying field (added below), so a numeric column keeps numeric ordering.
            $coltype = !empty($meta['link']) ? 'text' : $type;
            $column = (new column(
                $name,
                self::raw_title($name),
                $this->get_entity_name()
            ))
                ->add_field("{$alias}.{$name}")
                ->set_type(self::rb_column_type($coltype))
                ->set_is_sortable(true);

            // A %%TIMESTAMP() column holds a raw epoch integer, so it sorts chronologically; format
            // it for display with a callback (the optional per-column format, else Moodle's default).
            // Sorting still uses the underlying epoch field, not the formatted string.
            if ($type === 'timestamp') {
                $strftime = chart_presenter::strftime_format((string) ($meta['dateformat'] ?? ''));
                $column->set_callback(static function ($value, $row, $arg): string {
                    // Passing $fixday = false keeps the leading zero on %d (dd), so 'dd' really means 2 digits.
                    return empty($value) ? '' : userdate((int) $value, $arg, 99, false);
                }, $strftime);
            } else if (!empty($meta['link'])) {
                // A %%LINK() column stores the raw value (so it sorts/filters on the original) and
                // renders it as a link at display time. The stored path is site-relative (enforced in
                // view::link_columns()); wrapping it in a moodle_url prefixes the site address and
                // escapes it, and s() escapes the visible text — so neither the path nor the cell
                // value can inject markup. A `{}` in the path is the slot for the url-encoded value.
                if (!empty($meta['linkkey'])) {
                    // 3-arg %%LINK(display, keycol, 'path')%%: fill {} from a *different* output
                    // column. Pull that column into this column's row under a per-column alias, so
                    // the callback sees both the display value ($value) and the key ($row->$keyalias).
                    $keyalias = 'linkkey_' . $name;
                    $column->add_field("{$alias}.{$meta['linkkey']}", $keyalias);
                    $column->set_callback(static function ($value, $row, $arg) use ($keyalias): string {
                        $key = isset($row->$keyalias) ? (string) $row->$keyalias : null;
                        return self::render_link((string) ($value ?? ''), (string) $arg, $key);
                    }, (string) $meta['link']);
                } else {
                    $column->set_callback(static function ($value, $row, $arg): string {
                        return self::render_link((string) ($value ?? ''), (string) $arg);
                    }, (string) $meta['link']);
                }
            } else if (!empty($meta['textcase'])) {
                // A %%CASE() column stores the raw text (so it sorts/filters on the original value);
                // apply the requested case only for display. The same helper formats chart labels
                // (see chart_presenter::chart_series()), so table and chart match.
                $column->set_callback(static function ($value, $row, $arg): string {
                    return chart_presenter::format_textcase((string) ($value ?? ''), (string) $arg);
                }, (string) $meta['textcase']);
            }

            $cols[] = $column;
        }
        return $cols;
    }

    /**
     * Build a Report Builder filter for every view column.
     *
     * @return filter[]
     */
    private function build_filters(): array {
        $alias = $this->get_table_alias($this->viewname);
        $filters = [];
        foreach ($this->columnsmeta as $name => $meta) {
            $isenum = !empty($meta['enum']) && ($meta['type'] ?? 'text') === 'text';

            $f = (new filter(
                $isenum ? select::class : self::rb_filter_class($meta['type'] ?? 'text'),
                $name,
                self::raw_title($name),
                $this->get_entity_name(),
                "{$alias}.{$name}"
            ))->add_joins($this->get_joins());

            if ($isenum) {
                $f->set_options_callback($this->enum_options_callback($name));
            }
            $filters[] = $f;
        }
        return $filters;
    }

    /**
     * Build the distinct-value options callback for a dropdown (enum) filter. Deferred to filter-render
     * time so the option list always reflects the live view (values added since publish still appear).
     * Runs one cheap `SELECT DISTINCT` on the view; degrades to an empty list on error.
     *
     * @param string $name Column name.
     * @return callable():array<string,string>
     */
    private function enum_options_callback(string $name): callable {
        $viewname = $this->viewname;
        return static function () use ($viewname, $name): array {
            global $DB;
            try {
                $values = $DB->get_fieldset_sql(
                    "SELECT DISTINCT {$name} FROM {{$viewname}}
                      WHERE {$name} IS NOT NULL
                   ORDER BY {$name}"
                );
            } catch (\dml_exception $e) {
                return [];
            }
            $options = [];
            foreach ($values as $value) {
                $options[(string) $value] = (string) $value;
            }
            return $options;
        };
    }

    /**
     * Render an arbitrary column name as a {@see lang_string}. Routed through the language
     * entry `adhocheader = '{$a}'` so we don't need a language entry per column name.
     *
     * @param string $name Column name to render as the header.
     * @return lang_string
     */
    private static function raw_title(string $name): lang_string {
        return new lang_string('reportsourceheader', 'report_sql', $name);
    }

    /**
     * Map a column type token to a Report Builder column type constant.
     *
     * @param string $token
     * @return int
     */
    private static function rb_column_type(string $token): int {
        return match ($token) {
            'int'       => column::TYPE_INTEGER,
            'float'     => column::TYPE_FLOAT,
            'bool'      => column::TYPE_BOOLEAN,
            'timestamp' => column::TYPE_TIMESTAMP,
            default     => column::TYPE_TEXT,
        };
    }

    /**
     * Map a column type token to a Report Builder filter class.
     *
     * @param string $token
     * @return class-string
     */
    private static function rb_filter_class(string $token): string {
        return match ($token) {
            'int', 'float' => number::class,
            'bool'         => boolean_select::class,
            'timestamp'    => date::class,
            default        => text::class,
        };
    }

    /**
     * Render a %%LINK() cell: wrap the raw value in an <a href> pointing at the stored site-relative
     * path. The path is guaranteed site-relative by {@see \report_sql\local\sql\view::link_columns()};
     * routing it through a {@see \moodle_url} prefixes the site address and escapes the URL, and s()
     * escapes the visible text — so neither the path nor the value can inject markup. A `{}` in the
     * path is the slot for the url-encoded value; an empty value renders nothing (no dangling link).
     * The link opens in a new tab (`target="_blank"`, with `rel="noopener"` to block reverse tabnabbing).
     *
     * @param string $value Raw cell value (the column stores it untransformed) — the visible link text.
     * @param string $path Stored site-relative path, with an optional `{}` value slot.
     * @param string|null $key Value to fill the `{}` slot with; null uses $value (the two-argument form).
     *     Set from a 3-arg %%LINK(display, keycol, 'path')%% so the visible text and the link key differ.
     * @return string Link HTML, or '' for an empty value.
     */
    public static function render_link(string $value, string $path, ?string $key = null): string {
        if ($value === '') {
            return '';
        }
        $path = str_replace('{}', rawurlencode($key ?? $value), $path);
        return \html_writer::link(new \moodle_url($path), s($value), ['target' => '_blank', 'rel' => 'noopener']);
    }
}
