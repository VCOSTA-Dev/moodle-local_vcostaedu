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

namespace local_vcostaedu\event;

/**
 * A one-time login link was refused, when requested or when opened.
 *
 * @package    local_vcostaedu
 * @copyright  2026 VCOSTA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class login_link_refused extends \core\event\base {
    /**
     * Event data.
     */
    protected function init() {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('event_login_link_refused', 'local_vcostaedu');
    }

    /**
     * Event description, in English like the core events.
     *
     * @return string
     */
    public function get_description() {
        $who = empty($this->relateduserid) ? 'an unknown user' : "the user with id '$this->relateduserid'";
        return "A one-time login link for $who was refused at the '{$this->other['stage']}' stage. " .
            "Reason: '{$this->other['reason']}'.";
    }

    /**
     * Checks the data.
     *
     * @throws \coding_exception
     */
    protected function validate_data() {
        parent::validate_data();
        if (!isset($this->other['reason'])) {
            throw new \coding_exception('The \'reason\' value must be set in other.');
        }
        if (!isset($this->other['stage'])) {
            throw new \coding_exception('The \'stage\' value must be set in other.');
        }
    }
}
