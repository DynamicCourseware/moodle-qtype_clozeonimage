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
require_once($CFG->dirroot . '/question/format/xml/format.php');
require_once($CFG->dirroot . '/question/type/clozeonimage/questiontype.php');
require_once($CFG->dirroot . '/question/type/clozeonimage/edit_clozeonimage_form.php');

/**
 * Tests for stable runtime slots when a wrapped child question is missing.
 *
 * @package    qtype_clozeonimage
 * @copyright  2026 DynamicCourseware.org (Dominique Bauer)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\qtype_clozeonimage::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\qtype_clozeonimage_renderer::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\qtype_clozeonimage_edit_form::class)]
final class missing_child_test extends \qbehaviour_walkthrough_test_base {
    /** @var string[] Wrapped sources in dense sequence order. */
    private const SOURCES = [
        0 => '{1:SHORTANSWER:=Alpha~Wrong}',
        3 => '{1:NUMERICAL:=2:0}',
        6 => '{1:SHORTANSWER:=Gamma~Wrong}',
    ];

    /** @var int[] Sparse visible row numbers in dense sequence order. */
    private const ROWNUMBERS = [1, 4, 7];

    /** @var int[] Horizontal coordinates in dense sequence order. */
    private const XLEFT = [11, 44, 77];

    /** @var int[] Vertical coordinates in dense sequence order. */
    private const YTOP = [101, 404, 707];

    /**
     * Store the background image in a user draft area.
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
     * Create a persisted question with three dense children and sparse visible rows.
     */
    private function create_saved_question(): \stdClass {
        global $USER;

        $context = \context_system::instance();
        $category = $this->getDataGenerator()->get_plugin_generator('core_question')->create_question_category([
            'contextid' => $context->id,
        ]);
        $formdata = (object) [
            'category' => $category->id . ',' . $context->id,
            'name' => 'Missing wrapped child',
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
     * Remove one child record without updating the stored sequence, then reload the parent.
     */
    private function reload_with_missing_child(\stdClass $question, int $missingslot): \stdClass {
        global $DB;

        $childids = array_values(array_map('intval', explode(',', $question->options->sequence)));
        $DB->delete_records('question', ['id' => $childids[$missingslot - 1]]);
        \question_bank::notify_question_edited($question->id);

        return \question_bank::load_question_data($question->id);
    }

    /**
     * Build the real edit form for an existing question and populate its draft areas.
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
     * Prepare complete form-like data for explicitly replacing a missing row.
     */
    private function make_repair_data(\stdClass $question, string $replacement): \stdClass {
        $context = \context::instance_by_id($question->contextid);
        $bgdraftitemid = 0;
        file_prepare_draft_area(
            $bgdraftitemid,
            $context->id,
            'qtype_clozeonimage',
            'bgimage',
            $question->id,
            \qtype_clozeonimage_edit_form::file_picker_options()
        );
        $afterdraftitemid = 0;
        $aftertext = file_prepare_draft_area(
            $afterdraftitemid,
            $context->id,
            'qtype_clozeonimage',
            'aftertext',
            $question->id,
            ['subdirs' => 1, 'maxfiles' => EDITOR_UNLIMITED_FILES],
            $question->options->aftertext
        );

        return (object) [
            'category' => $question->categoryobject->id . ',' . $context->id,
            'name' => $question->name,
            'questiontext' => [
                'text' => $question->questiontext,
                'format' => $question->questiontextformat,
                'itemid' => 0,
            ],
            'generalfeedback' => [
                'text' => $question->generalfeedback,
                'format' => $question->generalfeedbackformat,
                'itemid' => 0,
            ],
            'penalty' => $question->penalty,
            'status' => $question->status,
            'idnumber' => $question->idnumber,
            'subquestion' => [0 => self::SOURCES[0], 3 => $replacement, 6 => self::SOURCES[6]],
            'xleft' => [0 => self::XLEFT[0], 3 => self::XLEFT[1], 6 => self::XLEFT[2]],
            'ytop' => [0 => self::YTOP[0], 3 => self::YTOP[1], 6 => self::YTOP[2]],
            'anchor' => [0 => 0, 3 => 4, 6 => 8],
            'displaymode' => $question->options->displaymode,
            'displaywidth' => $question->options->displaywidth,
            'bgimage' => $bgdraftitemid,
            'aftertext' => [
                'text' => $aftertext,
                'format' => $question->options->aftertextformat,
                'itemid' => $afterdraftitemid,
            ],
            'hint' => [],
            'hintclearwrong' => [],
            'hintshownumcorrect' => [],
        ];
    }

    /**
     * Slots used to exercise missing children at every sequence position.
     */
    public static function missing_slot_provider(): array {
        return [
            'first child' => [1],
            'middle child' => [2],
            'last child' => [3],
        ];
    }

    /**
     * Every original sequence ordinal survives loading and runtime construction.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('missing_slot_provider')]
    public function test_missing_child_preserves_sequence_slot(int $missingslot): void {
        $question = $this->create_saved_question();
        $storedsequence = $question->options->sequence;
        $questiondata = $this->reload_with_missing_child($question, $missingslot);

        $this->assertSame($storedsequence, $questiondata->options->sequence);
        $this->assertSame([1, 2, 3], array_keys($questiondata->options->questions));
        $this->assertSame('subquestion_replacement', $questiondata->options->questions[$missingslot]->qtype);
        $this->assertSame([1 => 1, 2 => 4, 3 => 7], $questiondata->options->questionrows);

        $runtime = \question_bank::make_question($questiondata);
        $expectedchildren = array_values(array_diff([1, 2, 3], [$missingslot]));
        $this->assertSame([1, 2, 3], $runtime->subquestionslots);
        $this->assertSame($expectedchildren, array_keys($runtime->subquestions));
        $this->assertSame([1, 2, 3], array_keys($runtime->positions));
        foreach ([1, 2, 3] as $slot) {
            $this->assertSame(self::XLEFT[$slot - 1], (int) $runtime->positions[$slot]->xleft);
            $this->assertSame(self::YTOP[$slot - 1], (int) $runtime->positions[$slot]->ytop);
        }

        $expectedfields = array_map(static fn(int $slot): string => 'sub' . $slot . '_answer', $expectedchildren);
        $this->assertSame($expectedfields, array_keys($runtime->get_expected_data()));
        $this->assertIsFloat(\question_bank::get_qtype('clozeonimage')->get_random_guess_score($questiondata));
    }

    /**
     * An existing sub3_ response is still graded by the original third child after slot 2 is lost.
     */
    public function test_middle_child_loss_does_not_reinterpret_third_response(): void {
        global $DB;

        $questiondata = $this->create_saved_question();
        $runtime = \question_bank::make_question($questiondata);

        $this->start_attempt_at_question($runtime, 'deferredfeedback', 1);
        $this->process_submission([
            'sub1_answer' => 'Alpha',
            'sub2_answer' => '2',
            'sub3_answer' => 'Gamma',
        ]);
        $this->check_current_state(\question_state::$complete);
        $this->save_quba();

        $childids = array_values(array_map('intval', explode(',', $questiondata->options->sequence)));
        $DB->delete_records('question', ['id' => $childids[1]]);
        \question_bank::notify_question_edited($questiondata->id);
        $this->load_quba();

        $this->assertSame([1, 3], array_keys($this->get_question_attempt()->get_question()->subquestions));
        $this->assertSame('Gamma', $this->get_question_attempt()->get_last_qt_var('sub3_answer'));
        $this->finish();
        $this->check_current_state(\question_state::$gradedright);
        $this->check_current_mark(1);
    }

    /**
     * Rendering exposes both the damaged question and the missing positioned slot.
     */
    public function test_renderer_reports_missing_positioned_child(): void {
        $questiondata = $this->reload_with_missing_child($this->create_saved_question(), 2);
        $runtime = \question_bank::make_question($questiondata);

        $this->start_attempt_at_question($runtime, 'deferredfeedback', 1);
        $this->render();

        $this->assertStringContainsString(
            get_string('corruptedquestion', 'qtype_multianswer'),
            $this->currentoutput
        );
        $this->assertStringContainsString(
            get_string('missingsubquestion', 'qtype_multianswer'),
            $this->currentoutput
        );
        $this->assertStringContainsString('left:44px;top:404px;', $this->currentoutput);
        $this->assertStringContainsString('sub1_answer', $this->currentoutput);
        $this->assertStringContainsString('sub3_answer', $this->currentoutput);
        $this->assertStringNotContainsString('sub2_answer', $this->currentoutput);
    }

    /**
     * Healthy questions retain their existing rendering and grading behavior.
     */
    public function test_healthy_question_is_unchanged(): void {
        $runtime = \question_bank::make_question($this->create_saved_question());

        $this->start_attempt_at_question($runtime, 'deferredfeedback', 1);
        $this->render();
        $this->assertStringNotContainsString(get_string('corruptedquestion', 'qtype_multianswer'), $this->currentoutput);
        $this->assertStringNotContainsString(get_string('missingsubquestion', 'qtype_multianswer'), $this->currentoutput);

        $this->process_submission([
            'sub1_answer' => 'Alpha',
            'sub2_answer' => '2',
            'sub3_answer' => 'Gamma',
        ]);
        $this->check_current_state(\question_state::$complete);
        $this->finish();
        $this->check_current_state(\question_state::$gradedright);
        $this->check_current_mark(1);
    }

    /**
     * The edit form retains a missing sparse row and blocks an unresolved save without changing storage.
     */
    public function test_edit_form_preserves_and_rejects_unresolved_missing_row(): void {
        global $DB;

        $question = $this->create_saved_question();
        $storedsequence = $question->options->sequence;
        $questiondata = $this->reload_with_missing_child($question, 2);
        [$form, $formquestion] = $this->make_edit_form($questiondata);
        $quickform = $this->get_quickform($form);

        $this->assertSame('', $this->get_row_value($quickform, 3, 'subquestion'));
        $this->assertSame(self::SOURCES[6], $this->get_row_value($quickform, 6, 'subquestion'));
        $this->assertSame(self::XLEFT[1], (int) $this->get_row_value($quickform, 3, 'xleft'));
        $this->assertSame(self::YTOP[1], (int) $this->get_row_value($quickform, 3, 'ytop'));
        $this->assertSame(4, (int) $formquestion->anchor[3]);
        $this->assertStringContainsString(
            get_string('missingsubquestionedit', 'qtype_clozeonimage'),
            $quickform->getElementError('subquestionrow[3]')
        );

        $sources = [0 => self::SOURCES[0], 3 => '', 6 => self::SOURCES[6]];
        $errors = $form->validation($this->make_validation_data($formquestion, $sources), []);
        $this->assertStringContainsString(
            get_string('missingsubquestionedit', 'qtype_clozeonimage'),
            $errors['subquestionrow[3]']
        );
        $this->assertSame(
            $storedsequence,
            $DB->get_field('qtype_clozeonimage', 'sequence', ['questionid' => $question->id])
        );
        $this->assertSame(3, $DB->count_records('qtype_clozeonimage_pos', ['questionid' => $question->id]));
    }

    /**
     * A valid explicit replacement creates a healthy new version without shifting surviving children.
     */
    public function test_valid_replacement_repairs_missing_row(): void {
        global $DB;

        $question = $this->create_saved_question();
        $oldchildids = array_values(array_map('intval', explode(',', $question->options->sequence)));
        $oldfirstentry = get_question_bank_entry($oldchildids[0])->id;
        $oldthirdentry = get_question_bank_entry($oldchildids[2])->id;
        $questiondata = $this->reload_with_missing_child($question, 2);
        [$form, $formquestion] = $this->make_edit_form($questiondata);
        $replacement = '{1:SHORTANSWER:=Beta~Wrong}';
        $sources = [0 => self::SOURCES[0], 3 => $replacement, 6 => self::SOURCES[6]];

        $errors = $form->validation($this->make_validation_data($formquestion, $sources), []);
        $this->assertArrayNotHasKey('subquestionrow[3]', $errors);

        $saved = \question_bank::get_qtype('clozeonimage')->save_question(
            clone $questiondata,
            $this->make_repair_data($questiondata, $replacement)
        );
        $repaired = \question_bank::load_question_data($saved->id);
        $newchildids = array_values(array_map('intval', explode(',', $repaired->options->sequence)));

        $this->assertCount(3, $newchildids);
        $this->assertSame([1, 2, 3], array_keys($repaired->options->questions));
        $this->assertSame([1 => 1, 2 => 4, 3 => 7], $repaired->options->questionrows);
        $this->assertSame(
            [self::SOURCES[0], $replacement, self::SOURCES[6]],
            array_values(array_map(
                static fn($child): string => $child->questiontext,
                $repaired->options->questions
            ))
        );
        $this->assertSame([1, 4, 7], array_map('intval', array_keys($repaired->options->positions)));
        $this->assertSame([self::XLEFT[1], self::YTOP[1], 4], [
            (int) $repaired->options->positions[4]->xleft,
            (int) $repaired->options->positions[4]->ytop,
            (int) $repaired->options->positions[4]->anchor,
        ]);
        $this->assertSame($oldfirstentry, get_question_bank_entry($newchildids[0])->id);
        $this->assertSame($oldthirdentry, get_question_bank_entry($newchildids[2])->id);
        $this->assertNotSame($oldthirdentry, get_question_bank_entry($newchildids[1])->id);
        $this->assertSame(1, $DB->count_records('qtype_clozeonimage', ['questionid' => $repaired->id]));
        $this->assertSame(3, $DB->count_records('qtype_clozeonimage_pos', ['questionid' => $repaired->id]));
    }

    /**
     * An ordinary blank intermediate row in a healthy question remains valid.
     */
    public function test_healthy_blank_intermediate_row_remains_allowed(): void {
        $question = $this->create_saved_question();
        [$form, $formquestion] = $this->make_edit_form($question);
        $sources = [0 => self::SOURCES[0], 1 => '', 3 => self::SOURCES[3], 6 => self::SOURCES[6]];

        $errors = $form->validation($this->make_validation_data($formquestion, $sources), []);

        $this->assertArrayNotHasKey('subquestionrow[1]', $errors);
        $this->assertArrayNotHasKey('subquestionrow[3]', $errors);
    }

    /**
     * Missing persisted children are refused by Moodle XML export at every sequence position.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('missing_slot_provider')]
    public function test_xml_export_refuses_missing_child(int $missingslot): void {
        $questiondata = $this->reload_with_missing_child($this->create_saved_question(), $missingslot);

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('cannotexportmissingchild', 'qtype_clozeonimage'));
        (new \qformat_xml())->writequestion($questiondata);
    }

    /**
     * Healthy Moodle XML export retains all three portable source rows.
     */
    public function test_healthy_xml_export_remains_complete(): void {
        $xml = (new \qformat_xml())->writequestion($this->create_saved_question());

        $this->assertSame(3, substr_count($xml, '<subquestion>'));
        foreach (self::SOURCES as $source) {
            $this->assertStringContainsString($source, $xml);
        }
        $this->assertStringNotContainsString('subquestion_replacement', $xml);
    }
}
