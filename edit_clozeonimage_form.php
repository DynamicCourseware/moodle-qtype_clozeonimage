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
 * Editing form for qtype_clozeonimage.
 *
 * @package    qtype_clozeonimage
 * @copyright  2026 DynamicCourseware.org (Dominique Bauer)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/type/edit_question_form.php');
require_once($CFG->dirroot . '/question/type/clozeonimage/questiontype.php');

/**
 * Cloze on Image editing form.
 */
class qtype_clozeonimage_edit_form extends question_edit_form {
    /** Initial number of subquestion authoring rows. */
    private const START_NUM_SUBQUESTIONS = 3;

    /** Number of additional subquestion rows added per request. */
    private const ADD_NUM_SUBQUESTIONS = 3;

    /**
     * Return the configuration used for the background-image file picker.
     *
     * @return array File-picker options.
     */
    public static function file_picker_options(): array {
        return [
            'accepted_types' => ['web_image'],
            'maxbytes' => 0,
            'maxfiles' => 1,
            'subdirs' => 0,
        ];
    }

    #[\Override]
    protected function definition_inner($mform) {
        global $OUTPUT, $PAGE;

        $mform->removeElement('defaultmark');

        $mform->addElement('header', 'imageheader', get_string('imageheader', 'qtype_clozeonimage'));
        $mform->setExpanded('imageheader');
        $mform->addElement(
            'filepicker',
            'bgimage',
            get_string('bgimage', 'qtype_clozeonimage'),
            null,
            self::file_picker_options()
        );
        $mform->addHelpButton('bgimage', 'bgimage', 'qtype_clozeonimage');

        $mform->addElement('hidden', 'displaymode', qtype_clozeonimage::DISPLAY_ORIGINAL);
        $mform->setType('displaymode', PARAM_INT);
        $displaywidthelements = [
            $mform->createElement('text', 'displaywidth', '', [
                'type' => 'number',
                'step' => 1,
            ]),
            $mform->createElement('static', 'displaywidthrangehelp', '', html_writer::span(
                '',
                'form-text text-muted ms-2',
                ['id' => 'qtype-clozeonimage-displaywidth-range']
            )),
        ];
        $mform->addGroup(
            $displaywidthelements,
            'displaywidthgroup',
            get_string('displaywidthpx', 'qtype_clozeonimage'),
            '',
            false
        );
        $mform->setType('displaywidth', PARAM_INT);
        $mform->setDefault('displaywidth', 0);
        $mform->addElement('select', 'controlappearance', get_string('controlappearance', 'qtype_clozeonimage'), [
            qtype_clozeonimage::CONTROL_APPEARANCE_TRANSLUCENT =>
                get_string('controlappearancetranslucent', 'qtype_clozeonimage'),
            qtype_clozeonimage::CONTROL_APPEARANCE_OPAQUE =>
                get_string('controlappearanceopaque', 'qtype_clozeonimage'),
        ]);
        $mform->setType('controlappearance', PARAM_INT);
        $mform->setDefault('controlappearance', qtype_clozeonimage::CONTROL_APPEARANCE_TRANSLUCENT);
        $mform->addHelpButton('controlappearance', 'controlappearance', 'qtype_clozeonimage');

        $mform->addElement('header', 'previewheader', get_string('previewheader', 'qtype_clozeonimage'));
        $mform->setExpanded('previewheader');
        $mform->addElement('html', html_writer::div(
            $this->build_preview_html(),
            'qtype-clozeonimage-preview-area mb-3',
            ['id' => 'qtype-clozeonimage-preview-area']
        ));
        $mform->registerNoSubmitButton('updatepreview');
        $mform->addElement('submit', 'updatepreview', get_string('updatepreview', 'qtype_clozeonimage'));

        $mform->addElement('header', 'subquestionsheader', get_string('subquestionsheader', 'qtype_clozeonimage'));
        $mform->setExpanded('subquestionsheader');
        if ($this->has_corrupt_position_metadata()) {
            $mform->addElement('static', 'positionmetadataerror', '', $OUTPUT->notification(
                get_string('corruptpositionmetadataedit', 'qtype_clozeonimage'),
                'error'
            ));
        }

        $columnheadings = html_writer::span(
            get_string('numbercolumn', 'qtype_clozeonimage'),
            'qtype-clozeonimage-number-heading'
        ) .
            html_writer::span(
                get_string('left', 'qtype_clozeonimage') . $OUTPUT->help_icon('left', 'qtype_clozeonimage'),
                'qtype-clozeonimage-left-heading'
            ) .
            html_writer::span(
                get_string('top', 'qtype_clozeonimage') . $OUTPUT->help_icon('top', 'qtype_clozeonimage'),
                'qtype-clozeonimage-top-heading'
            ) .
            html_writer::div(
                html_writer::span(get_string('anchor', 'qtype_clozeonimage')) .
                    $OUTPUT->help_icon('anchor', 'qtype_clozeonimage'),
                'qtype-clozeonimage-anchor-column-heading'
            ) .
            html_writer::span(
                get_string('sourcecolumn', 'qtype_clozeonimage') .
                    $OUTPUT->help_icon('sourcecolumn', 'qtype_clozeonimage'),
                'qtype-clozeonimage-source-heading'
            );
        $mform->addElement('html', html_writer::div(
            $columnheadings,
            'qtype-clozeonimage-column-headings'
        ));

        $count = $this->get_subquestion_repeat_count();
        $repeated = [];
        $rowelements = [];
        $coordinateattributes = [
            'inputmode' => 'text',
            'pattern' => '-?[0-9]*',
            'class' => 'qtype-clozeonimage-coordinate',
        ];
        $rowelements[] = $mform->createElement('text', 'xleft', '', $coordinateattributes);
        $rowelements[] = $mform->createElement('text', 'ytop', '', $coordinateattributes);
        $rowelements[] = $mform->createElement('html', html_writer::start_div(
            'qtype-clozeonimage-anchor-area',
            ['role' => 'group',
            'aria-label' => get_string(
                'anchorforsubquestion',
                'qtype_clozeonimage',
                '{no}'
            )]
        ) .
            html_writer::start_div('qtype-clozeonimage-anchor-grid'));
        foreach ($this->anchor_labels() as $value => $label) {
            $rowelements[] = $mform->createElement('radio', 'anchor', '', '', $value, [
                'class' => 'qtype-clozeonimage-anchor',
                'aria-label' => $label,
                'title' => $label,
            ]);
        }
        $rowelements[] = $mform->createElement('html', html_writer::end_div() . html_writer::end_div());
        $rowelements[] = $mform->createElement(
            'textarea',
            'subquestion',
            '',
            ['rows' => 2, 'cols' => 90, 'class' => 'qtype-clozeonimage-source']
        );
        $rowlabel = html_writer::span(get_string('subquestion', 'qtype_clozeonimage'), 'visually-hidden') .
            ' {no}';
        $repeated[] = $mform->createElement(
            'group',
            'subquestionrow',
            $rowlabel,
            $rowelements,
            '',
            false,
            ['class' => 'qtype-clozeonimage-subquestion-row']
        );

        $repeatedoptions = [
            'subquestion' => ['type' => PARAM_RAW],
            'xleft' => ['type' => PARAM_INT],
            'ytop' => ['type' => PARAM_INT],
            'anchor' => ['type' => PARAM_INT],
        ];

        $count = $this->repeat_elements(
            $repeated,
            $count,
            $repeatedoptions,
            'nosubquestions',
            'addsubquestions',
            self::ADD_NUM_SUBQUESTIONS,
            get_string('addmoresubquestions', 'qtype_clozeonimage'),
            true
        );
        $submittedx = optional_param_array('xleft', [], PARAM_INT);
        $submittedy = optional_param_array('ytop', [], PARAM_INT);
        $submittedanchors = optional_param_array('anchor', [], PARAM_INT);
        $sources = optional_param_array('subquestion', [], PARAM_RAW);
        $isupdatepreview = optional_param('updatepreview', false, PARAM_BOOL);
        $missingsubquestionrows = $this->missing_subquestion_rows();
        if (!$sources && !empty($this->question->options->questions)) {
            foreach ($this->question->options->questions as $place => $wrapped) {
                $rowno = $this->visible_row_for_place((int) $place);
                $sources[$rowno - 1] = $wrapped->questiontext ?? '';
            }
        }
        for ($i = 0; $i < $count; $i++) {
            $row = $mform->getElement("subquestionrow[$i]");
            foreach ($row->getElements() as $element) {
                if ($element->getName() === "xleft[$i]") {
                    $element->updateAttributes(['aria-label' =>
                        get_string('leftposition', 'qtype_clozeonimage', $i + 1)]);
                } else if ($element->getName() === "ytop[$i]") {
                    $element->updateAttributes(['aria-label' =>
                        get_string('topposition', 'qtype_clozeonimage', $i + 1)]);
                } else if ($element->getName() === "subquestion[$i]") {
                    $element->updateAttributes(['aria-label' =>
                        get_string('sourceforsubquestion', 'qtype_clozeonimage', $i + 1)]);
                } else if (
                    $element instanceof HTML_QuickForm_html &&
                        str_contains($element->toHtml(), 'qtype-clozeonimage-anchor-area')
                ) {
                    $element->setValue(str_replace('{no}', (string)($i + 1), $element->toHtml()));
                }
            }
            $hassubmittedposition = array_key_exists($i, $submittedx) && array_key_exists($i, $submittedy);
            $hassavedposition = !$this->has_corrupt_position_metadata() &&
                !empty($this->question->options->positions[$i + 1]);
            if (!$hassubmittedposition && !$hassavedposition) {
                $mform->setDefault("xleft[$i]", 12 + 20 * $i);
                $mform->setDefault("ytop[$i]", 12 + 20 * $i);
            }
            $source = qtype_clozeonimage::normalise_subquestion_source((string)($sources[$i] ?? ''));
            [$wrapped, $diagnostics] = qtype_clozeonimage::diagnose_subquestion_source($source);
            $hasvalidsubquestion = $wrapped && !$diagnostics;
            if (isset($missingsubquestionrows[$i]) && $source === '') {
                $mform->setElementError("subquestionrow[$i]", self::format_subquestion_diagnostics(
                    [get_string('missingsubquestionedit', 'qtype_clozeonimage')],
                    $i + 1
                ));
            } else if ($isupdatepreview && $diagnostics) {
                $mform->setElementError(
                    "subquestionrow[$i]",
                    self::format_subquestion_diagnostics($diagnostics, $i + 1)
                );
            }
            if ($hasvalidsubquestion && !array_key_exists($i, $submittedanchors) && !$hassavedposition) {
                $mform->setDefault("anchor[$i]", qtype_clozeonimage::ANCHOR_CENTRE);
            } else if (!$hasvalidsubquestion && !array_key_exists($i, $submittedanchors)) {
                // QuickForm loosely compares an unset radio-group value with 0, selecting Top left.
                // This form-only value matches no radio and is never persisted for a blank row.
                $mform->setDefault("anchor[$i]", -1);
            }
        }

        $mform->addElement('header', 'aftertextheader', get_string('aftertext', 'qtype_clozeonimage'));
        $mform->addElement(
            'editor',
            'aftertext',
            get_string('aftertext', 'qtype_clozeonimage'),
            ['rows' => 8],
            $this->editoroptions
        );
        $mform->setType('aftertext', PARAM_RAW);
        $mform->addHelpButton('aftertext', 'aftertext', 'qtype_clozeonimage');

        $this->add_interactive_settings(true, true);
        $PAGE->requires->js_call_amd('qtype_clozeonimage/form', 'init', [
            $isupdatepreview,
        ]);
    }

