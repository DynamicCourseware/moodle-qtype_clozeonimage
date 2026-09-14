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
use question_bank;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for the normal Cloze on Image question lifecycle.
 *
 * @package    qtype_clozeonimage
 * @copyright  2026 DynamicCourseware.org (Dominique Bauer)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\qtype_clozeonimage::class)]
final class lifecycle_test extends \advanced_testcase {
    /** @var array Sparse form rows used by the fixture. */
    private const SOURCES = [
        0 => '{1:SHORTANSWER:=Owl~Dog}',
        3 => '{1:MULTICHOICE:=Paris~Rome}',
        6 => '{1:NUMERICAL:=42:0}',
    ];

    /** @var array Signed/outside horizontal coordinates. */
    private const XLEFT = [0 => -20, 3 => 1800, 6 => 75];

    /** @var array Signed/outside vertical coordinates. */
    private const YTOP = [0 => 84, 3 => 2500, 6 => -30];

    /** @var array Anchor values covering the range. */
    private const ANCHORS = [0 => 0, 3 => 4, 6 => 8];

    /**
     * Create a course, question-bank context, and category.
     *
     * @return array Course, context, and category.
     */
    private function create_question_bank_fixture(): array {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $qbank = $generator->get_plugin_generator('mod_qbank')->create_instance(['course' => $course->id]);
        $context = \context_module::instance($qbank->cmid);
        $category = $generator->get_plugin_generator('core_question')->create_question_category([
            'contextid' => $context->id,
        ]);

        return [$course, $context, $category];
    }

    /**
     * Put one file into a user draft area.
     *
     * @param string $filename File name.
     * @param string $content File content.
     * @return int Draft item ID.
     */
    private function create_draft_file(string $filename, string $content): int {
        global $USER;

        $draftitemid = file_get_unused_draft_itemid();
        get_file_storage()->create_file_from_string((object) [
            'contextid' => \context_user::instance($USER->id)->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => $draftitemid,
            'filepath' => '/',
            'filename' => $filename,
        ], $content);

        return $draftitemid;
    }

