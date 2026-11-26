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
use local_vcostaedu\login;
use local_vcostaedu\setup;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_vcostaedu\external\request_login_url::class)]
/**
 * Tests for local_vcostaedu_request_login_url.
 *
 * @package    local_vcostaedu
 * @category   test
 * @copyright  2026 VCOSTA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_vcostaedu\external\request_login_url
 */
final class request_login_url_test extends \advanced_testcase {
    /**
     * Calls the function as the web service would, cleaning the return value.
     *
     * @param string $username
     * @param string $returnurl
     * @return array
     */
    private function call(string $username, string $returnurl = ''): array {
        $result = request_login_url::execute($username, $returnurl);
        return external_api::clean_returnvalue(request_login_url::execute_returns(), $result);
    }

    /**
     * Expects the call to be refused with the given error code and no key to be created.
     *
     * @param string $username
     * @param string $errorcode
     */
    private function assert_refused(string $username, string $errorcode): void {
        global $DB;
        $before = $DB->count_records('user_private_key', ['script' => setup::KEY_SCRIPT]);
        try {
            $this->call($username);
            $this->fail("Expected refusal $errorcode for $username");
        } catch (\moodle_exception $e) {
            $this->assertSame($errorcode, $e->errorcode);
        }
        $this->assertSame($before, $DB->count_records('user_private_key', ['script' => setup::KEY_SCRIPT]));
    }

    /**
     * The service user gets a one-time link for an active user.
     */
    public function test_link_for_active_user(): void {
        global $DB, $CFG;
        $this->resetAfterTest();
        $this->setUser(setup::get_user());
        $student = $this->getDataGenerator()->create_user(['username' => 'student1']);
        set_config('loginvalidity', 45, setup::COMPONENT);

        $sink = $this->redirectEvents();
        $result = $this->call('student1', '/course/view.php?id=2');
        $events = $sink->get_events();
        $sink->close();

        $this->assertStringStartsWith($CFG->wwwroot . '/local/vcostaedu/login.php?key=', $result['loginurl']);
        $this->assertStringContainsString('returnurl=' . urlencode('/course/view.php?id=2'), $result['loginurl']);
        $this->assertEqualsWithDelta(time() + 45, $result['validuntil'], 2);
        $keys = $DB->get_records('user_private_key', ['script' => setup::KEY_SCRIPT, 'userid' => $student->id]);
        $this->assertCount(1, $keys);
        $this->assertStringContainsString('key=' . reset($keys)->value, $result['loginurl']);
        $this->assertInstanceOf(\local_vcostaedu\event\login_link_created::class, $events[0]);
    }

    /**
     * An address outside this Moodle is left out of the link.
     */
    public function test_external_returnurl_is_ignored(): void {
        $this->resetAfterTest();
        $this->setUser(setup::get_user());
        $this->getDataGenerator()->create_user(['username' => 'student1']);
        foreach (['https://evil.example.com/', '//evil.example.com/x', 'javascript:alert(1)'] as $url) {
            $result = $this->call('student1', $url);
            $this->assertStringNotContainsString('returnurl', $result['loginurl'], $url);
        }
    }

    /**
     * A user without the plugin capability cannot ask for links.
     */
    public function test_requires_capability(): void {
        $this->resetAfterTest();
        $this->getDataGenerator()->create_user(['username' => 'student1']);
        $this->setUser($this->getDataGenerator()->create_user());
        $this->expectException(\required_capability_exception::class);
        $this->call('student1');
    }

    /**
     * Each account that must not enter is refused with its own code, and no key is created.
     */
    public function test_refusals(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $this->setUser(setup::get_user());

        $this->assert_refused('nobody', login::errorcode('usernotfound'));
        $gone = $gen->create_user(['username' => 'gone']);
        $DB->set_field('user', 'deleted', 1, ['id' => $gone->id]);
        $this->assert_refused('gone', login::errorcode('userdeleted'));
        $this->assert_refused('guest', login::errorcode('userisguest'));
        $this->assert_refused('admin', login::errorcode('userisadmin'));
        $this->assert_refused(setup::USERNAME, login::errorcode('userisservice'));
        $gen->create_user(['username' => 'suspended1', 'suspended' => 1]);
        $this->assert_refused('suspended1', login::errorcode('usersuspended'));
        $gen->create_user(['username' => 'unconfirmed1', 'confirmed' => 0]);
        $this->assert_refused('unconfirmed1', login::errorcode('usernotconfirmed'));
        $gen->create_user(['username' => 'nologin1', 'auth' => 'nologin']);
        $this->assert_refused('nologin1', login::errorcode('usernologin'));
        $gen->create_user(['username' => 'ldap1', 'auth' => 'ldap']);
        $this->assert_refused('ldap1', login::errorcode('authdisabled'));
    }

    /**
     * Refusals are logged with the reason and the request stage.
     */
    public function test_refusal_is_logged(): void {
        $this->resetAfterTest();
        $this->setUser(setup::get_user());
        $this->getDataGenerator()->create_user(['username' => 'suspended1', 'suspended' => 1]);
        $sink = $this->redirectEvents();
        try {
            $this->call('suspended1');
        } catch (\moodle_exception $e) {
            $this->assertSame('vcostaedu_usersuspended', $e->errorcode);
        }
        $events = $sink->get_events();
        $sink->close();
        $this->assertInstanceOf(\local_vcostaedu\event\login_link_refused::class, $events[0]);
        $this->assertSame('request', $events[0]->other['stage']);
        $this->assertSame('usersuspended', $events[0]->other['reason']);
    }
}
