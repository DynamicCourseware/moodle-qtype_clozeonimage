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
require_once($CFG->dirroot . '/question/engine/tests/helpers.php');
require_once($CFG->dirroot . '/question/type/clozeonimage/question.php');

/**
 * Tests for clozeonimage response validation.
 *
 * @package    qtype_clozeonimage
 * @copyright  2026 DynamicCourseware.org (Dominique Bauer)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\qtype_clozeonimage_question::class)]
final class question_test extends \advanced_testcase {
    /**
     * Make a question containing two Numerical fields and one Short Answer field.
     */
    private function make_question(): \qtype_clozeonimage_question {
        $question = new \qtype_clozeonimage_question();
        \test_question_maker::initialise_a_question($question);
        $question->subquestions = [
            1 => \test_question_maker::make_question('numerical'),
            2 => \test_question_maker::make_question('numerical'),
            3 => \test_question_maker::make_question('shortanswer'),
        ];
        return $question;
    }

    /**
     * Make a question containing the choice layouts relevant to clearing wrong responses.
     */
    private function make_clearwrong_question(): \qtype_clozeonimage_question {
        $question = new \qtype_clozeonimage_question();
        \test_question_maker::initialise_a_question($question);

        $correct = \test_question_maker::make_a_multichoice_single_question();
        $correct->layout = (string) \qtype_multichoice_base::LAYOUT_VERTICAL;
        $correct->shuffleanswers = false;
        $correct->answers[13]->fraction = -0.3333333;
        $correct->answers[15]->fraction = 1;

        $partial = \test_question_maker::make_a_multichoice_single_question();
        $partial->layout = (string) \qtype_multichoice_base::LAYOUT_HORIZONTAL;
        $partial->shuffleanswers = false;
        $partial->answers[14]->fraction = 0.5;

        $wrong = \test_question_maker::make_a_multichoice_single_question();
        $wrong->layout = (string) \qtype_multichoice_base::LAYOUT_VERTICAL;
        $wrong->shuffleanswers = false;

        $dropdown = \test_question_maker::make_a_multichoice_single_question();
        $dropdown->layout = (string) \qtype_multichoice_base::LAYOUT_DROPDOWN;
        $dropdown->shuffleanswers = false;

        $multiresponse = \test_question_maker::make_a_multichoice_multi_question();
        $multiresponse->layout = (string) \qtype_multichoice_base::LAYOUT_VERTICAL;
        $multiresponse->shuffleanswers = false;

        $question->subquestions = [
            1 => $correct,
            2 => $partial,
            3 => $wrong,
            4 => $dropdown,
            5 => $multiresponse,
        ];
        $question->start_attempt(new \question_attempt_step(), 1);

        return $question;
    }

    public function test_clear_wrong_preserves_non_dropdown_multichoice_unanswered_value(): void {
        $question = $this->make_clearwrong_question();

        $cleared = $question->clear_wrong_from_response([
            'sub1_answer' => '2',
            'sub2_answer' => '1',
            'sub3_answer' => '2',
        ]);

        $this->assertSame('2', $cleared['sub1_answer']);
        $this->assertSame('-1', $cleared['sub2_answer']);
        $this->assertSame('-1', $cleared['sub3_answer']);
    }

    public function test_clear_wrong_leaves_dropdown_and_multiresponse_on_parent_path(): void {
        $question = $this->make_clearwrong_question();

        $cleared = $question->clear_wrong_from_response([
            'sub4_answer' => '1',
            'sub5_choice0' => '1',
            'sub5_choice1' => '1',
        ]);

        $this->assertSame('', $cleared['sub4_answer']);
        $this->assertSame('', $cleared['sub5_choice0']);
        $this->assertSame('', $cleared['sub5_choice1']);
        $this->assertNotContains('-1', $cleared);
    }

    public function test_one_invalid_numerical(): void {
        $messages = $this->make_question()->get_validation_messages([
            'sub1_answer' => 'abc',
            'sub2_answer' => '3.14',
            'sub3_answer' => 'answered',
        ]);

        $this->assertSame([get_string('invalidnumericalresponses', 'qtype_clozeonimage')], $messages);
    }

    public function test_two_invalid_numericals(): void {
        $messages = $this->make_question()->get_validation_messages([
            'sub1_answer' => 'abc',
            'sub2_answer' => 'def',
            'sub3_answer' => 'answered',
        ]);

        $this->assertSame([get_string('invalidnumericalresponses', 'qtype_clozeonimage')], $messages);
    }

    public function test_invalid_numerical_and_blank_shortanswer(): void {
        $messages = $this->make_question()->get_validation_messages([
            'sub1_answer' => 'abc',
            'sub2_answer' => '3.14',
        ]);

        $this->assertSame([
            get_string('invalidnumericalresponses', 'qtype_clozeonimage'),
            get_string('pleaseananswerallparts', 'qtype_multianswer'),
        ], $messages);
    }

    public function test_blank_numerical_only(): void {
        $messages = $this->make_question()->get_validation_messages([
            'sub2_answer' => '3.14',
            'sub3_answer' => 'answered',
        ]);

        $this->assertSame([get_string('pleaseananswerallparts', 'qtype_multianswer')], $messages);
    }

    public function test_complete_valid_response(): void {
        $messages = $this->make_question()->get_validation_messages([
            'sub1_answer' => '3.14',
            'sub2_answer' => '3.14',
            'sub3_answer' => 'answered',
        ]);

        $this->assertSame([], $messages);
    }
}
