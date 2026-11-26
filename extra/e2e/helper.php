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

/**
 * Command line helper for extra/e2e/e2e.sh. TEST SITES ONLY: it deletes users and changes settings.
 *
 * Not part of the plugin packages. Runs only when E2E_VCOSTAEDU=1 and MOODLE_DIR points
 * to the Moodle code (the folder with config.php).
 *
 * @package    local_vcostaedu
 * @copyright  2026 VCOSTA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// This test-only script lives outside the Moodle code and includes config.php from MOODLE_DIR,
// a path the sniff cannot recognise.
// phpcs:disable moodle.Files.MoodleInternal.MoodleInternalGlobalState

define('CLI_SCRIPT', true);

if (getenv('E2E_VCOSTAEDU') !== '1' || !getenv('MOODLE_DIR')) {
    fwrite(STDERR, "Use only through extra/e2e/e2e.sh on a test site.\n");
    exit(2);
}
require(rtrim(getenv('MOODLE_DIR'), '/') . '/config.php');
require_once($CFG->libdir . '/accesslib.php');

$action = $argv[1] ?? '';
$arg1 = $argv[2] ?? '';
$arg2 = $argv[3] ?? '';

// Plugin names, readable even when the plugin is not installed yet.

/** @var string Short name of the plugin service. */
const E2E_SERVICE_SHORTNAME = 'local_vcostaedu';

/** @var string Short name of the service role. */
const E2E_ROLE_SHORTNAME = 'vcostaedu_ws';

/** @var string Username of the service user. */
const E2E_USERNAME = 'vcostaedu_ws';

/** @var string Script name of the one-time login keys. */
const E2E_KEY_SCRIPT = 'local_vcostaedu';

/**
 * Finds a not deleted user by username.
 *
 * @param string $username
 * @return stdClass|false
 */
function e2e_user(string $username) {
    global $DB, $CFG;
    return $DB->get_record('user', ['username' => $username, 'mnethostid' => $CFG->mnet_localhost_id, 'deleted' => 0]);
}

/**
 * Creates a user.
 *
 * @param string $username
 * @param string $firstname
 * @param string $password
 * @return int user id
 */
function e2e_create_user(string $username, string $firstname, string $password): int {
    global $CFG;
    $user = (object) [
        'username' => $username,
        'auth' => 'manual',
        'confirmed' => 1,
        'mnethostid' => $CFG->mnet_localhost_id,
        'firstname' => $firstname,
        'lastname' => 'Example',
        'email' => $username . '@example.com',
        'password' => $password,
    ];
    // Moodle 5.3 has the core user class method; older versions only the user library function.
    if (method_exists(\core\user::class, 'create_user')) {
        return \core\user::create_user($user, true, false);
    }
    require_once($CFG->dirroot . '/user/lib.php');
    return user_create_user($user, true, false);
}

/**
 * Deletes a user (from Moodle 5.3, \core\user::delete_user(); before, delete_user()).
 *
 * @param stdClass $user
 */
function e2e_delete_user(stdClass $user): void {
    if (method_exists(\core\user::class, 'delete_user')) {
        \core\user::delete_user($user);
    } else {
        delete_user($user);
    }
}