    /**
     * Expand Text after image when the populated editor contains meaningful content.
     */
    public function definition_after_data() {
        parent::definition_after_data();

        $aftertext = $this->_form->getElement('aftertext')->getValue();
        if (!html_is_blank($aftertext['text'] ?? '')) {
            $this->_form->setExpanded('aftertextheader', true);
        }
    }

    /**
     * Return the accessible labels for the 3 by 3 anchor selector.
     *
     * @return string[]
     */
    private function anchor_labels(): array {
        $labels = [];
        for ($anchor = 0; $anchor <= 8; $anchor++) {
            $labels[$anchor] = get_string('anchorposition' . $anchor, 'qtype_clozeonimage');
        }
        return $labels;
    }

    /**
     * Determine the number of subquestion authoring rows required by the form.
     *
     * @return int Number of subquestion rows to display.
     */
    private function get_subquestion_repeat_count(): int {
        $submitted = optional_param('nosubquestions', 0, PARAM_INT);
        if ($submitted > 0) {
            return $submitted;
        }
        if (!empty($this->question->options->questions)) {
            if ($this->has_corrupt_position_metadata()) {
                return max(self::START_NUM_SUBQUESTIONS, count($this->question->options->questions));
            }
            $rownumbers = $this->question->options->questionrows ?? [];
            return max(
                self::START_NUM_SUBQUESTIONS,
                $rownumbers ? max($rownumbers) : count($this->question->options->questions)
            );
        }
        return self::START_NUM_SUBQUESTIONS;
    }

