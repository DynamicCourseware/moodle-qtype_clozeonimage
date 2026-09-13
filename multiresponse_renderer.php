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
 * Multiple Response renderer for qtype_clozeonimage.
 *
 * @package    qtype_clozeonimage
 * @copyright  2026 DynamicCourseware.org (Dominique Bauer)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Render vertical and horizontal Multiple Response controls with unified review feedback.
 */
class qtype_clozeonimage_multiresponse_renderer extends qtype_multianswer_subq_renderer_base {
    use qtype_clozeonimage_feedback_renderer_trait;

    #[\Override]
    public function subquestion(
        question_attempt $qa,
        question_display_options $options,
        $index,
        question_graded_automatically $subq
    ) {
        if (!$subq instanceof qtype_multichoice_multi_question) {
            throw new coding_exception('Expecting a Multiple Response subquestion.');
        }

        $this->displayoptions = $options;
        $this->initialise_feedback_appearance($qa);

        $fieldprefix = 'sub' . $index . '_';
        $basename = $qa->get_qt_field_name($fieldprefix . 'choice');
        $fieldprefixlen = strlen($fieldprefix);
        $response = [];
        foreach ($qa->get_last_qt_data() as $name => $value) {
            if (substr($name, 0, $fieldprefixlen) === $fieldprefix) {
                $response[substr($name, $fieldprefixlen)] = $value;
            }
        }

        $order = $subq->get_order($qa);
        $answered = $subq->is_complete_response($response);
        [$fraction] = $subq->grade_response($response);
        $specificfeedback = [];
        foreach ($order as $value => $ansid) {
            $answer = $subq->answers[$ansid];
            if (
                $options->feedback && $subq->is_choice_selected($response, $value) &&
                    trim($answer->feedback)
            ) {
                $answertext = $subq->format_text(
                    $answer->answer,
                    $answer->answerformat,
                    $qa,
                    'question',
                    'answer',
                    $ansid
                );
                $specificfeedback[] = html_writer::div(
                    html_writer::div($answertext, 'qtype-clozeonimage-feedback-choice') .
                    html_writer::div($subq->format_text(
                        $answer->feedback,
                        $answer->feedbackformat,
                        $qa,
                        'question',
                        'answerfeedback',
                        $ansid
                    ), 'qtype-clozeonimage-feedback-choice-text'),
                    'qtype-clozeonimage-feedback-choice-item'
                );
            }
        }

        [$stateclass, $statustext] = $this->review_state($fraction, $answered, $options);
        $correct = [];
        foreach ($subq->answers as $answer) {
            if (question_state::graded_state_for_fraction($answer->fraction) !== question_state::$gradedwrong) {
                $correct[] = $subq->format_text(
                    $answer->answer,
                    $answer->answerformat,
                    $qa,
                    'question',
                    'answer',
                    $answer->id
                );
            }
        }
        $rightanswer = $correct ? '<ul><li>' . implode('</li><li>', $correct) . '</li></ul>' : '';
        $feedbackpopup = $this->feedback_popup(
            $subq,
            $answered ? $fraction : null,
            implode('', $specificfeedback),
            $rightanswer,
            $options
        );

        $horizontal = (int) $subq->layout === qtype_multichoice_base::LAYOUT_HORIZONTAL;
        $inputattributes = [
            'type' => 'checkbox',
            'value' => 1,
            'class' => 'form-check-input',
        ];
        if ($options->readonly) {
            $inputattributes['disabled'] = 'disabled';
        } else {
            $inputattributes = $this->feedback_trigger_attributes($inputattributes, $feedbackpopup);
        }

        $grouplabelid = $basename . '-label';
        $grouplabel = $this->get_answer_label('multichoicex', 'qtype_multianswer');
        if ($statustext !== '') {
            $grouplabel .= ' ' . $statustext;
        }
        $result = html_writer::span($grouplabel, 'visually-hidden', ['id' => $grouplabelid]);
        $result .= $this->choices_wrapper_start($horizontal);
        foreach ($order as $value => $ansid) {
            $answer = $subq->answers[$ansid];
            $inputattributes['name'] = $basename . $value;
            $inputattributes['id'] = $basename . $value;
            $isselected = $subq->is_choice_selected($response, $value);
            if ($isselected) {
                $inputattributes['checked'] = 'checked';
            } else {
                unset($inputattributes['checked']);
            }

            $class = 'form-check text-wrap text-break qtype-clozeonimage-choice';
            $localstate = null;
            if ($options->correctness && $isselected) {
                if ($answer->fraction > 0) {
                    $class .= ' correct';
                    $localstate = question_state::$gradedright;
                } else if ($answer->fraction < 0) {
                    $class .= ' incorrect';
                    $localstate = question_state::$gradedwrong;
                }
            }
            if ($horizontal) {
                $class .= ' form-check-inline';
            }

            $result .= $this->choice_wrapper_start($class, $horizontal);
            $control = html_writer::empty_tag('input', $inputattributes);
            $choicelabel = $subq->format_text(
                $answer->answer,
                $answer->answerformat,
                $qa,
                'question',
                'answer',
                $ansid
            );
            if ($localstate !== null) {
                $choicelabel .= html_writer::span(
                    ' ' . $localstate->default_string(true),
                    'visually-hidden'
                );
            }
            $control .= html_writer::tag(
                'label',
                $choicelabel,
                ['for' => $inputattributes['id'], 'class' => 'form-check-label text-body']
            );
            $result .= html_writer::span($control, 'qtype-clozeonimage-choice-control');
            $result .= $this->choice_wrapper_end($horizontal);
        }
        $result .= $this->choices_wrapper_end($horizontal);
        if ($options->readonly) {
            $result .= $this->feedback_surface_button($feedbackpopup, (int) $index, $statustext);
        }

        $regionclass = 'qtype-clozeonimage-feedback-region';
        if ($stateclass !== '') {
            $regionclass .= ' qtype-clozeonimage-state-' . $stateclass;
        }
        return html_writer::div($result, $regionclass, [
            'role' => 'group',
            'aria-labelledby' => $grouplabelid,
        ]);
    }

