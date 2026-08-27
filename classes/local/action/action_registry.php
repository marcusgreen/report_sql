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

namespace report_sql\local\action;

use report_sql\local\action\handler\cohort_add;
use report_sql\local\action\handler\enrol_user;
use report_sql\local\action\handler\message_user;
use report_sql\local\action\handler\suspend_user;
use report_sql\local\action\handler\unenrol_user;

/**
 * Registry of the built-in bulk-action handlers.
 *
 * Single source of truth for which ops exist: the edit form offers {@see all()} as choices, the
 * actionable report renders only a query's chosen ops via {@see for_ops()}, and the dispatch page
 * resolves a posted op key with {@see instance()}.
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class action_registry {
    /**
     * Every known handler class, in display order.
     *
     * @return string[] Fully-qualified handler class names.
     */
    private static function classes(): array {
        return [
            enrol_user::class,
            unenrol_user::class,
            suspend_user::class,
            message_user::class,
            cohort_add::class,
        ];
    }

    /**
     * All handlers, keyed by their machine key.
     *
     * @return array<string, action_handler>
     */
    public static function all(): array {
        $handlers = [];
        foreach (self::classes() as $class) {
            /** @var action_handler $handler */
            $handler = new $class();
            $handlers[$handler->key()] = $handler;
        }
        return $handlers;
    }

    /**
     * A single handler by key, or null if unknown.
     *
     * @param string $key
     * @return action_handler|null
     */
    public static function instance(string $key): ?action_handler {
        return self::all()[$key] ?? null;
    }

    /**
     * The handlers matching a query's enabled op keys, preserving registry order and dropping any
     * unknown keys.
     *
     * @param string[] $keys
     * @return array<string, action_handler>
     */
    public static function for_ops(array $keys): array {
        return array_intersect_key(self::all(), array_flip($keys));
    }

    /**
     * key => localised label map, for form option lists.
     *
     * @return array<string, string>
     */
    public static function menu(): array {
        return array_map(static fn(action_handler $h): string => $h->label(), self::all());
    }
}
