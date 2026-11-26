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
 * "Connect to VCOSTAEDU" page: diagnosis, repair buttons and the data to paste into VCOSTAEDU.
 *
 * @package    local_vcostaedu
 * @copyright  2026 VCOSTA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_vcostaedu\diagnostics;
use local_vcostaedu\page_elements;
use local_vcostaedu\setup;

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

// Checks login and moodle/site:config.
admin_externalpage_setup('local_vcostaedu_connect');

$action = optional_param('action', '', PARAM_ALPHA);
$confirm = optional_param('confirm', 0, PARAM_BOOL);
$pageurl = new moodle_url('/local/vcostaedu/connect.php');
$actions = ['enablews', 'fix', 'generatekey', 'newkey'];

if ($action !== '' && !in_array($action, $actions, true)) {
    $action = '';
}

if ($action !== '' && $confirm) {
    require_sesskey();
    require_capability('moodle/site:config', context_system::instance());

    switch ($action) {
        case 'enablews':
            set_config('enablewebservices', 1);
            if (!diagnostics::rest_enabled()) {
                $protocols = empty($CFG->webserviceprotocols) ? [] : explode(',', $CFG->webserviceprotocols);
                $protocols[] = 'rest';
                set_config('webserviceprotocols', implode(',', array_unique(array_filter(array_map('trim', $protocols)))));
            }
            $message = get_string('done_enablews', 'local_vcostaedu');
            break;

        case 'fix':
            setup::run();
            $message = get_string('done_fix', 'local_vcostaedu');
            break;

        case 'generatekey':
            if (setup::get_token()) {
                redirect(
                    $pageurl,
                    get_string('done_generatekey_exists', 'local_vcostaedu'),
                    null,
                    \core\output\notification::NOTIFY_WARNING
                );
            }
            setup::generate_token(false);
            $message = get_string('done_generatekey', 'local_vcostaedu');
            break;

        case 'newkey':
            setup::generate_token(true);
            $message = get_string('done_newkey', 'local_vcostaedu');
            break;
    }
    redirect($pageurl, $message, null, \core\output\notification::NOTIFY_SUCCESS);
}

$PAGE->set_url($pageurl);
$title = get_string('connect', 'local_vcostaedu');
$PAGE->set_title($title);
$PAGE->set_heading($title);

echo $OUTPUT->header();
echo $OUTPUT->heading($title);

// Confirmation step for every action that changes something.
if ($action !== '') {
    $continue = new moodle_url($pageurl, ['action' => $action, 'confirm' => 1, 'sesskey' => sesskey()]);
    echo $OUTPUT->confirm(
        get_string('confirm_' . $action, 'local_vcostaedu'),
        new single_button(
            $continue,
            get_string('action_' . $action, 'local_vcostaedu'),
            'post',
            $action === 'newkey' ? single_button::BUTTON_DANGER : single_button::BUTTON_PRIMARY
        ),
        new single_button($pageurl, get_string('cancel'), 'get')
    );
    echo $OUTPUT->footer();
    exit;
}

echo html_writer::tag('p', s(get_string('connect_intro', 'local_vcostaedu')));

// Diagnosis.
echo $OUTPUT->heading(get_string('diag_heading', 'local_vcostaedu'), 3);
$items = diagnostics::get_items();
$badges = [
    diagnostics::OK => 'badge bg-success',
    diagnostics::WARNING => 'badge bg-warning text-dark',
    diagnostics::ERROR => 'badge bg-danger',
];
$table = new html_table();
$table->attributes['class'] = 'generaltable local-vcostaedu-diagnostics';
$table->head = [
    get_string('diag_col_item', 'local_vcostaedu'),
    get_string('diag_col_status', 'local_vcostaedu'),
    get_string('diag_col_message', 'local_vcostaedu'),
];
foreach ($items as $item) {
    $row = new html_table_row([
        s($item['title']),
        html_writer::span(s(get_string('status_' . $item['status'], 'local_vcostaedu')), $badges[$item['status']]),
        s($item['message']),
    ]);
    $row->attributes['data-item'] = $item['key'];
    $row->attributes['data-status'] = $item['status'];
    if ($item['status'] !== diagnostics::OK && $item['action']) {
        $row->attributes['data-action'] = $item['action'];
    }
    $table->data[] = $row;
}
echo html_writer::table($table);

