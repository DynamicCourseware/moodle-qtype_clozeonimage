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
            if ($subquestiontype === 'multiresponse_zero') {
                $subquestion->answers[13]->fraction = 0;
            }
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
     * Assert that popup contents use the uniform section structure.
     *
     * @param string $feedback Popup HTML.
     */
    private function assert_structured_feedback(string $feedback): void {
        $xpath = $this->xpath($feedback);
        $contents = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), ' .
            '" qtype-clozeonimage-feedback-content ")]');
        $this->assertCount(1, $contents);
        $sections = $xpath->query('./*[contains(concat(" ", normalize-space(@class), " "), ' .
            '" qtype-clozeonimage-feedback-section ")]', $contents->item(0));
        $this->assertGreaterThan(1, $sections->length);
        $this->assertStringContainsString(
            'qtype-clozeonimage-feedback-state',
            $sections->item(0)->getAttribute('class')
        );
        $this->assertContains(trim($sections->item(0)->textContent), [
            \question_state::$gradedright->default_string(true),
            \question_state::$gradedwrong->default_string(true),
            \question_state::$gradedpartial->default_string(true),
            \question_state::$gaveup->default_string(true),
        ]);
        $this->assertCount(0, $xpath->query('//br'));
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
        $this->assertCount(0, $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), ' .
            '" qtype-clozeonimage-review-surface ")]'));
        $this->assertCount(0, $xpath->query('//*[@data-bs-toggle="popover"]'));
        $this->assertCount(0, $xpath->query('//img[contains(@src, "grade_")]'));
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
     * Overall and local review states for each supported control family.
     *
     * @return array<string, array{string,array<string,int|string>,string,string}>
     */
    public static function review_state_surface_provider(): array {
        return [
            'Short Answer' => ['shortanswer', ['sub1_answer' => 'wrong'], 'incorrect', 'form-control'],
            'case-sensitive Short Answer' => [
                'shortanswer_case_sensitive', ['sub1_answer' => 'wrong'], 'incorrect', 'form-control',
            ],
            'Numerical' => ['numerical', ['sub1_answer' => '999'], 'incorrect', 'form-control'],
            'dropdown Multichoice' => ['multichoice', ['sub1_answer' => 0], 'correct', 'form-select'],
            'vertical Multichoice' => [
                'multichoice_vertical', ['sub1_answer' => 0], 'correct', 'qtype-clozeonimage-choice',
            ],
            'horizontal Multichoice' => [
                'multichoice_horizontal', ['sub1_answer' => 0], 'correct', 'qtype-clozeonimage-choice',
            ],
            'vertical Multiple Response' => [
                'multiresponse_vertical', ['sub1_choice0' => 1], 'partiallycorrect',
                'qtype-clozeonimage-choice',
            ],
            'horizontal Multiple Response' => [
                'multiresponse_horizontal', ['sub1_choice0' => 1], 'partiallycorrect',
                'qtype-clozeonimage-choice',
            ],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('review_state_surface_provider')]
    public function test_review_uses_global_border_state_and_local_background_state(
        string $subquestiontype,
        array $response,
        string $globalstate,
        string $localclass
    ): void {
        $xpath = $this->xpath($this->render_review($subquestiontype, $response));
        $regions = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), ' .
            '" qtype-clozeonimage-state-' . $globalstate . ' ")]');
        $this->assertCount(1, $regions);
        $region = $regions->item(0);
        $expectedlocalstate = $globalstate === 'partiallycorrect' ? 'correct' : $globalstate;
        $this->assertCount(1, $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), ' .
            '" ' . $localclass . ' ") and contains(concat(" ", normalize-space(@class), " "), ' .
            '" ' . $expectedlocalstate . ' ")]', $region));
        $this->assertStringContainsString(
            \question_state::graded_state_for_fraction($globalstate === 'correct' ? 1 :
                ($globalstate === 'partiallycorrect' ? 0.5 : 0))->default_string(true),
            $region->textContent
        );
    }

    /**
     * Review renderers for positioned choice controls.
     *
     * @return array<string, array{string,array<string,int>,string,int}>
     */
    public static function choice_review_provider(): array {
        return [
            'vertical Multichoice persisted layout' => [
                'multichoice_vertical', ['sub1_answer' => 1], 'radio', 3,
            ],
            'horizontal Multichoice persisted and shuffled layout' => [
                'multichoice_horizontal_shuffled', ['sub1_answer' => 1], 'radio', 3,
            ],
            'vertical Multichoice in-memory integer layout' => [
                'multichoice_vertical_integer', ['sub1_answer' => 1], 'radio', 3,
            ],
            'vertical Multiple Response persisted layout' => [
                'multiresponse_vertical', ['sub1_choice0' => 1], 'checkbox', 4,
            ],
            'horizontal Multiple Response persisted layout' => [
                'multiresponse_horizontal', ['sub1_choice0' => 1], 'checkbox', 4,
            ],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('choice_review_provider')]
    public function test_disabled_choice_review_uses_one_accessible_popover_surface(
        string $subquestiontype,
        array $response,
        string $inputtype,
        int $expectedchoices
    ): void {
        $xpath = $this->xpath($this->render_review($subquestiontype, $response));
        $regions = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), ' .
            '" qtype-clozeonimage-feedback-region ")]');
        $this->assertCount(1, $regions);
        $region = $regions->item(0);
        $inputs = $xpath->query('.//input[@type="' . $inputtype . '"]', $region);
        $this->assertCount($expectedchoices, $inputs);
        foreach ($inputs as $input) {
            $this->assertTrue($input->hasAttribute('disabled'));
            $this->assertFalse($input->hasAttribute('data-bs-toggle'));
        }

        $surfaces = $xpath->query('.//button[contains(concat(" ", normalize-space(@class), " "), ' .
            '" qtype-clozeonimage-review-surface ")]', $region);
        $this->assertCount(1, $surfaces);
        $surface = $surfaces->item(0);
        $this->assertSame('button', $surface->getAttribute('type'));
        $this->assertSame('popover', $surface->getAttribute('data-bs-toggle'));
        $this->assertSame('hover focus', $surface->getAttribute('data-bs-trigger'));
        $this->assertSame('body', $surface->getAttribute('data-bs-container'));
        $this->assertNotSame('', $surface->getAttribute('data-bs-content'));
        $this->assert_structured_feedback($surface->getAttribute('data-bs-content'));
        $this->assertStringContainsString(
            get_string('feedbackforsubquestion', 'qtype_clozeonimage', 1),
            $surface->textContent
        );
        $this->assertCount(0, $xpath->query('.//img', $region));
        $this->assertCount(0, $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), ' .
            '" specificfeedback ") or contains(concat(" ", normalize-space(@class), " "), " outcome ")]', $region));
    }

    public function test_multiresponse_review_uses_global_partial_state_and_local_correct_state(): void {
        $xpath = $this->xpath($this->render_review('multiresponse_vertical', ['sub1_choice0' => 1]));
        $regions = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), ' .
            '" qtype-clozeonimage-state-partiallycorrect ")]');
        $this->assertCount(1, $regions);
        $region = $regions->item(0);
        $this->assertCount(1, $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), ' .
            '" qtype-clozeonimage-choice ") and contains(concat(" ", normalize-space(@class), " "), ' .
            '" correct ")]', $region));
        $this->assertCount(0, $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), ' .
            '" qtype-clozeonimage-choice ") and contains(concat(" ", normalize-space(@class), " "), ' .
            '" partiallycorrect ")]', $region));
        $this->assertStringContainsString(
            \question_state::$gradedpartial->default_string(true),
            $xpath->query('.//*[@id and contains(@id, "-label")]', $region)->item(0)->textContent
        );
    }

    public function test_multiresponse_selected_invalid_choice_has_local_incorrect_state(): void {
        $xpath = $this->xpath($this->render_review('multiresponse_vertical', ['sub1_choice1' => 1]));
        $this->assertCount(1, $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), ' .
            '" qtype-clozeonimage-state-incorrect ")]'));
        $this->assertCount(1, $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), ' .
            '" qtype-clozeonimage-choice ") and contains(concat(" ", normalize-space(@class), " "), ' .
            '" incorrect ")]'));
        $this->assertStringContainsString('B is wrong', $xpath->query(
            '//button[contains(concat(" ", normalize-space(@class), " "), ' .
            '" qtype-clozeonimage-review-surface ")]'
        )->item(0)->getAttribute('data-bs-content'));
    }

    public function test_multiresponse_selected_zero_weight_choice_remains_locally_neutral(): void {
        $xpath = $this->xpath($this->render_review('multiresponse_zero', ['sub1_choice0' => 1]));
        $this->assertCount(1, $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), ' .
            '" qtype-clozeonimage-state-incorrect ")]'));
        $selectedchoices = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), ' .
            '" qtype-clozeonimage-choice ")][.//input[@checked]]');
        $this->assertCount(1, $selectedchoices);
        $this->assertStringNotContainsString(' correct ', ' ' . $selectedchoices->item(0)->getAttribute('class') . ' ');
        $this->assertStringNotContainsString(' incorrect ', ' ' . $selectedchoices->item(0)->getAttribute('class') . ' ');
        $this->assertStringNotContainsString(
            ' partiallycorrect ',
            ' ' . $selectedchoices->item(0)->getAttribute('class') . ' '
        );
    }

    public function test_multiresponse_complete_valid_selection_has_global_correct_state(): void {
        $xpath = $this->xpath($this->render_review('multiresponse_vertical', [
            'sub1_choice0' => 1,
            'sub1_choice2' => 1,
        ]));
        $this->assertCount(1, $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), ' .
            '" qtype-clozeonimage-state-correct ")]'));
        $this->assertCount(2, $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), ' .
            '" qtype-clozeonimage-choice ") and contains(concat(" ", normalize-space(@class), " "), ' .
            '" correct ")]'));
    }

    public function test_multiresponse_popup_associates_specific_feedback_with_choice_text(): void {
        $xpath = $this->xpath($this->render_review('multiresponse_vertical', [
            'sub1_choice0' => 1,
            'sub1_choice2' => 1,
        ]));
        $surface = $xpath->query('//button[contains(concat(" ", normalize-space(@class), " "), ' .
            '" qtype-clozeonimage-review-surface ")]')->item(0);
        $feedback = $surface->getAttribute('data-bs-content');
        $this->assert_structured_feedback($feedback);
        $this->assertStringContainsString('A', $feedback);
        $this->assertStringContainsString('A is part of the right answer', $feedback);
        $this->assertStringContainsString('C', $feedback);
        $this->assertStringContainsString('C is part of the right answer', $feedback);
        $this->assertStringContainsString(get_string('markoutofmax', 'question', (object) [
            'mark' => '1.00',
            'max' => '1.00',
        ]), $feedback);
    }

    public function test_feedback_without_correctness_still_has_review_surface_but_no_state(): void {
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
        $this->assertCount(1, $xpath->query('//button[contains(concat(" ", normalize-space(@class), " "), ' .
            '" qtype-clozeonimage-review-surface ")]'));
        $this->assertCount(0, $xpath->query('//*[contains(@class, "qtype-clozeonimage-state-")]'));
        $this->assertStringNotContainsString(
            \question_state::$gradedwrong->default_string(true),
            $xpath->query('//button[contains(concat(" ", normalize-space(@class), " "), ' .
                '" qtype-clozeonimage-review-surface ")]')->item(0)->textContent
        );
    }

    /**
     * Choice families with an unanswered review state.
     *
     * @return array<string, array{string}>
     */
    public static function unanswered_review_provider(): array {
        return [
            'Multichoice' => ['multichoice_vertical'],
            'Multiple Response' => ['multiresponse_horizontal'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('unanswered_review_provider')]
    public function test_unanswered_review_uses_neutral_state_and_accessible_feedback_surface(
        string $subquestiontype
    ): void {
        $xpath = $this->xpath($this->render_review($subquestiontype, []));
        $regions = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), ' .
            '" qtype-clozeonimage-state-notanswered ")]');
        $this->assertCount(1, $regions);
        $surface = $xpath->query('.//button[contains(concat(" ", normalize-space(@class), " "), ' .
            '" qtype-clozeonimage-review-surface ")]', $regions->item(0))->item(0);
        $this->assertNotNull($surface);
        $this->assertStringContainsString(
            \question_state::$gaveup->default_string(true),
            $surface->textContent
        );
    }

    /**
     * Native and overlay feedback surfaces used by text and dropdown controls.
     *
     * @return array<string, array{string,array<string,string>,string}>
     */
    public static function review_popover_provider(): array {
        return [
            'Short Answer' => ['shortanswer', ['sub1_answer' => 'wrong'], 'input'],
            'case-sensitive Short Answer' => [
                'shortanswer_case_sensitive', ['sub1_answer' => 'wrong'], 'input',
            ],
            'Numerical' => ['numerical', ['sub1_answer' => '999'], 'input'],
            'dropdown Multichoice' => ['multichoice', ['sub1_answer' => '1'], 'button'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('review_popover_provider')]
    public function test_text_and_dropdown_review_uses_geometry_neutral_popover_surface(
        string $subquestiontype,
        array $response,
        string $triggertag
    ): void {
        $xpath = $this->xpath($this->render_review($subquestiontype, $response));
        $triggers = $xpath->query('//' . $triggertag . '[contains(concat(" ", normalize-space(@class), " "), ' .
            '" qtype-clozeonimage-feedback-trigger ")]');
        $this->assertCount(1, $triggers);
        $trigger = $triggers->item(0);
        $this->assertSame('popover', $trigger->getAttribute('data-bs-toggle'));
        $this->assertSame('body', $trigger->getAttribute('data-bs-container'));
        $this->assertSame('hover focus', $trigger->getAttribute('data-bs-trigger'));
        $this->assertSame('true', $trigger->getAttribute('data-bs-html'));
        $this->assertNotSame('', $trigger->getAttribute('data-bs-content'));
        $this->assert_structured_feedback($trigger->getAttribute('data-bs-content'));
        $this->assertCount(0, $xpath->query('//a[contains(concat(" ", normalize-space(@class), " "), ' .
            '" feedbacktrigger ")]'));
        $this->assertCount(0, $xpath->query('//img[contains(@src, "grade_")]'));

        if ($subquestiontype === 'multichoice') {
            $select = $xpath->query('//select')->item(0);
            $this->assertTrue($select->hasAttribute('disabled'));
            $this->assertFalse($select->hasAttribute('data-bs-toggle'));
            $this->assertSame('button', $trigger->getAttribute('type'));
        } else {
            $this->assertTrue($trigger->hasAttribute('readonly'));
            $this->assertFalse($trigger->hasAttribute('disabled'));
            $this->assertFalse($trigger->hasAttribute('role'));
        }
    }

    public function test_enabled_choice_controls_are_feedback_triggers_without_overlay(): void {
        $this->start_attempt_at_question(
            $this->make_question(
                \qtype_clozeonimage::CONTROL_APPEARANCE_TRANSLUCENT,
                'multiresponse_horizontal'
            ),
            'deferredfeedback',
            1
        );
        $this->process_submission(['sub1_choice0' => 1]);
        $this->displayoptions->correctness = true;
        $this->displayoptions->feedback = true;
        $this->render();

        $xpath = $this->xpath($this->currentoutput);
        $inputs = $xpath->query('//input[@type="checkbox"]');
        $this->assertCount(4, $inputs);
        foreach ($inputs as $input) {
            $this->assertFalse($input->hasAttribute('disabled'));
            $this->assertSame('popover', $input->getAttribute('data-bs-toggle'));
            $this->assertFalse($input->hasAttribute('role'));
        }
        $this->assertCount(0, $xpath->query('//button[contains(concat(" ", normalize-space(@class), " "), ' .
            '" qtype-clozeonimage-review-surface ")]'));
    }

    public function test_enabled_dropdown_is_its_own_feedback_trigger_without_overlay(): void {
        $this->start_attempt_at_question(
            $this->make_question(
                \qtype_clozeonimage::CONTROL_APPEARANCE_TRANSLUCENT,
                'multichoice'
            ),
            'deferredfeedback',
            1
        );
        $this->process_submission(['sub1_answer' => 0]);
        $this->displayoptions->correctness = true;
        $this->displayoptions->feedback = true;
        $this->render();

        $xpath = $this->xpath($this->currentoutput);
        $selects = $xpath->query('//select');
        $this->assertCount(1, $selects);
        $select = $selects->item(0);
        $this->assertFalse($select->hasAttribute('disabled'));
        $this->assertSame('popover', $select->getAttribute('data-bs-toggle'));
        $this->assertCount(0, $xpath->query('//button[contains(concat(" ", normalize-space(@class), " "), ' .
            '" qtype-clozeonimage-review-surface ")]'));
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
