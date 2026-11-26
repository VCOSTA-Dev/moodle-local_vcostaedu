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

#[\PHPUnit\Framework\Attributes\CoversClass(\local_vcostaedu\setup::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_vcostaedu\environment::class)]
/**
 * Tests for the installation, repair and removal of what the plugin creates.
 *
 * @package    local_vcostaedu
 * @category   test
 * @copyright  2026 VCOSTA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_vcostaedu\setup
 * @covers     \local_vcostaedu\environment
 */
final class setup_test extends \advanced_testcase {
    /**
     * Counts of what the plugin creates, to prove nothing is duplicated.
     *
     * @return array
     */
    private function counts(): array {
        global $DB;
        $role = setup::get_role();
        $service = setup::get_service();
        return [
            'roles' => $DB->count_records('role', ['shortname' => setup::ROLE_SHORTNAME]),
            'capabilities' => $role ? $DB->count_records('role_capabilities', ['roleid' => $role->id]) : 0,
            'assignable' => $role ? $DB->count_records('role_allow_assign', ['roleid' => $role->id]) : 0,
            'users' => $DB->count_records('user', ['username' => setup::USERNAME, 'deleted' => 0]),
            'assignments' => $role ? $DB->count_records('role_assignments', ['roleid' => $role->id]) : 0,
            'services' => $DB->count_records('external_services', ['component' => setup::COMPONENT]),
            'functions' => $service ? $DB->count_records('external_services_functions', ['externalserviceid' => $service->id]) : 0,
            'authorised' => $service ? $DB->count_records('external_services_users', ['externalserviceid' => $service->id]) : 0,
        ];
    }

    /**
     * Includes the uninstall script.
     */
    private function require_uninstall(): void {
        global $CFG;
        require_once($CFG->dirroot . '/local/vcostaedu/db/uninstall.php');
    }

    /**
     * The installation creates the role, the user, the assignment and the service, and records what it created.
     */
    public function test_installation_creates_everything(): void {
        $this->resetAfterTest();

        $role = setup::get_role();
        $user = setup::get_user();
        $service = setup::get_service();
        $this->assertNotNull($role);
        $this->assertNotNull($user);
        $this->assertNotNull($service);
        $this->assertSame(setup::SERVICE_NAME, $service->name);
        $this->assertSame('manual', $user->auth);
        $this->assertSame(setup::USER_EMAIL, $user->email);
        $this->assertEquals($role->id, get_config(setup::COMPONENT, setup::CONFIG_CREATED_ROLE));
        $this->assertEquals($user->id, get_config(setup::COMPONENT, setup::CONFIG_CREATED_USER));
        $this->assertSame([], setup::missing_capabilities((int) $role->id));
        $this->assertSame([], setup::missing_assignable_roles((int) $role->id));
        $this->assertSame([], setup::missing_functions());
        $this->assertTrue(setup::is_service_user((int) $user->id));
        $this->assertTrue(user_has_role_assignment($user->id, $role->id, \context_system::instance()->id));
        $this->assertCount(17, setup::functions());
        $this->assertCount(29, setup::capabilities());
        // Web services are the administrator's decision: setting up does not switch them on.
        set_config('enablewebservices', 0);
        set_config('webserviceprotocols', '');
        setup::run();
        $this->assertEmpty(get_config('core', 'enablewebservices'));
        $this->assertEmpty(get_config('core', 'webserviceprotocols'));
        // No key is created by the installation.
        $this->assertNull(setup::get_token());
    }

    /**
     * The role has exactly the capabilities the service functions need, at system level, and not the
     * ones left out on purpose.
     */
    public function test_role_capabilities(): void {
        global $DB;
        $this->resetAfterTest();
        $expected = [
            'webservice/rest:use',
            'moodle/user:create',
            'moodle/user:update',
            'moodle/user:viewdetails',
            'moodle/user:viewhiddendetails',
            'moodle/user:viewalldetails',
            'moodle/course:view',
            'moodle/course:viewhiddencourses',
            'moodle/course:create',
            'moodle/course:update',
            'moodle/course:changefullname',
            'moodle/course:changeshortname',
            'moodle/course:changeidnumber',
            'moodle/course:changesummary',
            'moodle/course:changecategory',
            'moodle/course:visibility',
            'moodle/course:viewparticipants',
            'moodle/course:useremail',
            'moodle/course:enrolreview',
            'moodle/category:manage',
            'moodle/category:viewhiddencategories',
            'moodle/role:assign',
            'moodle/site:accessallgroups',
            'enrol/manual:enrol',
            'enrol/manual:unenrol',
            'enrol/manual:manage',
            'moodle/grade:viewall',
            'gradereport/user:view',
            'local/vcostaedu:requestloginurl',
        ];
        $role = setup::get_role();
        $granted = $DB->get_records_menu('role_capabilities', [
            'roleid' => $role->id,
            'contextid' => \context_system::instance()->id,
            'permission' => CAP_ALLOW,
        ], '', 'capability, id');
        $granted = array_keys($granted);
        sort($granted);
        sort($expected);
        $this->assertSame($expected, $granted);
        $this->assertEquals(count($expected), $DB->count_records('role_capabilities', ['roleid' => $role->id]));
        // Left out on purpose: it shows the identity fields of every user of the site (see docs/REVIEW.md).
        $this->assertNotContains('moodle/site:viewuseridentity', $granted);
    }

