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
