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

namespace local_vcostaedu;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_vcostaedu\login::class)]
/**
 * Tests for the one-time login link rules.
 *
 * @package    local_vcostaedu
 * @category   test
 * @copyright  2026 VCOSTA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_vcostaedu\login
 */
final class login_test extends \advanced_testcase {
    /**
     * An active user may enter; each kind of account that must not enter is refused with its own reason.
     */
    public function test_check_user(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();

        $this->assertNull(login::check_user($gen->create_user()));
        $this->assertSame('usernotfound', login::check_user(false));
        $this->assertSame('usernotfound', login::check_user(null));

        $deleted = $gen->create_user();
        $deleted->deleted = 1;
        $this->assertSame('userdeleted', login::check_user($deleted));

        $this->assertSame('userisguest', login::check_user(guest_user()));
        $this->assertSame('userisadmin', login::check_user(get_admin()));
        $this->assertSame('userisservice', login::check_user(setup::get_user()));
        $this->assertSame('usersuspended', login::check_user($gen->create_user(['suspended' => 1])));
        $this->assertSame('usernotconfirmed', login::check_user($gen->create_user(['confirmed' => 0])));
        $this->assertSame('usernologin', login::check_user($gen->create_user(['auth' => 'nologin'])));
        $this->assertSame('authdisabled', login::check_user($gen->create_user(['auth' => 'ldap'])));

        // Every reason has a refusal text and a web service error text.
        foreach (login::reasons() as $reason) {
            $this->assertTrue(get_string_manager()->string_exists('refused_' . $reason, setup::COMPONENT), $reason);
        }
        foreach (login::USER_REASONS as $reason) {
            $this->assertTrue(get_string_manager()->string_exists(login::errorcode($reason), setup::COMPONENT), $reason);
        }
        $this->assertSame(0, $DB->count_records('user_private_key', ['script' => setup::KEY_SCRIPT]));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('returnurl_provider')]
    /**
     * Only addresses inside this Moodle are kept as the return address.
     *
     * @param string $input
     * @param string $expected
     * @dataProvider returnurl_provider
     */
    public function test_clean_returnurl(string $input, string $expected): void {
        global $CFG;
        $this->resetAfterTest();
        $input = str_replace('{wwwroot}', $CFG->wwwroot, $input);
        $this->assertSame($expected, login::clean_returnurl($input));
    }

    /**
     * Return addresses and the expected result.
     *
     * @return array
     */
    public static function returnurl_provider(): array {
        return [
            'empty' => ['', ''],
            'local path' => ['/course/view.php?id=2', '/course/view.php?id=2'],
            'full local address' => ['{wwwroot}/my/', '/my/'],
            'site root' => ['{wwwroot}', '/'],
            'other site' => ['https://evil.example.com/x', ''],
            'protocol relative' => ['//evil.example.com/x', ''],
            'backslash' => ['/\\evil.example.com', ''],
            'javascript' => ['javascript:alert(1)', ''],
            'control character' => ["/my/\n", '/my/'],
            'relative without slash' => ['my/', ''],
            'loop to the login page' => ['/local/vcostaedu/login.php?key=x', ''],
        ];
    }

    /**
     * A key works once: it is deleted on the first use.
     */
    public function test_consume_key_once(): void {
        global $DB;
        $this->resetAfterTest();
        $student = $this->getDataGenerator()->create_user();
        [$key, $validuntil] = login::create_key((int) $student->id, 60);
        $this->assertGreaterThan(time(), $validuntil);

        $record = login::consume_key($key);
        $this->assertIsObject($record);
        $this->assertEquals($student->id, $record->userid);
        $this->assertFalse($DB->record_exists('user_private_key', ['value' => $key]));

        $this->assertSame(login::INVALIDKEY, login::consume_key($key));
    }

    /**
     * An expired key is refused and deleted; an unknown or empty key is refused.
     */
    public function test_consume_key_expired_and_unknown(): void {
        global $DB;
        $this->resetAfterTest();
        $student = $this->getDataGenerator()->create_user();
        [$key] = login::create_key((int) $student->id, 60);
        $DB->set_field('user_private_key', 'validuntil', time() - 1, ['value' => $key]);

        $this->assertSame(login::EXPIREDKEY, login::consume_key($key));
        $this->assertFalse($DB->record_exists('user_private_key', ['value' => $key]));
        $this->assertSame(login::INVALIDKEY, login::consume_key('00000000000000000000000000000000'));
        $this->assertSame(login::INVALIDKEY, login::consume_key(''));
    }

