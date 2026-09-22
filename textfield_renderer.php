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
    use qtype_clozeonimage_feedback_renderer_trait;

    #[\Override]
    public function subquestion(
        question_attempt $qa,
        question_display_options $options,
        $index,
        question_graded_automatically $subq
    ) {
        $this->displayoptions = $options;
        $this->initialise_feedback_appearance($qa);

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
            $this->page->requires->js_call_amd('qtype_clozeonimage/feedback', 'init');
            if ($qa->get_behaviour() instanceof qbehaviour_interactive && $qa->get_behaviour()->is_try_again_state()) {
                $inputattributes['data-clozeonimage-awaiting-retry'] = 'true';
            }
        }

        $answered = !is_null($response) && $response !== '';
        [$stateclass, $statustext] = $this->review_state($matchinganswer->fraction, $answered, $options);
        if ($stateclass !== '' && $stateclass !== 'notanswered') {
            $inputattributes['class'] .= ' ' . $stateclass;
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
        $inputattributes = $this->feedback_trigger_attributes($inputattributes, $feedbackpopup);

        $regionclass = 'subquestion qtype-clozeonimage-feedback-region';
        if ($stateclass !== '') {
            $regionclass .= ' qtype-clozeonimage-state-' . $stateclass;
        }
        $answerlabel = $this->get_answer_label();
        $answerlabel .= $this->result_state_text($statustext);
        $output = html_writer::start_tag('span', ['class' => $regionclass]);
        $output .= html_writer::tag('label', $answerlabel, [
            'class' => 'subq accesshide',
            'for' => $inputattributes['id'],
        ]);
        $output .= html_writer::empty_tag('input', $inputattributes);
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
}
