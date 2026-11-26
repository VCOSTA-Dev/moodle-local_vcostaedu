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
 * Entry through a one-time login link created by local_vcostaedu_request_login_url.
 *
 * This page is a login page: the visitor is not logged in yet, so it does not call
 * require_login(). Access is controlled by the one-time key instead.
 *
 * Order of the steps (it matters):
 *  1. the key is validated and deleted at once, under a lock (one use only);
 *  2. the user is read again from the database and must still be active; if not, the
 *     link is refused and only the session of the link's own owner, if open, is ended;
 *  3. only then, a session of another user in this browser is closed (a refused
 *     link never touches somebody else's session);
 *  4. only then complete_user_login() runs; any error after it closes the session.
 *
 * @package    local_vcostaedu
 * @copyright  2026 VCOSTA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:ignore moodle.Files.RequireLogin.Missing -- Login page: the one-time key replaces require_login().
require(__DIR__ . '/../../config.php');

use local_vcostaedu\login;

$keyvalue = optional_param('key', '', PARAM_ALPHANUM);
$shownreason = optional_param('reason', '', PARAM_ALPHA);
$returnurl = login::clean_returnurl(optional_param('returnurl', '', PARAM_RAW_TRIMMED));

$PAGE->set_url(new moodle_url('/local/vcostaedu/login.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('login');
$PAGE->set_title(get_string('loginpage', 'local_vcostaedu'));

// Refusal page shown on a fresh request after a session was closed.
if ($keyvalue === '') {
    http_response_code(403);
    echo login::render_refusal($shownreason !== '' ? $shownreason : login::INVALIDKEY);
    die();
}

// 1. Validate and delete the key before anything else.
$key = login::consume_key($keyvalue);
if (is_string($key)) {
    login::refuse($key, null);
}

// 2. Fresh copy of the user from the database; it must still be active.
$user = core_user::get_user($key->userid);
if ($reason = login::check_user($user)) {
    // The key was valid, so the link belongs to this person. If that same person is
    // logged in here and can no longer enter, the session is ended. Somebody else's
    // session is never touched.
    login::end_owner_session((int) $key->userid);
    login::refuse($reason, (int) $key->userid);
}

// 3. A session already open in this browser.
if (isloggedin() && !isguestuser()) {
    if ((int) $USER->id === (int) $user->id) {
        redirect(login::target($returnurl));
    }
    // Close the other user's session. A closed session cannot be reused in the same
    // request, so continue on a new request with a short internal key.
    login::end_session();
    [$nextkey] = login::create_key((int) $user->id, login::RELOGIN_VALIDITY);
    redirect(login::login_url($nextkey, $returnurl));
}

// 4. Log in. complete_login() checks the user again and closes the session on any error.
if ($reason = login::complete_login($user)) {
    login::refuse($reason, (int) $user->id);
}

\local_vcostaedu\event\login_link_used::create([
    'context' => context_system::instance(),
    'objectid' => $user->id,
    'relateduserid' => $user->id,
])->trigger();

redirect(login::target($returnurl));
