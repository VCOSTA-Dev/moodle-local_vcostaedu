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
 * Environment checks declared in environment.xml.
 *
 * Moodle runs them on the server check page and in the command line upgrade,
 * before the plugin is installed, so a blocking problem stops the installation
 * before anything is created.
 *
 * @package    local_vcostaedu
 * @copyright  2026 VCOSTA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class environment {
    /**
     * Fails when another service already uses the name or the short name of the plugin service.
     *
     * @param \environment_results $result
     * @return \environment_results|null the failed result, or null when there is nothing to report
     */
    public static function check_service_name(\environment_results $result): ?\environment_results {
        try {
            $other = setup::service_conflict();
        } catch (\Throwable $e) {
            // During a fresh Moodle installation the tables do not exist yet: nothing can conflict.
            return null;
        }
        if (!$other) {
            return null;
        }
        $result->setInfo(get_string('envservicename', setup::COMPONENT));
        $result->setStatus(false);
        $result->setFeedbackStr(['servicenameconflict', setup::COMPONENT, (object) [
            'name' => setup::SERVICE_NAME,
            'shortname' => setup::SERVICE_SHORTNAME,
            'othershortname' => $other->shortname ?? '-',
        ]]);
        return $result;
    }
}
