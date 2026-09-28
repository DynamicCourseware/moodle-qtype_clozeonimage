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
 * Adapted from Moodle core question/type/multianswer/renderer.php.
 * Modifications for Cloze on Image copyright 2026 DynamicCourseware.org.
 *
 * @package    qtype_clozeonimage
 * @copyright  2010 Pierre Pichet
 * @copyright  2011 The Open University
 * @copyright  2026 DynamicCourseware.org (Dominique Bauer)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Render dropdown Multichoice with a geometry-neutral review-feedback surface.
 */
class qtype_clozeonimage_multichoice_inline_renderer extends qtype_multianswer_multichoice_inline_renderer {
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

        $fieldname = 'sub' . $index . '_answer';
        $response = $qa->get_last_qt_var($fieldname);
        $choices = [];
        $matchinganswer = new question_answer(0, '', null, '', FORMAT_HTML);
        foreach ($subq->get_order($qa) as $value => $ansid) {
            $answer = $subq->answers[$ansid];
            $choices[$value] = $subq->format_text(
                $answer->answer,
                $answer->answerformat,
                $qa,
                'question',
                'answer',
                $ansid
            );
            if ($subq->is_choice_selected($response, $value)) {
                $matchinganswer = $answer;
            }
        }

        $inputname = $qa->get_qt_field_name($fieldname);
        $inputattributes = [
            'id' => $inputname,
            'class' => 'form-select d-inline-block mb-1',
        ];
        if ($options->readonly) {
            $inputattributes['disabled'] = 'disabled';
        }

        $answered = !is_null($response) && $response !== '' && (string) $response !== '-1';
        [$stateclass, $statustext] = $this->review_state($matchinganswer->fraction, $answered, $options);
        if ($stateclass !== '' && $stateclass !== 'notanswered') {
            $inputattributes['class'] .= ' ' . $stateclass;
        }

        $order = $subq->get_order($qa);
        $correctresponses = $subq->get_correct_response();
        $rightanswer = $subq->answers[$order[reset($correctresponses)]];
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
            $subq->format_text(
                $rightanswer->answer,
                $rightanswer->answerformat,
                $qa,
                'question',
                'answer',
                $rightanswer->id
            ),
            $options
        );

        $surfacebutton = '';
        if ($options->readonly) {
            $surfacebutton = $this->feedback_surface_button($feedbackpopup, (int) $index, $statustext);
        } else {
            $inputattributes = $this->feedback_trigger_attributes($inputattributes, $feedbackpopup);
        }

        $regionclass = 'subquestion qtype-clozeonimage-feedback-region';
        if ($stateclass !== '') {
            $regionclass .= ' qtype-clozeonimage-state-' . $stateclass;
        }
        $answerlabel = $this->get_answer_label();
        $answerlabel .= $this->result_state_text($statustext);

        $output = html_writer::start_tag('span', ['class' => $regionclass]);
        $output .= html_writer::tag('label', $answerlabel, [
            'class' => 'subq accesshide',
            'for' => $inputname,
        ]);
        $output .= html_writer::select($choices, $inputname, $response, ['' => '&nbsp;'], $inputattributes);
        $output .= $surfacebutton;
        $output .= html_writer::end_tag('span');

        return $output;
    }
}