    /**
     * With the role, the service user keeps a course in step with the academic record: full name,
     * short name, id number, summary and category.
     */
    public function test_service_user_updates_course_fields(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/externallib.php');
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $from = $generator->create_category(['name' => 'From']);
        $to = $generator->create_category(['name' => 'To']);
        $course = $generator->create_course([
            'category' => $from->id,
            'fullname' => 'Old name',
            'shortname' => 'OLD',
            'idnumber' => 'OLD-1',
            'summary' => 'Old summary',
        ]);
        $this->setUser(setup::get_user());

        $result = \core_course_external::update_courses([[
            'id' => $course->id,
            'fullname' => 'New name',
            'shortname' => 'NEW',
            'idnumber' => 'NEW-1',
            'summary' => 'New summary',
            'categoryid' => $to->id,
        ]]);

        $this->assertSame([], $result['warnings']);
        $saved = $DB->get_record('course', ['id' => $course->id]);
        $this->assertSame('New name', $saved->fullname);
        $this->assertSame('NEW', $saved->shortname);
        $this->assertSame('NEW-1', $saved->idnumber);
        $this->assertSame('New summary', $saved->summary);
        $this->assertEquals($to->id, $saved->category);
    }

    /**
     * Running the setup again duplicates nothing.
     */
    public function test_run_is_idempotent(): void {
        $this->resetAfterTest();
        $before = $this->counts();
        setup::run();
        setup::run();
        $this->assertSame($before, $this->counts());
        $this->assertSame(1, $before['roles']);
        $this->assertSame(1, $before['users']);
        $this->assertSame(1, $before['services']);
        $this->assertSame(17, $before['functions']);
    }

    /**
     * The repair puts back a capability, an assign permission and a function that were removed.
     */
    public function test_run_repairs_missing_items(): void {
        global $DB;
        $this->resetAfterTest();
        $role = setup::get_role();
        $syscontext = \context_system::instance();
        unassign_capability('moodle/course:create', $role->id, $syscontext->id);
        $studentid = $DB->get_field('role', 'id', ['shortname' => 'student']);
        $DB->delete_records('role_allow_assign', ['roleid' => $role->id, 'allowassign' => $studentid]);
        $DB->delete_records('external_services_functions', [
            'externalserviceid' => setup::get_service()->id,
            'functionname' => 'core_user_create_users',
        ]);
        $this->assertSame(['moodle/course:create'], setup::missing_capabilities((int) $role->id));
        $this->assertSame(['core_user_create_users'], setup::missing_functions());

        setup::run();

        $this->assertSame([], setup::missing_capabilities((int) $role->id));
        $this->assertSame([], setup::missing_assignable_roles((int) $role->id));
        $this->assertSame([], setup::missing_functions());
    }

    /**
     * A role and a user that already exist are reused and not recorded as created by the plugin.
     */
    public function test_existing_role_and_user_are_reused(): void {
        $this->resetAfterTest();
        // Start from a site without them, then create both by hand, as another tool would.
        setup::remove_created_items();
        $roleid = create_role('Existing', setup::ROLE_SHORTNAME, '');
        $user = $this->getDataGenerator()->create_user(['username' => setup::USERNAME]);

        setup::run();

        $this->assertEquals($roleid, setup::get_role()->id);
        $this->assertEquals($user->id, setup::get_user()->id);
        $this->assertFalse(get_config(setup::COMPONENT, setup::CONFIG_CREATED_ROLE));
        $this->assertFalse(get_config(setup::COMPONENT, setup::CONFIG_CREATED_USER));
        $this->assertSame([], setup::missing_capabilities($roleid));
    }