switch ($action) {
    case 'create-admin':
        // Temporary site administrator for the page tests; the password comes from E2E_ADMIN_PASS.
        $password = (string) getenv('E2E_ADMIN_PASS');
        $user = e2e_user($arg1);
        $userid = $user ? (int) $user->id : e2e_create_user($arg1, 'Example', $password);
        if ($user) {
            update_internal_user_password($user, $password);
        }
        if ($arg2 !== '') {
            // The language of this user only; the site language stays as it is.
            $DB->set_field('user', 'lang', $arg2, ['id' => $userid]);
        }
        $admins = array_filter(explode(',', $CFG->siteadmins));
        if (!in_array($userid, $admins)) {
            $admins[] = $userid;
            set_config('siteadmins', implode(',', $admins));
        }
        echo "ok\n";
        break;

    case 'set-validity':
        set_config('loginvalidity', (int) $arg1, 'local_vcostaedu');
        echo "ok\n";
        break;

    case 'set-config':
        set_config($arg1, $arg2);
        echo "ok\n";
        break;

    case 'count-keys':
        $user = e2e_user($arg1);
        echo $user ? $DB->count_records('user_private_key', ['script' => E2E_KEY_SCRIPT, 'userid' => $user->id]) : 0, "\n";
        break;

    case 'delete-user':
        if ($user = e2e_user($arg1)) {
            e2e_delete_user($user);
        }
        echo "ok\n";
        break;

    case 'make-deleted-record':
        // A deleted record that kept its username, as some external tools leave it.
        $user = e2e_user($arg1);
        $userid = $user ? (int) $user->id : e2e_create_user($arg1, 'Deleted', 'E2e!' . random_string(20) . '9a');
        $DB->set_field('user', 'deleted', 1, ['id' => $userid]);
        echo "ok\n";
        break;

    case 'set-field':
        $user = e2e_user($arg1);
        [$field, $value] = explode('=', $arg2, 2);
        if (!in_array($field, ['auth', 'confirmed', 'suspended'])) {
            exit(3);
        }
        $DB->set_field('user', $field, $value, ['id' => $user->id]);
        echo "ok\n";
        break;

    case 'create-noperm':
        // A user allowed to use REST, with a key for the plugin service, but without the plugin capability.
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'e2e_noperm']);
        if (!$roleid) {
            $roleid = create_role('E2E no permission', 'e2e_noperm', '');
            set_role_contextlevels($roleid, [CONTEXT_SYSTEM]);
            assign_capability('webservice/rest:use', CAP_ALLOW, $roleid, context_system::instance()->id, true);
        }
        $user = e2e_user('e2e_noperm');
        $userid = $user ? (int) $user->id : e2e_create_user('e2e_noperm', 'Noperm', 'E2e!' . random_string(20) . '9a');
        role_assign($roleid, $userid, context_system::instance()->id);
        \local_vcostaedu\setup::ensure_service_user($userid);
        // The key goes to stdout only, for the calling script to keep in memory.
        echo \core_external\util::generate_token(
            EXTERNAL_TOKEN_PERMANENT,
            \local_vcostaedu\setup::get_service(),
            $userid,
            context_system::instance(),
            time() + 3600,
            '',
            'e2e'
        ), "\n";
        break;

    case 'state':
        // Counts used to prove nothing is duplicated.
        $service = $DB->get_record('external_services', ['shortname' => E2E_SERVICE_SHORTNAME]);
        $roleid = $DB->get_field('role', 'id', ['shortname' => E2E_ROLE_SHORTNAME]);
        $out = [
            'role' => $DB->count_records('role', ['shortname' => E2E_ROLE_SHORTNAME]),
            'capabilities' => $roleid ? $DB->count_records('role_capabilities', ['roleid' => $roleid]) : 0,
            'assignable' => $roleid ? $DB->count_records('role_allow_assign', ['roleid' => $roleid]) : 0,
            'user' => $DB->count_records('user', ['username' => E2E_USERNAME, 'deleted' => 0]),
            'assignments' => $roleid ? $DB->count_records('role_assignments', ['roleid' => $roleid]) : 0,
            'services' => $DB->count_records('external_services', ['component' => 'local_vcostaedu']),
            'functions' => $service ? $DB->count_records('external_services_functions', ['externalserviceid' => $service->id]) : 0,
            'authorised' => $service ? $DB->count_records('external_services_users', ['externalserviceid' => $service->id]) : 0,
            'keys' => $service ? $DB->count_records('external_tokens', ['externalserviceid' => $service->id]) : 0,
        ];
        foreach ($out as $k => $v) {
            echo "$k=$v ";
        }
        echo "\n";
        break;

    case 'repair':
        \local_vcostaedu\setup::run();
        echo "ok\n";
        break;

    case 'remove-cap':
        $roleid = $DB->get_field('role', 'id', ['shortname' => E2E_ROLE_SHORTNAME]);
        unassign_capability($arg1, $roleid, context_system::instance()->id);
        echo "ok\n";
        break;

    case 'diag':
        foreach (\local_vcostaedu\diagnostics::get_items() as $item) {
            // Key=status, plus "->action" when a page button solves it.
            $suffix = ($item['status'] !== 'ok' && !empty($item['action'])) ? '->' . $item['action'] : '';
            echo $item['key'], '=', $item['status'], $suffix, ' ';
        }
        echo "\n";
        break;

    case 'diag-message':
        foreach (\local_vcostaedu\diagnostics::get_items() as $item) {
            if ($item['key'] === $arg1) {
                echo $item['message'], "\n";
            }
        }
        break;

    case 'sessions':
        $user = e2e_user($arg1);
        echo $user ? $DB->count_records('sessions', ['userid' => $user->id]) : 0, "\n";
        break;

    case 'events':
        // Events of a user since a time: short name and reason.
        $user = e2e_user($arg1);
        $userid = $user ? $user->id : 0;
        $rows = $DB->get_records_select(
            'logstore_standard_log',
            'timecreated >= :since AND (relateduserid = :u1 OR (userid = :u2 AND eventname = :login))',
            ['since' => (int) $arg2, 'u1' => $userid, 'u2' => $userid, 'login' => '\core\event\user_loggedin'],
            'id ASC',
            'id, eventname, other'
        );
        foreach ($rows as $row) {
            $other = json_decode((string) $row->other, true);
            $name = substr($row->eventname, strrpos($row->eventname, '\\') + 1);
            echo $name, (is_array($other) && isset($other['reason']) ? '(' . $other['reason'] . ')' : ''), ' ';
        }
        echo "\n";
        break;

    case 'enrol-status':
        // Manual enrolment status of a user in a course: active, suspended or none.
        $user = e2e_user($arg1);
        $status = $user ? $DB->get_field_sql(
            'SELECT ue.status FROM {user_enrolments} ue JOIN {enrol} e ON e.id = ue.enrolid
              WHERE e.enrol = ? AND e.courseid = ? AND ue.userid = ?',
            ['manual', (int) $arg2, $user->id]
        ) : false;
        echo $status === false ? 'none' : ((int) $status === ENROL_USER_ACTIVE ? 'active' : 'suspended'), "\n";
        break;

    case 'email-processor':
        // Turns the e-mail message output on (1) or off (0).
        $DB->set_field('message_processors', 'enabled', (int) $arg1, ['name' => 'email']);
        \core_plugin_manager::reset_caches();
        get_message_processors(false, true, true);
        echo "ok\n";
        break;

    case 'create-conflict':
        // A custom service (not the plugin's) with the plugin service name.
        if (!$DB->record_exists('external_services', ['shortname' => 'e2e_conflict'])) {
            $DB->insert_record('external_services', (object) ['name' => 'VCOSTAEDU', 'shortname' => 'e2e_conflict',
                'enabled' => 0, 'restrictedusers' => 1, 'component' => null, 'timecreated' => time(),
                'downloadfiles' => 0, 'uploadfiles' => 0]);
        }
        echo "ok\n";
        break;

    case 'remove-conflict':
        $DB->delete_records('external_services', ['shortname' => 'e2e_conflict']);
        echo "ok\n";
        break;

    case 'create-preexisting':
        // A role and a user with the plugin names, created before the plugin is installed.
        if (!$DB->record_exists('role', ['shortname' => E2E_ROLE_SHORTNAME])) {
            create_role('Pre-existing role', E2E_ROLE_SHORTNAME, '');
        }
        if (!e2e_user(E2E_USERNAME)) {
            e2e_create_user(E2E_USERNAME, 'Preexisting', 'E2e!' . random_string(20) . '9a');
        }
        echo "ok\n";
        break;

    case 'plugin':
        // Installed version of the plugin, role and user that exist, and what the plugin recorded as created.
        echo 'version=', get_config('local_vcostaedu', 'version') ?: '-',
            ' role=', (int) $DB->record_exists('role', ['shortname' => E2E_ROLE_SHORTNAME]),
            ' user=', (int) (bool) e2e_user(E2E_USERNAME),
            ' service=', (int) $DB->record_exists('external_services', ['shortname' => E2E_SERVICE_SHORTNAME]),
            ' createdrole=', get_config('local_vcostaedu', 'createdroleid') ? 1 : 0,
            ' createduser=', get_config('local_vcostaedu', 'createduserid') ? 1 : 0, "\n";
        break;

    case 'ws-config':
        echo 'enablewebservices=', (int) get_config('core', 'enablewebservices'),
            ' protocols=', get_config('core', 'webserviceprotocols') ?: '-', "\n";
        break;

    default:
        fwrite(STDERR, "Unknown action.\n");
        exit(2);
}
