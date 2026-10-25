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
require_once($CFG->dirroot . '/question/type/clozeonimage/feedback_renderer_trait.php');
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
        global $CFG;
        $question = $qa->get_question();

        $missingsubquestions = false;
        $intro = $question->intro ?? (object)['text' => $question->questiontext,
            'format' => $question->questiontextformat, 'slots' => []];
        $appearance = qtype_clozeonimage::control_appearance_class($question->controlappearance);
        $output = html_writer::div($this->render_zone($intro, $qa, $options,
            'question', 'questiontext', $question->id, $missingsubquestions),
            'qtype-clozeonimage-region qtype-clozeonimage-intro ' . $appearance);

        if (\qtype_clozeonimage\local\composition::active($question)) {
            $geometry = $question->composition ?: \qtype_clozeonimage\local\composition::defaults();
            $layers = [];
            foreach ($question->images as $item) { $layers['i' . $item->elementkey] = (int)$item->layer; }
            foreach ($question->blocks as $i => $block) { $layers['b' . $i] = (int)($block->position->layer ?? 100); }
            asort($layers, SORT_NUMERIC);
            $ranks = array_flip(array_keys($layers));
            $image = html_writer::div('', 'qtype-clozeonimage-surface', ['aria-hidden' => 'true',
                'style' => 'position:absolute;left:' . $geometry['x'] . 'px;top:' . $geometry['y'] .
                    'px;width:' . $geometry['width'] . 'px;height:' . $geometry['height'] .
                    'px;background:' . $geometry['background'] . ';']);
            foreach ($question->images as $item) {
                $files = get_file_storage()->get_area_files($question->contextid,
                    'qtype_clozeonimage', 'image', $item->id, 'id', false);
                if (!$files) {
                    continue;
                }
                $file = reset($files);
                $url = moodle_url::make_pluginfile_url($question->contextid, 'qtype_clozeonimage', 'image',
                    $qa->get_usage_id() . '/' . $qa->get_slot() . '/' . $item->id,
                    $file->get_filepath(), $file->get_filename())->out(false);
                $image .= html_writer::empty_tag('img', ['src' => $url, 'alt' => $item->name,
                    'data-image-key' => $item->elementkey, 'data-layer' => (int)$item->layer, 'class' => 'qtype-clozeonimage-scene-image',
                    'style' => 'position:absolute;left:' . $item->xleft . 'px;top:' . $item->ytop .
                        'px;width:' . $item->width . 'px;height:' . $item->height . 'px;z-index:' . (($ranks['i' . $item->elementkey] ?? 0) + 1) . ';']);
            }

            $controls = '';
            $slots = $question->subquestionslots ?: array_keys($question->subquestions);
            $blocks = $question->blocks;
            if (!$blocks && $question->intro === null && $question->additional === null) {
                // Also support in-memory questions made by the core test generator.
                foreach ($slots as $index) {
                    $blocks[] = (object)['text' => '{#1}', 'format' => FORMAT_HTML,
                        'slots' => [$index], 'position' => $question->positions[$index] ?? null];
                }
            }
            foreach ($blocks as $blockindex => $block) {
                $position = $block->position;
                $x = $position ? (float)$position->xleft : 12 + 20 * $blockindex;
                $y = $position ? (float)$position->ytop : 12 + 20 * $blockindex;
                $width = (float)($position->width ?? 320);
                $automatic = qtype_clozeonimage::is_automatic_block($block->text, count($block->slots));
                $renderblock = clone $block;
                if ($automatic) {
                    $child = $question->subquestions[$block->slots[0]] ?? null;
                    $renderblock->text = $child && in_array($child->qtype->name(), ['shortanswer', 'numerical'])
                        ? \qtype_clozeonimage\local\textfield_typography::prepare($block->text, true) : '{#1}';
                }
                $text = $this->render_zone($renderblock, $qa, $options, 'qtype_clozeonimage',
                    'blockcontent', $position->id ?? 0, $missingsubquestions);
                $controls .= html_writer::div($text,
                    'qtype-clozeonimage-subquestion qtype-clozeonimage-block qtype-clozeonimage-region' .
                        ($automatic ? ' qtype-clozeonimage-block-auto' : ''),
                    ['data-anchor' => (int)($position->anchor ?? qtype_clozeonimage::ANCHOR_CENTRE),
                        'data-relative-image' => $position->relativeimage ?? '',
                        'data-relative-x' => $position->relativex ?? 0,
                        'data-relative-y' => $position->relativey ?? 0,
                        'data-layer' => (int)($position->layer ?? 100),
                        'data-sticky-label' => get_string('subquestionno', 'qtype_clozeonimage', $blockindex + 1),
                        'data-sticky-bottom' => (int)($position->stickybottom ?? 0),
                        'data-sticky-top' => (int)($position->stickytop ?? 0),
                        'data-sticky-horizontal' => (int)($position->stickyhorizontal ?? 0),
                        'style' => 'z-index:' . (($ranks['b' . $blockindex] ?? $blockindex) + 1) . ';left:' . $x . 'px;top:' . $y . 'px;' .
                        ($automatic ? '' : 'width:' . $width . 'px;')]);
            }

            $composition = html_writer::div(
                $image . $controls,
                'qtype-clozeonimage-composition ' . qtype_clozeonimage::control_appearance_class(
                    $question->controlappearance
                ) . ' ' . qtype_clozeonimage::block_appearance_class($question->blockappearance),
                ['data-composition' => json_encode($geometry), 'style' => 'width:' . $geometry['width'] .
                    'px;height:' . max(0, $geometry['y'] + $geometry['height']) .
                    'px;margin-left:' . max(0, -$geometry['x']) . 'px;margin-top:' . max(0, -$geometry['y']) . 'px;']
            );
            if ($question->positionmetadataiscorrupt) {
                $output .= $this->notification(
                    get_string('corruptpositionmetadata', 'qtype_clozeonimage'),
                    'error'
                );
            }
            $output .= html_writer::div($composition, 'qtype-clozeonimage-scroll', [
                'data-sticky-hidden-label' => get_string('stickyhidden', 'qtype_clozeonimage')]);
            $this->page->requires->js_call_amd('qtype_clozeonimage/layout', 'init');
        }

        if ($question->aftertext !== '') {
            $additional = $question->additional ?? (object)['text' => $question->aftertext,
                'format' => $question->aftertextformat, 'slots' => []];
            $output .= html_writer::div($this->render_zone($additional, $qa, $options,
                'qtype_clozeonimage', 'aftertext', $question->id, $missingsubquestions),
                'qtype-clozeonimage-aftertext qtype-clozeonimage-region ' . $appearance);
        }
        if ($missingsubquestions) {
            $output .= $this->notification(get_string('corruptedquestion', 'qtype_multianswer'), 'error');
        }

        if ($qa->get_state() == question_state::$invalid) {
            foreach ($question->get_validation_messages($qa->get_last_qt_data()) as $message) {
                $output .= html_writer::div($message, 'validationerror');
            }
        }

        return $output;
    }

    /**
     * Format any rich-text zone using the same child renderers, feedback and file access rules.
     *
     * @param stdClass $zone Parsed text, format and ordered child slots.
     * @param question_attempt $qa Parent attempt.
     * @param question_display_options $options Response display options.
     * @param string $component File component.
     * @param string $filearea File area.
     * @param int $itemid File item.
     * @param bool $missing Whether a missing child was encountered.
     * @return string Formatted text with live Cloze controls.
     */
    private function render_zone($zone, $qa, $options, string $component, string $filearea,
            int $itemid, bool &$missing): string {
        $question = $qa->get_question();
        $replacements = [];
        $text = preg_replace_callback('/\{#([0-9]+)\}/', function($match) use (
            $zone, $qa, $options, $question, &$missing, &$replacements
        ) {
            $index = $zone->slots[(int)$match[1] - 1] ?? 0;
            $subq = $question->subquestions[$index] ?? null;
            if (!$subq) {
                $missing = true;
                $subq = qtype_multianswer::deleted_subquestion_replacement();
            }
            $marker = 'clozeonimage' . $index . 'answerplaceholder';
            $replacements[$marker] = $this->subquestion($qa, $options, $index, $subq);
            return html_writer::span($marker, 'nolink');
        }, $zone->format == FORMAT_HTML
            ? \qtype_clozeonimage\local\textfield_typography::prepare($zone->text) : $zone->text);
        $text = $question->format_text($text, $zone->format, $qa, $component, $filearea, $itemid);
        return strtr($text, $replacements);
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
