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

use html_writer;

/**
 * Small HTML pieces of the "Connect to VCOSTAEDU" page.
 *
 * Copying uses the core module core/copy_to_clipboard and showing the key uses the
 * core module core/togglesensitive, so the plugin ships no JavaScript of its own.
 *
 * @package    local_vcostaedu
 * @copyright  2026 VCOSTA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class page_elements {
    /**
     * A read-only field with a copy button. A secret field is masked and gets the core
     * show/hide toggle.
     *
     * @param string $id element id
     * @param string $label
     * @param string $value
     * @param bool $secret mask the value until the viewer asks to see it
     * @return string HTML
     */
    public static function copy_field(string $id, string $label, string $value, bool $secret): string {
        global $PAGE;

        $PAGE->requires->js_call_amd('core/copy_to_clipboard');
        if ($secret) {
            $PAGE->requires->js_call_amd('core/togglesensitive', 'init', [$id, false]);
        }

        $html = html_writer::label(s($label), $id, true, ['class' => 'form-label fw-bold']);
        $html .= html_writer::start_div('d-flex align-items-start gap-2 mb-3 local-vcostaedu-copyfield');
        $html .= html_writer::start_div('flex-grow-1');
        $html .= html_writer::empty_tag('input', [
            'type' => $secret ? 'password' : 'text',
            'id' => $id,
            'class' => 'form-control',
            'value' => $value,
            'readonly' => 'readonly',
            'autocomplete' => 'off',
            'spellcheck' => 'false',
        ]);
        $html .= html_writer::end_div();
        $html .= html_writer::tag('button', s(get_string('copy', setup::COMPONENT)), [
            'type' => 'button',
            'class' => 'btn btn-primary',
            'data-action' => 'copytoclipboard',
            'data-clipboard-target' => '#' . $id,
            'data-clipboard-success-message' => get_string('copied', setup::COMPONENT),
        ]);
        $html .= html_writer::end_div();
        return $html;
    }
}
