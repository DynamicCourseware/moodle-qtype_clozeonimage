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
 * Render vertical and horizontal Multichoice controls with disclosed inline feedback.
 */
class qtype_clozeonimage_multichoice_renderer extends qtype_multianswer_subq_renderer_base {
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
        if ($subq instanceof qtype_multichoice_multi_question) {
            throw new coding_exception('Expecting a single-answer Multichoice subquestion.');
        }

        $this->displayoptions = $options;
        $this->feedbackids = [];
        $this->triggercount = 0;
        $this->subquestionindex = (int) $index;

        $fieldname = 'sub' . $index . '_answer';
        $inputname = $qa->get_qt_field_name($fieldname);
        $response = $qa->get_last_qt_var($fieldname);
        $order = $subq->get_order($qa);
        $specificfeedback = [];
        $fraction = null;

        foreach ($order as $value => $ansid) {
            $answer = $subq->answers[$ansid];
            if (!$subq->is_choice_selected($response, $value)) {
                continue;
            }
            $fraction = $answer->fraction;
            if ($options->feedback && trim($answer->feedback)) {
                $feedbackid = $inputname . '-specificfeedback-' . $value;
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

        $outcome = $this->outcome($qa, $options, $subq, $fraction, $inputname);
        $fallbacktrigger = $this->feedbackids && !($options->correctness && $fraction !== null);
        $firstchoice = array_key_first($order);
        $horizontal = (int) $subq->layout === qtype_multichoice_base::LAYOUT_HORIZONTAL;
        $inputattributes = [
            'type' => 'radio',
            'name' => $inputname,
            'class' => 'form-check-input',
        ];
        if ($options->readonly) {
            $inputattributes['disabled'] = 'disabled';
        }

        $result = $this->choices_wrapper_start();
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
            $resulticon = '';
            if ($options->correctness && $isselected) {
                $class .= ' ' . $this->feedback_class($answer->fraction);
                $resulticon = $this->result_icon($this->feedback_image($answer->fraction));
            } else if ($fallbacktrigger && $value === $firstchoice) {
                $resulticon = $this->feedback_trigger($this->output->pix_icon('i/info', ''), true);
            }
            if ($horizontal) {
                $class .= ' form-check-inline';
            }

            $result .= html_writer::start_tag('div', ['class' => $class]);
            $control = html_writer::empty_tag('input', $inputattributes);
            $control .= html_writer::tag(
                'label',
                $subq->format_text($answer->answer, $answer->answerformat, $qa, 'question', 'answer', $ansid),
                ['for' => $inputattributes['id'], 'class' => 'form-check-label text-body']
            );
            $control .= $resulticon;
            $result .= html_writer::span($control, 'qtype-clozeonimage-choice-control');
            $result .= $specificfeedback[$value] ?? '';
            $result .= html_writer::end_tag('div');
        }
        $result .= $this->choices_wrapper_end();
        $result .= $outcome;

        if ($this->triggercount > 0) {
            $this->page->requires->js_call_amd('qtype_clozeonimage/feedback', 'init');
        }

        return html_writer::div($result, 'qtype-clozeonimage-choice-feedback', [
            'data-region' => 'clozeonimage-choice-feedback',
        ]);
    }

    /**
     * Render the outcome below the complete choice group.
     *
     * @param question_attempt $qa Question attempt being rendered.
     * @param question_display_options $options Display options.
     * @param question_graded_automatically $subq Subquestion being rendered.
     * @param float|null $fraction Fraction earned for the selected choice.
     * @param string $inputname Unique input name used as the outcome ID prefix.
     * @return string Rendered outcome, or an empty string.
     */
    private function outcome(
        question_attempt $qa,
        question_display_options $options,
        question_graded_automatically $subq,
        ?float $fraction,
        string $inputname
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
            foreach ($subq->answers as $answer) {
                if (
                    question_state::graded_state_for_fraction($answer->fraction) ===
                        question_state::$gradedright
                ) {
                    $feedback[] = get_string(
                        'correctansweris',
                        'qtype_multichoice',
                        $subq->format_text(
                            $answer->answer,
                            $answer->answerformat,
                            $qa,
                            'question',
                            'answer',
                            $answer->id
                        )
                    );
                    break;
                }
            }
        }

        if (!$feedback) {
            return '';
        }
        $outcomeid = $inputname . '-outcome';
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
     * Start the fieldset containing all Multichoice options.
     *
     * @return string Opening fieldset and accessible legend.
     */
    private function choices_wrapper_start(): string {
        return html_writer::start_tag('fieldset', ['class' => 'answer']) . html_writer::tag(
            'legend',
            $this->get_answer_label('multichoicex', 'qtype_multianswer'),
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
