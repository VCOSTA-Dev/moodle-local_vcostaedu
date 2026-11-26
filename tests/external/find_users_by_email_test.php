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

namespace local_vcostaedu\external;

use core_external\external_api;
use local_vcostaedu\setup;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_vcostaedu\external\find_users_by_email::class)]
/**
 * Tests for local_vcostaedu_find_users_by_email.
 *
 * @package    local_vcostaedu
 * @category   test
 * @copyright  2026 VCOSTA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_vcostaedu\external\find_users_by_email
 */
final class find_users_by_email_test extends \advanced_testcase {
    /**
     * Calls the function as the service user, as the web service would, cleaning the return value.
     *
     * @param string[] $emails
     * @return array
     */
    private function call(array $emails): array {
        $this->setUser(setup::get_user());
        $result = find_users_by_email::execute($emails);
        return external_api::clean_returnvalue(find_users_by_email::execute_returns(), $result);
    }

    /**
     * Expects the call to be refused as an invalid parameter.
     *
     * @param array $emails
     */
    private function assert_invalid(array $emails): void {
        try {
            $this->call($emails);
            $this->fail('Expected invalidparameter');
        } catch (\invalid_parameter_exception $e) {
            $this->assertSame('invalidparameter', $e->errorcode);
        }
    }

    /**
     * No account with the address: an empty list.
     */
    public function test_not_found(): void {
        $this->resetAfterTest();
        $this->assertSame([['email' => 'nobody@example.com', 'userids' => []]], $this->call(['nobody@example.com']));
    }

    /**
     * One account with the address.
     */
    public function test_found(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user(['email' => 'student.one@example.com']);
        $this->assertSame(
            [['email' => 'student.one@example.com', 'userids' => [(int) $user->id]]],
            $this->call(['student.one@example.com'])
        );
    }

    /**
     * Two accounts with the same address (the site allows it): both, in id order.
     */
    public function test_same_email_twice(): void {
        $this->resetAfterTest();
        set_config('allowaccountssameemail', 1);
        $first = $this->getDataGenerator()->create_user(['email' => 'shared@example.com']);
        $second = $this->getDataGenerator()->create_user(['email' => 'shared@example.com']);
        $this->assertSame(
            [['email' => 'shared@example.com', 'userids' => [(int) $first->id, (int) $second->id]]],
            $this->call(['shared@example.com'])
        );
    }

    /**
     * Case and spaces at the ends are ignored; the address comes back as it was sent.
     */
    public function test_case_and_spaces(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user(['email' => 'Mixed.Case@Example.com']);
        $this->assertSame(
            [['email' => '  mixed.case@EXAMPLE.COM ', 'userids' => [(int) $user->id]]],
            $this->call(['  mixed.case@EXAMPLE.COM '])
        );
    }

    /**
     * Deleted accounts never come back, even when the record kept the address.
     */
    public function test_deleted_left_out(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user(['email' => 'gone@example.com']);
        $DB->set_field('user', 'deleted', 1, ['id' => $user->id]);
        $this->assertSame([['email' => 'gone@example.com', 'userids' => []]], $this->call(['gone@example.com']));
    }

    /**
     * The guest account never comes back.
     */
    public function test_guest_left_out(): void {
        global $DB;
        $this->resetAfterTest();
        $guest = guest_user();
        $DB->set_field('user', 'email', 'guest@example.com', ['id' => $guest->id]);
        $this->assertSame([['email' => 'guest@example.com', 'userids' => []]], $this->call(['guest@example.com']));
    }

    /**
     * Suspended accounts come back: the caller decides.
     */
    public function test_suspended_included(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user(['email' => 'paused@example.com', 'suspended' => 1]);
        $this->assertSame([['email' => 'paused@example.com', 'userids' => [(int) $user->id]]], $this->call(['paused@example.com']));
    }

    /**
     * Several addresses: one item each, in the order received, repeated ones included.
     */
    public function test_several_in_order(): void {
        $this->resetAfterTest();
        $a = $this->getDataGenerator()->create_user(['email' => 'a@example.com']);
        $b = $this->getDataGenerator()->create_user(['email' => 'b@example.com']);
        $this->assertSame([
            ['email' => 'b@example.com', 'userids' => [(int) $b->id]],
            ['email' => 'none@example.com', 'userids' => []],
            ['email' => 'a@example.com', 'userids' => [(int) $a->id]],
            ['email' => 'B@example.com', 'userids' => [(int) $b->id]],
        ], $this->call(['b@example.com', 'none@example.com', 'a@example.com', 'B@example.com']));
    }

    /**
     * A malformed address, an empty list and more than 100 addresses are invalid parameters.
     */
    public function test_invalid_parameters(): void {
        $this->resetAfterTest();
        $this->assert_invalid(['not an address']);
        $this->assert_invalid(['ok@example.com', 'missing-at.example.com']);
        $this->assert_invalid(['']);
        $this->assert_invalid([]);
        $emails = [];
        for ($i = 1; $i <= find_users_by_email::MAX_EMAILS + 1; $i++) {
            $emails[] = "user$i@example.com";
        }
        $this->assert_invalid($emails);
        // Exactly 100 is accepted.
        $this->assertCount(find_users_by_email::MAX_EMAILS, $this->call(array_slice($emails, 0, find_users_by_email::MAX_EMAILS)));
    }

    /**
     * Without moodle/user:viewdetails the call is refused.
     */
    public function test_requires_capability(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $this->expectException(\required_capability_exception::class);
        find_users_by_email::execute(['someone@example.com']);
    }

    /**
     * The answer has only the address and the ids: no other data of the user.
     */
    public function test_answer_has_only_ids(): void {
        $this->resetAfterTest();
        $this->getDataGenerator()->create_user([
            'email' => 'private@example.com',
            'username' => 'private.user',
            'idnumber' => 'ID-SECRET',
            'firstname' => 'Hidden',
        ]);
        $result = $this->call(['private@example.com']);
        $this->assertSame(['email', 'userids'], array_keys($result[0]));
        foreach ($result[0]['userids'] as $id) {
            $this->assertIsInt($id);
        }
        $text = json_encode($result);
        foreach (['private.user', 'ID-SECRET', 'Hidden'] as $value) {
            $this->assertStringNotContainsString($value, $text);
        }
        $structure = find_users_by_email::execute_returns()->content->keys;
        $this->assertSame(['email', 'userids'], array_keys($structure));
    }
}
