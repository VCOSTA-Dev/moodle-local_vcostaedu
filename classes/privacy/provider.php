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

namespace local_vcostaedu\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use local_vcostaedu\setup;

/**
 * Privacy provider.
 *
 * The plugin keeps no table of its own. The only personal data it handles are the
 * one-time login keys, stored for a few seconds in the core user key table.
 *
 * @package    local_vcostaedu
 * @copyright  2026 VCOSTA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describes the stored data.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        // One-time login keys, kept for a few seconds in the core user key table.
        $collection->add_subsystem_link('core_userkey', [], 'privacy:metadata:core_userkey');

        // VCOSTAEDU, the external academic platform this plugin connects. With the key
        // generated on the "Connect to VCOSTAEDU" page it reads and writes, through the
        // Moodle web services, the data below, and it asks for login links.
        $collection->add_external_location_link('vcostaedu', [
            'username' => 'privacy:metadata:vcostaedu:username',
            'fullname' => 'privacy:metadata:vcostaedu:fullname',
            'email' => 'privacy:metadata:vcostaedu:email',
            'idnumber' => 'privacy:metadata:vcostaedu:idnumber',
            'suspended' => 'privacy:metadata:vcostaedu:suspended',
            'courses' => 'privacy:metadata:vcostaedu:courses',
            'enrolments' => 'privacy:metadata:vcostaedu:enrolments',
            'grades' => 'privacy:metadata:vcostaedu:grades',
            'loginurl' => 'privacy:metadata:vcostaedu:loginurl',
        ], 'privacy:metadata:vcostaedu');

        return $collection;
    }

    /**
     * Contexts with data for the user.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $sql = "SELECT ctx.id
                  FROM {user_private_key} k
                  JOIN {context} ctx ON ctx.instanceid = k.userid AND ctx.contextlevel = :contextlevel
                 WHERE k.userid = :userid AND k.script = :script";
        $contextlist = new contextlist();
        $contextlist->add_from_sql($sql, ['userid' => $userid, 'contextlevel' => CONTEXT_USER, 'script' => setup::KEY_SCRIPT]);
        return $contextlist;
    }

    /**
     * Users with data in the context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if (!$context instanceof \context_user) {
            return;
        }
        \core_userkey\privacy\provider::get_user_contexts_with_script($userlist, $context, setup::KEY_SCRIPT);
    }

    /**
     * Exports the user keys.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel == CONTEXT_USER && $context->instanceid == $contextlist->get_user()->id) {
                \core_userkey\privacy\provider::export_userkeys($context, [], setup::KEY_SCRIPT);
            }
        }
    }

    /**
     * Deletes the keys of every user in the context.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        if ($context->contextlevel == CONTEXT_USER) {
            \core_userkey\privacy\provider::delete_userkeys(setup::KEY_SCRIPT, $context->instanceid);
        }
    }

    /**
     * Deletes the keys of the user.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel == CONTEXT_USER && $context->instanceid == $contextlist->get_user()->id) {
                \core_userkey\privacy\provider::delete_userkeys(setup::KEY_SCRIPT, $context->instanceid);
            }
        }
    }

    /**
     * Deletes the keys of the listed users.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        $context = $userlist->get_context();
        if ($context instanceof \context_user && in_array($context->instanceid, $userlist->get_userids())) {
            \core_userkey\privacy\provider::delete_userkeys(setup::KEY_SCRIPT, $context->instanceid);
        }
    }
}