    /**
     * Create the server-rendered preview that the form AMD module enhances with dragging.
     */
    private function build_preview_html(): string {
        $sources = optional_param_array('subquestion', [], PARAM_RAW);
        if (!$sources && !empty($this->question->options->questions)) {
            foreach ($this->question->options->questions as $place => $wrapped) {
                $rowno = $this->visible_row_for_place((int) $place);
                $sources[$rowno - 1] = $wrapped->questiontext ?? '';
            }
        }

        $positions = [];
        $submittedx = optional_param_array('xleft', [], PARAM_INT);
        $submittedy = optional_param_array('ytop', [], PARAM_INT);
        foreach ($sources as $i => $source) {
            if (isset($submittedx[$i], $submittedy[$i])) {
                $positions[$i + 1] = [(int)$submittedx[$i], (int)$submittedy[$i]];
            } else if (
                !$this->has_corrupt_position_metadata() &&
                    !empty($this->question->options->positions[$i + 1])
            ) {
                $p = $this->question->options->positions[$i + 1];
                $positions[$i + 1] = [(int)$p->xleft, (int)$p->ytop];
            } else {
                $positions[$i + 1] = [12 + 20 * $i, 12 + 20 * $i];
            }
        }

        [$imageurl, $imagewidth] = $this->get_preview_image();

        $controls = '';
        $validcount = 0;
        foreach ($sources as $i => $source) {
            [$wrapped, $diagnostics] = qtype_clozeonimage::diagnose_subquestion_source((string)$source);
            if (!$wrapped || $diagnostics) {
                continue;
            }
            $validcount++;
            $no = $i + 1;
            [$x, $y] = $positions[$no];
            $control = $this->preview_control($wrapped, $no);
            $controls .= html_writer::div($control, 'qtype-clozeonimage-preview-subquestion', [
                'style' => 'left:' . $x . 'px;top:' . $y . 'px;',
                'data-row-index' => $i,
            ]);
        }

        $displaywidth = optional_param('displaywidth', -1, PARAM_INT);
        if ($displaywidth < 0) {
            $displaywidth = (int)($this->question->options->displaywidth ?? 0);
        }
        $previewwidth = $displaywidth > 0 ? $displaywidth : $imagewidth;
        $widthstyle = $previewwidth > 0 ? 'width:' . $previewwidth . 'px;' : '';
        $imageattributes = [
            'alt' => get_string('backgroundimagealt', 'qtype_clozeonimage'),
            'class' => 'qtype-clozeonimage-preview-image',
            'style' => $widthstyle,
        ];
        if ($imageurl) {
            $imageattributes['src'] = $imageurl;
        }
        $image = html_writer::empty_tag('img', $imageattributes);
        $composition = html_writer::div(
            $image . $controls,
            'qtype-clozeonimage-preview-composition ' . qtype_clozeonimage::control_appearance_class(
                optional_param(
                    'controlappearance',
                    $this->question->options->controlappearance
                        ?? qtype_clozeonimage::CONTROL_APPEARANCE_TRANSLUCENT,
                    PARAM_INT
                )
            ),
            ['id' => 'qtype-clozeonimage-preview-composition']
        );
        $message = (!$imageurl || !$validcount)
            ? html_writer::div(
                get_string('previewempty', 'qtype_clozeonimage'),
                'alert alert-info qtype-clozeonimage-preview-message'
            )
            : '';
        return $message . html_writer::div($composition, 'qtype-clozeonimage-scroll');
    }

