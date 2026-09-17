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
 * Non-dropdown Multichoice renderer for qtype_clozeonimage.
 *
 * @package    qtype_clozeonimage
 * @copyright  2026 DynamicCourseware.org (Dominique Bauer)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Render vertical and horizontal Multichoice controls with unified review feedback.
 */
class qtype_clozeonimage_multichoice_renderer extends qtype_multianswer_subq_renderer_base {
    use qtype_clozeonimage_feedback_renderer_trait;

    #[\Override]
    public function subquestion(
        question_attempt $qa,
        question_display_options $options,
        $index,
        question_graded_automatically $subq
    ) {
        if ($subq instanceof qtype_multichoice_multi_question) {
            throw new coding_exception('Expecting a single-answer Multichoice subquestion.');
        }

        $this->displayoptions = $options;
        $this->initialise_feedback_appearance($qa);

        $fieldname = 'sub' . $index . '_answer';
        $inputname = $qa->get_qt_field_name($fieldname);
        $response = $qa->get_last_qt_var($fieldname);
        $order = $subq->get_order($qa);
        $answered = !is_null($response) && $subq->is_complete_response(['answer' => $response]);
        $fraction = null;
        $specificfeedback = '';

        foreach ($order as $value => $ansid) {
            $answer = $subq->answers[$ansid];
            if (!$subq->is_choice_selected($response, $value)) {
                continue;
            }
            $fraction = $answer->fraction;
            if ($options->feedback && trim($answer->feedback)) {
                $specificfeedback = $subq->format_text(
                    $answer->feedback,
                    $answer->feedbackformat,
                    $qa,
                    'question',
                    'answerfeedback',
                    $ansid
                );
            }
        }

        [$stateclass, $statustext] = $this->review_state($fraction, $answered, $options);
        $rightanswer = '';
        foreach ($subq->answers as $answer) {
            if (question_state::graded_state_for_fraction($answer->fraction) === question_state::$gradedright) {
                $rightanswer = $subq->format_text(
                    $answer->answer,
                    $answer->answerformat,
                    $qa,
                    'question',
                    'answer',
                    $answer->id
                );
                break;
            }
        }
        $feedbackpopup = $this->feedback_popup(
            $subq,
            $fraction,
            $specificfeedback,
            $rightanswer,
            $options
        );

        $horizontal = (int) $subq->layout === qtype_multichoice_base::LAYOUT_HORIZONTAL;
        $inputattributes = [
            'type' => 'radio',
            'name' => $inputname,
            'class' => 'form-check-input',
            'data-role' => 'clozeonimage-multichoice-choice',
        ];
        if ($options->readonly) {
            $inputattributes['disabled'] = 'disabled';
        }

        $legend = $this->get_answer_label('multichoicex', 'qtype_multianswer');
        $legend .= $this->result_state_text($statustext);
        $result = $this->choices_wrapper_start($legend);
        foreach ($order as $value => $ansid) {
            $answer = $subq->answers[$ansid];
            $inputattributes['value'] = $value;
            $inputattributes['id'] = $inputname . $value;
            $isselected = $subq->is_choice_selected($response, $value);
            if ($isselected) {
                $inputattributes['checked'] = 'checked';
            } else {
                unset($inputattributes['checked']);
            }

            $class = 'form-check text-wrap text-break qtype-clozeonimage-choice';
            if ($options->correctness && $isselected) {
                $class .= ' ' . $this->feedback_class($answer->fraction);
            }
            if ($horizontal) {
                $class .= ' form-check-inline';
            }

            $result .= html_writer::start_tag('div', ['class' => $class]);
            $control = html_writer::empty_tag('input', $inputattributes);
            $choicelabel = $subq->format_text(
                $answer->answer,
                $answer->answerformat,
                $qa,
                'question',
                'answer',
                $ansid
            );
            if ($options->correctness && $isselected) {
                $choicelabel .= $this->result_state_text(
                    question_state::graded_state_for_fraction($answer->fraction)->default_string(true)
                );
            }
            $control .= html_writer::tag(
                'label',
                $choicelabel,
                ['for' => $inputattributes['id'], 'class' => 'form-check-label text-body']
            );
            $result .= html_writer::span($control, 'qtype-clozeonimage-choice-control');
            $result .= html_writer::end_tag('div');
        }
        if (!$options->readonly) {
            $clearid = $inputname . '-clear';
            $clearlabel = get_string('clearchoiceforsubquestion', 'qtype_clozeonimage', $index);
            $clearattributes = [
                'type' => 'radio',
                'name' => $inputname,
                'id' => $clearid,
                'value' => -1,
                'class' => 'visually-hidden qtype-clozeonimage-clear-choice-sentinel',
                'data-role' => 'clozeonimage-clear-choice-sentinel',
                'aria-hidden' => 'true',
            ];
            if ($answered) {
                $clearattributes['disabled'] = 'disabled';
            } else {
                $clearattributes['checked'] = 'checked';
            }
            $result .= html_writer::empty_tag('input', $clearattributes);
        }
        $result .= $this->choices_wrapper_end();
        if ($options->readonly) {
            $result .= $this->feedback_surface_button($feedbackpopup, (int) $index, $statustext);
        } else {
            $buttonattributes = [
                'type' => 'button',
                'class' => 'qtype-clozeonimage-clear-choice',
                'data-action' => 'clozeonimage-clear-choice',
                'title' => $clearlabel,
            ];
            if (!$answered || $feedbackpopup !== '') {
                $buttonattributes['hidden'] = 'hidden';
            }
            $result .= html_writer::tag(
                'button',
                html_writer::span('C', '', ['aria-hidden' => 'true']) .
                    html_writer::span($clearlabel, 'visually-hidden'),
                $buttonattributes
            );
            $this->page->requires->js_call_amd('qtype_clozeonimage/clearchoice', 'init');
        }

        $regionclass = 'qtype-clozeonimage-feedback-region';
        if ($stateclass !== '') {
            $regionclass .= ' qtype-clozeonimage-state-' . $stateclass;
        }
        $regionattributes = [
            'class' => $regionclass,
            'data-region' => 'clozeonimage-multichoice',
        ];
        if (!$options->readonly) {
            $regionattributes = $this->feedback_trigger_attributes($regionattributes, $feedbackpopup);
        }
        return html_writer::div($result, '', $regionattributes);
    }

    /**
     * Start the fieldset containing all Multichoice options.
     *
     * @param string $legend Accessible legend text.
     * @return string Opening fieldset and accessible legend.
     */
    private function choices_wrapper_start(string $legend): string {
        return html_writer::start_tag('fieldset', ['class' => 'answer']) . html_writer::tag(
            'legend',
            $legend,
            ['class' => 'visually-hidden']
        );
    }

    /**
     * End the fieldset containing all Multichoice options.
     *
     * @return string Closing fieldset tag.
     */
    private function choices_wrapper_end(): string {
        return html_writer::end_tag('fieldset');
    }
}
