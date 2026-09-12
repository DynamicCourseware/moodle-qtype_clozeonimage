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
require_once($CFG->dirroot . '/question/type/clozeonimage/questiontype.php');

/**
 * Tests for Cloze on Image rendering.
 *
 * @package    qtype_clozeonimage
 * @copyright  2026 DynamicCourseware.org (Dominique Bauer)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\qtype_clozeonimage_renderer::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\qtype_clozeonimage_textfield_renderer::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\qtype_clozeonimage_multichoice_inline_renderer::class)]
final class renderer_test extends \qbehaviour_walkthrough_test_base {
    /**
     * Make a renderable question with a real background-image file.
     */
    private function make_question(
        int $controlappearance = \qtype_clozeonimage::CONTROL_APPEARANCE_TRANSLUCENT,
        string $subquestiontype = 'shortanswer',
        int $questionid = 4242
    ): \qtype_clozeonimage_question {
        $question = new \qtype_clozeonimage_question();
        \test_question_maker::initialise_a_question($question);
        $question->id = $questionid;
        $question->contextid = \context_system::instance()->id;
        $question->name = 'Background alternative test';
        $question->questiontext = 'Question text before the image';
        $question->generalfeedback = '';
        $question->qtype = \question_bank::get_qtype('clozeonimage');
        if (str_starts_with($subquestiontype, 'multichoice')) {
            $subquestion = \test_question_maker::make_a_multichoice_single_question();
            $subquestion->layout = $subquestiontype === 'multichoice_integer'
                ? \qtype_multichoice_base::LAYOUT_DROPDOWN
                : '0';
            $subquestion->shuffleanswers = $subquestiontype === 'multichoice_s';
        } else {
            $subquestion = \test_question_maker::make_question('shortanswer');
        }
        $question->subquestions = [1 => $subquestion];
        $question->positions = [
            1 => (object) ['xleft' => 12, 'ytop' => 12, 'anchor' => 4],
        ];
        $question->displaywidth = 321;
        $question->controlappearance = $controlappearance;

        get_file_storage()->create_file_from_string((object) [
            'contextid' => $question->contextid,
            'component' => 'qtype_clozeonimage',
            'filearea' => 'bgimage',
            'itemid' => $question->id,
            'filepath' => '/',
            'filename' => 'private-background-filename.png',
        ], base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='
        ));

        return $question;
    }

    /**
     * Assert the background image retains its rendering attributes and localized alternative.
     */
    private function assert_background_image(string $html): void {
        $document = new \DOMDocument();
        @$document->loadHTML($html);
        $xpath = new \DOMXPath($document);
        $images = $xpath->query(
            '//img[contains(concat(" ", normalize-space(@class), " "), " qtype-clozeonimage-image ")]'
        );

        $this->assertCount(1, $images);
        $image = $images->item(0);
        $this->assertSame(get_string('backgroundimagealt', 'qtype_clozeonimage'), $image->getAttribute('alt'));
        $this->assertStringNotContainsString('private-background-filename.png', $image->getAttribute('alt'));
        $this->assertStringContainsString('private-background-filename.png', $image->getAttribute('src'));
        $this->assertSame('qtype-clozeonimage-image', $image->getAttribute('class'));
        $this->assertSame('max-width:none;height:auto;width:321px;', $image->getAttribute('style'));
    }

    public function test_attempt_and_review_use_localized_background_alternative(): void {
        $this->start_attempt_at_question($this->make_question(), 'deferredfeedback', 1);

        $this->render();
        $this->assert_background_image($this->currentoutput);

        $this->displayoptions->readonly = true;
        $this->render();
        $this->assert_background_image($this->currentoutput);
    }

    /**
     * Runtime appearance classes.
     *
     * @return array<string, array{int,string}>
     */
    public static function control_appearance_provider(): array {
        return [
            'translucent' => [
                \qtype_clozeonimage::CONTROL_APPEARANCE_TRANSLUCENT,
                'qtype-clozeonimage-appearance-translucent',
            ],
            'opaque' => [
                \qtype_clozeonimage::CONTROL_APPEARANCE_OPAQUE,
                'qtype-clozeonimage-appearance-opaque',
            ],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('control_appearance_provider')]
    public function test_runtime_control_appearance_class(int $controlappearance, string $class): void {
        $this->start_attempt_at_question($this->make_question($controlappearance), 'deferredfeedback', 1);

        $this->render();

        $this->assertStringContainsString(
            'class="qtype-clozeonimage-composition ' . $class . '"',
            $this->currentoutput
        );
    }

    /**
     * Appearance classes used by feedback popover triggers.
     *
     * @return array<string, array{int,string,string}>
     */
    public static function feedback_popover_appearance_provider(): array {
        return [
            'translucent text field' => [
                \qtype_clozeonimage::CONTROL_APPEARANCE_TRANSLUCENT,
                'shortanswer',
                'qtype-clozeonimage-appearance-translucent',
            ],
            'opaque text field' => [
                \qtype_clozeonimage::CONTROL_APPEARANCE_OPAQUE,
                'shortanswer',
                'qtype-clozeonimage-appearance-opaque',
            ],
            'translucent dropdown' => [
                \qtype_clozeonimage::CONTROL_APPEARANCE_TRANSLUCENT,
                'multichoice',
                'qtype-clozeonimage-appearance-translucent',
            ],
            'opaque dropdown' => [
                \qtype_clozeonimage::CONTROL_APPEARANCE_OPAQUE,
                'multichoice',
                'qtype-clozeonimage-appearance-opaque',
            ],
            'translucent shuffled dropdown' => [
                \qtype_clozeonimage::CONTROL_APPEARANCE_TRANSLUCENT,
                'multichoice_s',
                'qtype-clozeonimage-appearance-translucent',
            ],
            'opaque shuffled dropdown' => [
                \qtype_clozeonimage::CONTROL_APPEARANCE_OPAQUE,
                'multichoice_s',
                'qtype-clozeonimage-appearance-opaque',
            ],
            'translucent in-memory integer dropdown' => [
                \qtype_clozeonimage::CONTROL_APPEARANCE_TRANSLUCENT,
                'multichoice_integer',
                'qtype-clozeonimage-appearance-translucent',
            ],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('feedback_popover_appearance_provider')]
    public function test_feedback_trigger_uses_appearance_specific_popover_class(
        int $controlappearance,
        string $subquestiontype,
        string $expectedclass
    ): void {
        $this->start_attempt_at_question(
            $this->make_question($controlappearance, $subquestiontype),
            'deferredfeedback',
            1
        );
        $this->process_submission([
            'sub1_answer' => str_starts_with($subquestiontype, 'multichoice') ? '0' : 'wrong',
        ]);
        $this->finish();
        $this->displayoptions->correctness = true;
        $this->displayoptions->feedback = true;

        $this->render();

        $this->assertStringContainsString(
            'data-bs-custom-class="' . $expectedclass . '"',
            $this->currentoutput
        );
        $unexpectedclass = $controlappearance === \qtype_clozeonimage::CONTROL_APPEARANCE_OPAQUE
            ? 'qtype-clozeonimage-appearance-translucent'
            : 'qtype-clozeonimage-appearance-opaque';
        $this->assertStringNotContainsString(
            'data-bs-custom-class="' . $unexpectedclass . '"',
            $this->currentoutput
        );
    }

    public function test_mixed_appearance_feedback_triggers_remain_independent(): void {
        $this->quba->set_preferred_behaviour('deferredfeedback');
        $translucentslot = $this->quba->add_question($this->make_question(
            \qtype_clozeonimage::CONTROL_APPEARANCE_TRANSLUCENT,
            'shortanswer',
            4242
        ));
        $opaqueslot = $this->quba->add_question($this->make_question(
            \qtype_clozeonimage::CONTROL_APPEARANCE_OPAQUE,
            'multichoice',
            4243
        ));
        $this->quba->start_all_questions();

        $this->slot = $translucentslot;
        $this->process_submission(['sub1_answer' => 'wrong']);
        $this->slot = $opaqueslot;
        $this->process_submission(['sub1_answer' => '0']);
        $this->finish();
        $this->displayoptions->correctness = true;
        $this->displayoptions->feedback = true;

        $translucentoutput = $this->quba->render_question($translucentslot, $this->displayoptions);
        $opaqueoutput = $this->quba->render_question($opaqueslot, $this->displayoptions);

        $this->assertStringContainsString(
            'data-bs-custom-class="qtype-clozeonimage-appearance-translucent"',
            $translucentoutput
        );
        $this->assertStringNotContainsString(
            'data-bs-custom-class="qtype-clozeonimage-appearance-opaque"',
            $translucentoutput
        );
        $this->assertStringContainsString(
            'data-bs-custom-class="qtype-clozeonimage-appearance-opaque"',
            $opaqueoutput
        );
        $this->assertStringNotContainsString(
            'data-bs-custom-class="qtype-clozeonimage-appearance-translucent"',
            $opaqueoutput
        );
    }
}
