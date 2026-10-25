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
    /** @var array|null Prepared editor drafts, shared by the preview and form. */
    private ?array $blocksources = null;

    /** @var array|null Prepared image drafts shared by form and preview. */
    private ?array $compositionimages = null;

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

        $_POST = \qtype_clozeonimage\local\pixel_fields::apply($_POST);
        $firstinserted = null;
        $isrebuild = !empty($_POST['rebuildblocks']) && confirm_sesskey();
        if ($isrebuild) {
            $_POST = \qtype_clozeonimage\local\block_rebuild::apply($_POST);
        }
        if (!empty($_POST['insertblocks']) && confirm_sesskey()) {
            // A no-submit action rebuilds only the draft form, just like Add more blocks.
            [$_POST, $firstinserted] = \qtype_clozeonimage\local\block_insertion::apply($_POST);
        }

        $mform->removeElement('defaultmark');
        // A Cloze expression elsewhere can make an empty initial text perfectly valid.
        $questiontext = $mform->removeElement('questiontext');
        $mform->_required = array_values(array_diff($mform->_required, ['questiontext']));
        $mform->insertElementBefore($questiontext, 'generalfeedback');

        $mform->addElement('header', 'previewheader', get_string('previewheader', 'qtype_clozeonimage'));
        $mform->addHelpButton('previewheader', 'previewheader', 'qtype_clozeonimage');
        $mform->setExpanded('previewheader');
        $mform->addElement('static', 'positioninstruction', get_string('positioninstruction', 'qtype_clozeonimage'), '');
        $mform->addElement('html', html_writer::div(
            $this->build_preview_html(),
            'qtype-clozeonimage-preview-area mb-3',
            ['id' => 'qtype-clozeonimage-preview-area']
        ));
        $mform->registerNoSubmitButton('updatepreview');
        $mform->addElement('submit', 'updatepreview', get_string('updatepreview', 'qtype_clozeonimage'));
        $this->composition_fields($mform);
        $this->image_fields($mform);
        $backgroundchoices = [
            qtype_clozeonimage::CONTROL_APPEARANCE_TRANSPARENT => get_string('controlappearancetransparent', 'qtype_clozeonimage'),
            qtype_clozeonimage::CONTROL_APPEARANCE_TRANSLUCENT => get_string('controlappearancetranslucent', 'qtype_clozeonimage'),
            qtype_clozeonimage::CONTROL_APPEARANCE_OPAQUE => get_string('controlappearanceopaque', 'qtype_clozeonimage'),
        ];
        $mform->addElement('header', 'subquestionsheader', get_string('subquestionsheader', 'qtype_clozeonimage'));
        $mform->setExpanded('subquestionsheader');
        $mform->addHelpButton('subquestionsheader', 'subquestionsheader', 'qtype_clozeonimage');
        if ($this->has_corrupt_position_metadata()) {
            $mform->addElement('static', 'positionmetadataerror', '', $OUTPUT->notification(
                get_string('corruptpositionmetadataedit', 'qtype_clozeonimage'),
                'error'
            ));
        }

        $mform->addElement('html', html_writer::div(html_writer::empty_tag('input', [
            'type' => 'checkbox', 'data-action' => 'select-all-blocks',
            'aria-label' => get_string('selectallblocks', 'qtype_clozeonimage'),
        ]), 'qtype-clozeonimage-column-headings'));

        $count = $this->get_subquestion_repeat_count();
        $postedorigins = optional_param_array('roworigin', [], PARAM_INT);
        // Initial rows retain their native recovery identity on GET reload. New rows use this
        // form's scope plus their never-reused origin, including repeated POST reconstruction.
        $initialcount = optional_param('blockinitialcount', $count, PARAM_INT);
        $draftscope = optional_param('blockdraftscope', '', PARAM_ALPHANUM);
        if (!preg_match('/^[a-f0-9]{32}$/D', $draftscope)) {
            $draftscope = bin2hex(random_bytes(16));
        }
        $mform->addElement('hidden', 'blockinitialcount', $initialcount);
        $mform->setType('blockinitialcount', PARAM_INT);
        $mform->addElement('hidden', 'blockdraftscope', $draftscope);
        $mform->setType('blockdraftscope', PARAM_ALPHANUM);
        $mform->setConstant('blockdraftscope', $draftscope);
        $previouscount = max(optional_param('roworigincount', 0, PARAM_INT),
            $postedorigins ? max($postedorigins) : 0);
        $origincount = $previouscount + max(0, $count - count($postedorigins));
        $mform->addElement('hidden', 'roworigincount', $origincount);
        $mform->setType('roworigincount', PARAM_INT);
        $repeated = [];
        $rowelements = [];
        $rowelements[] = $mform->createElement('hidden', 'roworigin');
        $rowelements[] = $mform->createElement('html', html_writer::empty_tag('input', [
            'type' => 'checkbox', 'class' => 'qtype-clozeonimage-select-block',
            'aria-label' => get_string('selectblock', 'qtype_clozeonimage', '{no}'),
        ]));
        $rowelements[] = $mform->createElement('html', html_writer::span(
            $OUTPUT->render_from_template('core/drag_handle', [
                'movetitle' => get_string(
                    'movecontent',
                    'moodle',
                    get_string('subquestionno', 'qtype_clozeonimage', '{no}')
                ),
            ]),
            'qtype-clozeonimage-move d-none'
        ));
        foreach (['blockkey', 'relativex', 'relativey'] as $field) {
            $rowelements[] = $mform->createElement('hidden', $field);
        }
        $rowelements[] = $mform->createElement('html', html_writer::tag('label',
            get_string('sourceforsubquestion', 'qtype_clozeonimage', '{no}'), [
                'class' => 'visually-hidden qtype-clozeonimage-content-label',
                'for' => 'id_subquestion_{index}',
            ]));
        $rowelements[] = $mform->createElement(
            'editor',
            'subquestion',
            '',
            ['rows' => 4, 'class' => 'qtype-clozeonimage-source'],
            $this->block_editor_options()
        );
        $coordinates = [];
        foreach (['xleft' => 'left', 'ytop' => 'top', 'blockwidth' => 'blockwidth'] as $field => $label) {
            $attributes = $field === 'blockwidth' ? ['min' => 40, 'max' => 4194303,
                'class' => 'qtype-clozeonimage-blockwidth',
                'data-auto-help' => get_string('blockwidthautomatic_help', 'qtype_clozeonimage')] :
                ['class' => 'qtype-clozeonimage-coordinate'];
            array_push($coordinates, ...$this->pixel_elements($mform, $field, get_string($label, 'qtype_clozeonimage'),
                $attributes, '{index}'));
        }
        $rowelements = array_merge($rowelements, $coordinates);
        $rowelements[] = $mform->createElement('html', html_writer::start_div(
            'qtype-clozeonimage-anchor-area',
            ['role' => 'group',
            'aria-label' => get_string(
                'anchorforsubquestion',
                'qtype_clozeonimage',
                '{no}'
            )]
        ) .
            html_writer::span(get_string('anchor', 'qtype_clozeonimage')) .
            html_writer::start_div('qtype-clozeonimage-anchor-grid'));
        foreach ($this->anchor_labels() as $value => $label) {
            $rowelements[] = $mform->createElement('radio', 'anchor', '', '', $value, [
                'class' => 'qtype-clozeonimage-anchor',
                'aria-label' => $label,
                'title' => $label,
            ]);
        }
        $rowelements[] = $mform->createElement('html', html_writer::end_div() . html_writer::end_div());
        $choices = ['' => get_string('composition', 'qtype_clozeonimage')];
        foreach ($this->image_sources() as $i => $image) {
            if ($image['url'] && empty($_POST['imageremove'][$i])) {
                $choices[$image['elementkey']] = get_string('sceneimagename', 'qtype_clozeonimage', $i + 1);
            }
        }
        $rowelements[] = $mform->createElement('html', '<div class="qtype-clozeonimage-block-association-line"><div class="qtype-clozeonimage-property">' .
            html_writer::tag('label', get_string('relativeimage', 'qtype_clozeonimage'),
                ['for' => 'id_relativeimage_{index}']));
        $rowelements[] = $mform->createElement('select', 'relativeimage',
            '', $choices,
            ['class' => 'qtype-clozeonimage-association', 'aria-label' => get_string('relativeimage', 'qtype_clozeonimage')]);
        $rowelements[] = $mform->createElement('html', '</div><div class="qtype-clozeonimage-property">' .
            html_writer::tag('label', get_string('layer', 'qtype_clozeonimage'),
            ['for' => 'id_blocklayer_{index}']));
        $rowelements[] = $mform->createElement('hidden', 'blocklayer_current');
        $rowelements[] = $mform->createElement('text', 'blocklayer', '',
            ['type' => 'number', 'step' => 1, 'min' => -2147483648, 'max' => 2147483647, 'class' => 'qtype-clozeonimage-layer', 'data-layer-error' => get_string('invalidlayer', 'qtype_clozeonimage'),
                'aria-label' => get_string('layer', 'qtype_clozeonimage')]);
        $rowelements[] = $mform->createElement('html', '</div><div class="qtype-clozeonimage-sticky-options" role="group" aria-label="' .
            s(get_string('sticky', 'qtype_clozeonimage')) . '">' .
            html_writer::span(get_string('sticky', 'qtype_clozeonimage')));
        foreach (['stickyleft', 'stickyright', 'stickytop', 'stickybottom'] as $field) {
            $rowelements[] = $mform->createElement('advcheckbox', $field, '',
                get_string($field, 'qtype_clozeonimage'), null, [0, 1]);
        }
        $rowelements[] = $mform->createElement('html', '</div></div>');
        $rowlabel = html_writer::span(
            get_string('subquestionno', 'qtype_clozeonimage', '{no}'),
            'qtype-clozeonimage-row-number',
            ['data-block-label' => get_string('subquestionno', 'qtype_clozeonimage', '{blockno}')]
        );
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
            'stickybottom' => ['type' => PARAM_BOOL, 'default' => 0],
            'stickytop' => ['type' => PARAM_BOOL, 'default' => 0],
            'stickyleft' => ['type' => PARAM_BOOL, 'default' => 0],
            'stickyright' => ['type' => PARAM_BOOL, 'default' => 0],
            'blockkey' => ['type' => PARAM_ALPHANUM],
            'relativeimage' => ['type' => PARAM_ALPHANUM],
            'relativex' => ['type' => PARAM_FLOAT, 'default' => 0],
            'relativey' => ['type' => PARAM_FLOAT, 'default' => 0],
            'blocklayer' => ['type' => PARAM_RAW, 'default' => 100],
            'blocklayer_current' => ['type' => PARAM_RAW, 'default' => 100],
            'roworigin' => ['type' => PARAM_RAW, 'default' => '{no}'],
            'subquestion' => ['type' => PARAM_RAW],
            'xleft_px' => ['type' => PARAM_RAW],
            'ytop_px' => ['type' => PARAM_RAW],
            'blockwidth_px' => ['type' => PARAM_RAW],
            'xleft' => ['type' => PARAM_RAW],
            'ytop' => ['type' => PARAM_RAW],
            'blockwidth' => ['type' => PARAM_RAW],
            'anchor' => ['type' => PARAM_INT],
        ];

        $mform->addElement('html', '<div id="qtype-clozeonimage-rows">');
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
        $mform->removeElement('addsubquestions');
        $origincount = $previouscount + max(0, $count - count($postedorigins));
        $mform->setConstant('roworigincount', $origincount);
        $mform->addElement('html', '</div>');
        $actions = [];
        $actions[] = $mform->createElement('select', 'insertblockcount', '', [1 => 1, 2 => 2, 3 => 3],
            ['id' => 'id_insertblockcount', 'aria-label' => get_string('numberofblocks', 'qtype_clozeonimage')]);
        $actions[] = $mform->createElement('html', html_writer::tag('label',
            get_string('blockcountunit', 'qtype_clozeonimage'), ['for' => 'id_insertblockcount', 'class' => 'mb-0 me-2']));
        $actions[] = $mform->createElement('select', 'insertplacement', '', [
            'above' => get_string('insertabove', 'qtype_clozeonimage'),
            'below' => get_string('insertbelow', 'qtype_clozeonimage'),
        ], ['aria-label' => get_string('insertplacement', 'qtype_clozeonimage')]);
        $actions[] = $mform->createElement('html', html_writer::span(
            get_string('selectedblocks', 'qtype_clozeonimage'), 'mb-0'));
        $actions[] = $mform->createElement('submit', 'insertblocks', get_string('insertblocks', 'qtype_clozeonimage'),
            ['data-action' => 'insert-selected-blocks'] + ($count ? ['disabled' => 'disabled'] : []), false);
        $mform->setType('insertplacement', PARAM_ALPHA);
        $mform->setDefault('insertplacement', 'above');
        $mform->addGroup($actions, 'blockactions', '', ' ', false);
        $mform->addElement('select', 'controlappearance', get_string('controlappearance', 'qtype_clozeonimage'), $backgroundchoices);
        $mform->setType('controlappearance', PARAM_INT);
        $mform->setDefault('controlappearance', qtype_clozeonimage::CONTROL_APPEARANCE_TRANSLUCENT);
        $mform->addHelpButton('controlappearance', 'controlappearance', 'qtype_clozeonimage');
        $mform->addElement('select', 'blockappearance', get_string('blockappearance', 'qtype_clozeonimage'), $backgroundchoices);
        $mform->setType('blockappearance', PARAM_INT);
        $mform->setDefault('blockappearance', qtype_clozeonimage::CONTROL_APPEARANCE_TRANSPARENT);
        $mform->addHelpButton('blockappearance', 'blockappearance', 'qtype_clozeonimage');
        $reset = $mform->createElement('button', 'resetcoordinates', get_string('resetcoordinates', 'qtype_clozeonimage'),
            ['data-action' => 'clozeonimage-reset-coordinates'], ['customclassoverride' => 'btn-secondary']);
        $delete = $mform->createElement('button', 'deleteblocks', get_string('deleteselectedblocks', 'qtype_clozeonimage'), [
            'data-action' => 'delete-selected-blocks',
            'disabled' => 'disabled', 'data-title' => get_string('deleteselectedblocks', 'qtype_clozeonimage'),
            'data-confirm' => get_string('deleteblocksconfirm', 'qtype_clozeonimage', '{count}'),
            'data-delete' => get_string('delete'),
        ], ['customclassoverride' => 'btn-secondary']);
        $mform->addGroup([$reset, $delete], 'blockmaintenance', '', ' ', false);
        $mform->setType('insertblockcount', PARAM_INT);
        $mform->setDefault('insertblockcount', 1);
        $mform->addElement('hidden', 'insertbefore', '');
        $mform->setType('insertbefore', PARAM_SEQUENCE);
        $mform->registerNoSubmitButton('insertblocks');
        $mform->addElement('hidden', 'blockorder', '');
        $mform->setType('blockorder', PARAM_SEQUENCE);
        $mform->addElement('hidden', 'blockfocusorigin', 0);
        $mform->setType('blockfocusorigin', PARAM_INT);
        $mform->addElement('hidden', 'blockfocuskind', 'handle');
        $mform->setType('blockfocuskind', PARAM_ALPHA);
        $mform->addElement('submit', 'rebuildblocks', get_string('updatepreview', 'qtype_clozeonimage'),
            ['class' => 'd-none', 'tabindex' => -1, 'aria-hidden' => 'true']);
        $mform->registerNoSubmitButton('rebuildblocks');
        $submittedx = optional_param_array('xleft', [], PARAM_FLOAT);
        $submittedy = optional_param_array('ytop', [], PARAM_FLOAT);
        $submittedanchors = optional_param_array('anchor', [], PARAM_INT);
        $sources = self::submitted_sources();
        $isupdatepreview = optional_param('updatepreview', false, PARAM_BOOL);
        $missingsubquestionrows = $this->missing_subquestion_rows();
        if (!$sources && !isset($_POST['nosubquestions'])) {
            $sources = qtype_clozeonimage::block_sources($this->question);
        }
        for ($i = 0; $i < $count; $i++) {
            $row = $mform->getElement("subquestionrow[$i]");
            $mform->setDefault("blockkey[$i]", $this->question->options->positions[$i + 1]->elementkey ??
                \qtype_clozeonimage\local\composition::key());
            // repeat_elements creates indexed defaults, which take precedence over array defaults in set_data.
            foreach (['stickybottom' => 0, 'stickytop' => 0, 'relativex' => 0, 'relativey' => 0, 'blocklayer' => 100, 'blocklayer_current' => 100] as $field => $fallback) {
                $column = str_starts_with($field, 'blocklayer') ? 'layer' : $field;
                $mform->setDefault("{$field}[$i]", $this->question->options->positions[$i + 1]->$column ?? $fallback);
            }
            $horizontal = (int)($this->question->options->positions[$i + 1]->stickyhorizontal ?? 0);
            $mform->setDefault("stickyleft[$i]", (int)(bool)($horizontal & 1));
            $mform->setDefault("stickyright[$i]", (int)(bool)($horizontal & 2));
            $origin = $postedorigins[$i] ?? ($previouscount + $i - count($postedorigins) + 1);
            $identity = $origin <= $initialcount ? 'initial' : $draftscope;
            $editorid = 'id_coi_q' . (int)($this->question->id ?? 0) . '_' . $identity . '_' . $origin;
            foreach ($row->getElements() as $element) {
                if (str_contains((string)$element->getAttribute('id'), '{index}')) {
                    $element->updateAttributes(['id' => str_replace('{index}', (string)$i, $element->getAttribute('id'))]);
                }
                if ($element->getName() === "roworigin[$i]" && !isset($postedorigins[$i])) {
                    $mform->setDefault("roworigin[$i]", $previouscount + $i - count($postedorigins) + 1);
                } else if ($element->getName() === "xleft[$i]") {
                    $element->updateAttributes(['aria-label' =>
                        get_string('leftposition', 'qtype_clozeonimage', $i + 1)]);
                } else if ($element->getName() === "ytop[$i]") {
                    $element->updateAttributes(['aria-label' =>
                        get_string('topposition', 'qtype_clozeonimage', $i + 1)]);
                } else if ($element->getName() === "blockwidth[$i]") {
                    $element->updateAttributes(['aria-label' =>
                        get_string('blockwidthfor', 'qtype_clozeonimage', $i + 1)]);
                } else if ($element->getName() === "subquestion[$i]") {
                    $element->updateAttributes(['id' => $editorid, 'aria-label' =>
                        get_string('sourceforsubquestion', 'qtype_clozeonimage', $i + 1)]);
                } else if ($element instanceof HTML_QuickForm_html) {
                    $html = str_replace('id_subquestion_{index}', $editorid, $element->toHtml());
                    $element->setValue(str_replace(['{no}', '{index}'], [(string)($i + 1), (string)$i], $html));
                }
            }
            $hassubmittedposition = array_key_exists($i, $submittedx) && array_key_exists($i, $submittedy);
            $hassavedposition = !$this->has_corrupt_position_metadata() &&
                !empty($this->question->options->positions[$i + 1]);
            if (!$hassavedposition) {
                $mform->setDefault("blockwidth[$i]", 320);
            }
            if (!$hassubmittedposition && !$hassavedposition) {
                $mform->setDefault("xleft[$i]", 12 + 20 * $i);
                $mform->setDefault("ytop[$i]", 12 + 20 * $i);
            }
            $source = qtype_clozeonimage::source_text($sources[$i] ?? '');
            [$wrapped, $diagnostics] = qtype_clozeonimage::diagnose_block_source($source);
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
            } else if (!$hasvalidsubquestion && !array_key_exists($i, $submittedanchors) && !$hassavedposition) {
                // QuickForm loosely compares an unset radio-group value with 0, selecting Top left.
                // Only an unpositioned row needs this sentinel; invalid source must not clear a saved anchor.
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
        $PAGE->requires->js_call_amd('qtype_clozeonimage/background_image', 'init');
        $PAGE->requires->js_call_amd('qtype_clozeonimage/image_deletion', 'init');
        $PAGE->requires->js_call_amd('qtype_clozeonimage/composition_editor', 'init');
        $focus = $firstinserted === null ? null : ['index' => $firstinserted, 'kind' => 'editor'];
        if ($isrebuild) {
            $focusindex = array_search(optional_param('blockfocusorigin', 0, PARAM_INT), $postedorigins);
            $kind = optional_param('blockfocuskind', 'handle', PARAM_ALPHA);
            $focus = ['index' => $focusindex === false ? null : $focusindex,
                'kind' => in_array($kind, ['editor', 'handle', 'checkbox'], true) ? $kind : 'handle'];
        }
        $PAGE->requires->js_call_amd('qtype_clozeonimage/reorder', 'init', [
            editors_get_preferred_editor(FORMAT_HTML) instanceof \editor_tiny\editor, $focus,
        ]);
    }

    /** The base geometry to render before the editor is visible. */
    private function preview_geometry(): array {
        $geometry = \qtype_clozeonimage\local\composition::geometry($this->question->options ?? (object)[]);
        foreach ($geometry as $name => $value) {
            if (isset($_POST['composition' . $name])) {
                $geometry[$name] = $name === 'background' ? clean_param($_POST['composition' . $name], PARAM_TEXT)
                    : (float)$_POST['composition' . $name];
            }
        }
        if (!preg_match('/^#[a-f0-9]{6}$/i', $geometry['background'])) {
            $geometry['background'] = '#ffffff';
        }
        if ($geometry['width'] <= 0 || $geometry['height'] <= 0) {
            $geometry = \qtype_clozeonimage\local\composition::defaults();
        }
        return $geometry;
    }

    /** Image draft IDs survive every non-saving form rebuild. */
    private function image_sources(): array {
        global $USER;
        if ($this->compositionimages !== null) {
            return $this->compositionimages;
        }
        $this->compositionimages = [];
        $saved = array_values($this->question->options->images ?? []);
        $count = isset($_POST['imagecount']) ? max(0, min(100, (int)$_POST['imagecount'])) : count($saved);
        if (!empty($_POST['addimage'])) {
            $count++;
        }
        $count = max(1, $count);
        for ($i = 0; $i < $count; $i++) {
            $old = $saved[$i] ?? null;
            $image = ['elementkey' => '', 'name' => get_string('sceneimagename', 'qtype_clozeonimage', $i + 1),
                'xleft' => 0, 'ytop' => 0, 'width' => 0, 'height' => 0, 'layer' => $i];
            foreach (\qtype_clozeonimage\local\composition::IMAGE_FIELDS as $field => $column) {
                $image[$column] = $_POST[$field][$i] ?? $old->$column ?? $image[$column];
            }
            $image['elementkey'] = \qtype_clozeonimage\local\composition::key($image['elementkey']);
            $draft = (int)($_POST['sceneimage'][$i] ?? 0);
            if (!isset($_POST['sceneimage'][$i])) {
                file_prepare_draft_area($draft, $this->context->id, 'qtype_clozeonimage', 'image',
                    $old->id ?? null, self::file_picker_options());
            }
            $image['draft'] = $draft;
            $files = get_file_storage()->get_area_files(context_user::instance($USER->id)->id,
                'user', 'draft', $draft, 'id', false);
            $file = reset($files);
            $image['url'] = $file ? moodle_url::make_draftfile_url($draft, '/', $file->get_filename())->out(false) : '';
            $this->compositionimages[$i] = $image;
        }
        return $this->compositionimages;
    }

    /** Composition settings, plus a repeatable standard Moodle picker for each image. */
    private function composition_fields($mform): void {
        $dimensions = [$mform->createElement('html', '<div class="qtype-clozeonimage-dimension-line">' .
            html_writer::tag('label', get_string('aspectratio', 'qtype_clozeonimage'), ['for' => 'id_compositionpreset']))];
        $dimensions[] = $mform->createElement('select', 'compositionpreset', '',
            ['4:3' => '4:3', '16:9' => '16:9', '16:10' => '16:10', '1:1' => '1:1',
                'custom' => get_string('compositioncustom', 'qtype_clozeonimage')]);
        $dimensions[] = $mform->createElement('advcheckbox', 'compositionlocked', '',
            get_string('lockaspectratio', 'qtype_clozeonimage'));
        $dimensions[] = $mform->createElement('html', '</div><div class="qtype-clozeonimage-dimension-line">');
        foreach (['width', 'height'] as $dimension) {
            array_push($dimensions, ...$this->pixel_elements($mform, 'composition' . $dimension,
                get_string('composition' . $dimension, 'qtype_clozeonimage'), ['min' => 1, 'max' => 4194303]));
        }
        $dimensions[] = $mform->createElement('html', '</div>');
        $mform->addGroup($dimensions, 'compositiondimensions', get_string('dimensions', 'qtype_clozeonimage'), '', false);
        $mform->setType('compositionpreset', PARAM_ALPHANUMEXT);
        $mform->setType('compositionlocked', PARAM_BOOL);
        $mform->setDefault('compositionlocked', 1);
        foreach (['x' => 0, 'y' => 0, 'ratio' => 4 / 3] as $field => $default) {
            $mform->addElement('hidden', 'composition' . $field, $default);
            $mform->setType('composition' . $field, PARAM_RAW);
        }
        $mform->addElement('text', 'compositionbackground', get_string('compositionbackground', 'qtype_clozeonimage'),
            ['type' => 'color', 'class' => 'qtype-clozeonimage-color']);
        $mform->setType('compositionbackground', PARAM_RAW);
        $mform->setDefault('compositionbackground', '#ffffff');
        $show = $mform->createElement('advcheckbox', 'showcomposition', '', '',
            ['aria-label' => get_string('showcomposition', 'qtype_clozeonimage')]);
        $mform->addGroup([$show], 'showcompositiongroup', html_writer::tag('label',
            get_string('showcomposition', 'qtype_clozeonimage'), ['for' => 'id_showcomposition']), '', false);
        $mform->setDefault('showcomposition', 1);
        $mform->setType('showcomposition', PARAM_BOOL);
        $mform->addHelpButton('showcompositiongroup', 'showcomposition', 'qtype_clozeonimage');
    }

    /** Repeatable image pickers, independent of composition settings. */
    private function image_fields($mform): void {
        $mform->addElement('header', 'imagesheader', get_string('imagesheader', 'qtype_clozeonimage'));
        $mform->setExpanded('imagesheader');
        foreach ($this->image_sources() as $i => $image) {
            $name = get_string('sceneimagename', 'qtype_clozeonimage', $i + 1);
            $mform->addElement('filepicker', "sceneimage[$i]", $name, null, self::file_picker_options());
            $properties = [];
            foreach (\qtype_clozeonimage\local\composition::IMAGE_FIELDS as $field => $column) {
                if (in_array($field, ['imagekey', 'imagename'])) {
                    $properties[] = $mform->createElement('hidden', "{$field}[$i]");
                } else if ($field === 'imagelayer') {
                    $properties[] = $mform->createElement('hidden', "imagelayer_current[$i]", $image['layer']);
                    $mform->setType("imagelayer_current[$i]", PARAM_RAW);
                    $mform->setDefault("imagelayer_current[$i]", $image['layer']);
                    $properties[] = $mform->createElement('html', '<div class="qtype-clozeonimage-property">' .
                        html_writer::tag('label', get_string('layer', 'qtype_clozeonimage'), ['for' => "id_imagelayer_$i"]));
                    $properties[] = $mform->createElement('text', "{$field}[$i]", '', ['id' => "id_imagelayer_$i",
                        'type' => 'number', 'step' => 1, 'min' => -2147483648, 'max' => 2147483647,
                        'class' => 'qtype-clozeonimage-layer', 'data-layer-error' => get_string('invalidlayer', 'qtype_clozeonimage'), 'aria-label' => get_string('layer', 'qtype_clozeonimage') . ' — ' . $name]);
                    $properties[] = $mform->createElement('html', '</div>');
                } else {
                    array_push($properties, ...$this->pixel_elements($mform, "{$field}[$i]",
                        get_string($field, 'qtype_clozeonimage'), $field === 'imageheight' ? ['readonly' => 'readonly'] : []));
                }
                $mform->setType("{$field}[$i]", PARAM_RAW);
                $mform->setDefault("{$field}[$i]", $field === 'imagename' ? $name : $image[$column]);
            }
            $properties[] = $mform->createElement('advcheckbox', "imageremove[$i]", '',
                get_string('removeimage', 'qtype_clozeonimage', $name), ['class' => 'qtype-clozeonimage-image-delete',
                    'data-image-label' => $name]);
            $mform->addGroup($properties, "imageproperties[$i]", '', ' ', false);
            $mform->setType("imageremove[$i]", PARAM_BOOL);
            $mform->setDefault("sceneimage[$i]", $image['draft']);
        }
        $mform->addElement('hidden', 'imagecount', count($this->image_sources()));
        $mform->setType('imagecount', PARAM_INT);
        $mform->setConstant('imagecount', count($this->image_sources()));
        $mform->addElement('submit', 'addimage', get_string('addimage', 'qtype_clozeonimage'), null, false);
        $mform->registerNoSubmitButton('addimage');
    }

    /** Precise submitted geometry and its integer-only, labelled presentation. */
    private function pixel_elements($mform, string $name, string $label, array $attributes = [], ?string $index = null): array {
        // A fixed integer step base is essential after redisplaying an invalid decimal POST value.
        // Without min, HTML number inputs use their original value attribute as the step base.
        $imagecoordinate = preg_match('/^image(left|top)/', $name);
        $attributes += ['min' => $imagecoordinate ? -4194303 :
            (preg_match('/^image(width|height)/', $name) ? 0 : -4194303),
            'max' => str_starts_with($name, 'image') ? 4194303 : 4194303];
        $display = preg_replace('/^(\w+)/', '$1_px', $name);
        $id = 'id_' . str_replace(['[', ']'], ['_', ''], $display) . ($index === null ? '' : '_' . $index);
        $mform->setType($name, PARAM_RAW);
        $mform->setType($display, PARAM_RAW);
        return [
            $mform->createElement('hidden', $name, '', ['class' => $attributes['class'] ?? '',
                'data-pixel-value' => '1'] + $attributes),
            $mform->createElement('html', '<div class="qtype-clozeonimage-property">' .
                html_writer::tag('label', $label, ['for' => $id])),
            $mform->createElement('text', $display, '', ['id' => $id, 'type' => 'number', 'step' => 1,
                'class' => 'qtype-clozeonimage-pixel', 'data-pixel-error' => get_string('integerpixels', 'qtype_clozeonimage')] +
                array_diff_key($attributes, ['class' => true])),
            $mform->createElement('html', '</div>'),
        ];
    }

    /** Standard Moodle editor settings, including repository-backed images. */
    private function block_editor_options(): array {
        return ['maxfiles' => EDITOR_UNLIMITED_FILES, 'subdirs' => true,
            'context' => $this->context, 'autosave' => true];
    }

    /** Prepare one draft per block once, including URLs used by the initial positioning preview. */
    private function block_editor_sources(): array {
        $submitted = self::submitted_sources();
        if ($submitted || isset($_POST['nosubquestions'])) {
            return $submitted;
        }
        if ($this->blocksources === null) {
            $this->blocksources = qtype_clozeonimage::block_sources($this->question);
            foreach ($this->blocksources as $index => &$source) {
                $draftid = 0;
                $position = $this->question->options->positions[$index + 1] ?? null;
                $source['text'] = file_prepare_draft_area($draftid, $this->context->id,
                    'qtype_clozeonimage', 'blockcontent', $position->id ?? null,
                    $this->block_editor_options(), $source['text']);
                $source['itemid'] = $draftid;
            }
            unset($source);
        }
        return $this->blocksources;
    }

    /** Read nested editor values from the request, retaining the Moodle draft association. */
    private static function submitted_sources(array $default = []): array {
        $sources = $_POST['subquestion'] ?? $_GET['subquestion'] ?? (isset($_POST['nosubquestions']) ? [] : null);
        if (!is_array($sources)) {
            return $default;
        }
        $result = [];
        foreach ($sources as $index => $source) {
            if (!ctype_digit((string)$index)) {
                continue;
            }
            $result[(int)$index] = [
                'text' => clean_param(qtype_clozeonimage::source_text($source), PARAM_RAW),
                'format' => is_array($source) ? clean_param($source['format'] ?? FORMAT_HTML, PARAM_INT) : FORMAT_HTML,
                'itemid' => is_array($source) ? clean_param($source['itemid'] ?? 0, PARAM_INT) : 0,
            ];
        }
        return $result;
    }

    /**
     * Expand Text after image when the populated editor contains meaningful content.
     */
    public function definition_after_data() {
        parent::definition_after_data();
        $values = $this->_form->exportValues();
        foreach (\qtype_clozeonimage\local\pixel_fields::FIELDS as $name) {
            foreach (is_array($values[$name] ?? null) ? $values[$name] : [null => $values[$name] ?? null] as $i => $value) {
                if ($value === null || $value === '') { continue; }
                $suffix = is_array($values[$name] ?? null) ? "[$i]" : '';
                $display = $name . '_px' . $suffix;
                $submitted = $suffix === '' ? ($_POST[$name . '_px'] ?? null) : ($_POST[$name . '_px'][$i] ?? null);
                $this->_form->setDefault($display, $submitted ?? \qtype_clozeonimage\local\pixel_fields::rounded($value));
            }
        }

        foreach (\qtype_clozeonimage\local\pixel_fields::errors($_POST) as $name => $error) {
            $this->_form->setElementError($name, $error);
        }
        foreach (['blocklayer' => 'subquestionrow', 'imagelayer' => 'imageproperties'] as $field => $group) {
            foreach ($_POST[$field] ?? [] as $i => $value) {
                try {
                    \qtype_clozeonimage\local\composition::layer($value);
                } catch (moodle_exception $e) {
                    $this->_form->setElementError($group . "[$i]", $e->getMessage());
                }
            }
        }
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
        if (isset($_POST['nosubquestions']) || isset($_GET['nosubquestions'])) {
            return max(0, $submitted);
        }
        if (!$this->has_corrupt_position_metadata() && !empty($this->question->options->positions)) {
            // Text-only blocks have no child slots, including a trailing block or an inactive composition.
            return max(self::START_NUM_SUBQUESTIONS, max(array_keys($this->question->options->positions)));
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
     * Apply configured filters and HTML cleaning without invalidating this editor's draft URLs.
     *
     * Moodle's formatter deliberately marks unprocessed draft URLs as broken. Temporarily
     * represent only the current user's editor area with an opaque relative URL prefix.
     * Sanitise and filter the complete markup, then restore the prefix through the standard
     * draft URL API. No HTML or file contents bypass cleaning or draftfile.php access checks.
     */
    private function format_preview_content(string $text, int $format, int $draftid): string {
        global $USER;

        $marker = 'clozeonimagepreviewfile' . bin2hex(random_bytes(16)) . '/';
        if ($draftid > 0) {
            $text = file_rewrite_urls_to_pluginfile($text, $draftid);
            $text = str_replace('@@PLUGINFILE@@/', $marker, $text);
        }
        [$text, $positioning] = $format === (int)FORMAT_HTML
            ? \qtype_clozeonimage\local\preview_positioning::protect($text) : [$text, []];
        $text = format_text($text, $format, ['context' => $this->context, 'para' => false,
            'filter' => true, 'noclean' => false]);
        $text = \qtype_clozeonimage\local\preview_positioning::restore($text, $positioning);
        if ($draftid > 0) {
            $text = str_replace($marker, '@@PLUGINFILE@@/', $text);
            $text = file_rewrite_pluginfile_urls($text, 'draftfile.php',
                context_user::instance($USER->id)->id, 'user', 'draft', $draftid);
        }
        return $text;
    }

    /**
     * Create the server-rendered preview that the form AMD module enhances with dragging.
     */
    private function build_preview_html(): string {
        $sources = $this->block_editor_sources();

        $positions = [];
        $submittedx = optional_param_array('xleft', [], PARAM_FLOAT);
        $submittedy = optional_param_array('ytop', [], PARAM_FLOAT);
        foreach ($sources as $i => $source) {
            if (isset($submittedx[$i], $submittedy[$i])) {
                $positions[$i + 1] = [(float)$submittedx[$i], (float)$submittedy[$i]];
            } else if (
                !$this->has_corrupt_position_metadata() &&
                    !empty($this->question->options->positions[$i + 1])
            ) {
                $p = $this->question->options->positions[$i + 1];
                $positions[$i + 1] = [(float)$p->xleft, (float)$p->ytop];
            } else {
                $positions[$i + 1] = [12 + 20 * $i, 12 + 20 * $i];
            }
        }

        $geometry = $this->preview_geometry();

        $controls = '';
        $validcount = 0;
        foreach ($sources as $i => $source) {
            [$wrapped, $diagnostics] = qtype_clozeonimage::diagnose_block_source($source);
            if (!$wrapped || $diagnostics) {
                continue;
            }
            $validcount++;
            $no = $i + 1;
            [$x, $y] = $positions[$no];
            $automatic = qtype_clozeonimage::is_automatic_block(
                $wrapped->questiontext['text'], count($wrapped->options->questions));
            $sourcehtml = $wrapped->questiontext['text'];
            if ($automatic && !in_array($wrapped->options->questions[1]->qtype, ['shortanswer', 'numerical'])) {
                $sourcehtml = '{#1}';
            } else {
                $sourcehtml = \qtype_clozeonimage\local\textfield_typography::prepare($sourcehtml, $automatic);
            }
            $replacements = [];
            $text = preg_replace_callback('/\{#([0-9]+)\}/', function($match) use ($wrapped, $no, &$replacements) {
                $ordinal = (int)$match[1];
                $marker = 'clozeonimagepreview' . $no . 'child' . $ordinal;
                $replacements[$marker] = $this->preview_control($wrapped->options->questions[$ordinal],
                    $no . '_' . $ordinal);
                return html_writer::span($marker, 'nolink');
            }, $sourcehtml);
            $text = $this->format_preview_content($text, $wrapped->questiontext['format'], (int)($source['itemid'] ?? 0));
            $control = strtr($text, $replacements) .
                html_writer::span((string)$no, 'qtype-clozeonimage-preview-number') .
                html_writer::span('', 'qtype-clozeonimage-resize', ['aria-hidden' => 'true']);
            $widths = optional_param_array('blockwidth', [], PARAM_FLOAT);
            $width = max(40, min(4194303, (float)($widths[$i] ?? $this->question->options->positions[$no]->width ?? 320)));
            $previouswidths = optional_param_array('previewwidth', [], PARAM_FLOAT);
            $previousheights = optional_param_array('previewheight', [], PARAM_FLOAT);
            $controls .= html_writer::div($control,
                'qtype-clozeonimage-preview-subquestion qtype-clozeonimage-block' .
                    ($automatic ? ' qtype-clozeonimage-block-auto' : ''), [
                'style' => 'left:' . $x . 'px;top:' . $y . 'px;' . ($automatic ? '' : 'width:' . $width . 'px;'),
                'data-row-index' => $i,
                'data-previous-width' => $previouswidths[$i] ?? 0,
                'data-previous-height' => $previousheights[$i] ?? 0,
            ]);
        }

        $image = html_writer::div('', 'qtype-clozeonimage-surface', ['aria-hidden' => 'true']);
        foreach ($this->image_sources() as $index => $item) {
            if (!$item['url'] || !empty($_POST['imageremove'][$index])) {
                continue;
            }
            $image .= html_writer::div(html_writer::empty_tag('img', ['src' => $item['url'], 'alt' => $item['name']]) .
                html_writer::span('', 'qtype-clozeonimage-image-resize', ['aria-hidden' => 'true']),
                'qtype-clozeonimage-preview-scene-image', ['data-image-key' => $item['elementkey'],
                    'data-image-index' => $index, 'data-layer' => (int)$item['layer'], 'style' => 'position:absolute;left:' . (float)$item['xleft'] .
                        'px;top:' . (float)$item['ytop'] . 'px;width:' . (float)$item['width'] .
                        'px;height:' . (float)$item['height'] . 'px;z-index:' . (int)$item['layer'] . ';']);
        }
        $composition = html_writer::div(
            $image . $controls . implode('', array_map(function($corner) {
                return html_writer::tag('button', '', ['type' => 'button',
                    'class' => 'qtype-clozeonimage-composition-resize qtype-clozeonimage-composition-resize-' . $corner,
                    'data-corner' => $corner, 'aria-label' => get_string('resizecomposition' . str_replace('-', '', $corner),
                        'qtype_clozeonimage')]);
            }, ['top-left', 'bottom-right'])),
            'qtype-clozeonimage-preview-composition ' . qtype_clozeonimage::control_appearance_class(
                optional_param(
                    'controlappearance',
                    $this->question->options->controlappearance
                        ?? qtype_clozeonimage::CONTROL_APPEARANCE_TRANSLUCENT,
                    PARAM_INT
                )
            ) . ' ' . qtype_clozeonimage::block_appearance_class(optional_param(
                'blockappearance', $this->question->options->blockappearance ?? 2, PARAM_INT)),
            ['id' => 'qtype-clozeonimage-preview-composition', 'data-composition' => json_encode($geometry)]
        );
        $message = html_writer::div(get_string('inactiveblocks', 'qtype_clozeonimage'),
            'alert alert-info qtype-clozeonimage-preview-message', ['role' => 'status']);
        return $message . html_writer::div(
            html_writer::div($composition, 'qtype-clozeonimage-preview-track'),
            'qtype-clozeonimage-scroll qtype-clozeonimage-preview-scroll'
        );
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
                    $draftimage = self::get_draft_image_file($draftitemid);
                    return [$file->url, $draftimage ? self::get_intrinsic_image_width($draftimage) : 0];
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
     * Read the intrinsic width as displayed by the browser, without changing the stored image.
     *
     * Moodle's image metadata reports encoded dimensions. JPEG EXIF orientations 5-8 swap
     * those axes when browsers display the image and expose its naturalWidth to form.js.
     *
     * @param stored_file $file Background image.
     * @return int Oriented intrinsic width, or zero when dimensions are unavailable.
     */
    private static function get_intrinsic_image_width(stored_file $file): int {
        $info = $file->get_imageinfo();
        if (!$info) {
            return 0;
        }
        if ($info['mimetype'] === 'image/jpeg' && function_exists('exif_read_data')) {
            $handle = $file->get_content_file_handle();
            try {
                $exif = @exif_read_data($handle, 'IFD0');
            } finally {
                fclose($handle);
            }
            if (in_array((int) ($exif['Orientation'] ?? 0), [5, 6, 7, 8], true)) {
                return (int) $info['height'];
            }
        }
        return (int) $info['width'];
    }

    /**
     * Render a disabled control with Moodle's normal form classes and a number overlay.
     */
    private function preview_control(stdClass $wrapped, string $no): string {
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
            $width = $wrapped->clozeonimagewidth ?? null;
            $attributes = [
                'type' => 'text',
                'class' => 'form-control d-inline mb-1',
                'disabled' => 'disabled',
                'aria-label' => get_string('subquestionno', 'qtype_clozeonimage', $no),
            ] + \qtype_clozeonimage\local\field_width::attributes($width);
            if (!$width) {
                $size = 1;
                foreach ($wrapped->answer as $answer) {
                    $text = is_array($answer) ? $answer['text'] : $answer;
                    $size = max($size, core_text::strlen(trim((string)$text)));
                }
                $attributes['size'] = min(60, $size);
            }
            $control = html_writer::empty_tag('input', $attributes);
        }

        return html_writer::span($control, 'qtype-clozeonimage-preview-control qtype-clozeonimage-control');
    }

    /**
     * Render a disabled radio-button or checkbox preview using the core Cloze structure.
     */
    private function preview_choice_controls(stdClass $wrapped, string $no): string {
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
            $choices .= html_writer::tag('span',
                html_writer::span($choice, 'qtype-clozeonimage-choice-control'), ['class' => $class]);
        }

        return \qtype_clozeonimage\local\choice_layout::start($horizontal, [
            'role' => $type === 'radio' ? 'radiogroup' : 'group',
            'aria-label' => get_string('subquestionno', 'qtype_clozeonimage', $no),
        ]) . $choices . html_writer::end_tag('span');
    }

    #[\Override]
    public function set_data($question) {
        $geometry = \qtype_clozeonimage\local\composition::geometry($question->options ?? (object)[]);
        foreach ($geometry as $name => $value) {
            $question->{'composition' . $name} = $value;
        }
        if (empty($question->id) && !isset($_POST['compositionwidth'])) {
            $question->compositionwidth = 0;
            $question->compositionheight = 0;
        }
        foreach ($this->image_sources() as $i => $image) {
            foreach (\qtype_clozeonimage\local\composition::IMAGE_FIELDS as $field => $column) {
                $question->{$field}[$i] = $image[$column];
            }
            $question->sceneimage[$i] = $image['draft'];
        }
        if (!empty($question->options)) {
            $question->showcomposition = $question->options->showcomposition ?? 1;
            $question->displaymode = $question->options->displaymode ?? qtype_clozeonimage::DISPLAY_ORIGINAL;
            $question->displaywidth = $question->options->displaywidth ?? 0;
            $question->blockappearance = qtype_clozeonimage::normalise_block_appearance($question->options->blockappearance ?? null);
            $question->controlappearance = qtype_clozeonimage::normalise_control_appearance(
                $question->options->controlappearance ?? null
            );

            $question->subquestion = [];
            $question->xleft = [];
            $question->ytop = [];
            $question->anchor = [];
            $question->blockwidth = [];
            foreach ($this->block_editor_sources() as $index => $source) {
                $rowno = $index + 1;
                $question->subquestion[$index] = $source;
                $question->blockwidth[$index] = (float)($question->options->positions[$rowno]->width ?? 320);
                $position = $this->has_corrupt_position_metadata()
                    ? null
                    : ($question->options->positions[$rowno] ?? null);
                $question->xleft[$index] = $position ? (float)$position->xleft : 12 + 20 * $index;
                $question->ytop[$index] = $position ? (float)$position->ytop : 12 + 20 * $index;
                foreach (['blockkey' => 'elementkey', 'relativeimage' => 'relativeimage', 'relativex' => 'relativex',
                        'relativey' => 'relativey', 'blocklayer' => 'layer',
                        'stickybottom' => 'stickybottom', 'stickytop' => 'stickytop'] as $field => $column) {
                    $question->{$field}[$index] = $position->$column ?? ($field === 'blocklayer' ? 100 : '');
                }
                $question->stickyleft[$index] = (int)(bool)((int)($position->stickyhorizontal ?? 0) & 1);
                $question->stickyright[$index] = (int)(bool)((int)($position->stickyhorizontal ?? 0) & 2);
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

        $submittedx = optional_param_array('xleft', [], PARAM_FLOAT);
        $submittedy = optional_param_array('ytop', [], PARAM_FLOAT);
        $submittedanchors = optional_param_array('anchor', [], PARAM_INT);
        $question->xleft = $question->xleft ?? [];
        $question->ytop = $question->ytop ?? [];
        $question->anchor = $question->anchor ?? [];
        foreach ($submittedx as $index => $x) {
            if (array_key_exists($index, $submittedy)) {
                $question->xleft[$index] = (float)$x;
                $question->ytop[$index] = (float)$submittedy[$index];
            }
        }
        foreach ($submittedanchors as $index => $anchor) {
            $question->anchor[$index] = qtype_clozeonimage::normalise_anchor((int)$anchor);
        }

        $sources = self::submitted_sources($question->subquestion ?? []);
        $missingsubquestionrows = $this->missing_subquestion_rows();
        foreach ($sources as $index => $source) {
            if (self::is_blank_source(qtype_clozeonimage::source_text($source)) && !isset($missingsubquestionrows[$index])) {
                $question->anchor[$index] = -1;
            }
        }

        $question = $this->data_preprocessing_hints($question, true, true);
        parent::set_data($question);
    }

    /**
     * Whether a subquestion source row is completely blank.
     */
    private static function is_blank_source(string $source): bool {
        return qtype_clozeonimage::source_is_blank($source);
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
     * @param array|null $origins Submitted row identities, or null to read the request.
     * @param array|null $sources Submitted sources, or null to read the request.
     * @return int[] Form index => visible row number.
     */
    private function missing_subquestion_rows(?array $origins = null, ?array $sources = null): array {
        $missingrows = [];
        $origins = $origins ?? optional_param_array('roworigin', [], PARAM_RAW);
        $sources = $sources ?? self::submitted_sources();
        if (!qtype_clozeonimage::valid_row_origins($origins, $sources, optional_param('roworigincount', 0, PARAM_INT))) {
            $origins = [];
        }
        foreach (($this->question->options->questions ?? []) as $place => $wrapped) {
            if (($wrapped->qtype ?? '') !== 'subquestion_replacement') {
                continue;
            }
            $rowno = $this->visible_row_for_place((int) $place);
            if ($rowno <= 0) {
                continue;
            }
            if ($origins) {
                $index = array_search($rowno, array_map('intval', $origins), true);
                if ($index !== false) {
                    $rowno = $index + 1;
                }
            }
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
        $errors += \qtype_clozeonimage\local\pixel_fields::errors($data);
        foreach (($data['blocklayer'] ?? []) as $i => $layer) {
            try { \qtype_clozeonimage\local\composition::layer($layer); } catch (moodle_exception $e) {
                $errors["subquestionrow[$i]"] = $e->getMessage();
            }
        }

        if (array_key_exists('roworigin', $data) &&
                !qtype_clozeonimage::valid_row_origins(
                    $data['roworigin'], $data['subquestion'] ?? [], (int)($data['roworigincount'] ?? 0)
                )) {
            $errors['subquestionrow[0]'] = get_string('invalidroworder', 'qtype_clozeonimage');
            return $errors;
        }

        if ($this->has_corrupt_position_metadata()) {
            $errors['positionmetadataerror'] = get_string(
                'corruptpositionmetadataedit',
                'qtype_clozeonimage'
            );
        }

        try {
            \qtype_clozeonimage\local\composition::geometry($data);
            \qtype_clozeonimage\local\composition::images((object)$data);
        } catch (moodle_exception $e) {
            $errors['compositiondimensions'] = $e->getMessage();
        }
        $compositionactive = \qtype_clozeonimage\local\composition::active($data);
        $validcount = 0;
        foreach (($data['subquestion'] ?? []) as $i => $source) {
            $normalised = qtype_clozeonimage::source_text($source);
            if (qtype_clozeonimage::source_is_blank($normalised)) {
                continue;
            }
            try {
                \qtype_clozeonimage\local\composition::block_width($data['blockwidth'][$i] ?? 320);
            } catch (moodle_exception $e) {
                $errors["subquestionrow[$i]"] = $e->getMessage();
            }
            [$parsed, $diagnostics] = qtype_clozeonimage::diagnose_block_source($source);
            $validcount += $compositionactive ? count($parsed->options->questions ?? []) : 0;
            if ($compositionactive && $diagnostics) {
                $errors['subquestionrow[' . $i . ']'] =
                    self::format_subquestion_diagnostics($diagnostics, $i + 1);
            }
        }
        foreach ($this->missing_subquestion_rows($data['roworigin'] ?? null, $data['subquestion'] ?? []) as $i => $rowno) {
            $source = qtype_clozeonimage::normalise_subquestion_source(
                qtype_clozeonimage::source_text($data['subquestion'][$i] ?? '')
            );
            if ($source === '') {
                $errors['subquestionrow[' . $i . ']'] = self::format_subquestion_diagnostics(
                    [get_string('missingsubquestionedit', 'qtype_clozeonimage')],
                    $rowno
                );
            }
        }
        foreach (['questiontext', 'aftertext'] as $field) {
            [$parsed, $diagnostics] = qtype_clozeonimage::diagnose_block_source($data[$field] ?? '');
            $validcount += count($parsed->options->questions ?? []);
            if ($diagnostics) {
                $errors[$field] = implode(' ', $diagnostics);
            }
        }
        if (!$validcount) {
            $errors['questiontext'] ??= get_string('nosubquestions', 'qtype_clozeonimage');
        }

        return $errors;
    }

    #[\Override]
    public function qtype() {
        return 'clozeonimage';
    }
}
