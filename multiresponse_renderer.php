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
 * Render vertical and horizontal Multiple Response controls with disclosed inline feedback.
 */
class qtype_clozeonimage_multiresponse_renderer extends qtype_multianswer_subq_renderer_base {
    /** @var string[] IDs of all feedback elements controlled by each result trigger. */
    private array $feedbackids = [];

    /** @var int Number of focusable result triggers rendered for the subquestion. */
    private int $triggercount = 0;

    /** @var int Current subquestion number used in accessible result-trigger labels. */
    private int $subquestionindex = 0;

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
        $this->feedbackids = [];
        $this->triggercount = 0;
        $this->subquestionindex = (int) $index;

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
        $fraction = 0;
        $hasselection = false;
        foreach ($order as $value => $ansid) {
            if ($subq->is_choice_selected($response, $value)) {
                $hasselection = true;
                $fraction += $subq->answers[$ansid]->fraction;
            }
        }
        $answerfraction = $fraction > 0.999 ? 1.0 : 0.5;
        $specificfeedback = [];
        foreach ($order as $value => $ansid) {
            $answer = $subq->answers[$ansid];
            if (
                $options->feedback && $subq->is_choice_selected($response, $value) &&
                    trim($answer->feedback)
            ) {
                $feedbackid = $basename . $value . '-specificfeedback';
                $this->feedbackids[] = $feedbackid;
                $specificfeedback[$value] = html_writer::tag(
                    'div',
                    $subq->format_text(
                        $answer->feedback,
                        $answer->feedbackformat,
                        $qa,
                        'question',
                        'answerfeedback',
                        $ansid
                    ),
                    ['class' => 'specificfeedback', 'id' => $feedbackid]
                );
            }
        }

        $outcome = $this->outcome($qa, $options, $subq, $fraction, $basename);
        $fallbacktrigger = $this->feedbackids && !($options->correctness && $hasselection);
        $firstchoice = array_key_first($order);
        $horizontal = (int) $subq->layout === qtype_multichoice_base::LAYOUT_HORIZONTAL;
        $inputattributes = [
            'type' => 'checkbox',
            'value' => 1,
            'class' => 'form-check-input',
        ];
        if ($options->readonly) {
            $inputattributes['disabled'] = 'disabled';
        }

        $result = $this->choices_wrapper_start($horizontal);
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
            $resulticon = '';
            if ($options->correctness && $isselected) {
                $iconfraction = $answer->fraction > 0 ? $answerfraction : 0;
                $class .= ' ' . $this->feedback_class($iconfraction);
                $resulticon = $this->result_icon($this->feedback_image($iconfraction));
            } else if ($fallbacktrigger && $value === $firstchoice) {
                $resulticon = $this->feedback_trigger($this->output->pix_icon('i/info', ''), true);
            }
            if ($horizontal) {
                $class .= ' form-check-inline';
            }

            $result .= $this->choice_wrapper_start($class, $horizontal);
            $control = html_writer::empty_tag('input', $inputattributes);
            $control .= html_writer::tag(
                'label',
                $subq->format_text($answer->answer, $answer->answerformat, $qa, 'question', 'answer', $ansid),
                ['for' => $inputattributes['id'], 'class' => 'form-check-label text-body']
            );
            $control .= $resulticon;
            $result .= html_writer::span($control, 'qtype-clozeonimage-choice-control');
            $result .= $specificfeedback[$value] ?? '';
            $result .= $this->choice_wrapper_end($horizontal);
        }
        $result .= $this->choices_wrapper_end($horizontal);
        $result .= $outcome;

        if ($this->triggercount > 0) {
            $this->page->requires->js_call_amd('qtype_clozeonimage/feedback', 'init');
        }

        return html_writer::div($result, 'qtype-clozeonimage-choice-feedback', [
            'data-region' => 'clozeonimage-choice-feedback',
        ]);
    }

    /**
     * Render the outcome below the complete Multiple Response group.
     *
     * @param question_attempt $qa Question attempt being rendered.
     * @param question_display_options $options Display options.
     * @param qtype_multichoice_multi_question $subq Subquestion being rendered.
     * @param float $fraction Total response fraction.
     * @param string $basename Unique input-name base used as the outcome ID prefix.
     * @return string Rendered outcome, or an empty string.
     */
    private function outcome(
        question_attempt $qa,
        question_display_options $options,
        qtype_multichoice_multi_question $subq,
        float $fraction,
        string $basename
    ): string {
        $feedback = [];
        if (
            $options->feedback && $options->marks >= question_display_options::MARK_AND_MAX &&
                $subq->defaultmark > 0
        ) {
            $mark = new stdClass();
            $mark->mark = format_float($fraction * $subq->defaultmark, $options->markdp);
            $mark->max = format_float($subq->defaultmark, $options->markdp);
            $feedback[] = html_writer::tag('div', get_string('markoutofmax', 'question', $mark));
        }

        if ($options->rightanswer) {
            $correct = [];
            foreach ($subq->answers as $answer) {
                if (
                    question_state::graded_state_for_fraction($answer->fraction) !==
                        question_state::$gradedwrong
                ) {
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
            $correct = '<ul><li>' . implode('</li><li>', $correct) . '</li></ul>';
            $feedback[] = get_string('correctansweris', 'qtype_multichoice', $correct);
        }

        if (!$feedback) {
            return '';
        }
        $outcomeid = $basename . '-outcome';
        $this->feedbackids[] = $outcomeid;
        return html_writer::div(implode('<br />', $feedback), 'outcome', ['id' => $outcomeid]);
    }

    /**
     * Render a correctness icon, making it a trigger when feedback content exists.
     *
     * @param string $icon Rendered Moodle correctness icon.
     * @return string Rendered result icon or feedback trigger.
     */
    private function result_icon(string $icon): string {
        if ($this->feedbackids) {
            return $this->feedback_trigger($icon);
        }
        return html_writer::span($icon, 'qtype-clozeonimage-choice-result-icon');
    }

    /**
     * Render a native feedback-disclosure button.
     *
     * @param string $icon Rendered Moodle result or information icon.
     * @param bool $group Whether this is a group-level fallback trigger.
     * @return string Rendered feedback trigger.
     */
    private function feedback_trigger(string $icon, bool $group = false): string {
        $this->triggercount++;
        $class = 'btn btn-link p-0 qtype-clozeonimage-choice-feedback-trigger';
        if ($group) {
            $class .= ' qtype-clozeonimage-choice-feedback-trigger-group';
        }
        $label = get_string('feedbackforsubquestion', 'qtype_clozeonimage', $this->subquestionindex);
        return html_writer::tag('button', $icon . html_writer::span($label, 'visually-hidden'), [
            'type' => 'button',
            'class' => $class,
            'data-region' => 'clozeonimage-feedback-trigger',
            'aria-controls' => implode(' ', $this->feedbackids),
            'aria-expanded' => 'false',
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
