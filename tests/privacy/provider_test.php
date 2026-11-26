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

namespace local_vcostaedu\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_vcostaedu\login;
use local_vcostaedu\setup;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_vcostaedu\privacy\provider::class)]
/**
 * Tests for the privacy provider.
 *
 * @package    local_vcostaedu
 * @category   test
 * @copyright  2026 VCOSTA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_vcostaedu\privacy\provider
 */
final class provider_test extends \core_privacy\tests\provider_testcase {
    /**
     * The metadata declares the login keys and the external system.
     */
    public function test_get_metadata(): void {
        $items = provider::get_metadata(new collection('local_vcostaedu'))->get_collection();
        $names = [];
        foreach ($items as $item) {
            $names[get_class($item)] = $item->get_name();
            $this->assertTrue(get_string_manager()->string_exists($item->get_summary(), 'local_vcostaedu'));
            foreach ($item->get_privacy_fields() as $field) {
                $this->assertTrue(get_string_manager()->string_exists($field, 'local_vcostaedu'), $field);
            }
        }
        $this->assertSame('core_userkey', $names[\core_privacy\local\metadata\types\subsystem_link::class]);
        $this->assertSame('vcostaedu', $names[\core_privacy\local\metadata\types\external_location::class]);
    }

    /**
     * Contexts, users, export and deletion of the login keys.
     */
    public function test_user_data(): void {
        global $DB;
        $this->resetAfterTest();
        $student = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        login::create_key((int) $student->id, 60);
        login::create_key((int) $other->id, 60);
        $context = \context_user::instance($student->id);

        $contextlist = provider::get_contexts_for_userid((int) $student->id);
        $this->assertSame([(int) $context->id], array_map('intval', $contextlist->get_contextids()));

        $userlist = new userlist($context, 'local_vcostaedu');
        provider::get_users_in_context($userlist);
        $this->assertSame([(int) $student->id], array_map('intval', $userlist->get_userids()));

        // The core user key export reads the keys of the current user, as a data request does.
        $this->setUser($student);
        provider::export_user_data(new approved_contextlist($student, 'local_vcostaedu', [$context->id]));
        $this->assertTrue(writer::with_context($context)->has_any_data_in_any_context());

        provider::delete_data_for_user(new approved_contextlist($student, 'local_vcostaedu', [$context->id]));
        $this->assertFalse($DB->record_exists('user_private_key', ['userid' => $student->id, 'script' => setup::KEY_SCRIPT]));
        $this->assertTrue($DB->record_exists('user_private_key', ['userid' => $other->id, 'script' => setup::KEY_SCRIPT]));

        $othercontext = \context_user::instance($other->id);
        provider::delete_data_for_users(new approved_userlist($othercontext, 'local_vcostaedu', [$other->id]));
        $this->assertFalse($DB->record_exists('user_private_key', ['userid' => $other->id, 'script' => setup::KEY_SCRIPT]));
    }

    /**
     * Deleting a whole user context removes the keys of that user.
     */
    public function test_delete_data_for_all_users_in_context(): void {
        global $DB;
        $this->resetAfterTest();
        $student = $this->getDataGenerator()->create_user();
        login::create_key((int) $student->id, 60);
        provider::delete_data_for_all_users_in_context(\context_user::instance($student->id));
        $this->assertFalse($DB->record_exists('user_private_key', ['userid' => $student->id, 'script' => setup::KEY_SCRIPT]));
        // Other contexts are ignored.
        provider::delete_data_for_all_users_in_context(\context_system::instance());
    }
}
