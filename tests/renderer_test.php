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
#[\PHPUnit\Framework\Attributes\CoversClass(\qtype_clozeonimage_multichoice_renderer::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\qtype_clozeonimage_multiresponse_renderer::class)]
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
        if (str_starts_with($subquestiontype, 'multiresponse')) {
            $subquestion = \test_question_maker::make_a_multichoice_multi_question();
            $subquestion->layout = str_ends_with($subquestiontype, 'horizontal')
                ? (string) \qtype_multichoice_base::LAYOUT_HORIZONTAL
                : (string) \qtype_multichoice_base::LAYOUT_VERTICAL;
            $subquestion->shuffleanswers = str_contains($subquestiontype, 'shuffled');
        } else if (str_starts_with($subquestiontype, 'multichoice')) {
            $subquestion = \test_question_maker::make_a_multichoice_single_question();
            if (str_contains($subquestiontype, 'horizontal')) {
                $subquestion->layout = (string) \qtype_multichoice_base::LAYOUT_HORIZONTAL;
            } else if (str_contains($subquestiontype, 'vertical')) {
                $subquestion->layout = str_contains($subquestiontype, 'integer')
                    ? \qtype_multichoice_base::LAYOUT_VERTICAL
                    : (string) \qtype_multichoice_base::LAYOUT_VERTICAL;
            } else {
                $subquestion->layout = $subquestiontype === 'multichoice_integer'
                    ? \qtype_multichoice_base::LAYOUT_DROPDOWN
                    : '0';
            }
            $subquestion->shuffleanswers = $subquestiontype === 'multichoice_s' ||
                str_contains($subquestiontype, 'shuffled');
        } else if ($subquestiontype === 'numerical') {
            $subquestion = \test_question_maker::make_question('numerical');
        } else {
            $subquestion = \test_question_maker::make_question('shortanswer');
            $subquestion->usecase = $subquestiontype === 'shortanswer_case_sensitive';
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
     * Parse rendered output and return an XPath helper.
     *
     * @param string $html Rendered question HTML.
     * @return \DOMXPath XPath helper for the rendered document.
     */
    private function xpath(string $html): \DOMXPath {
        $document = new \DOMDocument();
        @$document->loadHTML($html);
        return new \DOMXPath($document);
    }

    /**
     * Render a finished question with all relevant review information enabled.
     *
     * @param string $subquestiontype Subquestion test-fixture type.
     * @param array<string, int|string> $response Submitted response.
     * @return string Rendered review HTML.
     */
    private function render_review(string $subquestiontype, array $response): string {
        $this->start_attempt_at_question(
            $this->make_question(\qtype_clozeonimage::CONTROL_APPEARANCE_TRANSLUCENT, $subquestiontype),
            'deferredfeedback',
            1
        );
        if ($response) {
            $this->process_submission($response);
        }
        $this->finish();
        $this->displayoptions->correctness = true;
        $this->displayoptions->feedback = true;
        $this->displayoptions->rightanswer = true;
        $this->displayoptions->marks = \question_display_options::MARK_AND_MAX;

        $this->render();
        return $this->currentoutput;
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
            'translucent numerical field' => [
                \qtype_clozeonimage::CONTROL_APPEARANCE_TRANSLUCENT,
                'numerical',
                'qtype-clozeonimage-appearance-translucent',
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

    /**
     * Answer controls that must retain their native attempt-mode keyboard semantics.
     *
     * @return array<string, array{string,string,int}>
     */
    public static function attempt_control_provider(): array {
        return [
            'Short Answer' => ['shortanswer', '//input[@type="text"]', 1],
            'Numerical' => ['numerical', '//input[@type="text"]', 1],
            'dropdown Multichoice' => ['multichoice', '//select', 1],
            'vertical Multichoice' => ['multichoice_vertical', '//input[@type="radio"]', 3],
            'horizontal Multichoice' => ['multichoice_horizontal', '//input[@type="radio"]', 3],
            'vertical Multiple Response' => ['multiresponse_vertical', '//input[@type="checkbox"]', 4],
            'horizontal Multiple Response' => ['multiresponse_horizontal', '//input[@type="checkbox"]', 4],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('attempt_control_provider')]
    public function test_attempt_controls_retain_native_keyboard_semantics(
        string $subquestiontype,
        string $controlxpath,
        int $expectedcount
    ): void {
        $this->start_attempt_at_question(
            $this->make_question(\qtype_clozeonimage::CONTROL_APPEARANCE_TRANSLUCENT, $subquestiontype),
            'deferredfeedback',
            1
        );

        $this->render();
        $xpath = $this->xpath($this->currentoutput);
        $controls = $xpath->query($controlxpath);

        $this->assertCount($expectedcount, $controls);
        foreach ($controls as $control) {
            $this->assertFalse($control->hasAttribute('disabled'));
            $this->assertFalse($control->hasAttribute('readonly'));
            $this->assertNotSame('-1', $control->getAttribute('tabindex'));
        }
        $this->assertCount(0, $xpath->query('//*[@data-region="clozeonimage-feedback-trigger"]'));
    }

    /**
     * Text controls whose width must remain stable after grading.
     *
     * @return array<string, array{string,string}>
     */
    public static function stable_text_width_provider(): array {
        return [
            'Short Answer' => ['shortanswer', 'A deliberately long submitted response'],
            'case-sensitive Short Answer' => [
                'shortanswer_case_sensitive',
                'A deliberately long CASE-sensitive response',
            ],
            'Numerical' => ['numerical', '12345678901234567890'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('stable_text_width_provider')]
    public function test_text_control_size_does_not_change_after_grading(
        string $subquestiontype,
        string $response
    ): void {
        $this->start_attempt_at_question(
            $this->make_question(\qtype_clozeonimage::CONTROL_APPEARANCE_TRANSLUCENT, $subquestiontype),
            'deferredfeedback',
            1
        );
        $this->render();
        $attemptinputs = $this->xpath($this->currentoutput)->query('//input[@type="text"]');
        $this->assertCount(1, $attemptinputs);
        $attemptsize = $attemptinputs->item(0)->getAttribute('size');
        $this->assertNotSame('', $attemptsize);

        $this->process_submission(['sub1_answer' => $response]);
        $this->finish();
        $this->displayoptions->correctness = true;
        $this->displayoptions->feedback = true;
        $this->render();
        $reviewinputs = $this->xpath($this->currentoutput)->query('//input[@type="text"]');

        $this->assertCount(1, $reviewinputs);
        $this->assertSame($attemptsize, $reviewinputs->item(0)->getAttribute('size'));
    }

    /**
     * Review renderers for positioned choice controls.
     *
     * @return array<string, array{string,array<string,int>,string,int}>
     */
    public static function choice_review_provider(): array {
        return [
            'vertical Multichoice persisted layout' => [
                'multichoice_vertical',
                ['sub1_answer' => 1],
                'fieldset',
                1,
            ],
            'horizontal Multichoice persisted and shuffled layout' => [
                'multichoice_horizontal_shuffled',
                ['sub1_answer' => 1],
                'fieldset',
                1,
            ],
            'vertical Multichoice in-memory integer layout' => [
                'multichoice_vertical_integer',
                ['sub1_answer' => 1],
                'fieldset',
                1,
            ],
            'vertical Multiple Response persisted layout' => [
                'multiresponse_vertical',
                ['sub1_choice0' => 1, 'sub1_choice2' => 1],
                'div',
                2,
            ],
            'horizontal Multiple Response persisted layout' => [
                'multiresponse_horizontal',
                ['sub1_choice0' => 1, 'sub1_choice2' => 1],
                'table',
                2,
            ],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('choice_review_provider')]
    public function test_choice_review_feedback_has_accessible_shared_disclosure(
        string $subquestiontype,
        array $response,
        string $answertag,
        int $expectedtriggers
    ): void {
        $xpath = $this->xpath($this->render_review($subquestiontype, $response));
        $regions = $xpath->query('//div[@data-region="clozeonimage-choice-feedback"]');
        $this->assertCount(1, $regions);
        $region = $regions->item(0);
        $triggers = $xpath->query('.//button[@data-region="clozeonimage-feedback-trigger"]', $region);

        $this->assertCount($expectedtriggers, $triggers);
        $expectedchoices = str_starts_with($subquestiontype, 'multiresponse') ? 4 : 3;
        $this->assertCount(
            $expectedchoices,
            $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), ' .
                '" qtype-clozeonimage-choice-control ")]', $region)
        );
        $this->assertCount(
            $expectedtriggers,
            $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), ' .
                '" qtype-clozeonimage-choice-control ")]/' .
                'button[@data-region="clozeonimage-feedback-trigger"]', $region)
        );
        $inputtype = str_starts_with($subquestiontype, 'multiresponse') ? 'checkbox' : 'radio';
        $choiceinputs = $xpath->query('.//input[@type="' . $inputtype . '"]', $region);
        $this->assertNotCount(0, $choiceinputs);
        foreach ($choiceinputs as $choiceinput) {
            $this->assertTrue($choiceinput->hasAttribute('disabled'));
            $this->assertNotSame('-1', $choiceinput->getAttribute('tabindex'));
        }
        $controlledids = null;
        foreach ($triggers as $trigger) {
            $this->assertSame('button', $trigger->getAttribute('type'));
            $this->assertSame('false', $trigger->getAttribute('aria-expanded'));
            $this->assertFalse($trigger->hasAttribute('data-bs-toggle'));
            $this->assertFalse($trigger->hasAttribute('data-bs-trigger'));
            $this->assertStringContainsString(
                get_string('feedbackforsubquestion', 'qtype_clozeonimage', 1),
                $trigger->textContent
            );
            $this->assertNotSame('', $trigger->getAttribute('aria-controls'));
            if ($controlledids === null) {
                $controlledids = $trigger->getAttribute('aria-controls');
            } else {
                $this->assertSame($controlledids, $trigger->getAttribute('aria-controls'));
            }
        }

        foreach (explode(' ', $controlledids) as $controlledid) {
            $this->assertCount(1, $xpath->query('.//*[@id="' . $controlledid . '"]', $region));
        }
        $this->assertCount(
            $expectedtriggers,
            $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), ' .
                '" qtype-clozeonimage-choice-control ")]/following-sibling::*[' .
                'contains(concat(" ", normalize-space(@class), " "), ' .
                '" specificfeedback ")]', $region)
        );
        $this->assertCount(
            1,
            $xpath->query('./' . $answertag . '[contains(concat(" ", normalize-space(@class), " "), ' .
                '" answer ")]/following-sibling::*[contains(concat(" ", normalize-space(@class), " "), ' .
                '" outcome ")]', $region)
        );
        $this->assertCount(
            $subquestiontype === 'multiresponse_horizontal' ? 1 : 0,
            $xpath->query('.//table[contains(concat(" ", normalize-space(@class), " "), " answer ")]', $region)
        );
        $this->assertCount(
            0,
            $xpath->query('.//button[contains(concat(" ", normalize-space(@class), " "), ' .
                '" qtype-clozeonimage-choice-feedback-trigger-group ")]', $region)
        );
    }

    public function test_choice_feedback_uses_group_fallback_without_correctness_icon(): void {
        $this->start_attempt_at_question(
            $this->make_question(
                \qtype_clozeonimage::CONTROL_APPEARANCE_TRANSLUCENT,
                'multichoice_vertical'
            ),
            'deferredfeedback',
            1
        );
        $this->process_submission(['sub1_answer' => 1]);
        $this->finish();
        $this->displayoptions->correctness = false;
        $this->displayoptions->feedback = true;
        $this->displayoptions->rightanswer = false;
        $this->displayoptions->marks = \question_display_options::MARK_AND_MAX;

        $this->render();
        $xpath = $this->xpath($this->currentoutput);
        $this->assertCount(
            1,
            $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), ' .
                '" qtype-clozeonimage-choice-control ")]/button[' .
                'contains(concat(" ", normalize-space(@class), " "), ' .
                '" qtype-clozeonimage-choice-feedback-trigger-group ")]')
        );
        $this->assertCount(1, $xpath->query('//*[@class="specificfeedback"]'));
        $this->assertCount(1, $xpath->query('//*[@class="outcome"]'));
    }

    public function test_choice_feedback_uses_group_fallback_for_gave_up_outcome(): void {
        $xpath = $this->xpath($this->render_review('multiresponse_horizontal', []));

        $this->assertCount(
            1,
            $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), ' .
                '" qtype-clozeonimage-choice-control ")]/button[' .
                'contains(concat(" ", normalize-space(@class), " "), ' .
                '" qtype-clozeonimage-choice-feedback-trigger-group ")]')
        );
        $this->assertCount(1, $xpath->query('//*[@class="outcome"]'));
    }

    /**
     * Existing popover renderers that must retain Moodle's Bootstrap attributes.
     *
     * @return array<string, array{string,array<string,string>}>
     */
    public static function existing_popover_provider(): array {
        return [
            'Short Answer' => ['shortanswer', ['sub1_answer' => 'wrong']],
            'Numerical' => ['numerical', ['sub1_answer' => '999']],
            'dropdown Multichoice' => ['multichoice', ['sub1_answer' => '1']],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('existing_popover_provider')]
    public function test_existing_text_and_dropdown_popovers_are_unchanged(
        string $subquestiontype,
        array $response
    ): void {
        $xpath = $this->xpath($this->render_review($subquestiontype, $response));
        $triggers = $xpath->query('//a[contains(concat(" ", normalize-space(@class), " "), " feedbacktrigger ")]');

        $this->assertCount(1, $triggers);
        $trigger = $triggers->item(0);
        $this->assertSame('button', $trigger->getAttribute('role'));
        $this->assertSame('0', $trigger->getAttribute('tabindex'));
        $this->assertSame('popover', $trigger->getAttribute('data-bs-toggle'));
        $this->assertSame('body', $trigger->getAttribute('data-bs-container'));
        $this->assertSame('hover focus', $trigger->getAttribute('data-bs-trigger'));
        $this->assertSame('true', $trigger->getAttribute('data-bs-html'));
        $this->assertNotSame('', $trigger->getAttribute('data-bs-content'));
        $this->assertFalse($trigger->hasAttribute('data-region'));

        if ($subquestiontype === 'multichoice') {
            $controls = $xpath->query('//select');
            $this->assertCount(1, $controls);
            $this->assertTrue($controls->item(0)->hasAttribute('disabled'));
        } else {
            $controls = $xpath->query('//input[@type="text"]');
            $this->assertCount(1, $controls);
            $this->assertTrue($controls->item(0)->hasAttribute('readonly'));
            $this->assertFalse($controls->item(0)->hasAttribute('disabled'));
            $this->assertNotSame('-1', $controls->item(0)->getAttribute('tabindex'));
        }
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