    /**
     * Uninstalling removes the role and the user the plugin created.
     */
    public function test_uninstall_removes_created_items(): void {
        global $DB;
        $this->resetAfterTest();
        $this->require_uninstall();
        $role = setup::get_role();
        $user = setup::get_user();
        $this->assertTrue(xmldb_local_vcostaedu_uninstall());

        $this->assertFalse($DB->record_exists('role', ['id' => $role->id]));
        $this->assertTrue((bool) $DB->get_field('user', 'deleted', ['id' => $user->id]));
        $this->assertNull(setup::get_role());
        $this->assertNull(setup::get_user());
        $this->assertFalse(get_config(setup::COMPONENT, setup::CONFIG_CREATED_ROLE));
    }

    /**
     * Uninstalling leaves a role and a user that existed before the plugin.
     */
    public function test_uninstall_keeps_preexisting_items(): void {
        $this->resetAfterTest();
        $this->require_uninstall();
        setup::remove_created_items();
        $roleid = create_role('Existing', setup::ROLE_SHORTNAME, '');
        $user = $this->getDataGenerator()->create_user(['username' => setup::USERNAME]);
        setup::run();

        $this->assertTrue(xmldb_local_vcostaedu_uninstall());

        $this->assertEquals($roleid, setup::get_role()->id);
        $this->assertEquals($user->id, setup::get_user()->id);
    }

    /**
     * Uninstalling removes pending login keys.
     */
    public function test_uninstall_removes_login_keys(): void {
        global $DB;
        $this->resetAfterTest();
        $this->require_uninstall();
        $student = $this->getDataGenerator()->create_user();
        login::create_key((int) $student->id, 60);
        xmldb_local_vcostaedu_uninstall();
        $this->assertFalse($DB->record_exists('user_private_key', ['script' => setup::KEY_SCRIPT]));
    }

    /**
     * Another service with the plugin service name blocks the installation before anything is created.
     */
    public function test_service_name_conflict(): void {
        global $DB, $CFG;
        $this->resetAfterTest();
        require_once($CFG->libdir . '/environmentlib.php');
        require_once($CFG->dirroot . '/local/vcostaedu/db/install.php');

        // No conflict with the plugin's own service.
        $this->assertNull(setup::service_conflict());
        $this->assertNull(environment::check_service_name(new \environment_results('custom_check')));

        // A site where another service already has the name.
        $DB->delete_records('external_services', ['component' => setup::COMPONENT]);
        $DB->insert_record('external_services', (object) [
            'name' => setup::SERVICE_NAME,
            'shortname' => 'otherservice',
            'enabled' => 0,
            'restrictedusers' => 1,
            'component' => null,
            'timecreated' => time(),
            'downloadfiles' => 0,
            'uploadfiles' => 0,
        ]);
        $this->assertSame('otherservice', setup::service_conflict()->shortname);

        // The environment check fails, so Moodle stops before installing.
        $result = environment::check_service_name(new \environment_results('custom_check'));
        $this->assertNotNull($result);
        $this->assertFalse($result->getStatus());
        $this->assertSame('servicenameconflict', $result->getFeedbackStr()[0]);

        // The installation itself also refuses, as a second barrier.
        setup::remove_created_items();
        try {
            xmldb_local_vcostaedu_install();
            $this->fail('The installation should stop on a service name conflict.');
        } catch (\moodle_exception $e) {
            $this->assertSame('servicenameconflict', $e->errorcode);
        }
        $this->assertNull(setup::get_role());
        $this->assertNull(setup::get_user());
    }

    /**
     * A new key revokes only the plugin keys.
     */
    public function test_generate_token(): void {
        global $DB;
        $this->resetAfterTest();
        $first = setup::generate_token(false);
        $this->assertSame($first, setup::get_token()->token);

        $second = setup::generate_token(true);
        $this->assertNotSame($first, $second);
        $this->assertFalse($DB->record_exists('external_tokens', ['token' => $first]));
        $this->assertSame($second, setup::get_token()->token);
    }

    /**
     * The login link validity always stays inside the allowed range.
     */
    public function test_login_validity(): void {
        $this->resetAfterTest();
        set_config('loginvalidity', 90, setup::COMPONENT);
        $this->assertSame(90, setup::login_validity());
        set_config('loginvalidity', 5, setup::COMPONENT);
        $this->assertSame(setup::LOGIN_VALIDITY_MIN, setup::login_validity());
        set_config('loginvalidity', 9999, setup::COMPONENT);
        $this->assertSame(setup::LOGIN_VALIDITY_MAX, setup::login_validity());
        set_config('loginvalidity', 'abc', setup::COMPONENT);
        $this->assertSame(setup::LOGIN_VALIDITY_DEFAULT, setup::login_validity());
    }
}
