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

#[\PHPUnit\Framework\Attributes\CoversClass(\local_vcostaedu\diagnostics::class)]
/**
 * Tests for the diagnosis of the "Connect to VCOSTAEDU" page.
 *
 * @package    local_vcostaedu
 * @category   test
 * @copyright  2026 VCOSTA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_vcostaedu\diagnostics
 */
final class diagnostics_test extends \advanced_testcase {
    /**
     * Status of each item, by key.
     *
     * @return array
     */
    private function statuses(): array {
        $out = [];
        foreach (diagnostics::get_items() as $item) {
            $out[$item['key']] = $item['status'];
        }
        return $out;
    }

    /**
     * Right after installation: web services, REST and key are missing, each with the action that solves it.
     */
    public function test_fresh_installation(): void {
        $this->resetAfterTest();
        set_config('enablewebservices', 0);
        set_config('webserviceprotocols', '');
        $items = diagnostics::get_items();
        $keys = array_column($items, 'key');
        $this->assertSame(
            ['webservices', 'rest', 'user', 'role', 'roleassign', 'service', 'serviceuser', 'token', 'email', 'versions'],
            $keys
        );
        $statuses = $this->statuses();
        $this->assertSame(diagnostics::ERROR, $statuses['webservices']);
        $this->assertSame(diagnostics::ERROR, $statuses['rest']);
        $this->assertSame(diagnostics::ERROR, $statuses['token']);
        foreach (['user', 'role', 'roleassign', 'service', 'serviceuser'] as $key) {
            $this->assertSame(diagnostics::OK, $statuses[$key], $key);
        }
        $this->assertSame(['enablews', 'generatekey'], diagnostics::needed_actions($items));
        $this->assertTrue(diagnostics::webservices_need_enabling());
        $this->assertTrue(diagnostics::has_errors($items));
        foreach ($items as $item) {
            $this->assertNotEmpty($item['title']);
            $this->assertNotEmpty($item['message']);
        }
    }

    /**
     * With web services, REST and a key, nothing is left to do.
     */
    public function test_ready(): void {
        $this->resetAfterTest();
        set_config('enablewebservices', 1);
        set_config('webserviceprotocols', 'rest');
        setup::generate_token(false);
        $items = diagnostics::get_items();
        $this->assertSame([], diagnostics::needed_actions($items));
        $this->assertFalse(diagnostics::webservices_need_enabling());
        $this->assertFalse(diagnostics::has_errors($items));
    }

    /**
     * Missing role capability, missing service and suspended service user are reported.
     */
    public function test_problems_are_reported(): void {
        global $DB;
        $this->resetAfterTest();
        $role = setup::get_role();
        unassign_capability('moodle/user:create', $role->id, \context_system::instance()->id);
        $this->assertSame(diagnostics::ERROR, $this->statuses()['role']);
        $this->assertContains('fix', diagnostics::needed_actions(diagnostics::get_items()));

        $DB->set_field('user', 'suspended', 1, ['id' => setup::get_user()->id]);
        $statuses = $this->statuses();
        $this->assertSame(diagnostics::ERROR, $statuses['user']);

        $DB->delete_records('external_services', ['component' => setup::COMPONENT]);
        $this->assertSame(diagnostics::ERROR, $this->statuses()['service']);
    }

    /**
     * A service conflict is explained on the service line.
     */
    public function test_service_conflict_is_reported(): void {
        global $DB;
        $this->resetAfterTest();
        $DB->delete_records('external_services', ['component' => setup::COMPONENT]);
        $DB->insert_record('external_services', (object) [
            'name' => setup::SERVICE_NAME,
            'shortname' => 'otherservice',
            'enabled' => 0,
            'restrictedusers' => 1,
            'timecreated' => time(),
        ]);
        foreach (diagnostics::get_items() as $item) {
            if ($item['key'] === 'service') {
                $this->assertSame(diagnostics::ERROR, $item['status']);
                $this->assertStringContainsString('otherservice', $item['message']);
            }
        }
    }

    /**
     * E-mail sending states.
     */
    public function test_email(): void {
        $this->resetAfterTest();
        set_config('noemailever', 1);
        $this->assertSame(diagnostics::WARNING, $this->statuses()['email']);
        set_config('noemailever', 0);
        set_config('smtphosts', 'smtp.example.com:587');
        $this->assertSame(diagnostics::OK, $this->statuses()['email']);
    }
}