// Buttons.
echo $OUTPUT->heading(get_string('actions_heading', 'local_vcostaedu'), 3);
$token = setup::get_token();
$needed = diagnostics::needed_actions($items);
// The button that solves a problem shown above is highlighted; the others stay plain.
$style = fn(string $name) => in_array($name, $needed, true) ? single_button::BUTTON_PRIMARY : single_button::BUTTON_SECONDARY;
$buttons = [];
if (diagnostics::webservices_need_enabling()) {
    // Shown only when there is something to turn on.
    $buttons['enablews'] = $style('enablews');
}
$buttons['fix'] = $style('fix');
if ($token) {
    // Replacing the key stops the connection in use: looks like a warning.
    $buttons['newkey'] = single_button::BUTTON_DANGER;
} else {
    $buttons['generatekey'] = $style('generatekey');
}
// With more than one thing to do, a short numbered list shows the order, and the
// buttons get the same numbers. With one thing or nothing to do, no list.
$steps = array_values(array_filter(['enablews', 'fix', 'generatekey'], fn($a) => in_array($a, $needed, true)));
$stepnumber = [];
if (count($steps) > 1) {
    $list = '';
    foreach ($steps as $i => $name) {
        $stepnumber[$name] = $i + 1;
        $list .= html_writer::tag('li', s(get_string('step_' . $name, 'local_vcostaedu')));
    }
    echo html_writer::tag('p', s(get_string('steps_intro', 'local_vcostaedu', count($steps))), ['class' => 'mb-1']);
    echo html_writer::tag('ol', $list, ['class' => 'local-vcostaedu-steps']);
}
echo html_writer::start_div('d-flex flex-wrap gap-2 mb-3');
foreach ($buttons as $name => $type) {
    $label = get_string('action_' . $name, 'local_vcostaedu');
    if (isset($stepnumber[$name])) {
        $label = get_string('step_button', 'local_vcostaedu', (object) ['number' => $stepnumber[$name], 'label' => $label]);
    }
    $button = new single_button(new moodle_url($pageurl, ['action' => $name]), $label, 'get', $type);
    $button->class .= ' local-vcostaedu-action-' . $name;
    echo $OUTPUT->render($button);
}
echo html_writer::end_div();
echo html_writer::tag('p', s(get_string('actions_help', 'local_vcostaedu')), ['class' => 'text-muted']);

// Data to paste into VCOSTAEDU.
echo $OUTPUT->heading(get_string('data_heading', 'local_vcostaedu'), 3);
echo html_writer::tag('p', s(get_string('data_intro', 'local_vcostaedu')));

echo page_elements::copy_field('local_vcostaedu_url', get_string('data_url', 'local_vcostaedu'), $CFG->wwwroot, false);
if ($token) {
    echo page_elements::copy_field('local_vcostaedu_token', get_string('data_token', 'local_vcostaedu'), $token->token, true);
    echo html_writer::tag('p', s(get_string('data_token_warning', 'local_vcostaedu')), ['class' => 'text-muted']);
} else {
    echo $OUTPUT->notification(get_string('data_token_none', 'local_vcostaedu'), \core\output\notification::NOTIFY_WARNING);
}

// Settings link.
echo html_writer::tag('p', html_writer::link(
    new moodle_url('/admin/settings.php', ['section' => 'local_vcostaedu_settings']),
    s(get_string('settings_link', 'local_vcostaedu', setup::login_validity()))
));

echo $OUTPUT->footer();
