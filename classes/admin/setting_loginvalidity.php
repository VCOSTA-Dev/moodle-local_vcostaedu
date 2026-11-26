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

namespace local_vcostaedu\admin;

use local_vcostaedu\setup;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/adminlib.php');

/**
 * Login link validity, in seconds, limited to a safe range.
 *
 * @package    local_vcostaedu
 * @copyright  2026 VCOSTA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class setting_loginvalidity extends \admin_setting_configtext {
    /**
     * Constructor.
     */
    public function __construct() {
        parent::__construct(
            'local_vcostaedu/loginvalidity',
            new \lang_string('loginvalidity', 'local_vcostaedu'),
            new \lang_string('loginvalidity_desc', 'local_vcostaedu', self::range()),
            setup::LOGIN_VALIDITY_DEFAULT,
            PARAM_INT,
            5
        );
    }

    /**
     * Accepts only whole numbers inside the range.
     *
     * @param string $data
     * @return bool|string true or an error message
     */
    public function validate($data) {
        if (!preg_match('/^\d+$/', trim((string) $data))) {
            return get_string('loginvalidity_error', 'local_vcostaedu', self::range());
        }
        $value = (int) $data;
        if ($value < setup::LOGIN_VALIDITY_MIN || $value > setup::LOGIN_VALIDITY_MAX) {
            return get_string('loginvalidity_error', 'local_vcostaedu', self::range());
        }
        return true;
    }

    /**
     * Allowed range, for the language strings.
     *
     * @return array
     */
    protected static function range(): array {
        return ['min' => setup::LOGIN_VALIDITY_MIN, 'max' => setup::LOGIN_VALIDITY_MAX];
    }
}
