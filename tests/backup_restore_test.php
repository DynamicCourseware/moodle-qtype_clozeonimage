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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/quiz/tests/classes/question_helper_test_trait.php');

/**
 * Tests for Cloze on Image backup and restore.
 *
 * @package    qtype_clozeonimage
 * @copyright  2026 DynamicCourseware.org (Dominique Bauer)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\backup_qtype_clozeonimage_plugin::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\restore_qtype_clozeonimage_plugin::class)]
final class backup_restore_test extends \advanced_testcase {
    use \mod_quiz\tests\question_helper_test_trait;

    /**
     * Control appearance values to verify through backup and restore.
     *
     * @return array<string, array{int}>
     */
    public static function control_appearance_provider(): array {
        return [
            'translucent' => [0],
            'opaque' => [1],
        ];
    }

    /**
     * Back up and restore a complete question through a quiz activity.
     *
     * @param int $controlappearance Control appearance value to preserve.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('control_appearance_provider')]
    public function test_backup_and_restore_question(int $controlappearance): void {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $questiongenerator = $generator->get_plugin_generator('core_question');
        $sourcecourse = $generator->create_course();
        $quiz = $generator->get_plugin_generator('mod_quiz')->create_instance(['course' => $sourcecourse->id]);
        $category = $questiongenerator->create_question_category([
            'contextid' => \context_module::instance($quiz->cmid)->id,
        ]);

        // Core Multianswer's fixture provides a real composite parent and two wrapped children.
        $parent = $questiongenerator->create_question('multianswer', 'twosubq', ['category' => $category->id]);
        $multianswer = $DB->get_record('question_multianswer', ['question' => $parent->id], '*', MUST_EXIST);
        $childids = array_values(array_filter(array_map('intval', explode(',', $multianswer->sequence))));

        // Add a third, differently typed wrapped question to exercise dense sequence ordering.
        $numerical = $questiongenerator->create_question('numerical', null, ['category' => $category->id]);
        $DB->set_field('question', 'parent', $parent->id, ['id' => $numerical->id]);
        $childids[] = $numerical->id;

        $DB->delete_records('question_multianswer', ['question' => $parent->id]);
        $DB->set_field('question', 'qtype', 'clozeonimage', ['id' => $parent->id]);

        $aftertext = '<p>Restored text <img src="@@PLUGINFILE@@/after.txt" alt=""></p>';
        $DB->insert_record('qtype_clozeonimage', (object) [
            'questionid' => $parent->id,
            'sequence' => implode(',', $childids),
            'displaymode' => 1,
            'displaywidth' => 1234,
            'controlappearance' => $controlappearance,
            'aftertext' => $aftertext,
            'aftertextformat' => FORMAT_HTML,
        ]);

        $positionvalues = [
            [1, -20, -30, 0],
            [4, 1800, 2500, 4],
            [7, 75, 95, 8],
        ];
        foreach ($positionvalues as [$no, $xleft, $ytop, $anchor]) {
            $DB->insert_record('qtype_clozeonimage_pos', (object) [
                'questionid' => $parent->id,
                'no' => $no,
                'xleft' => $xleft,
                'ytop' => $ytop,
                'anchor' => $anchor,
            ]);
        }

        $sourcecontext = \context_module::instance($quiz->cmid);
        $filestorage = get_file_storage();
        $filestorage->create_file_from_string((object) [
            'contextid' => $sourcecontext->id,
            'component' => 'qtype_clozeonimage',
            'filearea' => 'bgimage',
            'itemid' => $parent->id,
            'filepath' => '/',
            'filename' => 'background.png',
        ], 'not-a-real-png');
        $filestorage->create_file_from_string((object) [
            'contextid' => $sourcecontext->id,
            'component' => 'qtype_clozeonimage',
            'filearea' => 'aftertext',
            'itemid' => $parent->id,
            'filepath' => '/',
            'filename' => 'after.txt',
        ], 'embedded file');

        quiz_add_quiz_question($parent->id, $quiz);

        // Activity duplication is a real backup/restore round trip which deliberately creates new question IDs.
        \core_courseformat\formatactions::cm($sourcecourse)->duplicate($quiz->cmid);

        $restoredquizzes = get_fast_modinfo($sourcecourse)->get_instances_of('quiz');
        $this->assertCount(2, $restoredquizzes);
        foreach ($restoredquizzes as $candidate) {
            if ($candidate->instance !== $quiz->id) {
                $restoredquiz = $candidate;
                break;
            }
        }
        $this->assertNotEmpty($restoredquiz);
        $structure = \mod_quiz\question\bank\qbank_helper::get_question_structure(
            $restoredquiz->instance,
            $restoredquiz->context,
        );
        $restoredparentid = $structure[1]->questionid;
        $this->assertNotEquals($parent->id, $restoredparentid);

        $restoredoptions = $DB->get_record(
            'qtype_clozeonimage',
            ['questionid' => $restoredparentid],
            '*',
            MUST_EXIST
        );
        $this->assertSame(1, (int) $restoredoptions->displaymode);
        $this->assertSame(1234, (int) $restoredoptions->displaywidth);
        $this->assertSame($controlappearance, (int) $restoredoptions->controlappearance);
        $this->assertSame($aftertext, $restoredoptions->aftertext);
        $this->assertSame((int) FORMAT_HTML, (int) $restoredoptions->aftertextformat);

        $restoredchildids = array_values(array_filter(array_map(
            'intval',
            explode(',', $restoredoptions->sequence)
        )));
        $this->assertCount(3, $restoredchildids);
        $this->assertEmpty(array_intersect($childids, $restoredchildids));
        $restoredchildren = $DB->get_records_list(
            'question',
            'id',
            $restoredchildids,
            '',
            'id, qtype, parent'
        );
        $restoredqtypes = array_map(
            static fn($id) => $restoredchildren[$id]->qtype,
            $restoredchildids
        );
        $this->assertSame(['shortanswer', 'multichoice', 'numerical'], $restoredqtypes);
        foreach ($restoredchildids as $restoredchildid) {
            $this->assertSame(
                (int) $restoredparentid,
                (int) $DB->get_field('question', 'parent', ['id' => $restoredchildid])
            );
        }

        $restoredpositions = array_values($DB->get_records(
            'qtype_clozeonimage_pos',
            ['questionid' => $restoredparentid],
            'no ASC'
        ));
        $this->assertCount(3, $restoredpositions);
        foreach ($restoredpositions as $index => $position) {
            [$no, $xleft, $ytop, $anchor] = $positionvalues[$index];
            $this->assertSame($no, (int) $position->no);
            $this->assertSame($xleft, (int) $position->xleft);
            $this->assertSame($ytop, (int) $position->ytop);
            $this->assertSame($anchor, (int) $position->anchor);
        }

        $restoredcontext = \context_module::instance($restoredquiz->id);
        foreach ([['bgimage', 'background.png'], ['aftertext', 'after.txt']] as [$filearea, $filename]) {
            $this->assertNotFalse($filestorage->get_file(
                $restoredcontext->id,
                'qtype_clozeonimage',
                $filearea,
                $restoredparentid,
                '/',
                $filename,
            ));
        }
    }

    /**
     * Restoring a quiz that references an unchanged question-bank question reuses that question.
     */
    public function test_restore_reuses_identical_question(): void {
        global $CFG, $DB, $USER;

        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $questiongenerator = $generator->get_plugin_generator('core_question');
        $course = $generator->create_course();
        $qbank = $generator->get_plugin_generator('mod_qbank')->create_instance(['course' => $course->id]);
        $context = \context_module::instance($qbank->cmid);
        $category = $questiongenerator->create_question_category(['contextid' => $context->id]);
        $quiz = $generator->get_plugin_generator('mod_quiz')->create_instance(['course' => $course->id]);

        $parent = $questiongenerator->create_question('multianswer', 'twosubq', ['category' => $category->id]);
        $multianswer = $DB->get_record('question_multianswer', ['question' => $parent->id], '*', MUST_EXIST);
        $DB->delete_records('question_multianswer', ['question' => $parent->id]);
        $DB->set_field('question', 'qtype', 'clozeonimage', ['id' => $parent->id]);
        $DB->insert_record('qtype_clozeonimage', (object) [
            'questionid' => $parent->id,
            'sequence' => $multianswer->sequence,
            'displaymode' => 0,
            'displaywidth' => 640,
            'controlappearance' => 0,
            'aftertext' => '<p>Identity-preserved text</p>',
            'aftertextformat' => FORMAT_HTML,
        ]);
        $expectedpositions = [
            [1, -15, 20, 0],
            [4, 900, -40, 8],
        ];
        foreach ($expectedpositions as [$no, $xleft, $ytop, $anchor]) {
            $DB->insert_record('qtype_clozeonimage_pos', (object) [
                'questionid' => $parent->id,
                'no' => $no,
                'xleft' => $xleft,
                'ytop' => $ytop,
                'anchor' => $anchor,
            ]);
        }
        $filestorage = get_file_storage();
        foreach ([['bgimage', 'identity.png'], ['aftertext', 'identity.txt']] as [$filearea, $filename]) {
            $filestorage->create_file_from_string((object) [
                'contextid' => $context->id,
                'component' => 'qtype_clozeonimage',
                'filearea' => $filearea,
                'itemid' => $parent->id,
                'filepath' => '/',
                'filename' => $filename,
            ], $filename);
        }
        quiz_add_quiz_question($parent->id, $quiz);

        $initialquestioncount = $DB->count_records('question');
        $initialoptioncount = $DB->count_records('qtype_clozeonimage');
        $initialpositioncount = $DB->count_records('qtype_clozeonimage_pos');

        $backupcontroller = new \backup_controller(
            \backup::TYPE_1ACTIVITY,
            $quiz->cmid,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_IMPORT,
            $USER->id,
        );
        $backupid = $backupcontroller->get_backupid();
        $backupcontroller->execute_plan();
        $backupcontroller->destroy();

        $restorecontroller = new \restore_controller(
            $backupid,
            $course->id,
            \backup::INTERACTIVE_NO,
            \backup::MODE_IMPORT,
            $USER->id,
            \backup::TARGET_CURRENT_ADDING,
        );
        $this->assertTrue($restorecontroller->execute_precheck());
        $restorecontroller->execute_plan();
        $restorecontroller->destroy();

        $quizzes = get_fast_modinfo($course)->get_instances_of('quiz');
        $this->assertCount(2, $quizzes);
        foreach ($quizzes as $restoredquiz) {
            $structure = \mod_quiz\question\bank\qbank_helper::get_question_structure(
                $restoredquiz->instance,
                $restoredquiz->context,
            );
            $this->assertSame((int) $parent->id, (int) $structure[1]->questionid);
        }

        $this->assertSame($initialquestioncount, $DB->count_records('question'));
        $this->assertSame($initialoptioncount, $DB->count_records('qtype_clozeonimage'));
        $this->assertSame($initialpositioncount, $DB->count_records('qtype_clozeonimage_pos'));
        $this->assertSame(1, $DB->count_records('qtype_clozeonimage', ['questionid' => $parent->id]));

        $options = $DB->get_record('qtype_clozeonimage', ['questionid' => $parent->id], '*', MUST_EXIST);
        $this->assertSame($multianswer->sequence, $options->sequence);
        $this->assertSame(640, (int) $options->displaywidth);
        $this->assertSame(0, (int) $options->controlappearance);
        $positions = array_values($DB->get_records(
            'qtype_clozeonimage_pos',
            ['questionid' => $parent->id],
            'no ASC'
        ));
        $this->assertCount(2, $positions);
        foreach ($positions as $index => $position) {
            [$no, $xleft, $ytop, $anchor] = $expectedpositions[$index];
            $this->assertSame([$no, $xleft, $ytop, $anchor], [
                (int) $position->no,
                (int) $position->xleft,
                (int) $position->ytop,
                (int) $position->anchor,
            ]);
        }
        foreach ([['bgimage', 'identity.png'], ['aftertext', 'identity.txt']] as [$filearea, $filename]) {
            $this->assertNotFalse($filestorage->get_file(
                $context->id,
                'qtype_clozeonimage',
                $filearea,
                $parent->id,
                '/',
                $filename,
            ));
        }
    }

    /**
     * Restoring an attempted quiz recodes wrapped response data against the restored children.
     */
    public function test_restore_recodes_wrapped_question_response(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $questiongenerator = $generator->get_plugin_generator('core_question');
        $quizgenerator = $generator->get_plugin_generator('mod_quiz');
        $course = $generator->create_course();
        $student = $generator->create_and_enrol($course, 'student');
        $quiz = $quizgenerator->create_instance(['course' => $course->id]);
        $quizcontext = \context_module::instance($quiz->cmid);
        $category = $questiongenerator->create_question_category(['contextid' => $quizcontext->id]);

        $parent = $questiongenerator->create_question('multianswer', 'twosubq', ['category' => $category->id]);
        $multianswer = $DB->get_record('question_multianswer', ['question' => $parent->id], '*', MUST_EXIST);
        $oldchildids = array_values(array_filter(array_map('intval', explode(',', $multianswer->sequence))));
        $DB->delete_records('question_multianswer', ['question' => $parent->id]);
        $DB->set_field('question', 'qtype', 'clozeonimage', ['id' => $parent->id]);
        $DB->insert_record('qtype_clozeonimage', (object) [
            'questionid' => $parent->id,
            'sequence' => $multianswer->sequence,
            'displaymode' => 0,
            'displaywidth' => 640,
            'aftertext' => '',
            'aftertextformat' => FORMAT_HTML,
        ]);
        foreach ([1, 2] as $no) {
            $DB->insert_record('qtype_clozeonimage_pos', (object) [
                'questionid' => $parent->id,
                'no' => $no,
                'xleft' => 20 * $no,
                'ytop' => 30 * $no,
                'anchor' => 4,
            ]);
        }
        quiz_add_quiz_question($parent->id, $quiz, 0, 1.0);
        \mod_quiz\quiz_settings::create($quiz->id)->get_grade_calculator()->recompute_quiz_sumgrades();

        $this->setUser($student);
        $attempt = $quizgenerator->create_attempt($quiz->id, $student->id);
        $attemptobject = \mod_quiz\quiz_attempt::create($attempt->id);
        $questionattempt = $attemptobject->get_question_attempt(1);
        $attemptquestion = $questionattempt->get_question();
        $multichoicecorrect = $attemptquestion->subquestions[2]->get_correct_response();
        $oldresponseid = (int) reset($multichoicecorrect);
        $attemptobject->process_submitted_actions(time(), false, [
            $questionattempt->get_control_field_name('sequencecheck') =>
                (string) $questionattempt->get_sequence_check_count(),
            $questionattempt->get_flag_field_name() => (string) (int) $questionattempt->is_flagged(),
            $questionattempt->get_qt_field_name('sub1_answer') => 'Owl',
            $questionattempt->get_qt_field_name('sub2_answer') => (string) $oldresponseid,
        ]);
        $attemptobject->process_submit(time(), false);
        $attemptobject->process_grade_submission(time());
        $sourcefraction = $questionattempt->get_fraction();
        $sourceresponse = $questionattempt->get_last_qt_data();
        $this->assertNotNull($sourcefraction);
        $this->assertEquals(1.0, $sourcefraction);
        $this->assertSame('Owl', $sourceresponse['sub1_answer']);
        $this->assertSame((string) $oldresponseid, $sourceresponse['sub2_answer']);
        $this->setAdminUser();

        $backupid = $this->backup_quiz($quiz, get_admin());
        delete_course($course, false);
        $restoredcourse = $generator->create_course();
        $this->restore_quiz($backupid, $restoredcourse, get_admin());

        $modules = get_fast_modinfo($restoredcourse)->get_instances_of('quiz');
        $restoredmodule = reset($modules);
        $restoredattempts = quiz_get_user_attempts($restoredmodule->instance, $student->id);
        $this->assertCount(1, $restoredattempts);
        $restoredattempt = \mod_quiz\quiz_attempt::create(reset($restoredattempts)->id);
        $restoredquestionattempt = $restoredattempt->get_question_attempt(1);
        $restoredquestion = $restoredquestionattempt->get_question();
        $newchildids = array_map(
            static fn($subquestion) => $subquestion->id,
            $restoredquestion->subquestions
        );
        $this->assertEmpty(array_intersect($oldchildids, $newchildids));

        $restoredresponse = $restoredquestionattempt->get_last_qt_data();
        $newcorrectresponse = $restoredquestion->subquestions[2]->get_correct_response();
        $this->assertSame((string) reset($newcorrectresponse), $restoredresponse['sub2_answer']);
        $this->assertArrayHasKey('sub1_answer', $restoredresponse);
        $this->assertArrayHasKey('sub2_answer', $restoredresponse);
        $this->assertArrayNotHasKey('sub4_answer', $restoredresponse);
        $this->assertEquals($sourcefraction, $restoredquestionattempt->get_fraction());
    }
}
