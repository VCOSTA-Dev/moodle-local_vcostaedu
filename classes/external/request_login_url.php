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

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_vcostaedu\event\login_link_created;
use local_vcostaedu\login;
use local_vcostaedu\setup;

/**
 * local_vcostaedu_request_login_url: one-time login link for a user.
 *
 * @package    local_vcostaedu
 * @copyright  2026 VCOSTA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class request_login_url extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'username' => new external_value(PARAM_USERNAME, 'Username of the user who will enter Moodle'),
            'returnurl' => new external_value(
                PARAM_RAW_TRIMMED,
                'Optional path inside this Moodle to open after the login, for example /course/view.php?id=2. ' .
                'Any address outside this Moodle is ignored.',
                VALUE_DEFAULT,
                ''
            ),
        ]);
    }

    /**
     * Creates the link, or refuses with an error code of this plugin and no key.
     *
     * @param string $username
     * @param string $returnurl
     * @return array
     */
    public static function execute(string $username, string $returnurl = ''): array {
        global $DB, $CFG;

        $params = self::validate_parameters(
            self::execute_parameters(),
            ['username' => $username, 'returnurl' => $returnurl]
        );

        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/vcostaedu:requestloginurl', $context);

        // Deleted users are included on purpose, to answer with the right reason.
        $user = $DB->get_record('user', ['username' => $params['username'], 'mnethostid' => $CFG->mnet_localhost_id]);
        if ($reason = login::check_user($user)) {
            login::log_refused($reason, 'request', $user ? (int) $user->id : null);
            throw new \moodle_exception(login::errorcode($reason), 'local_vcostaedu', '', $params['username']);
        }

        $path = login::clean_returnurl($params['returnurl']);
        [$key, $validuntil] = login::create_key((int) $user->id, setup::login_validity());

        login_link_created::create([
            'context' => $context,
            'objectid' => $user->id,
            'relateduserid' => $user->id,
        ])->trigger();

        return [
            'loginurl' => login::login_url($key, $path)->out(false),
            'validuntil' => $validuntil,
        ];
    }

    /**
     * Return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'loginurl' => new external_value(PARAM_URL, 'One-time login address. Open it in the user\'s browser.'),
            'validuntil' => new external_value(PARAM_INT, 'Time (Unix timestamp) after which the address no longer works'),
        ]);
    }
}