    /**
     * Locate the submitted draft image.
     *
     * @return array{0:string,1:int}
     */
    private function get_preview_image(): array {
        $draftitemid = optional_param('bgimage', 0, PARAM_INT);
        if ($draftitemid) {
            $draft = file_get_drafarea_files($draftitemid);
            foreach ($draft->list as $file) {
                if (!empty($file->url) && $file->filename !== '.') {
                    return [$file->url, (int)($file->image_width ?? 0)];
                }
            }
        }

        return ['', 0];
    }

    /**
     * Locate the stored file for a draft background image.
     *
     * @param int $draftitemid Draft item ID.
     * @return stored_file|null The draft image, or null when none exists.
     */
    private static function get_draft_image_file(int $draftitemid): ?stored_file {
        global $USER;

        if (!$draftitemid) {
            return null;
        }

        $files = get_file_storage()->get_directory_files(
            context_user::instance($USER->id)->id,
            'user',
            'draft',
            $draftitemid,
            '/',
            false,
            false
        );
        foreach ($files as $file) {
            return $file;
        }

        return null;
    }

    /**
     * Render a disabled control with Moodle's normal form classes and a number overlay.
     */
    private function preview_control(stdClass $wrapped, int $no): string {
        if (
            $wrapped->qtype === 'multichoice' && !empty($wrapped->single) &&
                $wrapped->layout === qtype_multichoice_base::LAYOUT_DROPDOWN
        ) {
            $choices = ['' => '&nbsp;'];
            foreach ($wrapped->answer as $key => $answer) {
                $text = is_array($answer) ? $answer['text'] : $answer;
                $choices[(string)$key] = s($text);
            }
            $control = html_writer::select($choices, 'preview_' . $no, '', false, [
                'class' => 'form-select d-inline-block mb-1',
                'disabled' => 'disabled',
                'aria-label' => get_string('subquestionno', 'qtype_clozeonimage', $no),
            ]);
        } else if ($wrapped->qtype === 'multichoice') {
            $control = $this->preview_choice_controls($wrapped, $no);
        } else {
            $size = 1;
            foreach ($wrapped->answer as $answer) {
                $text = is_array($answer) ? $answer['text'] : $answer;
                $size = max($size, core_text::strlen(trim((string)$text)));
            }
            $size = min(60, $size);
            $control = html_writer::empty_tag('input', [
                'type' => 'text',
                'size' => $size,
                'class' => 'form-control d-inline mb-1',
                'disabled' => 'disabled',
                'aria-label' => get_string('subquestionno', 'qtype_clozeonimage', $no),
            ]);
        }

        $number = html_writer::span((string)$no, 'qtype-clozeonimage-preview-number');
        return html_writer::div($control . $number, 'qtype-clozeonimage-preview-control');
    }

