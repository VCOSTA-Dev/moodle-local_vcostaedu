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
 * Post installation steps.
 *
 * @package    local_vcostaedu
 * @copyright  2026 VCOSTA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Creates (or reuses) the role, the service user and the service authorisation.
 *
 * Web services and the REST protocol are not switched on here: that is the
 * administrator's decision, made on the "Connect to VCOSTAEDU" page.
 *
 * @return bool
 */
function xmldb_local_vcostaedu_install() {
    global $CFG;
    require_once($CFG->libdir . '/upgradelib.php');

    // The environment check normally stops a name conflict earlier. This is the
    // last line of defence, before anything is created.
    \local_vcostaedu\setup::require_no_service_conflict();

    // Moodle registers the plugin capabilities and services only after this
    // function returns. Register them now so the role and the service can be set up.
    // Both calls are idempotent; Moodle repeats them right after this.
    update_capabilities('local_vcostaedu');
    external_update_descriptions('local_vcostaedu');

    \local_vcostaedu\setup::run();
    return true;
}

/**
 * Finishes an installation that was interrupted. Moodle calls this on the next upgrade.
 *
 * @return bool
 */
function xmldb_local_vcostaedu_install_recovery() {
    return xmldb_local_vcostaedu_install();
}
