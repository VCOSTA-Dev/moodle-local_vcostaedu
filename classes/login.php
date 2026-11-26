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

use moodle_url;
use stdClass;

/**
 * Rules for the one-time login link.
 *
 * The same user check runs when the link is requested and again, on a fresh copy
 * of the user read from the database, right before complete_user_login(). Moodle
 * itself only notices a suspended account inside complete_user_login(), after the
 * session already exists; checking before is what keeps a suspended user out.
 *
 * @package    local_vcostaedu
 * @copyright  2026 VCOSTA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class login {
    /** Key not found or already used. */
    public const INVALIDKEY = 'invalidkey';

    /** Key past its validity. */
    public const EXPIREDKEY = 'expiredkey';

    /** The same key is being used by another request right now. */
    public const BUSY = 'busy';

    /** Something failed while logging in; the session was closed. */
    public const LOGINFAILED = 'loginfailed';

    /** User reasons, in the order they are checked. */
    public const USER_REASONS = [
        'usernotfound',
        'userdeleted',
        'userisguest',
        'userisadmin',
        'userisservice',
        'usersuspended',
        'usernotconfirmed',
        'usernologin',
        'authdisabled',
    ];

    /** Seconds the internal key used after closing another user's session stays valid. */
    public const RELOGIN_VALIDITY = 30;

    /**
     * All refusal reasons.
     *
     * @return string[]
     */
    public static function reasons(): array {
        return array_merge([self::INVALIDKEY, self::EXPIREDKEY, self::BUSY, self::LOGINFAILED], self::USER_REASONS);
    }

    /**
     * Error code returned by the web service for a reason.
     *
     * @param string $reason
     * @return string
     */
    public static function errorcode(string $reason): string {
        return 'vcostaedu_' . $reason;
    }

    /**
     * Checks whether the user may enter through a link.
     *
     * @param stdClass|null|false $user user record read from the database
     * @return string|null refusal reason, or null when the user may enter
     */
    public static function check_user($user): ?string {
        global $CFG;
        if (empty($user) || empty($user->id)) {
            return 'usernotfound';
        }
        if (!empty($user->deleted)) {
            return 'userdeleted';
        }
        if (isguestuser($user) || (int) $user->id === (int) ($CFG->siteguest ?? 0)) {
            return 'userisguest';
        }
        if (is_siteadmin($user)) {
            return 'userisadmin';
        }
        if ($user->username === setup::USERNAME) {
            return 'userisservice';
        }
        if (!empty($user->suspended)) {
            return 'usersuspended';
        }
        if (empty($user->confirmed)) {
            return 'usernotconfirmed';
        }
        if ($user->auth === 'nologin') {
            return 'usernologin';
        }
        if (!is_enabled_auth($user->auth)) {
            return 'authdisabled';
        }
        return null;
    }

    /**
     * Returns the return address as a path inside this Moodle, or '' when it is empty or not local.
     *
     * Accepts a path ("/course/view.php?id=2") or a full address that starts with this
     * Moodle's address. Anything else is ignored. The result is always used as
     * $CFG->wwwroot . path, so it can never leave the site.
     *
     * @param string|null $url
     * @return string
     */
    public static function clean_returnurl(?string $url): string {
        global $CFG;
        $url = trim((string) $url);
        if ($url === '' || strlen($url) > 1000) {
            return '';
        }
        // Control characters and backslashes are used to trick browsers into leaving the site.
        if (preg_match('/[\x00-\x1f\x7f\\\\]/', $url)) {
            return '';
        }
        $root = rtrim($CFG->wwwroot, '/');
        if (strcasecmp($url, $root) === 0) {
            return '/';
        }
        if (stripos($url, $root . '/') === 0) {
            $url = substr($url, strlen($root));
        }
        if ($url[0] !== '/' || strpos($url, '//') === 0) {
            return '';
        }
        if (clean_param($url, PARAM_LOCALURL) !== $url) {
            return '';
        }
        // Do not send the user back to this page.
        if (stripos($url, '/local/vcostaedu/login.php') === 0) {
            return '';
        }
        return $url;
    }

    /**
     * Where to go after the login.
     *
     * @param string $returnurl already cleaned with {@see self::clean_returnurl()}
     * @return moodle_url
     */
    public static function target(string $returnurl): moodle_url {
        if ($returnurl === '' || $returnurl === '/') {
            return new moodle_url('/my/');
        }
        return new moodle_url($returnurl);
    }

    /**
     * Builds the login address for a key.
     *
     * @param string $key
     * @param string $returnurl already cleaned
     * @return moodle_url
     */
    public static function login_url(string $key, string $returnurl): moodle_url {
        $params = ['key' => $key];
        if ($returnurl !== '') {
            $params['returnurl'] = $returnurl;
        }
        return new moodle_url('/local/vcostaedu/login.php', $params);
    }

    /**
     * Creates a one-time key for the user.
     *
     * @param int $userid
     * @param int $validity seconds
     * @return array [key, validuntil]
     */
    public static function create_key(int $userid, int $validity): array {
        global $DB;
        // Old keys that were never used are useless; drop them.
        $DB->delete_records_select(
            'user_private_key',
            'script = :script AND validuntil < :now',
            ['script' => setup::KEY_SCRIPT, 'now' => time()]
        );
        $validuntil = time() + $validity;
        $key = create_user_key(setup::KEY_SCRIPT, $userid, null, null, $validuntil);
        return [$key, $validuntil];
    }

    /**
     * Validates the key and deletes it at once, so it can be used only one time.
     *
     * A lock makes sure two requests with the same key cannot both pass.
     *
     * @param string $value
     * @return stdClass|string the key record, or the refusal reason
     */
    public static function consume_key(string $value) {
        global $DB;
        if ($value === '') {
            return self::INVALIDKEY;
        }
        $factory = \core\lock\lock_config::get_lock_factory('local_vcostaedu');
        $lock = $factory->get_lock('loginkey_' . md5($value), 5);
        if (!$lock) {
            return self::BUSY;
        }
        try {
            try {
                $key = validate_user_key($value, setup::KEY_SCRIPT, null);
            } catch (\moodle_exception $e) {
                // An expired key is deleted too, so it is gone for good.
                $DB->delete_records('user_private_key', ['script' => setup::KEY_SCRIPT, 'value' => $value]);
                return $e->errorcode === 'expiredkey' ? self::EXPIREDKEY : self::INVALIDKEY;
            }
            $DB->delete_records('user_private_key', ['id' => $key->id]);
            return $key;
        } finally {
            $lock->release();
        }
    }

    /**
     * Logs the user in. Must be called only after {@see self::check_user()} passed on a fresh record.
     *
     * If anything fails after the session was created, or the user stopped being
     * active meanwhile, the session is closed and the reason is returned.
     *
     * @param stdClass $user
     * @return string|null refusal reason, or null on success
     */
    public static function complete_login(stdClass $user): ?string {
        try {
            $full = get_complete_user_data('id', $user->id);
            if (!$full) {
                return 'usernotfound';
            }
            // Last check on the complete record, right before the session is created.
            if ($reason = self::check_user($full)) {
                return $reason;
            }
            complete_user_login($full);
            \core\session\manager::apply_concurrent_login_limit($full->id, session_id());

            // The account may have changed while the login ran. Check once more.
            if ($reason = self::check_user(\core_user::get_user($full->id))) {
                self::end_session();
                return $reason;
            }
            return null;
        } catch (\Throwable $e) {
            self::end_session();
            debugging('local_vcostaedu: login failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return self::LOGINFAILED;
        }
    }

    /**
     * Closes the current session, if any.
     */
    public static function end_session(): void {
        if (!isloggedin()) {
            return;
        }
        try {
            require_logout();
        } catch (\Throwable $e) {
            \core\session\manager::terminate_current();
        }
    }

    /**
     * Is the PHP session of this request still open (not closed by a logout in this request)?
     *
     * From Moodle 5.3, \core\session\manager::is_session_active() answers it. Moodle 4.5 to 5.2
     * do not have that method; there the session manager closes the PHP session whenever it
     * marks the session inactive (session_write_close() or session_abort()), so the PHP session
     * status gives the same answer.
     *
     * @return bool
     */
    public static function session_is_open(): bool {
        if (method_exists(\core\session\manager::class, 'is_session_active')) {
            return \core\session\manager::is_session_active();
        }
        return session_status() === PHP_SESSION_ACTIVE;
    }

    /**
     * Ends the session open in this browser only when it belongs to the given user.
     *
     * Used when a valid link is refused because its owner can no longer enter: the owner
     * is logged out, and nobody else ever is.
     *
     * @param int $ownerid owner of the link
     * @return bool whether a session was ended
     */
    public static function end_owner_session(int $ownerid): bool {
        global $USER;
        if (!isloggedin() || (int) $USER->id !== $ownerid) {
            return false;
        }
        self::end_session();
        return true;
    }

    /**
     * Records the refusal, shows the refusal page and stops.
     *
     * A session of somebody else open in this browser stays as it is. When the session
     * was closed in this request, the page is shown on a fresh request, because a
     * closed session cannot render a page.
     *
     * @param string $reason
     * @param int|null $userid user the link was for, when known
     */
    public static function refuse(string $reason, ?int $userid): void {
        self::log_refused($reason, 'login', $userid);
        if (!self::session_is_open()) {
            redirect(new moodle_url('/local/vcostaedu/login.php', ['reason' => $reason]));
        }
        http_response_code(403);
        echo self::render_refusal($reason);
        die();
    }

    /**
     * HTML of the refusal page. No session is created by it.
     *
     * @param string $reason any value; unknown values are shown as an invalid link
     * @return string
     */
    public static function render_refusal(string $reason): string {
        global $OUTPUT;
        if (!in_array($reason, self::reasons(), true)) {
            $reason = self::INVALIDKEY;
        }
        $html = $OUTPUT->header();
        $html .= $OUTPUT->heading(get_string('loginpage', 'local_vcostaedu'));
        $html .= $OUTPUT->notification(
            get_string('refused_' . $reason, 'local_vcostaedu'),
            \core\output\notification::NOTIFY_ERROR,
            false
        );
        $html .= \html_writer::tag('p', s(get_string('refused_help', 'local_vcostaedu')));
        $html .= $OUTPUT->single_button(
            new moodle_url('/login/index.php'),
            get_string('refused_gotologin', 'local_vcostaedu'),
            'get'
        );
        $html .= \html_writer::tag('span', '', ['data-local-vcostaedu-refused' => $reason]);
        $html .= $OUTPUT->footer();
        return $html;
    }

    /**
     * Records a refused login.
     *
     * @param string $reason
     * @param string $stage 'request' (web service) or 'login' (login.php)
     * @param int|null $userid user the link was for, when known
     */
    public static function log_refused(string $reason, string $stage, ?int $userid): void {
        try {
            $data = [
                'context' => \context_system::instance(),
                'other' => ['reason' => $reason, 'stage' => $stage],
            ];
            if ($userid) {
                $data['relateduserid'] = $userid;
            }
            event\login_link_refused::create($data)->trigger();
        } catch (\Throwable $e) {
            debugging('local_vcostaedu: could not log refusal: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}
