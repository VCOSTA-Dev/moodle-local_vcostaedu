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
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use invalid_parameter_exception;

/**
 * local_vcostaedu_find_users_by_email: the ids of the accounts that have each e-mail address.
 *
 * Lets VCOSTAEDU link an account that already exists in Moodle to its academic record when the
 * e-mail address is the only data in common. Only ids come out: no name, username, id number or
 * status. The core functions would need moodle/site:viewuseridentity for this, which shows every
 * identity field of every user of the site.
 *
 * @package    local_vcostaedu
 * @copyright  2026 VCOSTA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class find_users_by_email extends external_api {
    /** Most e-mail addresses in one call. */
    public const MAX_EMAILS = 100;

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'emails' => new external_multiple_structure(
                new external_value(PARAM_RAW, 'E-mail address. Spaces at the ends are ignored, and so is the case.'),
                'E-mail addresses to look for, 1 to ' . self::MAX_EMAILS . ' in one call'
            ),
        ]);
    }

    /**
     * Looks for the accounts of each e-mail address, in one database query.
     *
     * Accounts of this site only, not deleted, never the guest. Suspended accounts are included:
     * the caller decides what to do with them. When the site allows several accounts with the same
     * e-mail address, all of them come back, in id order.
     *
     * @param string[] $emails
     * @return array one item per address, in the order received: the address as sent and the ids
     */
    public static function execute(array $emails): array {
        global $CFG, $DB;

        $params = self::validate_parameters(self::execute_parameters(), ['emails' => $emails]);
        $emails = $params['emails'];
        if (count($emails) < 1 || count($emails) > self::MAX_EMAILS) {
            throw new invalid_parameter_exception('Send 1 to ' . self::MAX_EMAILS . ' e-mail addresses.');
        }
        $keys = [];
        foreach ($emails as $email) {
            $key = \core_text::strtolower(trim($email));
            if (!validate_email($key)) {
                throw new invalid_parameter_exception('Invalid e-mail address: ' . s($email));
            }
            $keys[$key] = [];
        }

        // Looking up users is reading user data: the same check as the core user search functions.
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('moodle/user:viewdetails', $context);

        [$insql, $inparams] = $DB->get_in_or_equal(array_keys($keys), SQL_PARAMS_NAMED, 'email');
        $sql = "SELECT id, email
                  FROM {user}
                 WHERE deleted = 0
                   AND mnethostid = :mnethostid
                   AND id <> :guestid
                   AND LOWER(email) $insql
              ORDER BY id";
        $records = $DB->get_records_sql($sql, $inparams + [
            'mnethostid' => $CFG->mnet_localhost_id,
            'guestid' => (int) ($CFG->siteguest ?? 0),
        ]);
        foreach ($records as $record) {
            $key = \core_text::strtolower(trim($record->email));
            if (array_key_exists($key, $keys)) {
                $keys[$key][] = (int) $record->id;
            }
        }

        $result = [];
        foreach ($emails as $email) {
            $result[] = ['email' => $email, 'userids' => $keys[\core_text::strtolower(trim($email))]];
        }
        return $result;
    }

    /**
     * Return value.
     *
     * @return external_multiple_structure
     */
    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure([
                'email' => new external_value(PARAM_RAW, 'The e-mail address as it was sent'),
                'userids' => new external_multiple_structure(
                    new external_value(PARAM_INT, 'Id of an account with this e-mail address'),
                    'Ids of the accounts with this e-mail address, in id order; empty when there is none'
                ),
            ])
        );
    }
}
