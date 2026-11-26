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

/**
 * Checks, item by item, whether Moodle is ready for VCOSTAEDU.
 *
 * @package    local_vcostaedu
 * @copyright  2026 VCOSTA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class diagnostics {
    /** Item is fine. */
    public const OK = 'ok';

    /** Item works but deserves attention. */
    public const WARNING = 'warning';

    /** Item blocks the connection. */
    public const ERROR = 'error';

    /** Moodle branches the plugin was tested on: 4.5 to 5.3. */
    public const TESTED_BRANCHES = ['405', '500', '501', '502', '503'];

    /**
     * Runs every check.
     *
     * Each item has: key, status, title and message (plain text, not escaped yet).
     *
     * @return array[]
     */
    public static function get_items(): array {
        return [
            self::check_webservices(),
            self::check_rest(),
            self::check_user(),
            self::check_role(),
            self::check_role_assignment(),
            self::check_service(),
            self::check_service_user(),
            self::check_token(),
            self::check_email(),
            self::check_versions(),
        ];
    }

    /**
     * Is there any item with an error?
     *
     * @param array[] $items
     * @return bool
     */
    public static function has_errors(array $items): bool {
        foreach ($items as $item) {
            if ($item['status'] === self::ERROR) {
                return true;
            }
        }
        return false;
    }

    /**
     * Builds an item.
     *
     * @param string $key
     * @param string $status
     * @param string $messageid string id of the message
     * @param mixed $a string parameter
     * @return array
     */
    protected static function item(string $key, string $status, string $messageid, $a = null): array {
        return [
            'key' => $key,
            'status' => $status,
            'title' => get_string('diag_' . $key, 'local_vcostaedu'),
            'message' => get_string($messageid, 'local_vcostaedu', $a),
            'action' => self::ACTIONS[$messageid] ?? null,
        ];
    }

    /**
     * Actions (page buttons) of the plugin that solve each problem, by message id.
     *
     * Problems missing here are solved outside the plugin (for example a suspended
     * service user, or another service using the name "VCOSTAEDU").
     */
    protected const ACTIONS = [
        'diag_webservices_error' => 'enablews',
        'diag_rest_error' => 'enablews',
        'diag_user_missing' => 'fix',
        'diag_role_missing' => 'fix',
        'diag_role_incomplete' => 'fix',
        'diag_roleassign_error' => 'fix',
        'diag_service_missing' => 'fix',
        'diag_service_disabled' => 'fix',
        'diag_service_incomplete' => 'fix',
        'diag_serviceuser_error' => 'fix',
        'diag_token_missing' => 'generatekey',
    ];

    /**
     * Actions that would solve something right now.
     *
     * @param array[] $items
     * @return string[]
     */
    public static function needed_actions(array $items): array {
        $actions = [];
        foreach ($items as $item) {
            if ($item['status'] !== self::OK && $item['action']) {
                $actions[$item['action']] = $item['action'];
            }
        }
        return array_values($actions);
    }

    /**
     * Is there anything to turn on with "Turn on web services and REST"?
     *
     * @return bool
     */
    public static function webservices_need_enabling(): bool {
        global $CFG;
        return empty($CFG->enablewebservices) || !self::rest_enabled();
    }

    /**
     * Web services switched on.
     *
     * @return array
     */
    protected static function check_webservices(): array {
        global $CFG;
        if (!empty($CFG->enablewebservices)) {
            return self::item('webservices', self::OK, 'diag_webservices_ok');
        }
        return self::item('webservices', self::ERROR, 'diag_webservices_error');
    }

    /**
     * REST protocol switched on.
     *
     * @return array
     */
    protected static function check_rest(): array {
        if (self::rest_enabled()) {
            return self::item('rest', self::OK, 'diag_rest_ok');
        }
        return self::item('rest', self::ERROR, 'diag_rest_error');
    }

    /**
     * Is the REST protocol in the list of enabled protocols?
     *
     * @return bool
     */
    public static function rest_enabled(): bool {
        global $CFG;
        $protocols = empty($CFG->webserviceprotocols) ? [] : explode(',', $CFG->webserviceprotocols);
        return in_array('rest', array_map('trim', $protocols));
    }

    /**
     * Service user exists and can work.
     *
     * @return array
     */
    protected static function check_user(): array {
        $user = setup::get_user();
        if (!$user) {
            return self::item('user', self::ERROR, 'diag_user_missing', setup::USERNAME);
        }
        if ($user->suspended || $user->auth === 'nologin') {
            return self::item('user', self::ERROR, 'diag_user_suspended', setup::USERNAME);
        }
        return self::item('user', self::OK, 'diag_user_ok', setup::USERNAME);
    }

    /**
     * Role has every capability and may assign the needed roles.
     *
     * @return array
     */
    protected static function check_role(): array {
        $role = setup::get_role();
        if (!$role) {
            return self::item('role', self::ERROR, 'diag_role_missing', setup::ROLE_SHORTNAME);
        }
        $unknown = setup::unknown_capabilities();
        $missing = array_diff(setup::missing_capabilities((int) $role->id), $unknown);
        $assign = array_keys(setup::missing_assignable_roles((int) $role->id));
        if ($missing || $assign) {
            $a = (object) [
                'missing' => $missing ? implode(', ', $missing) : '-',
                'assign' => $assign ? implode(', ', $assign) : '-',
            ];
            return self::item('role', self::ERROR, 'diag_role_incomplete', $a);
        }
        if ($unknown) {
            return self::item('role', self::WARNING, 'diag_role_unknown', implode(', ', $unknown));
        }
        return self::item('role', self::OK, 'diag_role_ok', count(setup::capabilities()));
    }

    /**
     * Role given to the user at system level.
     *
     * @return array
     */
    protected static function check_role_assignment(): array {
        $role = setup::get_role();
        $user = setup::get_user();
        if ($role && $user && user_has_role_assignment($user->id, $role->id, \context_system::instance()->id)) {
            return self::item('roleassign', self::OK, 'diag_roleassign_ok');
        }
        return self::item('roleassign', self::ERROR, 'diag_roleassign_error');
    }

    /**
     * Service exists, is on and has every function.
     *
     * @return array
     */
    protected static function check_service(): array {
        $service = setup::get_service();
        if (!$service) {
            if ($other = setup::service_conflict()) {
                return self::item('service', self::ERROR, 'diag_service_conflict', (object) [
                    'name' => setup::SERVICE_NAME,
                    'othershortname' => $other->shortname ?? '-',
                ]);
            }
            return self::item('service', self::ERROR, 'diag_service_missing');
        }
        if (!$service->enabled) {
            return self::item('service', self::ERROR, 'diag_service_disabled', $service->name);
        }
        $unknown = setup::unknown_functions();
        $missing = array_diff(setup::missing_functions(), $unknown);
        if ($missing) {
            return self::item(
                'service',
                self::ERROR,
                'diag_service_incomplete',
                (object) ['name' => $service->name, 'list' => implode(', ', $missing)]
            );
        }
        if ($unknown) {
            return self::item(
                'service',
                self::WARNING,
                'diag_service_unknown',
                (object) ['name' => $service->name, 'list' => implode(', ', $unknown)]
            );
        }
        return self::item(
            'service',
            self::OK,
            'diag_service_ok',
            (object) ['name' => $service->name, 'count' => count(setup::functions())]
        );
    }

    /**
     * Service user authorised in the service.
     *
     * @return array
     */
    protected static function check_service_user(): array {
        $user = setup::get_user();
        if ($user && setup::is_service_user((int) $user->id)) {
            return self::item('serviceuser', self::OK, 'diag_serviceuser_ok');
        }
        return self::item('serviceuser', self::ERROR, 'diag_serviceuser_error');
    }

    /**
     * Token exists.
     *
     * @return array
     */
    protected static function check_token(): array {
        if (setup::get_token()) {
            return self::item('token', self::OK, 'diag_token_ok');
        }
        return self::item('token', self::ERROR, 'diag_token_missing');
    }

    /**
     * Moodle e-mail sending.
     *
     * When Moodle cannot send e-mail, suspending or reactivating an enrolment through
     * enrol_manual_enrol_users saves the change but answers "Message was not sent.".
     *
     * @return array
     */
    protected static function check_email(): array {
        global $CFG;
        if (!empty($CFG->noemailever)) {
            return self::item('email', self::WARNING, 'diag_email_off');
        }
        $processors = get_message_processors(true);
        if (!isset($processors['email'])) {
            return self::item('email', self::WARNING, 'diag_email_processor');
        }
        if (empty($CFG->smtphosts)) {
            // Without SMTP, Moodle hands the mail to the server's sendmail program.
            return self::item('email', self::WARNING, self::sendmail_available() ? 'diag_email_nosmtp' : 'diag_email_nosendmail');
        }
        return self::item('email', self::OK, 'diag_email_ok', $CFG->smtphosts);
    }

    /**
     * Does the server have the sendmail program PHP uses when there is no SMTP server?
     *
     * @return bool
     */
    protected static function sendmail_available(): bool {
        $path = trim((string) ini_get('sendmail_path'));
        if ($path === '') {
            // Windows servers use SMTP settings of PHP instead; do not guess.
            return true;
        }
        $program = strtok($path, ' ');
        return $program !== false && @is_executable($program);
    }

    /**
     * Moodle and plugin versions.
     *
     * @return array
     */
    protected static function check_versions(): array {
        global $CFG;
        $plugin = \core_plugin_manager::instance()->get_plugin_info('local_vcostaedu');
        $a = (object) [
            'moodle' => $CFG->release,
            'plugin' => $plugin ? $plugin->release . ' (' . $plugin->versiondisk . ')' : '-',
        ];
        if (in_array((string) $CFG->branch, self::TESTED_BRANCHES, true)) {
            return self::item('versions', self::OK, 'diag_versions_ok', $a);
        }
        return self::item('versions', self::WARNING, 'diag_versions_untested', $a);
    }
}
