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
 * Renderer for qtype_clozeonimage.
 *
 * @package    qtype_clozeonimage
 * @copyright  2026 DynamicCourseware.org (Dominique Bauer)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/type/multianswer/renderer.php');
require_once($CFG->dirroot . '/question/type/clozeonimage/textfield_renderer.php');
require_once($CFG->dirroot . '/question/type/clozeonimage/multichoice_inline_renderer.php');
require_once($CFG->dirroot . '/question/type/clozeonimage/multichoice_renderer.php');
require_once($CFG->dirroot . '/question/type/clozeonimage/multiresponse_renderer.php');

/**
 * Main Cloze on Image renderer.
 */
class qtype_clozeonimage_renderer extends qtype_multianswer_renderer {
    #[\Override]
    public function formulation_and_controls(question_attempt $qa, question_display_options $options) {
        $question = $qa->get_question();

        $output = $question->format_text(
            $question->questiontext,
            $question->questiontextformat,
            $qa,
            'question',
            'questiontext',
            $question->id
        );

        $imageurl = $this->background_image_url($qa);
        if ($imageurl) {
            $imagestyle = 'max-width:none;height:auto;';
            if (!empty($question->displaywidth)) {
                $imagestyle .= 'width:' . (int)$question->displaywidth . 'px;';
            }
            $image = html_writer::empty_tag('img', [
                'src' => $imageurl,
                'alt' => get_string('backgroundimagealt', 'qtype_clozeonimage'),
                'class' => 'qtype-clozeonimage-image',
                'style' => $imagestyle,
            ]);

            $controls = '';
            $missingsubquestions = false;
            $slots = $question->subquestionslots ?: array_keys($question->subquestions);
            foreach ($slots as $index) {
                if (isset($question->subquestions[$index])) {
                    $subq = $question->subquestions[$index];
                } else {
                    $missingsubquestions = true;
                    $subq = qtype_multianswer::deleted_subquestion_replacement();
                }
                $position = $question->positions[$index] ?? null;
                $x = $position ? (int)$position->xleft : 12 + 20 * ($index - 1);
                $y = $position ? (int)$position->ytop : 12 + 20 * ($index - 1);
                $controls .= html_writer::div(
                    $this->subquestion($qa, $options, $index, $subq),
                    'qtype-clozeonimage-subquestion',
                    ['style' => 'left:' . $x . 'px;top:' . $y . 'px;']
                );
            }

            $composition = html_writer::div(
                $image . $controls,
                'qtype-clozeonimage-composition ' . qtype_clozeonimage::control_appearance_class(
                    $question->controlappearance
                )
            );
            if ($missingsubquestions) {
                $output .= $this->notification(get_string('corruptedquestion', 'qtype_multianswer'), 'error');
            }
            if ($question->positionmetadataiscorrupt) {
                $output .= $this->notification(
                    get_string('corruptpositionmetadata', 'qtype_clozeonimage'),
                    'error'
                );
            }
            $output .= html_writer::div($composition, 'qtype-clozeonimage-scroll');
            $this->page->requires->js_call_amd('qtype_clozeonimage/layout', 'init');
        }

        if ($question->aftertext !== '') {
            $output .= html_writer::div(
                $question->format_text(
                    $question->aftertext,
                    $question->aftertextformat,
                    $qa,
                    'qtype_clozeonimage',
                    'aftertext',
                    $question->id
                ),
                'qtype-clozeonimage-aftertext'
            );
        }

        if ($qa->get_state() == question_state::$invalid) {
            foreach ($question->get_validation_messages($qa->get_last_qt_data()) as $message) {
                $output .= html_writer::div($message, 'validationerror');
            }
        }

        return $output;
    }

    #[\Override]
    public function subquestion(
        question_attempt $qa,
        question_display_options $options,
        $index,
        question_automatically_gradable $subq
    ) {
        $subtype = $subq->qtype->name();
        if ($subtype === 'shortanswer' || $subtype === 'numerical') {
            $renderer = $this->page->get_renderer('qtype_clozeonimage', 'textfield');
            return $renderer->subquestion($qa, $options, $index, $subq);
        }
        if ($subtype === 'multichoice') {
            if (
                !($subq instanceof qtype_multichoice_multi_question) &&
                    (int) $subq->layout === qtype_multichoice_base::LAYOUT_DROPDOWN
            ) {
                $renderer = $this->page->get_renderer('qtype_clozeonimage', 'multichoice_inline');
            } else if ($subq instanceof qtype_multichoice_multi_question) {
                $renderer = $this->page->get_renderer('qtype_clozeonimage', 'multiresponse');
            } else {
                $renderer = $this->page->get_renderer('qtype_clozeonimage', 'multichoice');
            }
            return $renderer->subquestion($qa, $options, $index, $subq);
        }
        return parent::subquestion($qa, $options, $index, $subq);
    }

    /**
     * Return the URL of the background image for a question attempt.
     *
     * @param question_attempt $qa Question attempt being rendered.
     * @return string Background image URL, or an empty string when no image exists.
     */
    private function background_image_url(question_attempt $qa): string {
        $question = $qa->get_question();
        $qubaid = $qa->get_usage_id();
        $slot = $qa->get_slot();
        $fs = get_file_storage();
        $files = $fs->get_area_files(
            $question->contextid,
            'qtype_clozeonimage',
            'bgimage',
            $question->id,
            'itemid, filepath, filename',
            false
        );
        if (!$files) {
            return '';
        }
        $file = reset($files);
        return moodle_url::make_pluginfile_url(
            $question->contextid,
            'qtype_clozeonimage',
            'bgimage',
            "$qubaid/$slot/{$question->id}",
            $file->get_filepath(),
            $file->get_filename()
        )->out(false);
    }
}
