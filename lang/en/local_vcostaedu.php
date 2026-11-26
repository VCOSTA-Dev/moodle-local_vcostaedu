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
 * English strings.
 *
 * @package    local_vcostaedu
 * @copyright  2026 VCOSTA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['action_enablews'] = 'Turn on web services and REST';
$string['action_fix'] = 'Fix role and service';
$string['action_generatekey'] = 'Generate key';
$string['action_newkey'] = 'Generate new key';
$string['actions_heading'] = 'Actions';
$string['actions_help'] = 'Every button asks for confirmation before changing anything. "Fix role and service" only adds what is missing; it deletes nothing.';
$string['confirm_enablews'] = 'Turn on web services and the REST protocol for the whole site? This lets authorised systems, such as VCOSTAEDU, talk to Moodle.';
$string['confirm_fix'] = 'Check and create what is missing: role, permissions, service user, role assignment and the service with its functions? Nothing that exists is deleted.';
$string['confirm_generatekey'] = 'Generate the VCOSTAEDU access key?';
$string['confirm_newkey'] = 'Warning: generating a new key breaks the connection in use. The current key stops working at once, and VCOSTAEDU cannot talk to Moodle (users, enrolments, student login) until you paste the new key into VCOSTAEDU. Generate anyway?';
$string['connect'] = 'Connect to VCOSTAEDU';
$string['connect_intro'] = 'This page checks whether Moodle is ready to talk to VCOSTAEDU, fixes what is missing and shows the data you paste into VCOSTAEDU.';
$string['copied'] = 'Copied to clipboard';
$string['copy'] = 'Copy';
$string['data_heading'] = 'Data to paste into VCOSTAEDU';
$string['data_intro'] = 'In VCOSTAEDU, in the Moodle settings, paste these two items.';
$string['data_token'] = 'Key';
$string['data_token_none'] = 'There is no key yet. Click "Generate key".';
$string['data_token_warning'] = 'The key gives access to student and course data. Do not send it by e-mail or show it in a recorded meeting.';
$string['data_url'] = 'Moodle address';
$string['diag_col_item'] = 'Item';
$string['diag_col_message'] = 'What to do';
$string['diag_col_status'] = 'Status';
$string['diag_email'] = 'Moodle e-mail sending';
$string['diag_email_nosendmail'] = 'There is no e-mail (SMTP) server set and the Moodle server has no sending program (sendmail): no e-mail goes out. Effect: every student enrolment made by VCOSTAEDU in a course that has a teacher (the first one, suspending, reactivating and even repeating it unchanged) saves the change, but Moodle answers with the error "Message was not sent.", because it tries to send the course welcome message to the student. The change is kept; VCOSTAEDU treats that answer as a warning. To avoid it: set up the e-mail (SMTP) server in Site administration > Server > Email > Outgoing mail configuration, or turn off the welcome message in Site administration > Plugins > Enrolments > Manual enrolments.';
$string['diag_email_nosmtp'] = 'There is no e-mail (SMTP) server set; Moodle tries to send through its own server, and that may fail. Effect: every student enrolment made by VCOSTAEDU in a course that has a teacher (the first one, suspending, reactivating and even repeating it unchanged) saves the change, but Moodle answers with the error "Message was not sent.", because it tries to send the course welcome message to the student. The change is kept; VCOSTAEDU treats that answer as a warning. To avoid it: set up the e-mail (SMTP) server in Site administration > Server > Email > Outgoing mail configuration, or turn off the welcome message in Site administration > Plugins > Enrolments > Manual enrolments.';
$string['diag_email_off'] = 'E-mail sending is off on this Moodle (noemailever). Moodle sends no e-mail at all, not even the course welcome message, and enrolments answer without error. If students should receive e-mail, turn sending on and set up the e-mail server.';
$string['diag_email_ok'] = 'Moodle sends e-mail through the server {$a}.';
$string['diag_email_processor'] = 'The e-mail message output is off in Site administration > Messaging. Moodle sends no message by e-mail, not even the course welcome message, and enrolments answer without error. Turn the e-mail output on if students should receive e-mail.';
$string['diag_heading'] = 'Diagnosis';
$string['diag_rest'] = 'REST protocol';
$string['diag_rest_error'] = 'Off. VCOSTAEDU uses this protocol. Click "Turn on web services and REST".';
$string['diag_rest_ok'] = 'On. Nothing to do.';
$string['diag_role'] = 'Role and permissions';
$string['diag_role_incomplete'] = 'Missing permissions: {$a->missing}. Missing roles it may assign: {$a->assign}. Click "Fix role and service".';
$string['diag_role_missing'] = 'The role "{$a}" does not exist. Click "Fix role and service".';
$string['diag_role_ok'] = 'The role has all {$a} permissions and may assign student, non-editing teacher and teacher.';
$string['diag_role_unknown'] = 'These permissions do not exist in this Moodle version and were skipped: {$a}. Tell the VCOSTAEDU team.';
$string['diag_roleassign'] = 'Role of the service user';
$string['diag_roleassign_error'] = 'The service user does not have the role. Click "Fix role and service".';
$string['diag_roleassign_ok'] = 'The service user has the role for the whole site.';
$string['diag_service'] = 'Service and functions';
$string['diag_service_conflict'] = 'Another external service already uses the name "{$a->name}" (short name: {$a->othershortname}), so the plugin service could not be created. Rename or remove the other service in Site administration > Server > Web services > External services, then click "Fix role and service".';
$string['diag_service_disabled'] = 'The service "{$a}" is off. Click "Fix role and service".';
$string['diag_service_incomplete'] = 'Functions missing from the service "{$a->name}": {$a->list}. Click "Fix role and service".';
$string['diag_service_missing'] = 'The plugin service does not exist. Click "Fix role and service".';
$string['diag_service_ok'] = 'The service "{$a->name}" is on and has all {$a->count} functions.';
$string['diag_service_unknown'] = 'These functions of the service "{$a->name}" do not exist in this Moodle version: {$a->list}. Tell the VCOSTAEDU team.';
$string['diag_serviceuser'] = 'Service authorisation';
$string['diag_serviceuser_error'] = 'The service user is not allowed to use the service. Click "Fix role and service".';
$string['diag_serviceuser_ok'] = 'The service user is allowed to use the service.';
$string['diag_token'] = 'Access key';
$string['diag_token_missing'] = 'There is no key yet. Click "Generate key".';
$string['diag_token_ok'] = 'The key exists. It is shown below, in "Data to paste into VCOSTAEDU".';
$string['diag_user'] = 'Service user';
$string['diag_user_missing'] = 'The user "{$a}" does not exist. Click "Fix role and service".';
$string['diag_user_ok'] = 'The user "{$a}" exists and is active.';
$string['diag_user_suspended'] = 'The user "{$a}" is suspended or blocked from logging in. Reactivate it in Site administration > Users. The plugin does not reactivate it on its own.';
$string['diag_versions'] = 'Versions';
$string['diag_versions_ok'] = 'Moodle {$a->moodle}, plugin {$a->plugin}. Moodle version tested with the plugin.';
$string['diag_versions_untested'] = 'Moodle {$a->moodle}, plugin {$a->plugin}. The plugin was tested on Moodle 4.5 to 5.3. Check the diagnosis carefully.';
$string['diag_webservices'] = 'Web services';
$string['diag_webservices_error'] = 'Off. VCOSTAEDU cannot talk to Moodle. Click "Turn on web services and REST".';
$string['diag_webservices_ok'] = 'On. Nothing to do.';
$string['done_enablews'] = 'Web services and REST turned on.';
$string['done_fix'] = 'Role and service checked and fixed.';
$string['done_generatekey'] = 'Key generated. Copy it and paste it into VCOSTAEDU.';
$string['done_generatekey_exists'] = 'A key already exists. To replace it, use "Generate new key".';
$string['done_newkey'] = 'New key generated. The previous one no longer works. Copy the new one and paste it into VCOSTAEDU.';
$string['envservicename'] = 'VCOSTAEDU service name available';
$string['event_login_link_created'] = 'Login link created';
$string['event_login_link_refused'] = 'Login link refused';
$string['event_login_link_used'] = 'Login through link';
$string['loginpage'] = 'Entry from VCOSTAEDU';
$string['loginvalidity'] = 'Login link validity (seconds)';
$string['loginvalidity_desc'] = 'How long a login link sent by VCOSTAEDU can be opened. After that it no longer works. Between {$a->min} and {$a->max} seconds.';
$string['loginvalidity_error'] = 'Type a whole number between {$a->min} and {$a->max}.';
$string['pluginname'] = 'VCOSTAEDU';
$string['privacy:metadata:core_userkey'] = 'One-time login links are stored as user keys for a few seconds and deleted when used or expired.';
$string['privacy:metadata:vcostaedu'] = 'VCOSTAEDU is the external academic platform this plugin connects to Moodle. With the key generated on the "Connect to VCOSTAEDU" page, it reads and writes the data below through the Moodle web services, and asks for login links for students.';
$string['privacy:metadata:vcostaedu:courses'] = 'The courses and categories the person is in.';
$string['privacy:metadata:vcostaedu:email'] = 'The email address of the person.';
$string['privacy:metadata:vcostaedu:enrolments'] = 'The enrolments and roles of the person in courses, and whether they are suspended.';
$string['privacy:metadata:vcostaedu:fullname'] = 'The first name and last name of the person.';
$string['privacy:metadata:vcostaedu:grades'] = 'The grades of the person in courses.';
$string['privacy:metadata:vcostaedu:idnumber'] = 'The ID number of the person.';
$string['privacy:metadata:vcostaedu:loginurl'] = 'The one-time login links requested for the person.';
$string['privacy:metadata:vcostaedu:suspended'] = 'Whether the account of the person is suspended.';
$string['privacy:metadata:vcostaedu:username'] = 'The username of the person in Moodle.';
$string['refused_authdisabled'] = 'The login method of this account is turned off in Moodle.';
$string['refused_busy'] = 'This link is being opened in another tab or window.';
$string['refused_expiredkey'] = 'This login link has expired.';
$string['refused_gotologin'] = 'Go to the Moodle login page';
$string['refused_help'] = 'No session was opened. Go back to VCOSTAEDU and click again, or log in to Moodle with your username and password.';
$string['refused_invalidkey'] = 'This login link is not valid: it was already used or does not exist.';
$string['refused_loginfailed'] = 'The login could not be completed.';
$string['refused_userdeleted'] = 'The account of this link was deleted from Moodle.';
$string['refused_userisadmin'] = 'Site administrator accounts cannot enter through a link. Log in with username and password.';
$string['refused_userisguest'] = 'The guest account cannot enter through a link.';
$string['refused_userisservice'] = 'The VCOSTAEDU service account cannot enter through a link.';
$string['refused_usernologin'] = 'This account is blocked from logging in to Moodle.';
$string['refused_usernotconfirmed'] = 'This account has not been confirmed in Moodle yet.';
$string['refused_usernotfound'] = 'The account of this link no longer exists in Moodle.';
$string['refused_usersuspended'] = 'This account is suspended in Moodle. Contact the school office.';
$string['servicemissing'] = 'The VCOSTAEDU service does not exist. Use "Fix role and service".';
$string['servicenameconflict'] = 'Another external service on this site already uses the name "{$a->name}" or the short name "{$a->shortname}" (short name of the other service: {$a->othershortname}). The plugin needs this name and is not installed while it is in use. Rename or remove the other service in Site administration > Server > Web services > External services, then try again.';
$string['serviceroledescription'] = 'Role used by the VCOSTAEDU integration. Created by the local_vcostaedu plugin.';
$string['servicerolename'] = 'VCOSTAEDU (service)';
$string['serviceuserdescription'] = 'Service account of the VCOSTAEDU integration. Do not use it to log in to Moodle.';
$string['serviceuserlastname'] = 'Service';
$string['settings_link'] = 'Login link validity: {$a} seconds. Change it in the settings.';
$string['settingspage'] = 'Settings';
$string['status_error'] = 'Error';
$string['status_ok'] = 'OK';
$string['status_warning'] = 'Attention';
$string['step_button'] = '{$a->number}. {$a->label}';
$string['step_enablews'] = 'Turn on web services and the REST protocol.';
$string['step_fix'] = 'Fix the role and the service.';
$string['step_generatekey'] = 'Generate the key and copy the address and the key into VCOSTAEDU.';
$string['steps_intro'] = '{$a} steps to go. Do them in this order:';
$string['vcostaedu:requestloginurl'] = 'Ask for a login link for a user';
$string['vcostaedu_authdisabled'] = 'The authentication method of the user "{$a}" is turned off in Moodle. No link was created.';
$string['vcostaedu_userdeleted'] = 'The user "{$a}" was deleted. No link was created.';
$string['vcostaedu_userisadmin'] = '"{$a}" is a site administrator. Administrators never enter through a link. No link was created.';
$string['vcostaedu_userisguest'] = '"{$a}" is the guest account, which cannot enter through a link. No link was created.';
$string['vcostaedu_userisservice'] = '"{$a}" is the VCOSTAEDU service account, which cannot enter through a link. No link was created.';
$string['vcostaedu_usernologin'] = 'The user "{$a}" has the "No login" (nologin) authentication. No link was created.';
$string['vcostaedu_usernotconfirmed'] = 'The user "{$a}" is not confirmed yet. No link was created.';
$string['vcostaedu_usernotfound'] = 'There is no user "{$a}" in this Moodle. No link was created.';
$string['vcostaedu_usersuspended'] = 'The user "{$a}" is suspended. No link was created.';