    /**
     * Creating a key drops expired keys of the plugin.
     */
    public function test_create_key_drops_expired_keys(): void {
        global $DB;
        $this->resetAfterTest();
        $student = $this->getDataGenerator()->create_user();
        [$old] = login::create_key((int) $student->id, 60);
        $DB->set_field('user_private_key', 'validuntil', time() - 10, ['value' => $old]);
        login::create_key((int) $student->id, 60);
        $this->assertFalse($DB->record_exists('user_private_key', ['value' => $old]));
        $this->assertSame(1, $DB->count_records('user_private_key', ['script' => setup::KEY_SCRIPT]));
    }

    /**
     * A refused link ends the session only when it belongs to the owner of the link.
     */
    public function test_end_owner_session(): void {
        $this->resetAfterTest();
        $owner = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();

        // Somebody else is logged in: nothing happens.
        $this->setUser($other);
        $this->assertFalse(login::end_owner_session((int) $owner->id));
        $this->assertTrue(isloggedin());

        // Nobody is logged in: nothing happens.
        $this->setUser(null);
        $this->assertFalse(login::end_owner_session((int) $owner->id));

        // The owner is logged in: the session is ended.
        $this->setUser($owner);
        $this->assertTrue(login::end_owner_session((int) $owner->id));
        $this->assertFalse(isloggedin());
    }

    /**
     * Logging in checks the user again and refuses a user who stopped being active.
     */
    public function test_complete_login_refuses_inactive_user(): void {
        global $DB;
        $this->resetAfterTest();
        $student = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'suspended', 1, ['id' => $student->id]);
        $this->assertSame('usersuspended', login::complete_login($student));
        $this->assertFalse(isloggedin());
    }

    /**
     * Login address and target.
     */
    public function test_urls(): void {
        $this->resetAfterTest();
        $url = login::login_url('abc', '/course/view.php?id=3')->out(false);
        $this->assertStringContainsString('/local/vcostaedu/login.php?key=abc', $url);
        $this->assertStringContainsString('returnurl=', $url);
        $this->assertStringNotContainsString('returnurl', login::login_url('abc', '')->out(false));
        $this->assertStringEndsWith('/my/', login::target('')->out(false));
        $this->assertStringEndsWith('/course/view.php?id=3', login::target('/course/view.php?id=3')->out(false));
    }

    /**
     * The refusal page shows the message of the reason, and unknown reasons as an invalid link.
     */
    public function test_render_refusal(): void {
        global $PAGE;
        $this->resetAfterTest();
        $PAGE->set_url('/local/vcostaedu/login.php');
        $PAGE->set_context(\context_system::instance());
        $html = login::render_refusal('usersuspended');
        $this->assertStringContainsString(get_string('refused_usersuspended', setup::COMPONENT), $html);
        $this->assertStringContainsString('data-local-vcostaedu-refused="usersuspended"', $html);
    }

    /**
     * Unknown reasons are shown as an invalid link.
     */
    public function test_render_refusal_unknown_reason(): void {
        global $PAGE;
        $this->resetAfterTest();
        $PAGE->set_url('/local/vcostaedu/login.php');
        $PAGE->set_context(\context_system::instance());
        $html = login::render_refusal('<script>');
        $this->assertStringContainsString('data-local-vcostaedu-refused="invalidkey"', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }

    /**
     * Refusals are logged with their reason.
     */
    public function test_log_refused(): void {
        $this->resetAfterTest();
        $student = $this->getDataGenerator()->create_user();
        $sink = $this->redirectEvents();
        login::log_refused('usersuspended', 'login', (int) $student->id);
        $events = $sink->get_events();
        $sink->close();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(event\login_link_refused::class, $events[0]);
        $this->assertSame('usersuspended', $events[0]->other['reason']);
        $this->assertEquals($student->id, $events[0]->relateduserid);
    }
}