    /**
     * Create a complete form-like object for an initial save.
     *
     * @param stdClass $category Question category.
     * @param context $context Question-bank context.
     * @param string $suffix Fixture suffix.
     * @param int $controlappearance Answer control appearance.
     * @return stdClass Form data.
     */
    private function make_initial_form(
        \stdClass $category,
        \context $context,
        string $suffix,
        int $controlappearance = \qtype_clozeonimage::CONTROL_APPEARANCE_TRANSLUCENT
    ): \stdClass {
        return (object) [
            'category' => $category->id . ',' . $context->id,
            'name' => 'Cloze on Image lifecycle ' . $suffix,
            'questiontext' => [
                'text' => '<p>Instructions before the image ' . $suffix . '.</p>',
                'format' => FORMAT_HTML,
                'itemid' => 0,
            ],
            'generalfeedback' => ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0],
            'penalty' => 0.3333333,
            'status' => question_version_status::QUESTION_STATUS_READY,
            'idnumber' => null,
            'subquestion' => self::SOURCES,
            'xleft' => self::XLEFT,
            'ytop' => self::YTOP,
            'anchor' => self::ANCHORS,
            'displaymode' => \qtype_clozeonimage::DISPLAY_ORIGINAL,
            'displaywidth' => 1234,
            'controlappearance' => $controlappearance,
            'bgimage' => $this->create_draft_file('background-' . $suffix . '.png', 'background-' . $suffix),
            'aftertext' => [
                'text' => '<p>Text after the image ' . $suffix
                    . '. <a href="@@PLUGINFILE@@/after-' . $suffix . '.txt">File</a></p>',
                'format' => FORMAT_HTML,
                'itemid' => $this->create_draft_file('after-' . $suffix . '.txt', 'aftertext-' . $suffix),
            ],
            'hint' => [],
            'hintclearwrong' => [],
            'hintshownumcorrect' => [],
        ];
    }

    /**
     * Save a complete initial Cloze on Image question through the normal qtype API.
     *
     * @param stdClass $category Question category.
     * @param context $context Question-bank context.
     * @param string $suffix Fixture suffix.
     * @param int $controlappearance Answer control appearance.
     * @return stdClass Saved question data.
     */
    private function create_saved_question(
        \stdClass $category,
        \context $context,
        string $suffix,
        int $controlappearance = \qtype_clozeonimage::CONTROL_APPEARANCE_TRANSLUCENT
    ): \stdClass {
        global $USER;

        $question = (object) [
            'qtype' => 'clozeonimage',
            'createdby' => $USER->id,
            'idnumber' => null,
            'status' => question_version_status::QUESTION_STATUS_READY,
        ];
        $saved = question_bank::get_qtype('clozeonimage')->save_question(
            $question,
            $this->make_initial_form($category, $context, $suffix, $controlappearance)
        );

        return question_bank::load_question_data($saved->id);
    }

    /**
     * Supported answer control appearances.
     *
     * @return array<string, array{int}>
     */
    public static function control_appearance_provider(): array {
        return [
            'translucent' => [\qtype_clozeonimage::CONTROL_APPEARANCE_TRANSLUCENT],
            'opaque' => [\qtype_clozeonimage::CONTROL_APPEARANCE_OPAQUE],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('control_appearance_provider')]
    public function test_control_appearance_save_load_and_runtime(int $controlappearance): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [, $context, $category] = $this->create_question_bank_fixture();

        $saved = $this->create_saved_question($category, $context, 'appearance', $controlappearance);

        $this->assertSame($controlappearance, (int) $saved->options->controlappearance);
        $runtimequestion = \question_bank::make_question($saved);
        $this->assertSame($controlappearance, $runtimequestion->controlappearance);
    }

    public function test_multiple_tries_hints_save_and_load(): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        [, $context, $category] = $this->create_question_bank_fixture();

        $formdata = $this->make_initial_form($category, $context, 'hints');
        $formdata->hint = [
            ['text' => '<p>First hint.</p>', 'format' => FORMAT_HTML, 'itemid' => 0],
            ['text' => '<p>Second hint.</p>', 'format' => FORMAT_HTML, 'itemid' => 0],
        ];
        $formdata->hintclearwrong = [1, 0];
        $formdata->hintshownumcorrect = [0, 1];
        $question = (object) [
            'qtype' => 'clozeonimage',
            'createdby' => $USER->id,
            'idnumber' => null,
            'status' => question_version_status::QUESTION_STATUS_READY,
        ];

        $saved = question_bank::get_qtype('clozeonimage')->save_question($question, $formdata);
        $records = array_values($DB->get_records('question_hints', [
            'questionid' => $saved->id,
        ], 'id ASC'));

        $this->assertCount(2, $records);
        $this->assertSame('<p>First hint.</p>', $records[0]->hint);
        $this->assertSame(1, (int) $records[0]->clearwrong);
        $this->assertSame(0, (int) $records[0]->shownumcorrect);
        $this->assertSame('<p>Second hint.</p>', $records[1]->hint);
        $this->assertSame(0, (int) $records[1]->clearwrong);
        $this->assertSame(1, (int) $records[1]->shownumcorrect);

        $loaded = question_bank::load_question_data($saved->id);
        $hints = array_values($loaded->hints);
        $this->assertCount(2, $hints);
        $this->assertSame('<p>First hint.</p>', $hints[0]->hint);
        $this->assertSame(1, (int) $hints[0]->clearwrong);
        $this->assertSame(0, (int) $hints[0]->shownumcorrect);
        $this->assertSame('<p>Second hint.</p>', $hints[1]->hint);
        $this->assertSame(0, (int) $hints[1]->clearwrong);
        $this->assertSame(1, (int) $hints[1]->shownumcorrect);
    }

    /**
     * Prepare form-like edit data, including new drafts copied from both plugin file areas.
     *
     * @param stdClass $question Saved question data.
     * @return stdClass Form data.
     */
    private function prepare_edit_form(\stdClass $question): \stdClass {
        $context = \context::instance_by_id($question->contextid);
        $bgdraftitemid = 0;
        file_prepare_draft_area(
            $bgdraftitemid,
            $context->id,
            'qtype_clozeonimage',
            'bgimage',
            $question->id,
            ['subdirs' => 0, 'maxbytes' => 0, 'maxfiles' => 1]
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

        $subquestions = [];
        $xleft = [];
        $ytop = [];
        $anchors = [];
        foreach ($question->options->questions as $place => $wrapped) {
            $rowno = (int) $question->options->questionrows[$place];
            $rowindex = $rowno - 1;
            $position = $question->options->positions[$rowno];
            $subquestions[$rowindex] = $wrapped->questiontext;
            $xleft[$rowindex] = (int) $position->xleft;
            $ytop[$rowindex] = (int) $position->ytop;
            $anchors[$rowindex] = (int) $position->anchor;
        }

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
            'subquestion' => $subquestions,
            'xleft' => $xleft,
            'ytop' => $ytop,
            'anchor' => $anchors,
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
     * Return dense child IDs from a loaded question.
     *
     * @param stdClass $question Loaded question data.
     * @return int[] Child IDs in sequence order.
     */
    private function child_ids(\stdClass $question): array {
        return array_values(array_filter(array_map('intval', explode(',', $question->options->sequence))));
    }

    /**
     * Assert the dense-child/sparse-row invariant and parent links.
     *
     * @param stdClass $question Loaded question data.
     * @return int[] Dense child IDs.
     */
    private function assert_composite_integrity(\stdClass $question): array {
        global $DB;

        $childids = $this->child_ids($question);
        $this->assertCount(3, $childids);
        $this->assertSame([1, 2, 3], array_keys($question->options->questions));
        $this->assertSame([1 => 1, 2 => 4, 3 => 7], $question->options->questionrows);
        $this->assertSame([1, 4, 7], array_keys($question->options->positions));
        $this->assertSame(
            ['shortanswer', 'multichoice', 'numerical'],
            array_values(array_map(static fn($child) => $child->qtype, $question->options->questions))
        );
        foreach ($childids as $childid) {
            $this->assertSame(
                (int) $question->id,
                (int) $DB->get_field('question', 'parent', ['id' => $childid], MUST_EXIST)
            );
        }

        return $childids;
    }

    /**
     * Assert a plugin file exists with the expected content.
     *
     * @param int $contextid Context ID.
     * @param string $filearea File area.
     * @param int $questionid Question item ID.
     * @param string $filename File name.
     * @param string $content Expected content.
     */
    private function assert_plugin_file(
        int $contextid,
        string $filearea,
        int $questionid,
        string $filename,
        string $content
    ): void {
        $file = get_file_storage()->get_file(
            $contextid,
            'qtype_clozeonimage',
            $filearea,
            $questionid,
            '/',
            $filename
        );
        $this->assertNotFalse($file);
        $this->assertSame($content, $file->get_content());
    }

    /**
     * Ordinary question-bank copying creates an independent composite question.
     */
    public function test_question_copy(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        [, $context, $category] = $this->create_question_bank_fixture();
        $original = $this->create_saved_question($category, $context, 'copy');
        $originalchildids = $this->assert_composite_integrity($original);

        $copyform = $this->prepare_edit_form($original);
        $copyform->name .= ' copy';
        $copyseed = clone $original;
        $copyseed->id = 0;
        $copyseed->status = question_version_status::QUESTION_STATUS_READY;
        $copy = question_bank::get_qtype('clozeonimage')->save_question($copyseed, $copyform);
        $copy = question_bank::load_question_data($copy->id);

        $this->assertNotSame((int) $original->id, (int) $copy->id);
        $this->assertNotSame((int) $original->questionbankentryid, (int) $copy->questionbankentryid);
        $copychildids = $this->assert_composite_integrity($copy);
        $this->assertEmpty(array_intersect($originalchildids, $copychildids));
        $this->assertSame($original->questiontext, $copy->questiontext);
        $this->assertSame($original->options->aftertext, $copy->options->aftertext);
        $this->assertSame((int) $original->options->displaymode, (int) $copy->options->displaymode);
        $this->assertSame((int) $original->options->displaywidth, (int) $copy->options->displaywidth);
        foreach ([1, 4, 7] as $rowno) {
            $originalposition = $original->options->positions[$rowno];
            $copyposition = $copy->options->positions[$rowno];
            $this->assertSame((int) $originalposition->xleft, (int) $copyposition->xleft);
            $this->assertSame((int) $originalposition->ytop, (int) $copyposition->ytop);
            $this->assertSame((int) $originalposition->anchor, (int) $copyposition->anchor);
        }
        $this->assertSame(1, $DB->count_records('qtype_clozeonimage', ['questionid' => $copy->id]));
        $this->assertSame(3, $DB->count_records('qtype_clozeonimage_pos', ['questionid' => $copy->id]));

        foreach ([$original->id, $copy->id] as $questionid) {
            $this->assert_plugin_file(
                $context->id,
                'bgimage',
                $questionid,
                'background-copy.png',
                'background-copy'
            );
            $this->assert_plugin_file(
                $context->id,
                'aftertext',
                $questionid,
                'after-copy.txt',
                'aftertext-copy'
            );
        }

        $reloadedoriginal = question_bank::load_question_data($original->id);
        $this->assertSame($originalchildids, $this->assert_composite_integrity($reloadedoriginal));
        $this->assertSame(1, $DB->count_records('qtype_clozeonimage', ['questionid' => $original->id]));
        $this->assertSame(3, $DB->count_records('qtype_clozeonimage_pos', ['questionid' => $original->id]));
    }

    /**
     * Editing creates a new parent and child version while preserving the previous version.
     */
    public function test_question_versioning(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        [, $context, $category] = $this->create_question_bank_fixture();
        $original = $this->create_saved_question($category, $context, 'version');
        $originalchildids = $this->assert_composite_integrity($original);
        $originalversion = $DB->get_record('question_versions', ['questionid' => $original->id], '*', MUST_EXIST);

        $editform = $this->prepare_edit_form($original);
        $editform->subquestion[3] = '{1:MULTICHOICE:=Berlin~Rome}';
        $editform->xleft[0] = 321;
        $editform->anchor[3] = 7;
        $editform->displaywidth = 777;
        $editform->aftertext['text'] = '<p>Changed text after the image.'
            . ' <a href="@@PLUGINFILE@@/after-version.txt">File</a></p>';

        $newversion = question_bank::get_qtype('clozeonimage')->save_question(clone $original, $editform);
        $newversion = question_bank::load_question_data($newversion->id);
        $newchildids = $this->assert_composite_integrity($newversion);
        $newversionrecord = $DB->get_record('question_versions', ['questionid' => $newversion->id], '*', MUST_EXIST);

        $this->assertNotSame((int) $original->id, (int) $newversion->id);
        $this->assertSame((int) $originalversion->questionbankentryid, (int) $newversionrecord->questionbankentryid);
        $this->assertSame(1, (int) $originalversion->version);
        $this->assertSame(2, (int) $newversionrecord->version);
        $this->assertSame($originalversion->status, $newversionrecord->status);
        $this->assertEmpty(array_intersect($originalchildids, $newchildids));
        foreach ($originalchildids as $index => $oldchildid) {
            $oldchildversion = $DB->get_record('question_versions', ['questionid' => $oldchildid], '*', MUST_EXIST);
            $newchildversion = $DB->get_record('question_versions', ['questionid' => $newchildids[$index]], '*', MUST_EXIST);
            $this->assertSame(
                (int) $oldchildversion->questionbankentryid,
                (int) $newchildversion->questionbankentryid
            );
        }

        $this->assertSame($original->questiontext, $newversion->questiontext);
        $this->assertSame('{1:MULTICHOICE:=Berlin~Rome}', $newversion->options->questions[2]->questiontext);
        $this->assertSame(321, (int) $newversion->options->positions[1]->xleft);
        $this->assertSame(7, (int) $newversion->options->positions[4]->anchor);
        $this->assertSame(777, (int) $newversion->options->displaywidth);
        $this->assertStringContainsString('Changed text after the image.', $newversion->options->aftertext);
        foreach ([$original->id, $newversion->id] as $questionid) {
            $this->assertSame(1, $DB->count_records('qtype_clozeonimage', ['questionid' => $questionid]));
            $this->assertSame(3, $DB->count_records('qtype_clozeonimage_pos', ['questionid' => $questionid]));
        }

        $reloadedoriginal = question_bank::load_question_data($original->id);
        $this->assertSame($originalchildids, $this->assert_composite_integrity($reloadedoriginal));
        $this->assertSame(self::SOURCES[3], $reloadedoriginal->options->questions[2]->questiontext);
        $this->assertSame(self::XLEFT[0], (int) $reloadedoriginal->options->positions[1]->xleft);
        $this->assertSame(self::ANCHORS[3], (int) $reloadedoriginal->options->positions[4]->anchor);
        $this->assertSame(1234, (int) $reloadedoriginal->options->displaywidth);
        $this->assertStringContainsString('Text after the image version.', $reloadedoriginal->options->aftertext);

        foreach ([$original->id, $newversion->id] as $questionid) {
            $this->assert_plugin_file(
                $context->id,
                'bgimage',
                $questionid,
                'background-version.png',
                'background-version'
            );
            $this->assert_plugin_file(
                $context->id,
                'aftertext',
                $questionid,
                'after-version.txt',
                'aftertext-version'
            );
        }
    }

    /**
     * Physical deletion removes the composite and its files without affecting another question.
     */
    public function test_question_deletion_cleanup(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        [, $context, $category] = $this->create_question_bank_fixture();
        $target = $this->create_saved_question($category, $context, 'delete');
        $unrelated = $this->create_saved_question($category, $context, 'unrelated');
        $targetchildids = $this->assert_composite_integrity($target);
        $unrelatedchildids = $this->assert_composite_integrity($unrelated);
        $targetversion = $DB->get_record('question_versions', ['questionid' => $target->id], '*', MUST_EXIST);
        $targetchildversionrecords = $DB->get_records_list('question_versions', 'questionid', $targetchildids);
        $targetchildversions = [];
        foreach ($targetchildversionrecords as $targetchildversion) {
            $targetchildversions[(int) $targetchildversion->questionid] = $targetchildversion;
        }
        $this->assertCount(3, $targetchildversions);
        $unrelatedversion = $DB->get_record('question_versions', ['questionid' => $unrelated->id], '*', MUST_EXIST);
        $nonexistentid = (int) $DB->get_field_sql('SELECT MAX(id) FROM {question}') + 1000;
        $this->assertFalse($DB->record_exists('question', ['id' => $nonexistentid]));
        $DB->set_field(
            'qtype_clozeonimage',
            'sequence',
            $target->options->sequence . ',' . $unrelated->id . ',' . $nonexistentid,
            ['questionid' => $target->id]
        );

        question_delete_question($target->id);

        $this->assertFalse($DB->record_exists('question', ['id' => $target->id]));
        $this->assertFalse($DB->record_exists('question_versions', ['id' => $targetversion->id]));
        $this->assertFalse($DB->record_exists('question_bank_entries', [
            'id' => $targetversion->questionbankentryid,
        ]));
        $this->assertFalse($DB->record_exists('qtype_clozeonimage', ['questionid' => $target->id]));
        $this->assertFalse($DB->record_exists('qtype_clozeonimage_pos', ['questionid' => $target->id]));
        foreach ($targetchildids as $targetchildid) {
            $this->assertFalse($DB->record_exists('question', ['id' => $targetchildid]));
            $targetchildversion = $targetchildversions[$targetchildid];
            $this->assertFalse($DB->record_exists('question_versions', ['id' => $targetchildversion->id]));
            $this->assertFalse($DB->record_exists('question_bank_entries', [
                'id' => $targetchildversion->questionbankentryid,
            ]));
        }
        $this->assertEmpty(get_file_storage()->get_area_files(
            $context->id,
            'qtype_clozeonimage',
            'bgimage',
            $target->id,
            'id',
            false
        ));
        $this->assertEmpty(get_file_storage()->get_area_files(
            $context->id,
            'qtype_clozeonimage',
            'aftertext',
            $target->id,
            'id',
            false
        ));
        $this->assertSame(0, $DB->count_records('files', [
            'contextid' => $context->id,
            'component' => 'qtype_clozeonimage',
            'itemid' => $target->id,
        ]));

        $reloadedunrelated = question_bank::load_question_data($unrelated->id);
        $this->assertSame($unrelatedchildids, $this->assert_composite_integrity($reloadedunrelated));
        $this->assertSame(
            $unrelatedversion->status,
            $DB->get_field('question_versions', 'status', ['id' => $unrelatedversion->id], MUST_EXIST)
        );
        $this->assertSame(1, $DB->count_records('qtype_clozeonimage', ['questionid' => $unrelated->id]));
        $this->assertSame(3, $DB->count_records('qtype_clozeonimage_pos', ['questionid' => $unrelated->id]));
        $this->assert_plugin_file(
            $context->id,
            'bgimage',
            $unrelated->id,
            'background-unrelated.png',
            'background-unrelated'
        );
        $this->assert_plugin_file(
            $context->id,
            'aftertext',
            $unrelated->id,
            'after-unrelated.txt',
            'aftertext-unrelated'
        );
    }
}
