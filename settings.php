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
 * Admin menu entries.
 *
 * @package    local_vcostaedu
 * @copyright  2026 VCOSTA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $ADMIN->add('localplugins', new admin_category(
        'local_vcostaedu',
        new lang_string('pluginname', 'local_vcostaedu')
    ));

    $ADMIN->add('local_vcostaedu', new admin_externalpage(
        'local_vcostaedu_connect',
        new lang_string('connect', 'local_vcostaedu'),
        new moodle_url('/local/vcostaedu/connect.php'),
        'moodle/site:config'
    ));

    $settings = new admin_settingpage('local_vcostaedu_settings', new lang_string('settingspage', 'local_vcostaedu'));
    if ($ADMIN->fulltree) {
        $settings->add(new \local_vcostaedu\admin\setting_loginvalidity());
    }
    $ADMIN->add('local_vcostaedu', $settings);
}