    /**
     * Render a disabled radio-button or checkbox preview using the core Cloze structure.
     */
    private function preview_choice_controls(stdClass $wrapped, int $no): string {
        $horizontal = $wrapped->layout === qtype_multichoice_base::LAYOUT_HORIZONTAL;
        $type = !empty($wrapped->single) ? 'radio' : 'checkbox';
        $choices = '';
        foreach ($wrapped->answer as $key => $answer) {
            $text = is_array($answer) ? $answer['text'] : $answer;
            $id = 'qtype-clozeonimage-preview-' . $no . '-' . $key;
            $attributes = [
                'type' => $type,
                'id' => $id,
                'name' => 'preview_' . $no . '_' . $key,
                'class' => 'form-check-input',
                'disabled' => 'disabled',
            ];
            if ($type === 'radio') {
                $attributes['name'] = 'preview_' . $no;
                $attributes['value'] = $key;
            }
            $choice = html_writer::empty_tag('input', $attributes) .
                html_writer::tag('label', s($text), [
                    'for' => $id,
                    'class' => 'form-check-label text-body',
                ]);
            $class = 'form-check text-wrap text-break';
            if ($horizontal) {
                $class .= ' form-check-inline';
            }
            $choices .= html_writer::div($choice, $class);
        }

        return html_writer::tag(
            'fieldset',
            html_writer::tag(
                'legend',
                get_string('subquestionno', 'qtype_clozeonimage', $no),
                ['class' => 'visually-hidden']
            ) . $choices,
            ['class' => 'answer']
        );
    }

