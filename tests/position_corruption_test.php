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

namespace qtype_clozeonimage;

use core_question\local\bank\question_version_status;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/engine/tests/helpers.php');
require_once($CFG->dirroot . '/question/type/clozeonimage/questiontype.php');
require_once($CFG->dirroot . '/question/type/clozeonimage/edit_clozeonimage_form.php');
require_once($CFG->dirroot . '/question/format/xml/format.php');

/**
 * Tests for safe runtime behavior when stored position metadata is inconsistent.
 *
 * @package    qtype_clozeonimage
 * @copyright  2026 DynamicCourseware.org (Dominique Bauer)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\qtype_clozeonimage::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\qtype_clozeonimage_renderer::class)]
final class position_corruption_test extends \qbehaviour_walkthrough_test_base {
    /** @var int[] Sparse visible row numbers in dense child order. */
    private const ROWNUMBERS = [1, 4, 7];

    /** @var int[] Stored horizontal coordinates in dense child order. */
    private const XLEFT = [11, 44, 77];

    /** @var int[] Stored vertical coordinates in dense child order. */
    private const YTOP = [101, 404, 707];

    /** @var array<int, string> Sources keyed by their sparse zero-based form row. */
    private const SOURCES = [
        0 => '{1:SHORTANSWER:=Alpha~Wrong}',
        3 => '{1:NUMERICAL:=2:0}',
        6 => '{1:SHORTANSWER:=Gamma~Wrong}',
    ];

    /**
     * Store a background image in a user draft area.
     */
    private function create_background_draft(): int {
        global $USER;

        $draftitemid = file_get_unused_draft_itemid();
        get_file_storage()->create_file_from_string((object) [
            'contextid' => \context_user::instance($USER->id)->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => $draftitemid,
            'filepath' => '/',
            'filename' => 'background.png',
        ], base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='
        ));

        return $draftitemid;
    }

    /**
     * Create a healthy persisted question with three dense children and sparse rows.
     */
    private function create_saved_question(): \stdClass {
        global $USER;

        $context = \context_system::instance();
        $category = $this->getDataGenerator()->get_plugin_generator('core_question')->create_question_category([
            'contextid' => $context->id,
        ]);
        $formdata = (object) [
            'category' => $category->id . ',' . $context->id,
            'name' => 'Position metadata corruption',
            'questiontext' => ['text' => 'Question text', 'format' => FORMAT_HTML, 'itemid' => 0],
            'generalfeedback' => ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0],
            'penalty' => 0.3333333,
            'status' => question_version_status::QUESTION_STATUS_READY,
            'idnumber' => null,
            'subquestion' => self::SOURCES,
            'xleft' => [0 => self::XLEFT[0], 3 => self::XLEFT[1], 6 => self::XLEFT[2]],
            'ytop' => [0 => self::YTOP[0], 3 => self::YTOP[1], 6 => self::YTOP[2]],
            'anchor' => [0 => 0, 3 => 4, 6 => 8],
            'displaymode' => \qtype_clozeonimage::DISPLAY_ORIGINAL,
            'displaywidth' => 640,
            'bgimage' => $this->create_background_draft(),
            'aftertext' => ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0],
            'hint' => [],
            'hintclearwrong' => [],
            'hintshownumcorrect' => [],
        ];
        $question = (object) [
            'qtype' => 'clozeonimage',
            'createdby' => $USER->id,
            'status' => question_version_status::QUESTION_STATUS_READY,
            'idnumber' => null,
        ];
        $saved = \question_bank::get_qtype('clozeonimage')->save_question($question, $formdata);

        return \question_bank::load_question_data($saved->id);
    }

    /**
     * Mutate persisted positions, invalidate the question cache and reload it.
     */
    private function reload_with_position_corruption(\stdClass $question, string $kind): \stdClass {
        global $DB;

        switch ($kind) {
            case 'fewer':
                $DB->delete_records('qtype_clozeonimage_pos', [
                    'questionid' => $question->id,
                    'no' => 4,
                ]);
                break;
            case 'more':
                $DB->insert_record('qtype_clozeonimage_pos', (object) [
                    'questionid' => $question->id,
                    'no' => 9,
                    'xleft' => 999,
                    'ytop' => 999,
                    'anchor' => 4,
                ]);
                break;
            case 'zero':
                $DB->set_field('qtype_clozeonimage_pos', 'no', 0, [
                    'questionid' => $question->id,
                    'no' => 1,
                ]);
                break;
            case 'negative':
                $DB->set_field('qtype_clozeonimage_pos', 'no', -2, [
                    'questionid' => $question->id,
                    'no' => 1,
                ]);
                break;
            default:
                throw new \coding_exception('Unknown position-corruption fixture.');
        }

        \question_bank::notify_question_edited($question->id);
        return \question_bank::load_question_data($question->id);
    }

    /**
     * Return the persisted option sequence and raw position rows without reducing rows to a map.
     */
    private function get_persisted_metadata(\stdClass $question): array {
        global $DB;

        $positions = array_values($DB->get_records(
            'qtype_clozeonimage_pos',
            ['questionid' => $question->id],
            'no ASC, id ASC',
            'id, questionid, no, xleft, ytop, anchor'
        ));
        return [
            'sequence' => $DB->get_field('qtype_clozeonimage', 'sequence', ['questionid' => $question->id]),
            'positions' => array_map(static fn(\stdClass $position): array => [
                'id' => (int) $position->id,
                'questionid' => (int) $position->questionid,
                'no' => (int) $position->no,
                'xleft' => (int) $position->xleft,
                'ytop' => (int) $position->ytop,
                'anchor' => (int) $position->anchor,
            ], $positions),
        ];
    }

    /**
     * Build and populate the real edit form for an existing question.
     *
     * @return array{0:\qtype_clozeonimage_edit_form,1:\stdClass}
     */
    private function make_edit_form(\stdClass $question): array {
        global $PAGE;

        $PAGE->set_url(new \moodle_url('/'));
        $formquestion = fullclone($question);
        $formquestion->formoptions = (object) [
            'canmove' => false,
            'cansaveasnew' => false,
            'canedit' => true,
            'movecontext' => null,
            'repeatelements' => true,
        ];
        $formquestion->beingcopied = false;
        $formquestion->inputs = null;
        $context = \context::instance_by_id($question->contextid);
        $form = new \qtype_clozeonimage_edit_form(
            new \moodle_url('/'),
            $formquestion,
            $question->categoryobject,
            new \core_question\local\bank\question_edit_contexts($context)
        );
        $form->set_data($formquestion);

        return [$form, $formquestion];
    }

    /**
     * Access the underlying QuickForm object.
     */
    private function get_quickform(\qtype_clozeonimage_edit_form $form): \MoodleQuickForm {
        $property = new \ReflectionProperty(\qtype_clozeonimage_edit_form::class, '_form');
        return $property->getValue($form);
    }

    /**
     * Read one child element value from a repeated row.
     */
    private function get_row_value(\MoodleQuickForm $form, int $index, string $name): mixed {
        foreach ($form->getElement('subquestionrow[' . $index . ']')->getElements() as $element) {
            if ($element->getName() === $name . '[' . $index . ']') {
                return $element->getValue();
            }
        }
        $this->fail('Could not find repeated element ' . $name . '[' . $index . '].');
    }

    /**
     * Form validation data using the drafts prepared while opening the edit form.
     */
    private function make_validation_data(\stdClass $question, array $sources): array {
        $questiontext = is_array($question->questiontext)
            ? $question->questiontext
            : ['text' => $question->questiontext, 'format' => $question->questiontextformat];
        $generalfeedback = is_array($question->generalfeedback)
            ? $question->generalfeedback
            : ['text' => $question->generalfeedback, 'format' => $question->generalfeedbackformat];
        return [
            'category' => $question->categoryobject->id,
            'name' => $question->name,
            'questiontext' => $questiontext,
            'generalfeedback' => $generalfeedback,
            'bgimage' => $question->bgimage,
            'displaywidth' => 1,
            'subquestion' => $sources,
        ];
    }

    /**
     * Detectable database corruption types.
     */
    public static function corruption_provider(): array {
        return [
            'fewer position rows' => ['fewer'],
            'more position rows' => ['more'],
            'zero visible row number' => ['zero'],
            'negative visible row number' => ['negative'],
        ];
    }

    /**
     * Healthy sparse position metadata retains its established mapping.
     */
    public function test_healthy_sparse_positions_remain_unchanged(): void {
        $questiondata = $this->create_saved_question();

        $this->assertFalse(\qtype_clozeonimage::has_corrupt_position_metadata($questiondata));
        $this->assertSame([1 => 1, 2 => 4, 3 => 7], $questiondata->options->questionrows);
        $runtime = \question_bank::make_question($questiondata);
        $this->assertFalse($runtime->positionmetadataiscorrupt);
        $this->assertSame([1, 2, 3], array_keys($runtime->positions));
        foreach ([1, 2, 3] as $slot) {
            $this->assertSame(self::XLEFT[$slot - 1], (int) $runtime->positions[$slot]->xleft);
            $this->assertSame(self::YTOP[$slot - 1], (int) $runtime->positions[$slot]->ytop);
        }
    }

    /**
     * Raw position count and positive row-number violations are detected during loading.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('corruption_provider')]
    public function test_loader_detects_position_corruption(string $kind): void {
        $questiondata = $this->reload_with_position_corruption($this->create_saved_question(), $kind);

        $this->assertTrue(\qtype_clozeonimage::has_corrupt_position_metadata($questiondata));
        $runtime = \question_bank::make_question($questiondata);
        $this->assertTrue($runtime->positionmetadataiscorrupt);
        $this->assertSame([1, 2, 3], array_keys($runtime->subquestions));
        $this->assertSame([], $runtime->positions);
    }

    /**
     * Duplicate row numbers are rejected before records are reduced to a keyed map.
     */
    public function test_integrity_checker_detects_duplicate_row_numbers(): void {
        $positions = [
            (object) ['no' => 1],
            (object) ['no' => 4],
            (object) ['no' => 4],
        ];

        $this->assertFalse(\qtype_clozeonimage::is_position_metadata_valid($positions, 3));
        $this->assertTrue(\qtype_clozeonimage::is_position_metadata_valid([
            (object) ['no' => 1],
            (object) ['no' => 4],
            (object) ['no' => 7],
        ], 3));
    }

    /**
     * Corrupt metadata uses fallback placement without changing response slots or grading.
     */
    public function test_corrupt_runtime_uses_fallbacks_and_preserves_dense_response_slots(): void {
        $questiondata = $this->reload_with_position_corruption($this->create_saved_question(), 'fewer');
        $runtime = \question_bank::make_question($questiondata);

        $this->assertSame([], $runtime->positions);
        $this->assertSame(
            ['sub1_answer', 'sub2_answer', 'sub3_answer'],
            array_keys($runtime->get_expected_data())
        );
        [$fraction, $state] = $runtime->grade_response([
            'sub1_answer' => 'Alpha',
            'sub2_answer' => '2',
            'sub3_answer' => 'Gamma',
        ]);
        $this->assertSame(1.0, $fraction);
        $this->assertSame(\question_state::$gradedright, $state);

        $this->start_attempt_at_question($runtime, 'deferredfeedback', 1);
        $this->render();
        foreach ([12, 32, 52] as $coordinate) {
            $this->assertStringContainsString(
                'left:' . $coordinate . 'px;top:' . $coordinate . 'px;',
                $this->currentoutput
            );
        }
        $this->assertStringNotContainsString('left:11px;top:101px;', $this->currentoutput);
        $this->assertStringNotContainsString('left:77px;top:707px;', $this->currentoutput);
    }

    /**
     * Position corruption has its own visible warning and healthy rendering does not.
     */
    public function test_renderer_reports_position_corruption_only_when_needed(): void {
        $corrupt = \question_bank::make_question(
            $this->reload_with_position_corruption($this->create_saved_question(), 'more')
        );
        $this->start_attempt_at_question($corrupt, 'deferredfeedback', 1);
        $this->render();
        $this->assertStringContainsString(
            get_string('corruptpositionmetadata', 'qtype_clozeonimage'),
            $this->currentoutput
        );
        $this->assertStringNotContainsString(get_string('corruptedquestion', 'qtype_multianswer'), $this->currentoutput);

        $healthy = \question_bank::make_question($this->create_saved_question());
        $this->start_attempt_at_question($healthy, 'deferredfeedback', 1);
        $this->render();
        $this->assertStringNotContainsString(
            get_string('corruptpositionmetadata', 'qtype_clozeonimage'),
            $this->currentoutput
        );
        $this->assertStringContainsString('left:11px;top:101px;', $this->currentoutput);
        $this->assertStringContainsString('left:44px;top:404px;', $this->currentoutput);
        $this->assertStringContainsString('left:77px;top:707px;', $this->currentoutput);
    }

    /**
     * Missing-child corruption remains independent from healthy position metadata.
     */
    public function test_missing_child_behavior_remains_unchanged(): void {
        global $DB;

        $questiondata = $this->create_saved_question();
        $childids = array_values(array_map('intval', explode(',', $questiondata->options->sequence)));
        $DB->delete_records('question', ['id' => $childids[1]]);
        \question_bank::notify_question_edited($questiondata->id);
        $questiondata = \question_bank::load_question_data($questiondata->id);

        $this->assertFalse(\qtype_clozeonimage::has_corrupt_position_metadata($questiondata));
        $runtime = \question_bank::make_question($questiondata);
        $this->assertSame([1, 2, 3], array_keys($runtime->positions));
        $this->start_attempt_at_question($runtime, 'deferredfeedback', 1);
        $this->render();
        $this->assertStringContainsString(get_string('corruptedquestion', 'qtype_multianswer'), $this->currentoutput);
        $this->assertStringContainsString(get_string('missingsubquestion', 'qtype_multianswer'), $this->currentoutput);
        $this->assertStringNotContainsString(
            get_string('corruptpositionmetadata', 'qtype_clozeonimage'),
            $this->currentoutput
        );
    }

    /**
     * Missing-child and position corruption warnings remain independently visible.
     */
    public function test_both_corruption_conditions_are_reported(): void {
        global $DB;

        $questiondata = $this->create_saved_question();
        $childids = array_values(array_map('intval', explode(',', $questiondata->options->sequence)));
        $DB->delete_records('question', ['id' => $childids[1]]);
        $DB->delete_records('qtype_clozeonimage_pos', [
            'questionid' => $questiondata->id,
            'no' => 4,
        ]);
        \question_bank::notify_question_edited($questiondata->id);
        $runtime = \question_bank::make_question(\question_bank::load_question_data($questiondata->id));

        $this->start_attempt_at_question($runtime, 'deferredfeedback', 1);
        $this->render();
        $this->assertStringContainsString(get_string('corruptedquestion', 'qtype_multianswer'), $this->currentoutput);
        $this->assertStringContainsString(get_string('missingsubquestion', 'qtype_multianswer'), $this->currentoutput);
        $this->assertStringContainsString(
            get_string('corruptpositionmetadata', 'qtype_clozeonimage'),
            $this->currentoutput
        );
    }

    /**
     * A corrupt edit form shows a safe dense inspection view and blocks validation without changing storage.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('corruption_provider')]
    public function test_corrupt_edit_form_is_safe_and_blocks_validation(string $kind): void {
        $questiondata = $this->reload_with_position_corruption($this->create_saved_question(), $kind);
        $before = $this->get_persisted_metadata($questiondata);
        [$form, $formquestion] = $this->make_edit_form($questiondata);
        $quickform = $this->get_quickform($form);

        $this->assertStringContainsString(
            get_string('corruptpositionmetadataedit', 'qtype_clozeonimage'),
            $quickform->getElement('positionmetadataerror')->toHtml()
        );
        foreach (array_values(self::SOURCES) as $index => $source) {
            $this->assertSame($source, $this->get_row_value($quickform, $index, 'subquestion'));
            $coordinate = 12 + 20 * $index;
            $this->assertSame($coordinate, (int) $this->get_row_value($quickform, $index, 'xleft'));
            $this->assertSame($coordinate, (int) $this->get_row_value($quickform, $index, 'ytop'));
        }
        $this->assertFalse($quickform->elementExists('subquestionrow[6]'));

        $sources = array_values(self::SOURCES);
        $sources[3] = '';
        $errors = $form->validation($this->make_validation_data($formquestion, $sources), []);
        $this->assertSame(
            get_string('corruptpositionmetadataedit', 'qtype_clozeonimage'),
            $errors['positionmetadataerror']
        );
        $this->assertSame($before, $this->get_persisted_metadata($questiondata));
    }

    /**
     * The qtype save entry point refuses every persistently detectable corruption without writing anything.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('corruption_provider')]
    public function test_qtype_save_guard_blocks_corrupt_version(string $kind): void {
        global $DB;

        $questiondata = $this->reload_with_position_corruption($this->create_saved_question(), $kind);
        $before = $this->get_persisted_metadata($questiondata);
        $questioncount = $DB->count_records('question');

        try {
            \question_bank::get_qtype('clozeonimage')->save_question(
                clone $questiondata,
                (object) ['id' => $questiondata->id]
            );
            $this->fail('Saving corrupt position metadata should throw an exception.');
        } catch (\moodle_exception $exception) {
            $this->assertStringContainsString(
                get_string('corruptpositionmetadataedit', 'qtype_clozeonimage'),
                $exception->getMessage()
            );
        }

        $this->assertSame($questioncount, $DB->count_records('question'));
        $this->assertSame($before, $this->get_persisted_metadata($questiondata));
    }

    /**
     * Copy-style save data cannot discard the corruption marker or normalize the source question.
     */
    public function test_qtype_save_guard_blocks_corrupt_copy(): void {
        global $DB;

        $questiondata = $this->reload_with_position_corruption($this->create_saved_question(), 'fewer');
        $before = $this->get_persisted_metadata($questiondata);
        $questioncount = $DB->count_records('question');
        $copy = fullclone($questiondata);
        $copy->id = 0;

        try {
            \question_bank::get_qtype('clozeonimage')->save_question(
                $copy,
                (object) ['id' => $questiondata->id]
            );
            $this->fail('Copying corrupt position metadata should throw an exception.');
        } catch (\moodle_exception $exception) {
            $this->assertStringContainsString(
                get_string('corruptpositionmetadataedit', 'qtype_clozeonimage'),
                $exception->getMessage()
            );
        }

        $this->assertSame($questioncount, $DB->count_records('question'));
        $this->assertSame($before, $this->get_persisted_metadata($questiondata));
    }

    /**
     * Moodle XML export refuses every persistently detectable position corruption.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('corruption_provider')]
    public function test_xml_export_refuses_position_corruption(string $kind): void {
        $questiondata = $this->reload_with_position_corruption($this->create_saved_question(), $kind);

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('cannotexportcorruptpositionmetadata', 'qtype_clozeonimage'));
        (new \qformat_xml())->writequestion($questiondata);
    }

    /**
     * Healthy sparse metadata, blank form rows and Moodle XML export retain their existing behavior.
     */
    public function test_healthy_edit_and_export_remain_unchanged(): void {
        $questiondata = $this->create_saved_question();
        [$form, $formquestion] = $this->make_edit_form($questiondata);
        $quickform = $this->get_quickform($form);

        $this->assertFalse($quickform->elementExists('positionmetadataerror'));
        foreach (self::SOURCES as $index => $source) {
            $this->assertSame($source, $this->get_row_value($quickform, $index, 'subquestion'));
        }
        $sources = self::SOURCES;
        $sources[1] = '';
        $errors = $form->validation($this->make_validation_data($formquestion, $sources), []);
        $this->assertArrayNotHasKey('positionmetadataerror', $errors);
        $this->assertArrayNotHasKey('subquestionrow[1]', $errors);

        $xml = (new \qformat_xml())->writequestion($questiondata);
        $this->assertSame(3, substr_count($xml, '<subquestion>'));
        foreach (self::ROWNUMBERS as $rowno) {
            $this->assertStringContainsString('<no>' . $rowno . '</no>', $xml);
        }
    }
}
