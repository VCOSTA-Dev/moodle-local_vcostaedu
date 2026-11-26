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
 * Uninstall steps.
 *
 * @package    local_vcostaedu
 * @copyright  2026 VCOSTA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Removes what the plugin created and nothing else.
 *
 * Moodle itself removes the plugin service, its tokens, the plugin capability and
 * the plugin settings after this function. Here the plugin removes the role and the
 * service user it created; a role or user that existed before the plugin stays.
 *
 * @return bool
 */
function xmldb_local_vcostaedu_uninstall() {
    global $DB;
    // One-time login keys left behind.
    $DB->delete_records('user_private_key', ['script' => \local_vcostaedu\setup::KEY_SCRIPT]);
    \local_vcostaedu\setup::remove_created_items();
    return true;
}
