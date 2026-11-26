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
 * External functions and the service used by VCOSTAEDU.
 *
 * @package    local_vcostaedu
 * @copyright  2026 VCOSTA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_vcostaedu_request_login_url' => [
        'classname' => 'local_vcostaedu\external\request_login_url',
        'description' => 'Creates a one-time login link for an active user. Refuses, without creating any key, '
            . 'missing, deleted, suspended, unconfirmed, nologin, guest and site administrator accounts.',
        'type' => 'write',
        'capabilities' => 'local/vcostaedu:requestloginurl',
        'ajax' => false,
        'loginrequired' => true,
    ],
    'local_vcostaedu_find_users_by_email' => [
        'classname' => 'local_vcostaedu\external\find_users_by_email',
        'description' => 'Ids of the accounts that have each e-mail address (1 to 100 per call), to link existing '
            . 'accounts to the academic record. Only ids come out; never the guest or deleted accounts.',
        'type' => 'read',
        'capabilities' => 'moodle/user:viewdetails',
        'ajax' => false,
        'loginrequired' => true,
    ],
];

// The name must be unique on the site. If another service already uses it, the
// environment check in environment.xml stops the installation before anything is created.
$services = [
    'VCOSTAEDU' => [
        'shortname' => 'local_vcostaedu',
        'functions' => [
            'core_webservice_get_site_info',
            'core_user_create_users',
            'core_user_update_users',
            'core_user_get_users_by_field',
            'core_user_get_users',
            'core_course_get_categories',
            'core_course_create_categories',
            'core_course_get_courses_by_field',
            'core_course_create_courses',
            'core_course_update_courses',
            'enrol_manual_enrol_users',
            'enrol_manual_unenrol_users',
            'core_enrol_get_enrolled_users',
            'core_enrol_get_users_courses',
            'gradereport_user_get_grade_items',
            'local_vcostaedu_request_login_url',
            'local_vcostaedu_find_users_by_email',
        ],
        'restrictedusers' => 1,
        'enabled' => 1,
        'downloadfiles' => 0,
        'uploadfiles' => 0,
    ],
];
