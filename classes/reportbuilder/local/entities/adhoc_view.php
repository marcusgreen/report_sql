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
use core_reportbuilder\local\filters\{boolean_select, date, number, text};
use core_reportbuilder\local\report\{column, filter};
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

    /** @var array<string, array{type:string,label:string,dateformat?:string}> */
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
            $column = (new column(
                $name,
                self::raw_title($name),
                $this->get_entity_name()
            ))
                ->add_field("{$alias}.{$name}")
                ->set_type(self::rb_column_type($type))
                ->set_is_sortable(true);

            // A %%TIMESTAMP() column holds a raw epoch integer, so it sorts chronologically; format
            // it for display with a callback (the optional per-column format, else Moodle's default).
            // Sorting still uses the underlying epoch field, not the formatted string.
            if ($type === 'timestamp') {
                $strftime = query::strftime_format((string) ($meta['dateformat'] ?? ''));
                $column->set_callback(static function ($value, $row, $arg): string {
                    // Passing $fixday = false keeps the leading zero on %d (dd), so 'dd' really means 2 digits.
                    return empty($value) ? '' : userdate((int) $value, $arg, 99, false);
                }, $strftime);
            } else if (!empty($meta['textcase'])) {
                // A %%CASE() column stores the raw text (so it sorts/filters on the original value);
                // apply the requested case only for display. The same helper formats chart labels
                // (see query::chart_series()), so table and chart match.
                $column->set_callback(static function ($value, $row, $arg): string {
                    return query::format_textcase((string) ($value ?? ''), (string) $arg);
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
            $filters[] = (new filter(
                self::rb_filter_class($meta['type'] ?? 'text'),
                $name,
                self::raw_title($name),
                $this->get_entity_name(),
                "{$alias}.{$name}"
            ))->add_joins($this->get_joins());
        }
        return $filters;
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
}
