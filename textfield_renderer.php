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
 * Text-field renderer for qtype_clozeonimage.
 *
 * @package    qtype_clozeonimage
 * @copyright  2026 DynamicCourseware.org (Dominique Bauer)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Text-field renderer matching Cloze, except that the random width increase is removed.
 */
class qtype_clozeonimage_textfield_renderer extends qtype_multianswer_subq_renderer_base {
    /** @var string Appearance class transferred to the generated feedback popover. */
    private string $feedbackpopoverclass = 'qtype-clozeonimage-appearance-translucent';

    #[\Override]
    public function subquestion(
        question_attempt $qa,
        question_display_options $options,
        $index,
        question_graded_automatically $subq
    ) {
        $this->displayoptions = $options;
        $this->feedbackpopoverclass = qtype_clozeonimage::control_appearance_class(
            $qa->get_question()->controlappearance
        );

        $fieldprefix = 'sub' . $index . '_';
        $fieldname = $fieldprefix . 'answer';
        $response = $qa->get_last_qt_var($fieldname);

        if ($subq->qtype->name() === 'shortanswer') {
            $matchinganswer = $subq->get_matching_answer(['answer' => $response]);
        } else if ($subq->qtype->name() === 'numerical') {
            [$value] = $subq->ap->apply_units($response, '');
            $matchinganswer = $subq->get_matching_answer($value, 1);
        } else {
            $matchinganswer = $subq->get_matching_answer($response);
        }

        if (!$matchinganswer) {
            if (is_null($response) || $response === '') {
                $matchinganswer = new question_answer(0, '', null, '', FORMAT_HTML);
            } else {
                $matchinganswer = new question_answer(0, '', 0.0, '', FORMAT_HTML);
            }
        }

        // Keep the positioned control's width stable when a submitted response is rendered again.
        $size = 1;
        foreach ($subq->answers as $ans) {
            $size = max($size, core_text::strlen(trim($ans->answer)));
        }
        $size = min(60, $size);

        $inputattributes = [
            'type' => 'text',
            'name' => $qa->get_qt_field_name($fieldname),
            'value' => $response,
            'id' => $qa->get_qt_field_name($fieldname),
            'size' => $size,
            'class' => 'form-control d-inline mb-1',
        ];
        $validationerror = '';
        if ($subq->qtype->name() === 'numerical' && $qa->get_state() == question_state::$invalid) {
            $subresponse = ['answer' => $response];
            if (
                $subq->is_gradable_response($subresponse) && !$subq->is_complete_response($subresponse) &&
                    $subq->get_validation_error($subresponse) !== ''
            ) {
                $validationerror = get_string('invalidnumericalresponse', 'qtype_clozeonimage');
                $inputattributes['aria-invalid'] = 'true';
                $inputattributes['aria-describedby'] = $inputattributes['id'] . '-error';
            }
        }
        if ($options->readonly) {
            $inputattributes['readonly'] = 'readonly';
        }

        $feedbackimg = '';
        if ($options->correctness) {
            $inputattributes['class'] .= ' ' . $this->feedback_class($matchinganswer->fraction);
            $feedbackimg = $this->feedback_image($matchinganswer->fraction);
        }

        if ($subq->qtype->name() === 'shortanswer') {
            $correctanswer = $subq->get_matching_answer($subq->get_correct_response());
        } else {
            $correctanswer = $subq->get_correct_answer();
        }

        $feedbackpopup = $this->feedback_popup(
            $subq,
            $matchinganswer->fraction,
            $subq->format_text(
                $matchinganswer->feedback,
                $matchinganswer->feedbackformat,
                $qa,
                'question',
                'answerfeedback',
                $matchinganswer->id
            ),
            s($correctanswer->answer),
            $options
        );

        $output = html_writer::start_tag('span', ['class' => 'subquestion']);
        $output .= html_writer::tag('label', $this->get_answer_label(), [
            'class' => 'subq accesshide',
            'for' => $inputattributes['id'],
        ]);
        $output .= html_writer::empty_tag('input', $inputattributes);
        $output .= $this->get_feedback_image($feedbackimg, $feedbackpopup);
        $output .= html_writer::end_tag('span');
        if ($validationerror !== '') {
            $output .= html_writer::div(
                $validationerror,
                'validationerror qtype-clozeonimage-numerical-validation',
                [
                'id' => $inputattributes['id'] . '-error',
                ]
            );
        }
        return $output;
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
