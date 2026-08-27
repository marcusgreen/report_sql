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

use context;
use report_sql\local\action\action_registry;
use report_sql\local\action\base_action;

/**
 * Unit tests for the bulk-action registry and the shared execute loop.
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \report_sql\local\action\action_registry
 * @covers \report_sql\local\action\base_action
 */
final class action_registry_test extends \advanced_testcase {
    /**
     * The five built-in ops are registered and resolvable, and unknown keys resolve to null.
     */
    public function test_registry_contents(): void {
        $keys = array_keys(action_registry::all());
        $this->assertEqualsCanonicalizing(
            ['enrol_user', 'unenrol_user', 'suspend_user', 'message_user', 'cohort_add'],
            $keys
        );

        $this->assertInstanceOf(base_action::class, action_registry::instance('enrol_user'));
        $this->assertNull(action_registry::instance('bogus'));

        // for_ops keeps only known keys.
        $this->assertSame(
            ['enrol_user', 'cohort_add'],
            array_keys(action_registry::for_ops(['enrol_user', 'bogus', 'cohort_add']))
        );

        // menu() is key => non-empty label.
        foreach (action_registry::menu() as $key => $label) {
            $this->assertNotEmpty($label, "empty label for {$key}");
        }
    }

    /**
     * Destructive flags: only unenrol and suspend are destructive.
     */
    public function test_destructive_flags(): void {
        $this->assertTrue(action_registry::instance('unenrol_user')->is_destructive());
        $this->assertTrue(action_registry::instance('suspend_user')->is_destructive());
        $this->assertFalse(action_registry::instance('enrol_user')->is_destructive());
        $this->assertFalse(action_registry::instance('message_user')->is_destructive());
        $this->assertFalse(action_registry::instance('cohort_add')->is_destructive());
    }

    /**
     * A handler that records the subjects it touches, gated on a real capability at system context.
     *
     * @return base_action
     */
    private function fake_handler(): base_action {
        return new class extends base_action {
            /** @var int[] Subjects apply_one ran for. */
            public array $applied = [];
            /** @var int Subject id that apply_one should throw for (0 = never). */
            public int $failfor = 0;

            public function key(): string {
                return 'fake';
            }

            public function label(): string {
                return 'Fake';
            }

            public function required_capability(): string {
                return 'moodle/site:config';
            }

            protected function target_context(int $subjectid, context $reportctx, array $params): context {
                return \context_system::instance();
            }

            protected function apply_one(int $subjectid, context $targetctx, array $params): void {
                if ($subjectid === $this->failfor) {
                    throw new \moodle_exception('error');
                }
                $this->applied[] = $subjectid;
            }
        };
    }

    /**
     * execute() applies to every subject when the operator holds the capability.
     */
    public function test_execute_applies_with_capability(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $handler = $this->fake_handler();
        $result = $handler->execute([11, 22], \context_system::instance(), []);

        $this->assertSame([11, 22], $handler->applied);
        $this->assertSame(2, $result->applied_count());
        $this->assertSame(0, $result->skipped_count());
    }

    /**
     * execute() skips every subject (never calls apply_one) when the operator lacks the capability.
     */
    public function test_execute_skips_without_capability(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $handler = $this->fake_handler();
        $result = $handler->execute([11, 22], \context_system::instance(), []);

        $this->assertSame([], $handler->applied);
        $this->assertSame(0, $result->applied_count());
        $this->assertSame(2, $result->skipped_count());
        $this->assertSame([11, 22], array_keys($result->skipped_reasons()));
    }

    /**
     * One subject's failure is isolated: the rest still apply.
     */
    public function test_execute_isolates_failures(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $handler = $this->fake_handler();
        $handler->failfor = 22;
        $result = $handler->execute([11, 22, 33], \context_system::instance(), []);

        $this->assertSame([11, 33], $handler->applied);
        $this->assertSame(2, $result->applied_count());
        $this->assertSame([22], array_keys($result->skipped_reasons()));
    }

    /**
     * Non-positive / zero subject ids are ignored entirely.
     */
    public function test_execute_ignores_invalid_ids(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $handler = $this->fake_handler();
        $result = $handler->execute([0, -5, 7], \context_system::instance(), []);

        $this->assertSame([7], $handler->applied);
        $this->assertSame(1, $result->applied_count());
        $this->assertSame(0, $result->skipped_count());
    }
}
