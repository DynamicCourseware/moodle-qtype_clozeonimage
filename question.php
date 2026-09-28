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
 * Question definition for qtype_clozeonimage.
 *
 * Adapted from Moodle core question/type/multianswer/question.php.
 * Modifications for Cloze on Image copyright 2026 DynamicCourseware.org.
 *
 * @package    qtype_clozeonimage
 * @copyright  2010 Pierre Pichet
 * @copyright  2026 DynamicCourseware.org (Dominique Bauer)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/type/multianswer/question.php');

/**
 * A Cloze composite question whose subquestions are positioned on an image.
 */
class qtype_clozeonimage_question extends qtype_multianswer_question {
    /** @var string Comma-separated IDs of the wrapped subquestions. */
    public $sequence = '';

    /** @var int[] Original sequence slots, including slots whose child question is missing. */
    public $subquestionslots = [];

    /** @var array<int, stdClass> positions indexed by subquestion number. */
    public $positions = [];

    /** @var bool Whether stored position records cannot be associated safely with sequence slots. */
    public $positionmetadataiscorrupt = false;

    /** @var int 0 = original size; 1 = fit width. */
    public $displaymode = 0;

    /** @var int Stored composition width in CSS pixels; 0 means intrinsic width. */
    public $displaywidth = 0;

    /** @var int Answer control appearance. */
    public $controlappearance = 0;

    /** @var string Text displayed after the image. */
    public $aftertext = '';

    /** @var int Text format for aftertext. */
    public $aftertextformat = FORMAT_HTML;

    /**
     * Clear wrong responses while preserving the unanswered sentinel for non-dropdown single-choice controls.
     *
     * @param array $response Current response data.
     * @return array Response data with wrong parts cleared.
     */
    #[\Override]
    public function clear_wrong_from_response(array $response) {
        $cleanresponse = parent::clear_wrong_from_response($response);

        foreach ($this->subquestions as $index => $subquestion) {
            if (
                !($subquestion instanceof qtype_multichoice_single_question) ||
                    !in_array((int) $subquestion->layout, [
                        qtype_multichoice_base::LAYOUT_VERTICAL,
                        qtype_multichoice_base::LAYOUT_HORIZONTAL,
                    ], true)
            ) {
                continue;
            }

            $substep = $this->get_substep(null, $index);
            $subresponse = $substep->filter_array($response);
            [, $state] = $subquestion->grade_response($subresponse);
            if ($state == question_state::$gradedright) {
                continue;
            }

            foreach (array_keys($subresponse) as $name) {
                $fieldname = $substep->add_prefix($name);
                if (array_key_exists($fieldname, $cleanresponse) && $cleanresponse[$fieldname] === '') {
                    $cleanresponse[$fieldname] = '-1';
                }
            }
        }

        return $cleanresponse;
    }


    /**
     * Check access to files stored by this question type.
     */
    public function check_file_access($qa, $options, $component, $filearea, $args, $forcedownload) {
        if (
            $component === 'qtype_clozeonimage' &&
                ($filearea === 'bgimage' || $filearea === 'aftertext')
        ) {
            if (empty($args)) {
                return false;
            }
            $itemid = (int) reset($args);
            return $itemid === (int) $this->id;
        }

        return parent::check_file_access($qa, $options, $component, $filearea, $args, $forcedownload);
    }

    /**
     * Return a useful plain-text summary without relying on Cloze text markers.
     */
    public function get_question_summary() {
        $summary = $this->html_to_text($this->questiontext, $this->questiontextformat);
        foreach ($this->subquestions as $subq) {
            switch ($subq->qtype->name()) {
                case 'multichoice':
                    $choices = [];
                    $dummyqa = new question_attempt($subq, $this->contextid);
                    foreach ($subq->get_order($dummyqa) as $ansid) {
                        $choices[] = $this->html_to_text(
                            $subq->answers[$ansid]->answer,
                            $subq->answers[$ansid]->answerformat
                        );
                    }
                    $summary .= ' {' . implode('; ', $choices) . '}';
                    break;
                case 'numerical':
                case 'shortanswer':
                    $summary .= ' _____';
                    break;
            }
        }
        if ($this->aftertext !== '') {
            $summary .= ' ' . $this->html_to_text($this->aftertext, $this->aftertextformat);
        }
        return trim($summary);
    }

    /**
     * Return the question-level validation messages for this response.
     *
     * @param array $response The response to validate.
     * @return string[] Validation messages in display order.
     */
    public function get_validation_messages(array $response): array {
        $hasinvalidnumerical = false;
        $hasincompletechild = false;

        foreach ($this->subquestions as $i => $subq) {
            $subresponse = $this->get_substep(null, $i)->filter_array($response);
            if ($subq->is_complete_response($subresponse)) {
                continue;
            }
            if (
                $subq->qtype->name() === 'numerical' && $subq->is_gradable_response($subresponse) &&
                    $subq->get_validation_error($subresponse) !== ''
            ) {
                $hasinvalidnumerical = true;
                continue;
            }
            $hasincompletechild = true;
        }

        $messages = [];
        if ($hasinvalidnumerical) {
            $messages[] = get_string('invalidnumericalresponses', 'qtype_clozeonimage');
        }
        if ($hasincompletechild) {
            $messages[] = get_string('pleaseananswerallparts', 'qtype_multianswer');
        }
        return $messages;
    }

    /**
     * Return a plain-text validation error for question-engine callers.
     */
    public function get_validation_error(array $response) {
        return implode(' ', $this->get_validation_messages($response));
    }
}