    #[\Override]
    public function set_data($question) {
        $draftitemid = file_get_submitted_draft_itemid('bgimage');
        file_prepare_draft_area(
            $draftitemid,
            $this->context->id,
            'qtype_clozeonimage',
            'bgimage',
            !empty($question->id) ? (int)$question->id : null,
            self::file_picker_options()
        );
        $question->bgimage = $draftitemid;

        if (!empty($question->options)) {
            $question->displaymode = $question->options->displaymode ?? qtype_clozeonimage::DISPLAY_ORIGINAL;
            $question->displaywidth = $question->options->displaywidth ?? 0;
            $question->controlappearance = qtype_clozeonimage::normalise_control_appearance(
                $question->options->controlappearance ?? null
            );

            $question->subquestion = [];
            $question->xleft = [];
            $question->ytop = [];
            $question->anchor = [];
            foreach (($question->options->questions ?? []) as $place => $wrapped) {
                $rowno = $this->visible_row_for_place((int) $place);
                $index = $rowno - 1;
                $question->subquestion[$index] = $wrapped->questiontext ?? '';
                $position = $this->has_corrupt_position_metadata()
                    ? null
                    : ($question->options->positions[$rowno] ?? null);
                $question->xleft[$index] = $position ? (int)$position->xleft : 12 + 20 * $index;
                $question->ytop[$index] = $position ? (int)$position->ytop : 12 + 20 * $index;
                $question->anchor[$index] = $position
                    ? qtype_clozeonimage::normalise_anchor((int)$position->anchor)
                    : qtype_clozeonimage::ANCHOR_CENTRE;
            }

            $afterdraftid = file_get_submitted_draft_itemid('aftertext');
            $aftertext = file_prepare_draft_area(
                $afterdraftid,
                $this->context->id,
                'qtype_clozeonimage',
                'aftertext',
                !empty($question->id) ? (int)$question->id : null,
                $this->editoroptions,
                $question->options->aftertext ?? ''
            );
            $question->aftertext = [
                'text' => $aftertext,
                'format' => $question->options->aftertextformat ?? FORMAT_HTML,
                'itemid' => $afterdraftid,
            ];
            // The parent copies extra question fields from options immediately before setting form data.
            // Keep that copy from replacing the editor structure with the stored scalar text.
            $question->options->aftertext = $question->aftertext;
        } else {
            $question->aftertext = [
                'text' => '',
                'format' => editors_get_preferred_format(),
                'itemid' => file_get_unused_draft_itemid(),
            ];
        }

        $submittedx = optional_param_array('xleft', [], PARAM_INT);
        $submittedy = optional_param_array('ytop', [], PARAM_INT);
        $submittedanchors = optional_param_array('anchor', [], PARAM_INT);
        $question->xleft = $question->xleft ?? [];
        $question->ytop = $question->ytop ?? [];
        $question->anchor = $question->anchor ?? [];
        foreach ($submittedx as $index => $x) {
            if (array_key_exists($index, $submittedy)) {
                $question->xleft[$index] = (int)$x;
                $question->ytop[$index] = (int)$submittedy[$index];
            }
        }
        foreach ($submittedanchors as $index => $anchor) {
            $question->anchor[$index] = qtype_clozeonimage::normalise_anchor((int)$anchor);
        }

        $sources = optional_param_array('subquestion', $question->subquestion ?? [], PARAM_RAW);
        $missingsubquestionrows = $this->missing_subquestion_rows();
        foreach ($sources as $index => $source) {
            if (self::is_blank_source((string)$source) && !isset($missingsubquestionrows[$index])) {
                $question->anchor[$index] = -1;
            }
        }

        parent::set_data($question);
    }

    /**
     * Whether a subquestion source row is completely blank.
     */
    private static function is_blank_source(string $source): bool {
        return qtype_clozeonimage::normalise_subquestion_source($source) === '';
    }

    /**
     * Whether this existing question has structurally inconsistent position metadata.
     */
    private function has_corrupt_position_metadata(): bool {
        return qtype_clozeonimage::has_corrupt_position_metadata($this->question);
    }

