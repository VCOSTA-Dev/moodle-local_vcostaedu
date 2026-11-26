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

use context_system;
use stdClass;

/**
 * Creates, repairs and removes what the VCOSTAEDU connection needs inside Moodle.
 *
 * Every method that creates something is idempotent: it only adds what is missing.
 * The plugin records in its own settings the role and the user it created itself,
 * so that uninstalling removes them and leaves alone anything that existed before.
 *
 * @package    local_vcostaedu
 * @copyright  2026 VCOSTA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class setup {
    /** @var string Component name. */
    public const COMPONENT = 'local_vcostaedu';

    /** @var string Name of the service declared in db/services.php. */
    public const SERVICE_NAME = 'VCOSTAEDU';

    /** @var string Short name of the service declared in db/services.php. */
    public const SERVICE_SHORTNAME = 'local_vcostaedu';

    /** @var string Short name of the role given to the service user. */
    public const ROLE_SHORTNAME = 'vcostaedu_ws';

    /** @var string Username of the service user. */
    public const USERNAME = 'vcostaedu_ws';

    /** @var string Address of the service user. The .invalid domain never receives mail. */
    public const USER_EMAIL = 'vcostaedu-ws@example.invalid';

    /** @var string Name given to the tokens created by the plugin. */
    public const TOKEN_NAME = 'VCOSTAEDU (local_vcostaedu)';

    /** @var string Script name of the one-time login keys in the user_private_key table. */
    public const KEY_SCRIPT = 'local_vcostaedu';

    /** @var int Default validity of a login link, in seconds. */
    public const LOGIN_VALIDITY_DEFAULT = 60;

    /** @var int Minimum validity of a login link, in seconds. */
    public const LOGIN_VALIDITY_MIN = 15;

    /** @var int Maximum validity of a login link, in seconds. */
    public const LOGIN_VALIDITY_MAX = 300;

    /** @var string Plugin setting holding the id of the role the plugin created. */
    public const CONFIG_CREATED_ROLE = 'createdroleid';

    /** @var string Plugin setting holding the id of the user the plugin created. */
    public const CONFIG_CREATED_USER = 'createduserid';

    /** @var string[] Roles the service role may assign. */
    public const ASSIGNABLE_ROLES = ['student', 'teacher', 'editingteacher'];

    /** @var string[] Core functions used by VCOSTAEDU. The plugin's own functions are added in functions(). */
    public const CORE_FUNCTIONS = [
        'core_webservice_get_site_info',
        'core_user_create_users',
        'core_user_update_users',
        'core_user_get_users_by_field',
        'core_user_get_users',
        'core_course_get_categories',
        'core_course_create_categories',
        'core_course_get_courses_by_field',
        'core_course_create_courses',
        'core_course_update_courses',
        'enrol_manual_enrol_users',
        'enrol_manual_unenrol_users',
        'core_enrol_get_enrolled_users',
        'core_enrol_get_users_courses',
        'gradereport_user_get_grade_items',
    ];

    /** @var string[] Capabilities the service role needs. The plugin's own one is added in capabilities(). */
    public const CORE_CAPABILITIES = [
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
        // Each checked by core_course_update_courses when the field changes: keeping the course
        // in step with the academic record (name, short name, id number, summary, category).
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
    ];

    /**
     * All functions the plugin service must contain.
     *
     * @return string[]
     */
    public static function functions(): array {
        return array_merge(self::CORE_FUNCTIONS, ['local_vcostaedu_request_login_url', 'local_vcostaedu_find_users_by_email']);
    }

    /**
     * All capabilities the service role must have.
     *
     * @return string[]
     */
    public static function capabilities(): array {
        return array_merge(self::CORE_CAPABILITIES, ['local/vcostaedu:requestloginurl']);
    }

    /**
     * Current login link validity, always inside the allowed range.
     *
     * @return int seconds
     */
    public static function login_validity(): int {
        $value = get_config(self::COMPONENT, 'loginvalidity');
        if ($value === false || $value === '' || !is_numeric($value)) {
            return self::LOGIN_VALIDITY_DEFAULT;
        }
        return max(self::LOGIN_VALIDITY_MIN, min(self::LOGIN_VALIDITY_MAX, (int) $value));
    }

    /**
     * Runs every repair step. Safe to run many times.
     *
     * @return stdClass the service user
     */
    public static function run(): stdClass {
        $roleid = self::ensure_role();
        $user = self::ensure_user();
        self::ensure_role_assignment($roleid, (int) $user->id);
        self::ensure_service_functions();
        self::ensure_service_user((int) $user->id);
        return $user;
    }

    /**
     * Returns the service role record, or null.
     *
     * @return stdClass|null
     */
    public static function get_role(): ?stdClass {
        global $DB;
        return $DB->get_record('role', ['shortname' => self::ROLE_SHORTNAME]) ?: null;
    }

    /**
     * Creates the role if needed and adds any missing capability or assign permission.
     *
     * @return int role id
     */
    public static function ensure_role(): int {
        global $CFG;
        require_once($CFG->libdir . '/accesslib.php');

        $role = self::get_role();
        if ($role) {
            $roleid = (int) $role->id;
        } else {
            $roleid = create_role(
                get_string('servicerolename', self::COMPONENT),
                self::ROLE_SHORTNAME,
                get_string('serviceroledescription', self::COMPONENT)
            );
            set_config(self::CONFIG_CREATED_ROLE, $roleid, self::COMPONENT);
        }

        $levels = get_role_contextlevels($roleid);
        if (!in_array(CONTEXT_SYSTEM, $levels)) {
            $levels[] = CONTEXT_SYSTEM;
            set_role_contextlevels($roleid, $levels);
        }

        $syscontext = context_system::instance();
        $changed = false;
        foreach (self::missing_capabilities($roleid) as $capability) {
            if (get_capability_info($capability)) {
                assign_capability($capability, CAP_ALLOW, $roleid, $syscontext->id, true);
                $changed = true;
            }
        }
        if ($changed) {
            $syscontext->mark_dirty();
        }

        foreach (self::missing_assignable_roles($roleid) as $targetid) {
            core_role_set_assign_allowed($roleid, $targetid);
        }

        return $roleid;
    }

    /**
     * Capabilities the role is missing at system level.
     *
     * @param int $roleid
     * @return string[]
     */
    public static function missing_capabilities(int $roleid): array {
        global $DB;
        $syscontext = context_system::instance();
        $have = $DB->get_records_menu(
            'role_capabilities',
            ['roleid' => $roleid, 'contextid' => $syscontext->id, 'permission' => CAP_ALLOW],
            '',
            'capability, permission'
        );
        $missing = [];
        foreach (self::capabilities() as $capability) {
            if (!isset($have[$capability])) {
                $missing[] = $capability;
            }
        }
        return $missing;
    }

    /**
     * Capabilities from the list that do not exist in this Moodle at all.
     *
     * @return string[]
     */
    public static function unknown_capabilities(): array {
        return array_values(array_filter(self::capabilities(), fn($capability) => !get_capability_info($capability)));
    }

    /**
     * Target roles the role cannot assign yet.
     *
     * @param int $roleid
     * @return int[] role short name => role id
     */
    public static function missing_assignable_roles(int $roleid): array {
        global $DB;
        $missing = [];
        foreach (self::ASSIGNABLE_ROLES as $shortname) {
            $targetid = $DB->get_field('role', 'id', ['shortname' => $shortname]);
            if ($targetid && !$DB->record_exists('role_allow_assign', ['roleid' => $roleid, 'allowassign' => $targetid])) {
                $missing[$shortname] = (int) $targetid;
            }
        }
        return $missing;
    }

    /**
     * Returns the service user (not deleted), or null.
     *
     * @return stdClass|null
     */
    public static function get_user(): ?stdClass {
        global $DB, $CFG;
        return $DB->get_record('user', [
            'username' => self::USERNAME,
            'mnethostid' => $CFG->mnet_localhost_id,
            'deleted' => 0,
        ]) ?: null;
    }

    /**
     * Creates the service user if it does not exist. An existing user is reused as it is.
     *
     * @return stdClass
     */
    public static function ensure_user(): stdClass {
        global $CFG;
        if ($user = self::get_user()) {
            return $user;
        }
        $user = (object) [
            'username' => self::USERNAME,
            'auth' => 'manual',
            'confirmed' => 1,
            'mnethostid' => $CFG->mnet_localhost_id,
            'firstname' => 'VCOSTAEDU',
            'lastname' => get_string('serviceuserlastname', self::COMPONENT),
            'email' => self::USER_EMAIL,
            'emailstop' => 1,
            // Nobody knows or uses this password: the account only talks through its token.
            'password' => self::random_password(),
            'description' => get_string('serviceuserdescription', self::COMPONENT),
        ];
        $userid = self::create_user($user);
        set_config(self::CONFIG_CREATED_USER, $userid, self::COMPONENT);
        return self::get_user();
    }

    /**
     * Creates a user with the API of this Moodle version.
     *
     * From Moodle 5.3, \core\user::create_user() (user_create_user() is deprecated there).
     * Up to Moodle 5.2, user_create_user(), which is not deprecated in those versions.
     *
     * @param stdClass $user
     * @return int user id
     */
    public static function create_user(stdClass $user): int {
        global $CFG;
        if (method_exists(\core\user::class, 'create_user')) {
            return \core\user::create_user($user, true, false);
        }
        require_once($CFG->dirroot . '/user/lib.php');
        return user_create_user($user, true, false);
    }

    /**
     * Deletes a user with the API of this Moodle version.
     *
     * From Moodle 5.3, \core\user::delete_user() (delete_user() is deprecated there).
     * Up to Moodle 5.2, delete_user(), which is not deprecated in those versions.
     *
     * @param stdClass $user
     */
    public static function delete_user(stdClass $user): void {
        if (method_exists(\core\user::class, 'delete_user')) {
            \core\user::delete_user($user);
        } else {
            delete_user($user);
        }
    }

    /**
     * Random password that satisfies any password policy.
     *
     * @return string
     */
    protected static function random_password(): string {
        return random_string(24) . 'Aa1!' . random_string(8);
    }

    /**
     * Gives the role to the user at system level if not given yet.
     *
     * @param int $roleid
     * @param int $userid
     */
    public static function ensure_role_assignment(int $roleid, int $userid): void {
        $syscontext = context_system::instance();
        if (!user_has_role_assignment($userid, $roleid, $syscontext->id)) {
            role_assign($roleid, $userid, $syscontext->id);
        }
    }

    /**
     * Returns the plugin service record, or null.
     *
     * @return stdClass|null
     */
    public static function get_service(): ?stdClass {
        global $DB;
        return $DB->get_record('external_services', ['shortname' => self::SERVICE_SHORTNAME]) ?: null;
    }

    /**
     * Another service, not the plugin's, that already uses the name or the short name of the plugin service.
     *
     * Service names are unique in Moodle, so such a service blocks the installation.
     *
     * @return stdClass|null
     */
    public static function service_conflict(): ?stdClass {
        global $DB;
        $select = '(name = :name OR shortname = :shortname) AND (component IS NULL OR component <> :component)';
        $records = $DB->get_records_select('external_services', $select, [
            'name' => self::SERVICE_NAME,
            'shortname' => self::SERVICE_SHORTNAME,
            'component' => self::COMPONENT,
        ], 'id', 'id, name, shortname, component', 0, 1);
        return $records ? reset($records) : null;
    }

    /**
     * Stops the installation when another service already uses the plugin service name.
     *
     * @throws \moodle_exception
     */
    public static function require_no_service_conflict(): void {
        if ($other = self::service_conflict()) {
            throw new \moodle_exception('servicenameconflict', self::COMPONENT, '', (object) [
                'name' => self::SERVICE_NAME,
                'shortname' => self::SERVICE_SHORTNAME,
                'othershortname' => $other->shortname ?? '-',
            ]);
        }
    }

    /**
     * Creates the service from db/services.php if it is missing and adds any missing function to it.
     */
    public static function ensure_service_functions(): void {
        global $DB, $CFG;
        if (!self::get_service()) {
            self::require_no_service_conflict();
            require_once($CFG->libdir . '/upgradelib.php');
            external_update_descriptions(self::COMPONENT);
        }
        $service = self::get_service();
        if (!$service) {
            return;
        }
        foreach (self::missing_functions() as $function) {
            if ($DB->record_exists('external_functions', ['name' => $function])) {
                $DB->insert_record(
                    'external_services_functions',
                    (object) ['externalserviceid' => $service->id, 'functionname' => $function]
                );
            }
        }
        if (!$service->enabled) {
            $DB->set_field('external_services', 'enabled', 1, ['id' => $service->id]);
        }
    }

    /**
     * Functions missing from the plugin service.
     *
     * @return string[]
     */
    public static function missing_functions(): array {
        global $DB;
        $service = self::get_service();
        if (!$service) {
            return self::functions();
        }
        $have = $DB->get_records_menu(
            'external_services_functions',
            ['externalserviceid' => $service->id],
            '',
            'functionname, id'
        );
        return array_values(array_filter(self::functions(), fn($function) => !isset($have[$function])));
    }

    /**
     * Functions from the list that do not exist in this Moodle at all.
     *
     * @return string[]
     */
    public static function unknown_functions(): array {
        global $DB;
        return array_values(array_filter(
            self::functions(),
            fn($function) => !$DB->record_exists('external_functions', ['name' => $function])
        ));
    }

    /**
     * Authorises the user in the plugin service if not authorised yet.
     *
     * @param int $userid
     */
    public static function ensure_service_user(int $userid): void {
        global $DB;
        $service = self::get_service();
        if (!$service) {
            return;
        }
        if (!$DB->record_exists('external_services_users', ['externalserviceid' => $service->id, 'userid' => $userid])) {
            $DB->insert_record('external_services_users', (object) [
                'externalserviceid' => $service->id,
                'userid' => $userid,
                'iprestriction' => null,
                'validuntil' => null,
                'timecreated' => time(),
            ]);
        }
    }

    /**
     * Is the user authorised in the plugin service?
     *
     * @param int $userid
     * @return bool
     */
    public static function is_service_user(int $userid): bool {
        global $DB;
        $service = self::get_service();
        return $service && $DB->record_exists(
            'external_services_users',
            ['externalserviceid' => $service->id, 'userid' => $userid]
        );
    }

    /**
     * Returns the newest valid token of the service user for the plugin service, or null.
     *
     * @return stdClass|null
     */
    public static function get_token(): ?stdClass {
        global $DB;
        $service = self::get_service();
        $user = self::get_user();
        if (!$service || !$user) {
            return null;
        }
        $tokens = $DB->get_records_select(
            'external_tokens',
            'externalserviceid = :serviceid AND userid = :userid AND tokentype = :tokentype
             AND (validuntil = 0 OR validuntil IS NULL OR validuntil > :now)',
            ['serviceid' => $service->id, 'userid' => $user->id, 'tokentype' => EXTERNAL_TOKEN_PERMANENT, 'now' => time()],
            'timecreated DESC, id DESC',
            '*',
            0,
            1
        );
        return $tokens ? reset($tokens) : null;
    }

    /**
     * Creates a new permanent token for the service user. Optionally revokes the previous ones.
     *
     * Only tokens of the plugin service are revoked; tokens of other services are never touched.
     *
     * @param bool $revoke delete the existing plugin tokens first
     * @return string the new token
     */
    public static function generate_token(bool $revoke): string {
        global $DB;
        $user = self::run();
        $service = self::get_service();
        if (!$service) {
            throw new \moodle_exception('servicemissing', self::COMPONENT);
        }
        if ($revoke) {
            $DB->delete_records('external_tokens', [
                'externalserviceid' => $service->id,
                'userid' => $user->id,
                'tokentype' => EXTERNAL_TOKEN_PERMANENT,
            ]);
        }
        return \core_external\util::generate_token(
            EXTERNAL_TOKEN_PERMANENT,
            $service,
            (int) $user->id,
            context_system::instance(),
            0,
            '',
            self::TOKEN_NAME
        );
    }

    /**
     * Removes the role and the user the plugin created. Used when the plugin is uninstalled.
     *
     * Items that existed before the plugin (not recorded as created by it) stay on the site.
     *
     * @return string[] what was removed, for the log
     */
    public static function remove_created_items(): array {
        global $DB, $CFG;
        require_once($CFG->libdir . '/accesslib.php');
        $removed = [];

        $userid = (int) get_config(self::COMPONENT, self::CONFIG_CREATED_USER);
        $user = $userid ? $DB->get_record('user', ['id' => $userid, 'username' => self::USERNAME, 'deleted' => 0]) : false;
        if ($user) {
            $DB->delete_records('external_tokens', ['userid' => $user->id]);
            $DB->delete_records('external_services_users', ['userid' => $user->id]);
            self::delete_user($user);
            $removed[] = 'user';
        }

        $roleid = (int) get_config(self::COMPONENT, self::CONFIG_CREATED_ROLE);
        if ($roleid && $DB->record_exists('role', ['id' => $roleid, 'shortname' => self::ROLE_SHORTNAME])) {
            delete_role($roleid);
            $removed[] = 'role';
        }

        unset_config(self::CONFIG_CREATED_USER, self::COMPONENT);
        unset_config(self::CONFIG_CREATED_ROLE, self::COMPONENT);
        return $removed;
    }
}
