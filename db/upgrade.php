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
 * Upgrade steps.
 *
 * @package    local_vcostaedu
 * @copyright  2026 VCOSTA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrades the plugin.
 *
 * Version 1.0.0-rc2 is the first published version, so there are no version steps yet.
 * On every version change the setup is checked again, so that a capability or function
 * added by a new version reaches the role and the service. Nothing is duplicated.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_vcostaedu_upgrade($oldversion) {
    global $CFG;
    require_once($CFG->libdir . '/upgradelib.php');

    // Moodle registers the capabilities and functions of the new version only after
    // this function, so register them first.
    update_capabilities('local_vcostaedu');
    external_update_descriptions('local_vcostaedu');
    \local_vcostaedu\setup::run();

    return true;
}
