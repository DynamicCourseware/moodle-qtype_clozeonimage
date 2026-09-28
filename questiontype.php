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
            'sequence',
            'displaymode',
            'displaywidth',
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
        if (!self::is_position_metadata_valid($positionrecords, count($ids))) {
            // Keep this state with the runtime-only row map, which restore identity hashing already excludes.
            $question->options->questionrows[self::POSITION_METADATA_CORRUPT_MARKER] = true;
        }
        foreach ($positionrecords as $position) {
            $question->options->positions[(int)$position->no] = $position;
        }
        $rownumbers = array_keys($question->options->positions);

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
        $suffix = self::normalise_control_appearance($appearance) === self::CONTROL_APPEARANCE_OPAQUE
            ? 'opaque'
            : 'translucent';

        return 'qtype-clozeonimage-appearance-' . $suffix;
    }

    /**
     * Whether raw position records can be associated safely with all sequence slots.
     *
     * @param stdClass[] $positionrecords Raw records, before indexing by visible row number.
     * @param int $slotcount Number of logical child slots in the stored sequence.
     * @return bool Whether the position metadata is structurally consistent.
     */
    public static function is_position_metadata_valid(array $positionrecords, int $slotcount): bool {
        if (count($positionrecords) !== $slotcount) {
            return false;
        }

        $seen = [];
        foreach ($positionrecords as $position) {
            $no = filter_var($position->no ?? null, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1],
            ]);
            if ($no === false || isset($seen[$no])) {
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

        $output .= '    <displaymode>' . (int) $question->options->displaymode . "</displaymode>\n";
        $output .= '    <displaywidth>' . (int) $question->options->displaywidth . "</displaywidth>\n";
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
        foreach ($question->options->questions as $place => $wrapped) {
            $rowno = (int) ($question->options->questionrows[$place] ?? $place);
            if (isset($rows[$rowno])) {
                throw new coding_exception('Duplicate Cloze on Image row number ' . $rowno . ' during XML export.');
            }
            if (!isset($question->options->positions[$rowno])) {
                throw new coding_exception('Missing Cloze on Image position for row ' . $rowno . ' during XML export.');
            }
            $rows[$rowno] = [
                'source' => (string) $wrapped->questiontext,
                'position' => $question->options->positions[$rowno],
            ];
        }
        ksort($rows, SORT_NUMERIC);

        $output .= "    <subquestions>\n";
        foreach ($rows as $rowno => $row) {
            $position = $row['position'];
            $output .= "      <subquestion>\n";
            $output .= '        <no>' . $rowno . "</no>\n";
            $output .= "        <source>\n";
            $output .= $format->writetext($row['source'], 5);
            $output .= "        </source>\n";
            $output .= '        <xleft>' . (int) $position->xleft . "</xleft>\n";
            $output .= '        <ytop>' . (int) $position->ytop . "</ytop>\n";
            $output .= '        <anchor>' . (int) $position->anchor . "</anchor>\n";
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

        $rawcontrolappearance = $format->getpath($data, ['#', 'controlappearance', 0, '#'], null);
        $controlappearance = self::parse_xml_integer($rawcontrolappearance);
        if (
            !in_array(
                $controlappearance,
                [self::CONTROL_APPEARANCE_TRANSLUCENT, self::CONTROL_APPEARANCE_OPAQUE],
                true
            )
        ) {
            return false;
        }

        $backgroundfiles = $format->getpath($data, ['#', 'backgroundimage', 0, '#', 'file'], []);
        if (count($backgroundfiles) !== 1) {
            return false;
        }

        $rows = [];
        $subquestions = $format->getpath($data, ['#', 'subquestions', 0, '#', 'subquestion'], []);
        if (!$subquestions) {
            return false;
        }
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
            $wrapped = self::parse_subquestion_source($source);
            if (!$wrapped) {
                return false;
            }

            $rawxleft = $format->getpath($subquestion, ['#', 'xleft', 0, '#'], null);
            $rawytop = $format->getpath($subquestion, ['#', 'ytop', 0, '#'], null);
            $rawanchor = $format->getpath($subquestion, ['#', 'anchor', 0, '#'], null);
            if ($rawxleft === null || $rawytop === null || $rawanchor === null) {
                return false;
            }
            $xleft = self::parse_xml_integer($rawxleft);
            $ytop = self::parse_xml_integer($rawytop);
            $anchor = self::parse_xml_integer($rawanchor);
            if ($xleft === null || $ytop === null || $anchor === null || $anchor < 0 || $anchor > 8) {
                return false;
            }

            $rows[$rowno] = [
                'source' => $source,
                'question' => $wrapped,
                'xleft' => $xleft,
                'ytop' => $ytop,
                'anchor' => $anchor,
            ];
        }
        ksort($rows, SORT_NUMERIC);

        $qo = $format->import_headers($data);
        $qo->qtype = 'clozeonimage';
        $qo->displaymode = $displaymode;
        $qo->displaywidth = $displaywidth;
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
            $qo->subquestion[$rowindex] = $row['source'];
            $qo->xleft[$rowindex] = $row['xleft'];
            $qo->ytop[$rowindex] = $row['ytop'];
            $qo->anchor[$rowindex] = $row['anchor'];
            $qo->parsedsubquestions[] = (object) [
                'rowindex' => $rowindex,
                'question' => $row['question'],
            ];
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
        $this->require_consistent_position_metadata_for_save($question, $form);

        $form->parsedsubquestions = [];
        $form->defaultmark = 0;

        foreach (($form->subquestion ?? []) as $rowindex => $source) {
            $source = self::normalise_subquestion_source((string)$source);
            if ($source === '') {
                continue;
            }
            $wrapped = self::parse_subquestion_source($source);
            if ($wrapped) {
                $form->parsedsubquestions[] = (object)[
                    'rowindex' => (int)$rowindex,
                    'question' => $wrapped,
                ];
                $form->defaultmark += (float)$wrapped->defaultmark;
            }
        }

        if ($form->defaultmark <= 0) {
            $form->defaultmark = 1;
        }

        return parent::save_question($question, $form);
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

        return self::is_position_metadata_valid($positionrecords, count($ids));
    }

    /**
     * Save wrapped questions, plugin options, positions, image and hints.
     */
    public function save_question_options($formdata) {
        global $DB;

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
                    foreach ($oldids as $index => $id) {
                        if (isset($unordered[$id])) {
                            $oldwrappedquestions[$index] = $unordered[$id];
                        }
                    }
                }
            }
        }

        $sequence = [];
        $rowindices = [];
        foreach (($formdata->parsedsubquestions ?? []) as $placeindex => $entry) {
            $wrapped = $entry->question;
            $wrapped->id = 0;
            if (isset($oldwrappedquestions[$placeindex])) {
                $oldwrapped = $oldwrappedquestions[$placeindex];
                $wrapped->oldid = $oldwrapped->id;
                unset($oldwrappedquestions[$placeindex]);
            }
            $wrapped->name = $formdata->name;
            $wrapped->parent = $formdata->id;
            $wrapped->category = $formdata->category . ',1';
            $wrapped = question_bank::get_qtype($wrapped->qtype)->save_question(
                $wrapped,
                clone($wrapped)
            );
            $sequence[] = $wrapped->id;
            $rowindices[] = (int)$entry->rowindex;
        }

        foreach ($oldwrappedquestions as $oldwrapped) {
            question_delete_question($oldwrapped->id);
        }

        $options = $DB->get_record('qtype_clozeonimage', ['questionid' => $formdata->id]);
        if (!$options) {
            $options = (object)['questionid' => $formdata->id];
        }
        $options->sequence = implode(',', $sequence);
        $options->displaymode = isset($formdata->displaymode) ? (int)$formdata->displaymode : self::DISPLAY_ORIGINAL;
        $options->displaywidth = isset($formdata->displaywidth) ? max(0, (int)$formdata->displaywidth) : 0;
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
        foreach ($rowindices as $placeindex => $rowindex) {
            $x = isset($formdata->xleft[$rowindex])
                ? (int)$formdata->xleft[$rowindex]
                : 12 + 20 * $placeindex;
            $y = isset($formdata->ytop[$rowindex])
                ? (int)$formdata->ytop[$rowindex]
                : 12 + 20 * $placeindex;
            $anchor = isset($formdata->anchor[$rowindex])
                ? self::normalise_anchor((int)$formdata->anchor[$rowindex])
                : self::ANCHOR_CENTRE;
            $DB->insert_record('qtype_clozeonimage_pos', (object)[
                'questionid' => $formdata->id,
                'no' => $rowindex + 1,
                'xleft' => $x,
                'ytop' => $y,
                'anchor' => $anchor,
            ]);
        }

        file_save_draft_area_files(
            $formdata->bgimage,
            $formdata->context->id,
            'qtype_clozeonimage',
            'bgimage',
            $formdata->id,
            ['subdirs' => 0, 'maxbytes' => 0, 'maxfiles' => 1]
        );

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
        $question->displaymode = (int)($questiondata->options->displaymode ?? self::DISPLAY_ORIGINAL);
        $question->displaywidth = (int)($questiondata->options->displaywidth ?? 0);
        $question->controlappearance = self::normalise_control_appearance(
            $questiondata->options->controlappearance ?? null
        );
        $question->aftertext = (string)($questiondata->options->aftertext ?? '');
        $question->aftertextformat = (int)($questiondata->options->aftertextformat ?? FORMAT_HTML);
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
        $this->move_files_in_hints($questionid, $oldcontextid, $newcontextid);
    }

    #[\Override]
    protected function delete_files($questionid, $contextid) {
        $fs = get_file_storage();
        parent::delete_files($questionid, $contextid);
        $fs->delete_area_files($contextid, 'qtype_clozeonimage', 'bgimage', $questionid);
        $fs->delete_area_files($contextid, 'qtype_clozeonimage', 'aftertext', $questionid);
        $this->delete_files_in_hints($questionid, $contextid);
    }

    #[\Override]
    public function delete_question($questionid, $contextid) {
        global $DB;

        $DB->delete_records('qtype_clozeonimage_pos', ['questionid' => $questionid]);
        parent::delete_question($questionid, $contextid);
    }
}
