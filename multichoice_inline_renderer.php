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
 * Dropdown Multichoice renderer for qtype_clozeonimage.
 *
 * @package    qtype_clozeonimage
 * @copyright  2026 DynamicCourseware.org (Dominique Bauer)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Adds the per-question appearance class to the standard Cloze feedback popover.
 */
class qtype_clozeonimage_multichoice_inline_renderer extends qtype_multianswer_multichoice_inline_renderer {
    /** @var string Appearance class transferred to the generated feedback popover. */
    private string $feedbackpopoverclass = 'qtype-clozeonimage-appearance-translucent';

    #[\Override]
    public function subquestion(
        question_attempt $qa,
        question_display_options $options,
        $index,
        question_graded_automatically $subq
    ) {
        $this->feedbackpopoverclass = qtype_clozeonimage::control_appearance_class(
            $qa->get_question()->controlappearance
        );

        return parent::subquestion($qa, $options, $index, $subq);
    }

    #[\Override]
    protected function get_feedback_image(string $icon, string $feedbackcontents): string {
        if ($icon === '') {
            return '';
        }

        $this->page->requires->js_call_amd('qtype_multianswer/feedback', 'initPopovers');
        $this->page->requires->js_call_amd('qtype_clozeonimage/feedback', 'init');

        return html_writer::link('#', $icon, [
            'role' => 'button',
            'tabindex' => 0,
            'class' => 'feedbacktrigger btn btn-link p-0',
            'data-bs-toggle' => 'popover',
            'data-bs-container' => 'body',
            'data-bs-content' => $feedbackcontents,
            'data-bs-placement' => 'right',
            'data-bs-trigger' => 'hover focus',
            'data-bs-html' => 'true',
            'data-bs-custom-class' => $this->feedbackpopoverclass,
        ]);
    }
}