    /**
     * Return a safe inspectable row for one dense child place.
     *
     * Corrupt position rows cannot establish a trustworthy sparse-row association.
     *
     * @param int $place Dense one-based child place.
     * @return int Visible row number used by the form.
     */
    private function visible_row_for_place(int $place): int {
        if ($this->has_corrupt_position_metadata()) {
            return $place;
        }
        return (int) ($this->question->options->questionrows[$place] ?? $place);
    }

    /**
     * Return persisted missing-child rows keyed by their zero-based form index.
     *
     * @return int[] Form index => visible row number.
     */
    private function missing_subquestion_rows(): array {
        $missingrows = [];
        foreach (($this->question->options->questions ?? []) as $place => $wrapped) {
            if (($wrapped->qtype ?? '') !== 'subquestion_replacement') {
                continue;
            }
            $rowno = $this->visible_row_for_place((int) $place);
            $missingrows[$rowno - 1] = $rowno;
        }
        return $missingrows;
    }

    /**
     * Combine source diagnostics without changing Moodle core wording.
     *
     * @param string[] $diagnostics Ordered diagnostic messages.
     * @param int $rowno Visible subquestion row number.
     * @return string Form error HTML.
     */
    private static function format_subquestion_diagnostics(array $diagnostics, int $rowno): string {
        $prefix = html_writer::span(
            get_string('subquestionerrorprefix', 'qtype_clozeonimage', $rowno) . ' ',
            'visually-hidden'
        );
        return $prefix . implode(' ', $diagnostics);
    }

    #[\Override]
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if ($this->has_corrupt_position_metadata()) {
            $errors['positionmetadataerror'] = get_string(
                'corruptpositionmetadataedit',
                'qtype_clozeonimage'
            );
        }

        $draftitemid = (int)($data['bgimage'] ?? 0);
        $draft = file_get_drafarea_files($draftitemid);
        $draftimage = self::get_draft_image_file($draftitemid);
        $hasimage = false;
        foreach ($draft->list as $file) {
            if ($file->filename !== '.') {
                $hasimage = true;
                break;
            }
        }
        if (!$hasimage) {
            $errors['bgimage'] = get_string('nobgimage', 'qtype_clozeonimage');
        }

        $imagewidth = 0;
        foreach ($draft->list as $file) {
            if ($file->filename !== '.' && !empty($file->image_width)) {
                $imagewidth = (int)$file->image_width;
                break;
            }
        }
        $displaywidth = (int)($data['displaywidth'] ?? 0);
        if ($draftimage && file_is_svg_image_from_mimetype($draftimage->get_mimetype())) {
            if ($displaywidth < 200) {
                $errors['displaywidthgroup'] = get_string('displaywidthminimum', 'qtype_clozeonimage', 200);
            }
        } else if ($imagewidth > 0) {
            $minimumwidth = min(200, $imagewidth);
            if ($displaywidth < $minimumwidth || $displaywidth > $imagewidth) {
                $errors['displaywidthgroup'] = get_string('displaywidthrange', 'qtype_clozeonimage', (object)[
                    'min' => $minimumwidth,
                    'max' => $imagewidth,
                ]);
            }
        }

        $validcount = 0;
        foreach (($data['subquestion'] ?? []) as $i => $source) {
            $normalised = qtype_clozeonimage::normalise_subquestion_source((string)$source);
            if ($normalised === '') {
                continue;
            }
            $validcount++;
            [, $diagnostics] = qtype_clozeonimage::diagnose_subquestion_source($normalised);
            if ($diagnostics) {
                $errors['subquestionrow[' . $i . ']'] =
                    self::format_subquestion_diagnostics($diagnostics, $i + 1);
            }
        }
        foreach ($this->missing_subquestion_rows() as $i => $rowno) {
            $source = qtype_clozeonimage::normalise_subquestion_source(
                (string) ($data['subquestion'][$i] ?? '')
            );
            if ($source === '') {
                $errors['subquestionrow[' . $i . ']'] = self::format_subquestion_diagnostics(
                    [get_string('missingsubquestionedit', 'qtype_clozeonimage')],
                    $rowno
                );
            }
        }
        if (!$validcount) {
            $errors['subquestion[0]'] = get_string('nosubquestions', 'qtype_clozeonimage');
        }

        return $errors;
    }

    #[\Override]
    public function qtype() {
        return 'clozeonimage';
    }
}
