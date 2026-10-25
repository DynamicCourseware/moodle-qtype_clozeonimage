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
 * Question type implementation for qtype_clozeonimage.
 *
 * Adapted from Moodle core question/type/multianswer/questiontype.php.
 * Modifications for Cloze on Image copyright 2026 DynamicCourseware.org.
 *
 * @package    qtype_clozeonimage
 * @copyright  1999 onwards Martin Dougiamas {@link http://moodle.com}
 * @copyright  2026 DynamicCourseware.org (Dominique Bauer)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/type/questiontypebase.php');
require_once($CFG->dirroot . '/question/type/multianswer/questiontype.php');

/**
 * Cloze on Image question type.
 */
class qtype_clozeonimage extends question_type {
    /** @var string Runtime-only question-row key recording corrupt position metadata. */
    private const POSITION_METADATA_CORRUPT_MARKER = '_positionmetadataiscorrupt';

    /** @var int Centre of the positioned control. */
    public const ANCHOR_CENTRE = 4;

    /** @var int Original image size. */
    public const DISPLAY_ORIGINAL = 0;

    /** @var int Legacy fit mode retained for stored-data compatibility. */
    public const DISPLAY_FIT = 1;

    /** @var int Translucent answer control appearance. */
    public const CONTROL_APPEARANCE_TRANSLUCENT = 0;

    /** @var int Opaque answer control appearance. */
    public const CONTROL_APPEARANCE_OPAQUE = 1;

    /** @var int Transparent background, also the default for blocks. */
    public const CONTROL_APPEARANCE_TRANSPARENT = 2;

    /**
     * This qtype is composite, and response analysis is delegated to subquestions.
     */
    public function can_analyse_responses() {
        return false;
    }

    /**
     * Main options stored in the plugin table.
     */
    public function extra_question_fields() {
        return [
            'qtype_clozeonimage',
            'formatversion',
            'composition',
            'sequence',
            'showcomposition',
            'introcount',
            'aftercount',
            'displaymode',
            'displaywidth',
            'blockappearance',
            'controlappearance',
            'aftertext',
            'aftertextformat',
        ];
    }

    /**
     * Load plugin options, wrapped questions and positions.
     */
    public function get_question_options($question) {
        global $DB;

        parent::get_question_options($question);
        $question->options->questions = [];
        $question->options->images = array_values($DB->get_records('qtype_clozeonimage_img',
            ['questionid' => $question->id], 'layer, id'));
        $question->options->positions = [];
        $question->options->questionrows = [];

        $positionrecords = array_values($DB->get_records(
            'qtype_clozeonimage_pos',
            ['questionid' => $question->id],
            'no ASC, id ASC'
        ));
        $sequence = trim((string)($question->options->sequence ?? ''));
        $ids = [];
        if ($sequence !== '') {
            $ids = array_values(array_filter(array_map('intval', explode(',', $sequence))));
        }
        if (!self::is_position_metadata_valid($positionrecords, count($ids) -
                (int)($question->options->introcount ?? 0) - (int)($question->options->aftercount ?? 0))) {
            // Keep this state with the runtime-only row map, which restore identity hashing already excludes.
            $question->options->questionrows[self::POSITION_METADATA_CORRUPT_MARKER] = true;
        }
        foreach ($positionrecords as $position) {
            $question->options->positions[(int)$position->no] = $position;
        }
        $rownumbers = array_fill(0, (int)($question->options->introcount ?? 0), 0);
        foreach ($positionrecords as $position) {
            for ($slot = 0; $slot < (int)($position->slotcount ?? 1); $slot++) {
                $rownumbers[] = (int)$position->no;
            }
        }

        $rownumbers = array_merge($rownumbers, array_fill(0, (int)($question->options->aftercount ?? 0), -1));

        if ($ids) {
            $wrappedrecords = $DB->get_records_list('question', 'id', $ids);
            foreach ($ids as $index => $id) {
                $place = $index + 1;
                $question->options->questionrows[$place] = $rownumbers[$index] ?? $index + 1;
                $question->options->questions[$place] = (object) [
                    'qtype' => 'subquestion_replacement',
                    'defaultmark' => 1,
                    'options' => (object) ['answers' => []],
                ];
                if (!isset($wrappedrecords[$id])) {
                    continue;
                }
                $wrapped = $wrappedrecords[$id];
                question_bank::get_qtype($wrapped->qtype)->get_question_options($wrapped);
                $wrapped->category = $question->categoryobject->id;
                $question->options->questions[$place] = $wrapped;
            }
        }

        return true;
    }

    /**
     * Return a supported answer control appearance.
     *
     * @param mixed $appearance Stored or submitted appearance value.
     * @return int A CONTROL_APPEARANCE_* constant.
     */
    public static function normalise_control_appearance($appearance): int {
        if ($appearance === self::CONTROL_APPEARANCE_TRANSPARENT || $appearance === '2') {
            return self::CONTROL_APPEARANCE_TRANSPARENT;
        }
        if ($appearance === self::CONTROL_APPEARANCE_OPAQUE || $appearance === '1') {
            return self::CONTROL_APPEARANCE_OPAQUE;
        }

        return self::CONTROL_APPEARANCE_TRANSLUCENT;
    }

    /**
     * Return the semantic CSS class for an answer control appearance.
     *
     * @param mixed $appearance Stored or submitted appearance value.
     * @return string Appearance class name.
     */
    public static function control_appearance_class($appearance): string {
        $suffix = ['translucent', 'opaque', 'transparent'][self::normalise_control_appearance($appearance)];

        return 'qtype-clozeonimage-appearance-' . $suffix;
    }

    /** Normalise the independent block background, defaulting to transparent. */
    public static function normalise_block_appearance($appearance): int {
        return in_array($appearance, [0, 1, 2, '0', '1', '2'], true)
            ? (int)$appearance : self::CONTROL_APPEARANCE_TRANSPARENT;
    }

    /** Return the block background class without altering answer field backgrounds. */
    public static function block_appearance_class($appearance): string {
        return 'qtype-clozeonimage-block-appearance-' .
            ['translucent', 'opaque', 'transparent'][self::normalise_block_appearance($appearance)];
    }

    /**
     * Whether raw position records can be associated safely with all sequence slots.
     *
     * @param stdClass[] $positionrecords Raw records, before indexing by visible row number.
     * @param int $slotcount Number of logical child slots in the stored sequence.
     * @return bool Whether the position metadata is structurally consistent.
     */
    public static function is_position_metadata_valid(array $positionrecords, int $slotcount): bool {
        if (array_sum(array_map(fn($p) => (int)($p->slotcount ?? 1), $positionrecords)) !== $slotcount) {
            return false;
        }

        $seen = [];
        foreach ($positionrecords as $position) {
            $no = filter_var($position->no ?? null, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1],
            ]);
            if ($no === false || isset($seen[$no]) || (int)($position->slotcount ?? 1) < 0) {
                return false;
            }
            $seen[$no] = true;
        }

        return true;
    }

    /**
     * Whether loaded question data records structurally inconsistent position metadata.
     *
     * @param stdClass $questiondata Loaded question data.
     * @return bool Whether runtime placement must ignore persisted position associations.
     */
    public static function has_corrupt_position_metadata(stdClass $questiondata): bool {
        return !empty($questiondata->options->questionrows[self::POSITION_METADATA_CORRUPT_MARKER] ?? false);
    }

    /**
     * Remove line breaks from one source-code subquestion.
     */
    public static function normalise_subquestion_source(string $source): string {
        $source = preg_replace('/\R/u', '', $source);
        return trim((string)$source);
    }

    /**
     * Return a valid anchor value, defaulting invalid values to Centre.
     */
    public static function normalise_anchor(int $anchor): int {
        return ($anchor >= 0 && $anchor <= 8) ? $anchor : self::ANCHOR_CENTRE;
    }

    /**
     * Extract one source row and report structural diagnostics.
     *
     * @param string $source Subquestion source.
     * @return array{0:?stdClass,1:?stdClass,2:string[]} Parsed parent, wrapped child and diagnostics.
     */
    private static function extract_subquestion_source(string $source): array {
        $source = self::normalise_subquestion_source($source);
        if ($source === '') {
            return [null, null, []];
        }

        $parsed = qtype_multianswer_extract_question([
            'text' => $source,
            'format' => FORMAT_HTML,
            'itemid' => 0,
        ]);
        $questions = $parsed->options->questions;
        if (!$questions) {
            return [$parsed, null, [get_string('noclozesubquestion', 'qtype_clozeonimage')]];
        }
        if (count($questions) > 1) {
            return [$parsed, null, [get_string('multipleclozesubquestions', 'qtype_clozeonimage')]];
        }

        $wrapped = reset($questions);
        if (trim((string)$parsed->questiontext['text']) !== '{#1}') {
            return [$parsed, $wrapped, [get_string('clozesubquestionextratext', 'qtype_clozeonimage')]];
        }

        return [$parsed, $wrapped, []];
    }

    /**
     * Parse exactly one standard Cloze subquestion.
     *
     * @return stdClass|null The wrapped subquestion form object, or null if invalid.
     */
    public static function parse_subquestion_source(string $source): ?stdClass {
        [, $wrapped, $diagnostics] = self::extract_subquestion_source($source);
        return $diagnostics ? null : $wrapped;
    }

    /**
     * Diagnose one source row using the standard Moodle Cloze parser and validator.
     *
     * Blank rows have no diagnostic because intermediate blank authoring rows are allowed.
     *
     * @param string $source Subquestion source.
     * @return array{0:?stdClass,1:string[]} Structurally extracted child and ordered diagnostic messages.
     */
    public static function diagnose_subquestion_source(string $source): array {
        [$parsed, $wrapped, $diagnostics] = self::extract_subquestion_source($source);
        if (!$diagnostics && $wrapped) {
            $diagnostics = array_values(qtype_multianswer_validate_question($parsed));
        }

        return [$wrapped, array_values(array_unique($diagnostics))];
    }

    /** Return the text of a Moodle editor value (or a source supplied by an API caller). */
    public static function source_text($source): string {
        return (string)(is_array($source) ? ($source['text'] ?? '') : $source);
    }

    /** Remove comments only from the working copy passed to the Cloze parser. */
    public static function active_source(string $text): string {
        return preg_replace('/<!--.*?(?:-->|$)/s', '', $text);
    }

    /** A commented-out block is retained for later editing/reactivation, unlike an empty editor. */
    public static function source_is_blank(string $text): bool {
        return html_is_blank($text) && !str_contains($text, '<!--');
    }

    /**
     * Whether parsed block content is only one Cloze control with insignificant wrappers.
     * The core parser has already replaced the expression with {#1}; images and other
     * content-bearing elements must retain the ordinary, manually sized block.
     *
     * @param string $text Parsed HTML, not the raw Cloze expression.
     * @param int $childcount Number of parsed children.
     * @return bool
     */
    public static function is_automatic_block(string $text, int $childcount): bool {
        if ($childcount !== 1) {
            return false;
        }
        $text = preg_replace('/<!--.*?-->/s', '', $text);
        preg_match_all('/<\s*\/?\s*([a-z][a-z0-9]*)\b/i', $text, $tags);
        $wrappers = ['p', 'div', 'span', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'small',
            'big', 'sub', 'sup', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'];
        foreach ($tags[1] as $tag) {
            if (!in_array(strtolower($tag), $wrappers, true)) {
                return false;
            }
        }
        $plain = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return preg_replace('/[\s\x{00a0}\x{200b}\x{feff}]+/u', '', $plain) === '{#1}';
    }

    /** Parse one formatted block with the standard Cloze parser and validator. */
    public static function diagnose_block_source($source): array {
        $text = self::source_text($source);
        if (self::source_is_blank($text)) {
            return [null, []];
        }
        [$active, $widths, $widtherrors] = \qtype_clozeonimage\local\field_width::extract(self::active_source($text));
        $parsed = qtype_multianswer_extract_question([
            'text' => $active,
            'format' => is_array($source) ? (int)($source['format'] ?? FORMAT_HTML) : FORMAT_HTML,
            'itemid' => 0,
        ]);
        foreach ($widths as $ordinal => $width) {
            if (isset($parsed->options->questions[$ordinal])) {
                $parsed->options->questions[$ordinal]->clozeonimagewidth = $width;
            }
        }
        if (!$parsed->options->questions) {
            return [$parsed, $widtherrors];
        }
        return [$parsed, array_values(array_unique(array_merge($widtherrors,
            qtype_multianswer_validate_question($parsed))))];
    }

    /** Return block sources indexed by visible row, including the natural one-expression legacy case. */
    public static function block_sources(stdClass $question): array {
        $sources = [];
        if (!self::has_corrupt_position_metadata($question)) {
            foreach (($question->options->positions ?? []) as $row => $position) {
                if (($position->content ?? null) !== null) {
                    $sources[$row - 1] = ['text' => $position->content,
                        'format' => $position->contentformat, 'itemid' => 0];
                }
            }
        }
        foreach (($question->options->questions ?? []) as $place => $wrapped) {
            // Zone membership comes from counts, not a possibly corrupt position's row number.
            $introcount = (int)($question->options->introcount ?? 0);
            $aftercount = (int)($question->options->aftercount ?? 0);
            if ($place <= $introcount || $place > count($question->options->questions) - $aftercount) {
                continue;
            }
            $storedrow = $question->options->questionrows[$place] ?? $place;
            $row = self::has_corrupt_position_metadata($question) ? $place : $storedrow;
            if ($row <= 0 || isset($sources[$row - 1])) {
                continue;
            }
            $position = $question->options->positions[$row] ?? null;
            $sources[$row - 1] = [
                'text' => $position->content ?? $wrapped->questiontext ?? '',
                'format' => $position->contentformat ?? FORMAT_HTML,
                'itemid' => 0,
            ];
        }
        ksort($sources, SORT_NUMERIC);
        return $sources;
    }

    /** Whether the submitted background draft contains an image that will be saved. */
    public static function has_background_image(stdClass $form): bool {
        global $USER;
        if (!empty($form->removebgimage) || empty($form->bgimage)) {
            return false;
        }
        return (bool)get_file_storage()->get_area_files(
            context_user::instance($USER->id)->id, 'user', 'draft', (int)$form->bgimage, 'id', false);
    }

    /** Parse displayed zones in reading order; keep inactive blocks without generating child questions. */
    private static function parse_blocks(stdClass $form): void {
        $form->parsedsubquestions = [];
        $form->parsedblocks = [];
        $form->defaultmark = 0;
        \qtype_clozeonimage\local\composition::geometry($form);
        $form->compositionimages = \qtype_clozeonimage\local\composition::images($form);
        $compositionactive = \qtype_clozeonimage\local\composition::active($form);
        $append = static function($source, int $row, int $origin) use ($form): int {
            [$parsed, $diagnostics] = self::diagnose_block_source($source);
            if ($diagnostics) {
                throw new moodle_exception('invalidblock', 'qtype_clozeonimage', '', implode(' ', $diagnostics));
            }
            foreach (array_values($parsed->options->questions ?? []) as $ordinal => $wrapped) {
                $form->parsedsubquestions[] = (object)[
                    'rowindex' => $row, 'originalrow' => $origin, 'ordinal' => $ordinal, 'question' => $wrapped,
                ];
                $form->defaultmark += (float)$wrapped->defaultmark;
            }
            return count($parsed->options->questions ?? []);
        };
        $form->introcount = $append($form->questiontext ?? '', -1, 0);
        $keys = [];
        foreach (($form->subquestion ?? []) as $rowindex => $source) {
            if (self::source_is_blank(self::source_text($source))) {
                continue;
            }
            $width = \qtype_clozeonimage\local\composition::block_width($form->blockwidth[$rowindex] ?? 320);
            $key = \qtype_clozeonimage\local\composition::key($form->blockkey[$rowindex] ?? null);
            if (isset($keys[$key])) {
                throw new moodle_exception('invalidcomposition', 'qtype_clozeonimage');
            }
            $keys[$key] = true;
            $form->blockkey[$rowindex] = $key;
            foreach (['xleft', 'ytop', 'relativex', 'relativey'] as $field) {
                \qtype_clozeonimage\local\composition::coordinate($form->{$field}[$rowindex] ?? 0);
            }
            $form->parsedblocks[$rowindex] = (object)[
                'content' => self::source_text($source),
                'contentformat' => is_array($source) ? (int)($source['format'] ?? FORMAT_HTML) : FORMAT_HTML,
                'width' => $width,
                'slotcount' => $compositionactive ? $append($source, (int)$rowindex,
                    (int)($form->roworigin[$rowindex] ?? $rowindex + 1)) : 0,
            ];
        }
        $form->aftercount = $append($form->aftertext ?? '', -2, -1);
        if (!$form->parsedsubquestions) {
            throw new moodle_exception('nosubquestions', 'qtype_clozeonimage');
        }
    }

    /**
     * Export plugin-specific data in Moodle XML format.
     *
     * @param stdClass $question Question data loaded for export.
     * @param qformat_xml $format Moodle XML format handler.
     * @param mixed $extra Extra format-specific data.
     * @return string XML fragment.
     */
    public function export_to_xml($question, qformat_xml $format, $extra = null) {
        $fs = get_file_storage();
        $output = '';

        foreach ($question->options->questions as $wrapped) {
            if (($wrapped->qtype ?? '') === 'subquestion_replacement') {
                throw new moodle_exception('cannotexportmissingchild', 'qtype_clozeonimage');
            }
        }
        if (self::has_corrupt_position_metadata($question)) {
            throw new moodle_exception('cannotexportcorruptpositionmetadata', 'qtype_clozeonimage');
        }

        $output .= '    <formatversion>2</formatversion>' . "\n";
        $output .= '    <composition>' . s($question->options->composition ?? json_encode(
            \qtype_clozeonimage\local\composition::defaults())) . "</composition>\n";
        $output .= "    <images>\n";
        foreach (($question->options->images ?? []) as $image) {
            $output .= "      <image>\n";
            foreach (\qtype_clozeonimage\local\composition::IMAGE_FIELDS as $column) {
                $output .= '        <' . $column . '>' . s($image->$column) . '</' . $column . ">\n";
            }
            $output .= $format->write_files($fs->get_area_files($question->contextid,
                'qtype_clozeonimage', 'image', $image->id, 'filepath, filename', false));
            $output .= "      </image>\n";
        }
        $output .= "    </images>\n";
        $output .= '    <showcomposition>' . (int)($question->options->showcomposition ?? 1) . "</showcomposition>\n";
        $output .= '    <displaymode>' . (int) $question->options->displaymode . "</displaymode>\n";
        $output .= '    <displaywidth>' . (int) $question->options->displaywidth . "</displaywidth>\n";
        $output .= '    <blockappearance>' . (int)($question->options->blockappearance ?? 2) . "</blockappearance>\n";
        $output .= '    <controlappearance>' . (int) $question->options->controlappearance . "</controlappearance>\n";

        $output .= "    <backgroundimage>\n";
        $backgroundfiles = $fs->get_area_files(
            $question->contextid,
            'qtype_clozeonimage',
            'bgimage',
            $question->id,
            'sortorder, itemid, filepath, filename',
            false
        );
        $output .= $format->write_files($backgroundfiles);
        $output .= "    </backgroundimage>\n";

        $output .= '    <aftertext ' . $format->format($question->options->aftertextformat) . ">\n";
        $output .= $format->writetext($question->options->aftertext, 3);
        $aftertextfiles = $fs->get_area_files(
            $question->contextid,
            'qtype_clozeonimage',
            'aftertext',
            $question->id,
            'sortorder, itemid, filepath, filename',
            false
        );
        $output .= $format->write_files($aftertextfiles);
        $output .= "    </aftertext>\n";

        $rows = [];
        foreach (self::block_sources($question) as $index => $source) {
            $rowno = $index + 1;
            if (!isset($question->options->positions[$rowno])) {
                throw new coding_exception('Missing Cloze on Image position for row ' . $rowno . ' during XML export.');
            }
            $rows[$rowno] = ['source' => $source['text'], 'position' => $question->options->positions[$rowno]];
        }
        ksort($rows, SORT_NUMERIC);

        $output .= "    <subquestions>\n";
        foreach ($rows as $rowno => $row) {
            $position = $row['position'];
            $output .= "      <subquestion>\n";
            $output .= '        <no>' . $rowno . "</no>\n";
            $output .= '        <source ' . $format->format($position->contentformat ?? FORMAT_HTML) . ">\n";
            $output .= $format->writetext($row['source'], 5);
            $output .= $format->write_files($fs->get_area_files($question->contextid,
                'qtype_clozeonimage', 'blockcontent', $position->id, 'filepath, filename', false));
            $output .= "        </source>\n";
            $output .= '        <xleft>' . (float) $position->xleft . "</xleft>\n";
            $output .= '        <ytop>' . (float) $position->ytop . "</ytop>\n";
            $output .= '        <anchor>' . (int) $position->anchor . "</anchor>\n";
            $output .= '        <width>' . (float)($position->width ?? 320) . "</width>\n";
            foreach (['elementkey', 'relativeimage', 'relativex', 'relativey', 'layer', 'stickybottom', 'stickytop', 'stickyhorizontal'] as $field) {
                $output .= '        <' . $field . '>' . s($position->$field ?? '') . '</' . $field . ">\n";
            }
            $output .= "      </subquestion>\n";
        }
        $output .= "    </subquestions>\n";

        return $output;
    }

    /**
     * Import plugin-specific data from Moodle XML format.
     *
     * @param array $data Parsed question XML.
     * @param stdClass|null $question Question data processed so far.
     * @param qformat_xml $format Moodle XML format handler.
     * @param mixed $extra Extra format-specific data.
     * @return stdClass|false Imported question data, or false for another question type.
     */
    public function import_from_xml($data, $question, qformat_xml $format, $extra = null) {
        if (!isset($data['@']['type']) || $data['@']['type'] !== 'clozeonimage') {
            return false;
        }

        if (self::parse_xml_integer($format->getpath($data, ['#', 'formatversion', 0, '#'], '0')) !==
                \qtype_clozeonimage\local\composition::FORMAT) {
            return false;
        }

        $showcomposition = self::parse_xml_integer($format->getpath($data, ['#', 'showcomposition', 0, '#'], '1'));
        if (!in_array($showcomposition, [0, 1], true)) {
            return false;
        }

        $rawdisplaymode = $format->getpath($data, ['#', 'displaymode', 0, '#'], null);
        if ($rawdisplaymode === null) {
            $displaymode = self::DISPLAY_ORIGINAL;
        } else {
            $displaymode = self::parse_xml_integer($rawdisplaymode);
            if (!in_array($displaymode, [self::DISPLAY_ORIGINAL, self::DISPLAY_FIT], true)) {
                return false;
            }
        }

        $rawdisplaywidth = $format->getpath($data, ['#', 'displaywidth', 0, '#'], null);
        if ($rawdisplaywidth === null) {
            $displaywidth = 0;
        } else {
            $displaywidth = self::parse_xml_integer($rawdisplaywidth);
            if ($displaywidth === null || $displaywidth < 0) {
                return false;
            }
        }

        $blockappearance = self::parse_xml_integer($format->getpath($data, ['#', 'blockappearance', 0, '#'], '2'));
        if (!in_array($blockappearance, [0, 1, 2], true)) {
            return false;
        }
        $rawcontrolappearance = $format->getpath($data, ['#', 'controlappearance', 0, '#'], null);
        $controlappearance = self::parse_xml_integer($rawcontrolappearance);
        if (
            !in_array(
                $controlappearance,
                [self::CONTROL_APPEARANCE_TRANSLUCENT, self::CONTROL_APPEARANCE_OPAQUE, self::CONTROL_APPEARANCE_TRANSPARENT],
                true
            )
        ) {
            return false;
        }

        $backgroundfiles = $format->getpath($data, ['#', 'backgroundimage', 0, '#', 'file'], []);
        if (count($backgroundfiles) > 1) {
            return false;
        }

        $rows = [];
        $subquestions = $format->getpath($data, ['#', 'subquestions', 0, '#', 'subquestion'], []);
        foreach ($subquestions as $subquestion) {
            $rawrowno = $format->getpath($subquestion, ['#', 'no', 0, '#'], '', true);
            if (!preg_match('/^[0-9]+$/D', (string) $rawrowno) || (int) $rawrowno <= 0) {
                return false;
            }
            $rowno = (int) $rawrowno;
            if (isset($rows[$rowno])) {
                return false;
            }
            $source = $format->getpath($subquestion, ['#', 'source', 0, '#', 'text', 0, '#'], '', true);
            if ($source === '') {
                return false;
            }
            [$wrapped, $diagnostics] = self::diagnose_block_source($source);
            if (!$wrapped || ($showcomposition && $diagnostics)) {
                return false;
            }

            $rawxleft = $format->getpath($subquestion, ['#', 'xleft', 0, '#'], null);
            $rawytop = $format->getpath($subquestion, ['#', 'ytop', 0, '#'], null);
            $rawanchor = $format->getpath($subquestion, ['#', 'anchor', 0, '#'], null);
            if ($rawxleft === null || $rawytop === null || $rawanchor === null) {
                return false;
            }
            try {
                $xleft = \qtype_clozeonimage\local\composition::coordinate($rawxleft);
                $ytop = \qtype_clozeonimage\local\composition::coordinate($rawytop);
            } catch (moodle_exception $e) {
                return false;
            }
            $anchor = self::parse_xml_integer($rawanchor);
            if ($xleft === null || $ytop === null || $anchor === null || $anchor < 0 || $anchor > 8) {
                return false;
            }

            try {
                $width = \qtype_clozeonimage\local\composition::block_width(
                    $format->getpath($subquestion, ['#', 'width', 0, '#'], '320'));
            } catch (moodle_exception $e) {
                return false;
            }
            $sourceformat = $format->trans_format($format->getpath($subquestion, ['#', 'source', 0, '@', 'format'], 'html'));
            $rows[$rowno] = [
                'width' => $width,
                'format' => $sourceformat,
                'source' => $source,
                'editor' => $format->import_text_with_files($subquestion, ['#', 'source', 0]),
                'question' => $wrapped,
                'xleft' => $xleft,
                'ytop' => $ytop,
                'anchor' => $anchor,
                'metadata' => array_map(fn($field) => $format->getpath($subquestion,
                    ['#', $field, 0, '#'], ''), ['elementkey', 'relativeimage', 'relativex', 'relativey', 'layer', 'stickybottom', 'stickytop', 'stickyhorizontal']),
            ];
        }
        ksort($rows, SORT_NUMERIC);

        // Validate the whole displayed question before importing any draft files.
        $displayedcount = \qtype_clozeonimage\local\composition::active(['showcomposition' => $showcomposition]) ? array_sum(array_map(
            fn($row) => count($row['question']->options->questions), $rows)) : 0;
        foreach (['questiontext', 'aftertext'] as $field) {
            $source = $format->getpath($data, ['#', $field, 0, '#', 'text', 0, '#'], '');
            [$parsed, $diagnostics] = self::diagnose_block_source($source);
            if ($diagnostics) {
                return false;
            }
            $displayedcount += count($parsed->options->questions ?? []);
        }
        if (!$displayedcount) {
            return false;
        }

        $qo = $format->import_headers($data);
        $qo->qtype = 'clozeonimage';
        $qo->showcomposition = $showcomposition;
        $qo->composition = $format->getpath($data, ['#', 'composition', 0, '#'],
            json_encode(\qtype_clozeonimage\local\composition::defaults()));
        try {
            \qtype_clozeonimage\local\composition::geometry($qo);
            foreach ($format->getpath($data, ['#', 'images', 0, '#', 'image'], []) as $i => $image) {
                foreach (\qtype_clozeonimage\local\composition::IMAGE_FIELDS as $field => $column) {
                    $qo->{$field}[$i] = $format->getpath($image, ['#', $column, 0, '#'], '');
                }
                $qo->sceneimage[$i] = $format->import_files_as_draft($format->getpath($image, ['#', 'file'], []));
            }
            \qtype_clozeonimage\local\composition::images($qo);
        } catch (moodle_exception $e) {
            return false;
        }
        $qo->displaymode = $displaymode;
        $qo->displaywidth = $displaywidth;
        $qo->blockappearance = $blockappearance;
        $qo->controlappearance = $controlappearance;
        $qo->bgimage = $format->import_files_as_draft($backgroundfiles);
        $qo->aftertext = $format->import_text_with_files($data, ['#', 'aftertext', 0], '', 'html');

        $qo->subquestion = [];
        $qo->xleft = [];
        $qo->ytop = [];
        $qo->anchor = [];
        $qo->parsedsubquestions = [];
        foreach ($rows as $rowno => $row) {
            $rowindex = $rowno - 1;
            $qo->subquestion[$rowindex] = $row['editor'];
            $qo->blockwidth[$rowindex] = $row['width'];
            $qo->xleft[$rowindex] = $row['xleft'];
            $qo->ytop[$rowindex] = $row['ytop'];
            $qo->anchor[$rowindex] = $row['anchor'];
            foreach (['blockkey', 'relativeimage', 'relativex', 'relativey', 'blocklayer', 'stickybottom', 'stickytop', 'stickyhorizontal'] as $i => $field) {
                $qo->{$field}[$rowindex] = $row['metadata'][$i] ?? '';
            }
        }

        try {
            \qtype_clozeonimage\local\composition::layers($qo);
            self::parse_blocks($qo);
        } catch (moodle_exception $e) {
            return false;
        }
        $format->import_hints($qo, $data, true, false, $format->get_format($qo->questiontextformat));
        return $qo;
    }

    /**
     * Parse a Moodle XML value as a signed decimal integer without coercion.
     *
     * @param mixed $value Raw XML value.
     * @return int|null The integer, or null when the value is malformed or outside the PHP integer range.
     */
    private static function parse_xml_integer($value): ?int {
        if (!is_string($value) && !is_int($value)) {
            return null;
        }

        $value = (string) $value;
        if (!preg_match('/^-?[0-9]+$/D', $value)) {
            return null;
        }

        $negative = str_starts_with($value, '-');
        $digits = $negative ? substr($value, 1) : $value;
        $digits = ltrim($digits, '0');
        $digits = $digits === '' ? '0' : $digits;
        $normalised = $negative && $digits !== '0' ? '-' . $digits : $digits;
        $integer = (int) $value;

        return (string) $integer === $normalised ? $integer : null;
    }

    /**
     * Parse form sources before the core question record is saved so defaultmark is correct.
     */
    public function save_question($question, $form) {
        // Core writes the saved question ID back to this object. The question editor then
        // passes that same object to custom-field saving, including on initial creation.
        foreach (\qtype_clozeonimage\local\pixel_fields::apply((array)$form) as $name => $value) {
            $form->$name = $value;
        }
        $pixelerrors = \qtype_clozeonimage\local\pixel_fields::errors((array)$form, false);
        if ($pixelerrors) { throw new moodle_exception('integerpixels', 'qtype_clozeonimage'); }
        \qtype_clozeonimage\local\composition::layers($form);
        $this->require_consistent_position_metadata_for_save($question, $form);
        if (property_exists($form, 'roworigin') && !self::valid_row_origins(
                $form->roworigin, $form->subquestion ?? [],
                max((int)($form->roworigincount ?? 0), max([0, ...array_keys($question->options->positions ?? [])]))
            )) {
            throw new moodle_exception('invalidroworder', 'qtype_clozeonimage');
        }

        // Parse exactly the editor values that core will persist, including form-like API callers.
        foreach (['questiontext', 'aftertext'] as $field) {
            if (!is_array($form->$field ?? null)) {
                $form->$field = ['text' => self::source_text($form->$field ?? ''),
                    'format' => (int)($form->{$field . 'format'} ?? FORMAT_HTML), 'itemid' => 0];
            }
        }
        self::parse_blocks($form);

        return parent::save_question($question, $form);
    }

    /**
     * Validate surviving row identities, including blank rows and gaps left by deletion.
     * Imports and legacy API callers may omit this form-only field; supplied maps must be complete.
     *
     * @param mixed $origins Submitted original one-based row numbers.
     * @param array $sources Submitted sources indexed by current zero-based row.
     * @param int $originalcount Original form size before local deletions; lookup remains scoped to this question.
     * @return bool Whether every surviving row has a unique, valid original identity.
     */
    public static function valid_row_origins($origins, array $sources, int $originalcount = 0): bool {
        if ($origins === [] && $sources === []) {
            return true;
        }
        if (!is_array($origins) || !$origins || count($origins) !== count($sources)) {
            return false;
        }
        $expected = range(0, count($sources) - 1);
        $keys = array_keys($origins);
        sort($keys);
        $sourcekeys = array_keys($sources);
        sort($sourcekeys);
        if ($keys !== $expected || $sourcekeys !== $expected) {
            return false;
        }
        $seen = [];
        foreach ($origins as $origin) {
            if ((!is_int($origin) && !is_string($origin)) || !preg_match('/^[1-9][0-9]*$/D', (string)$origin)) {
                return false;
            }
            $number = (int)$origin;
            if ($number < 1 || $number > max($originalcount, count($sources)) || isset($seen[$number])) {
                return false;
            }
            $seen[$number] = true;
        }
        return true;
    }

    /**
     * Refuse to derive a new question from ambiguous persisted position metadata.
     *
     * @param stdClass $question Question record being created, copied or versioned.
     * @param stdClass $form Submitted form-like data.
     */
    private function require_consistent_position_metadata_for_save(stdClass $question, stdClass $form): void {
        if (self::has_corrupt_position_metadata($question)) {
            throw new moodle_exception('corruptpositionmetadataedit', 'qtype_clozeonimage');
        }

        $sourceid = !empty($question->id) ? (int) $question->id : (int) ($form->id ?? 0);
        if ($sourceid > 0 && !$this->persisted_position_metadata_is_valid($sourceid)) {
            throw new moodle_exception('corruptpositionmetadataedit', 'qtype_clozeonimage');
        }
    }

    /**
     * Check persisted position records using the same rules as normal question loading.
     *
     * @param int $questionid Existing parent question ID.
     * @return bool Whether the question has no options row or has consistent position metadata.
     */
    private function persisted_position_metadata_is_valid(int $questionid): bool {
        global $DB;

        $sequence = $DB->get_field('qtype_clozeonimage', 'sequence', ['questionid' => $questionid]);
        if ($sequence === false) {
            return true;
        }

        $sequence = trim((string) $sequence);
        $ids = $sequence === ''
            ? []
            : array_values(array_filter(array_map('intval', explode(',', $sequence))));
        $positionrecords = array_values($DB->get_records(
            'qtype_clozeonimage_pos',
            ['questionid' => $questionid],
            'no ASC, id ASC'
        ));

        $options = $DB->get_record('qtype_clozeonimage', ['questionid' => $questionid]);
        return self::is_position_metadata_valid($positionrecords, count($ids) -
            (int)$options->introcount - (int)$options->aftercount);
    }

    /**
     * Save wrapped questions, plugin options, positions, image and hints.
     */
    public function save_question_options($formdata) {
        global $DB;

        \qtype_clozeonimage\local\composition::layers($formdata);
        $oldwrappedquestions = [];
        if (!empty($formdata->oldparent)) {
            $oldsequence = $DB->get_field(
                'qtype_clozeonimage',
                'sequence',
                ['questionid' => $formdata->oldparent]
            );
            if ($oldsequence) {
                $oldids = array_values(array_filter(array_map('intval', explode(',', $oldsequence))));
                if ($oldids) {
                    $unordered = $DB->get_records_list('question', 'id', $oldids);
                    $oldpositions = array_values($DB->get_records(
                        'qtype_clozeonimage_pos', ['questionid' => $formdata->oldparent], 'no ASC, id ASC'
                    ));
                    $oldoptions = $DB->get_record('qtype_clozeonimage', ['questionid' => $formdata->oldparent]);
                    array_unshift($oldpositions, (object)['no' => 0, 'slotcount' => $oldoptions->introcount]);
                    $oldpositions[] = (object)['no' => -1, 'slotcount' => $oldoptions->aftercount];
                    $index = 0;
                    foreach ($oldpositions as $position) {
                        for ($ordinal = 0; $ordinal < (int)($position->slotcount ?? 1); $ordinal++) {
                            $id = $oldids[$index++] ?? 0;
                            if (isset($unordered[$id])) {
                                $oldwrappedquestions[(int)$position->no][$ordinal] = $unordered[$id];
                            }
                        }
                    }
                }
            }
        }

        $sequence = [];
        if (!isset($formdata->parsedblocks)) {
            self::parse_blocks($formdata);
        }
        foreach (($formdata->parsedsubquestions ?? []) as $entry) {
            $wrapped = $entry->question;
            $wrapped->id = 0;
            // XML imports supply parsed rows directly and have no authoring origin map.
            $originalrow = $entry->originalrow ?? $entry->rowindex + 1;
            $ordinal = $entry->ordinal ?? 0;
            if (isset($oldwrappedquestions[$originalrow][$ordinal])) {
                $oldwrapped = $oldwrappedquestions[$originalrow][$ordinal];
                $wrapped->oldid = $oldwrapped->id;
            }
            $wrapped->name = $formdata->name;
            $wrapped->parent = $formdata->id;
            $wrapped->category = $formdata->category . ',1';
            $wrapped = question_bank::get_qtype($wrapped->qtype)->save_question(
                $wrapped,
                clone($wrapped)
            );
            $sequence[] = $wrapped->id;
        }

        // Children belonging to an earlier question version must remain available to its attempts.

        $options = $DB->get_record('qtype_clozeonimage', ['questionid' => $formdata->id]);
        if (!$options) {
            $options = (object)['questionid' => $formdata->id];
        }
        $options->formatversion = \qtype_clozeonimage\local\composition::FORMAT;
        $options->composition = json_encode(\qtype_clozeonimage\local\composition::geometry($formdata));
        $images = $formdata->compositionimages;
        $options->sequence = implode(',', $sequence);
        $options->showcomposition = (int)!empty($formdata->showcomposition ?? 1);
        $options->introcount = $formdata->introcount;
        $options->aftercount = $formdata->aftercount;
        $options->displaymode = isset($formdata->displaymode) ? (int)$formdata->displaymode : self::DISPLAY_ORIGINAL;
        $options->displaywidth = isset($formdata->displaywidth) ? max(0, (int)$formdata->displaywidth) : 0;
        $options->blockappearance = self::normalise_block_appearance($formdata->blockappearance ?? null);
        $options->controlappearance = self::normalise_control_appearance($formdata->controlappearance ?? null);
        $options->aftertext = $this->import_or_save_files(
            $formdata->aftertext,
            $formdata->context,
            'qtype_clozeonimage',
            'aftertext',
            $formdata->id
        );
        $options->aftertextformat = (int)$formdata->aftertext['format'];

        if (empty($options->id)) {
            $options->id = $DB->insert_record('qtype_clozeonimage', $options);
        } else {
            $DB->update_record('qtype_clozeonimage', $options);
        }

        $DB->delete_records('qtype_clozeonimage_pos', ['questionid' => $formdata->id]);
        foreach ($formdata->parsedblocks as $rowindex => $block) {
            $placeindex = $rowindex;
            $x = isset($formdata->xleft[$rowindex])
                ? \qtype_clozeonimage\local\composition::coordinate($formdata->xleft[$rowindex])
                : 12 + 20 * $placeindex;
            $y = isset($formdata->ytop[$rowindex])
                ? \qtype_clozeonimage\local\composition::coordinate($formdata->ytop[$rowindex])
                : 12 + 20 * $placeindex;
            $anchor = isset($formdata->anchor[$rowindex])
                ? self::normalise_anchor((int)$formdata->anchor[$rowindex])
                : self::ANCHOR_CENTRE;
            $position = (object)[
                'questionid' => $formdata->id,
                'no' => $rowindex + 1,
                'xleft' => $x,
                'ytop' => $y,
                'anchor' => $anchor,
                'content' => $block->content,
                'contentformat' => $block->contentformat,
                'width' => $block->width,
                'slotcount' => $block->slotcount,
                'elementkey' => \qtype_clozeonimage\local\composition::key($formdata->blockkey[$rowindex] ?? null),
                'relativeimage' => (string)($formdata->relativeimage[$rowindex] ?? ''),
                'relativex' => (float)($formdata->relativex[$rowindex] ?? 0),
                'relativey' => (float)($formdata->relativey[$rowindex] ?? 0),
                'layer' => (int)($formdata->blocklayer[$rowindex] ?? 100),
                'stickybottom' => empty($formdata->stickybottom[$rowindex]) ? 0 : 1,
                'stickytop' => empty($formdata->stickytop[$rowindex]) ? 0 : 1,
                // Bit mask retains earlier draft values: 0 none, 1 left, 2 right, 3 both.
                'stickyhorizontal' => isset($formdata->stickyleft) || isset($formdata->stickyright)
                    ? ((!empty($formdata->stickyleft[$rowindex]) ? 1 : 0) |
                        (!empty($formdata->stickyright[$rowindex]) ? 2 : 0))
                    : ((int)($formdata->stickyhorizontal[$rowindex] ?? 0) & 3),
            ];
            if (!isset($images[$position->relativeimage])) {
                // Removing an image detaches its blocks at the submitted absolute position.
                $position->relativeimage = '';
            }
            foreach (['relativex', 'relativey'] as $field) {
                if (!is_finite($position->$field) || abs($position->$field) > 4194303) {
                    throw new moodle_exception('invalidcomposition', 'qtype_clozeonimage');
                }
            }
            $position->id = $DB->insert_record('qtype_clozeonimage_pos', $position);
            $source = $formdata->subquestion[$rowindex];
            if (!is_array($source)) {
                $source = ['text' => $source];
            }
            $position->content = $this->import_or_save_files($source, $formdata->context,
                'qtype_clozeonimage', 'blockcontent', $position->id);
            $DB->update_record('qtype_clozeonimage_pos', $position);
        }

        if (empty($formdata->removebgimage)) {
            file_save_draft_area_files(
                $formdata->bgimage ?? 0,
                $formdata->context->id,
                'qtype_clozeonimage',
                'bgimage',
                $formdata->id,
                ['subdirs' => 0, 'maxbytes' => 0, 'maxfiles' => 1]
            );
        }

        \qtype_clozeonimage\local\composition::save_images($formdata, $images);
        $this->save_hints($formdata, true);
        return new stdClass();
    }

    /**
     * Initialise the runtime composite question.
     */
    protected function initialise_question_instance(question_definition $question, $questiondata) {
        parent::initialise_question_instance($question, $questiondata);

        $question->subquestions = [];
        $question->subquestionslots = array_keys($questiondata->options->questions ?? []);
        foreach (($questiondata->options->questions ?? []) as $key => $subqdata) {
            if ($subqdata->qtype === 'subquestion_replacement') {
                continue;
            }
            $subqdata->contextid = $questiondata->contextid;
            $question->subquestions[$key] = question_bank::make_question($subqdata);
            $question->subquestions[$key]->defaultmark = $subqdata->defaultmark;
            if (isset($subqdata->options->layout)) {
                $question->subquestions[$key]->layout = $subqdata->options->layout;
            }
        }

        $question->positions = [];
        $question->positionmetadataiscorrupt = self::has_corrupt_position_metadata($questiondata);
        if (!$question->positionmetadataiscorrupt) {
            foreach (($questiondata->options->questionrows ?? []) as $key => $rowno) {
                if (isset($questiondata->options->positions[$rowno])) {
                    $question->positions[$key] = $questiondata->options->positions[$rowno];
                }
            }
        }
        $question->showcomposition = \qtype_clozeonimage\local\composition::active($questiondata->options);
        $question->composition = \qtype_clozeonimage\local\composition::geometry($questiondata->options);
        $question->images = $questiondata->options->images ?? [];
        $question->formatversion = (int)($questiondata->options->formatversion ?? 1);
        $question->hasbackgroundimage = (bool)get_file_storage()->get_area_files($question->contextid,
            'qtype_clozeonimage', 'bgimage', $question->id, 'id', false);
        $question->blocks = [];
        $question->fieldwidths = [];
        foreach (self::block_sources($questiondata) as $index => $source) {
            [$parsed] = self::diagnose_block_source($source);
            $position = $question->positionmetadataiscorrupt ? null : ($questiondata->options->positions[$index + 1] ?? null);
            $slots = array_keys(array_filter($questiondata->options->questionrows ?? [],
                fn($row) => $row === $index + 1));
            if ($question->positionmetadataiscorrupt) {
                $slots = [$index + 1];
            }
            foreach ($slots as $ordinal => $slot) {
                $question->fieldwidths[$slot] = $parsed->options->questions[$ordinal + 1]->clozeonimagewidth ?? null;
            }
            $question->blocks[] = (object)[
                'text' => $parsed ? $parsed->questiontext['text'] : '{#1}',
                'format' => $source['format'],
                'slots' => $slots,
                'position' => $position,
            ];
        }
        $question->displaymode = (int)($questiondata->options->displaymode ?? self::DISPLAY_ORIGINAL);
        $question->displaywidth = (int)($questiondata->options->displaywidth ?? 0);
        $question->blockappearance = self::normalise_block_appearance($questiondata->options->blockappearance ?? null);
        $question->controlappearance = self::normalise_control_appearance(
            $questiondata->options->controlappearance ?? null
        );
        $question->aftertext = (string)($questiondata->options->aftertext ?? '');
        $question->aftertextformat = (int)($questiondata->options->aftertextformat ?? FORMAT_HTML);
        foreach (['intro' => [$question->questiontext, $question->questiontextformat, 0],
                'additional' => [$question->aftertext, $question->aftertextformat, -1]] as $name => [$text, $format, $row]) {
            [$parsed] = self::diagnose_block_source(['text' => $text, 'format' => $format]);
            $count = (int)($questiondata->options->{$row === 0 ? 'introcount' : 'aftercount'} ?? 0);
            $slots = $count ? array_slice($question->subquestionslots, $row === 0 ? 0 : -$count, $count) : [];
            foreach ($slots as $ordinal => $slot) {
                $question->fieldwidths[$slot] = $parsed->options->questions[$ordinal + 1]->clozeonimagewidth ?? null;
            }
            $question->$name = (object)['text' => $slots ? ($parsed->questiontext['text'] ?? $text) : $text,
                'format' => $format, 'slots' => $slots];
        }

    }

    /**
     * Composite random-guess score, following the standard Cloze approach.
     */
    public function get_random_guess_score($questiondata) {
        $fractionsum = 0;
        $fractionmax = 0;
        foreach (($questiondata->options->questions ?? []) as $subqdata) {
            if ($subqdata->qtype === 'subquestion_replacement') {
                continue;
            }
            $fractionmax += $subqdata->defaultmark;
            $fractionsum += question_bank::get_qtype($subqdata->qtype)->get_random_guess_score($subqdata);
        }
        return $fractionmax > question_utils::MARK_TOLERANCE ? $fractionsum / $fractionmax : null;
    }

    #[\Override]
    protected function make_hint($hint) {
        return question_hint_with_parts::load_from_record($hint);
    }

    #[\Override]
    public function move_files($questionid, $oldcontextid, $newcontextid) {
        global $DB;
        $fs = get_file_storage();
        parent::move_files($questionid, $oldcontextid, $newcontextid);
        $fs->move_area_files_to_new_context(
            $oldcontextid,
            $newcontextid,
            'qtype_clozeonimage',
            'bgimage',
            $questionid
        );
        $fs->move_area_files_to_new_context(
            $oldcontextid,
            $newcontextid,
            'qtype_clozeonimage',
            'aftertext',
            $questionid
        );
        foreach ($DB->get_records('qtype_clozeonimage_pos', ['questionid' => $questionid]) as $position) {
            $fs->move_area_files_to_new_context($oldcontextid, $newcontextid,
                'qtype_clozeonimage', 'blockcontent', $position->id);
        }
        foreach ($DB->get_records('qtype_clozeonimage_img', ['questionid' => $questionid]) as $image) {
            $fs->move_area_files_to_new_context($oldcontextid, $newcontextid, 'qtype_clozeonimage', 'image', $image->id);
        }
        $this->move_files_in_hints($questionid, $oldcontextid, $newcontextid);
    }

    #[\Override]
    protected function delete_files($questionid, $contextid) {
        global $DB;
        $fs = get_file_storage();
        parent::delete_files($questionid, $contextid);
        foreach ($DB->get_records('qtype_clozeonimage_img', ['questionid' => $questionid]) as $image) {
            $fs->delete_area_files($contextid, 'qtype_clozeonimage', 'image', $image->id);
        }
        $DB->delete_records('qtype_clozeonimage_img', ['questionid' => $questionid]);
        $fs->delete_area_files($contextid, 'qtype_clozeonimage', 'bgimage', $questionid);
        $fs->delete_area_files($contextid, 'qtype_clozeonimage', 'aftertext', $questionid);
        foreach ($DB->get_records('qtype_clozeonimage_pos', ['questionid' => $questionid]) as $position) {
            $fs->delete_area_files($contextid, 'qtype_clozeonimage', 'blockcontent', $position->id);
        }
        $this->delete_files_in_hints($questionid, $contextid);
    }

    #[\Override]
    public function delete_question($questionid, $contextid) {
        global $DB;

        parent::delete_question($questionid, $contextid);
        $DB->delete_records('qtype_clozeonimage_pos', ['questionid' => $questionid]);
    }
}