    /**
     * Start the wrapper containing all Multiple Response choices.
     *
     * @param bool $horizontal Whether choices use the horizontal table layout.
     * @return string Opening wrapper markup.
     */
    private function choices_wrapper_start(bool $horizontal): string {
        if ($horizontal) {
            return html_writer::start_tag('table', ['class' => 'answer']) .
                html_writer::start_tag('tbody') . html_writer::start_tag('tr');
        }
        return html_writer::start_tag('div', ['class' => 'answer']);
    }

    /**
     * End the wrapper containing all Multiple Response choices.
     *
     * @param bool $horizontal Whether choices use the horizontal table layout.
     * @return string Closing wrapper markup.
     */
    private function choices_wrapper_end(bool $horizontal): string {
        if ($horizontal) {
            return html_writer::end_tag('tr') . html_writer::end_tag('tbody') . html_writer::end_tag('table');
        }
        return html_writer::end_tag('div');
    }

    /**
     * Start one Multiple Response choice wrapper.
     *
     * @param string $class Choice wrapper classes.
     * @param bool $horizontal Whether choices use the horizontal table layout.
     * @return string Opening choice wrapper.
     */
    private function choice_wrapper_start(string $class, bool $horizontal): string {
        return html_writer::start_tag($horizontal ? 'td' : 'div', ['class' => $class]);
    }

    /**
     * End one Multiple Response choice wrapper.
     *
     * @param bool $horizontal Whether choices use the horizontal table layout.
     * @return string Closing choice wrapper.
     */
    private function choice_wrapper_end(bool $horizontal): string {
        return html_writer::end_tag($horizontal ? 'td' : 'div');
    }
}
