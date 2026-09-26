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
     * Add a second single-answer Multichoice subquestion to a test question.
     *
     * @param \qtype_clozeonimage_question $question Question to extend.
     * @return void
     */
    private function add_second_multichoice_subquestion(\qtype_clozeonimage_question $question): void {
        $subquestion = \test_question_maker::make_a_multichoice_single_question();
        $subquestion->layout = (string) \qtype_multichoice_base::LAYOUT_HORIZONTAL;
        $question->subquestions[2] = $subquestion;
        $question->positions[2] = (object) ['xleft' => 120, 'ytop' => 120, 'anchor' => 4];
    }

    /**
     * Make an Interactive question with correct, partially correct, and wrong Multichoice responses.
     */
    private function make_interactive_clearwrong_question(): \qtype_clozeonimage_question {
        $question = $this->make_question(
            \qtype_clozeonimage::CONTROL_APPEARANCE_TRANSLUCENT,
            'multichoice_vertical'
        );
        $correct = $question->subquestions[1];
        $correct->shuffleanswers = false;
        $correct->answers[13]->fraction = -0.3333333;
        $correct->answers[15]->fraction = 1;

        $partial = \test_question_maker::make_a_multichoice_single_question();
        $partial->layout = (string) \qtype_multichoice_base::LAYOUT_HORIZONTAL;
        $partial->shuffleanswers = false;
        $partial->answers[14]->fraction = 0.5;
        $question->subquestions[2] = $partial;
        $question->positions[2] = (object) ['xleft' => 120, 'ytop' => 120, 'anchor' => 4];

        $wrong = \test_question_maker::make_a_multichoice_single_question();
        $wrong->layout = (string) \qtype_multichoice_base::LAYOUT_VERTICAL;
        $wrong->shuffleanswers = false;
        $question->subquestions[3] = $wrong;
        $question->positions[3] = (object) ['xleft' => 240, 'ytop' => 240, 'anchor' => 4];
        $question->hints = [new \question_hint_with_parts(1, 'Hint', FORMAT_HTML, false, true)];

        return $question;
    }

    /**
     * Extract the actual enabled hidden response fields rendered with the Try again control.
     *
     * @return array<string, int|string> Complete POST data for the question usage.
     */
    private function try_again_post_from_current_output(): array {
        $xpath = $this->xpath($this->currentoutput);
        $prefix = $this->quba->get_field_prefix($this->slot);
        $post = ['slots' => $this->slot];

        foreach ($xpath->query('//input[@name and not(@disabled)]') as $input) {
            $type = strtolower($input->getAttribute('type'));
            if ($type === 'submit') {
                continue;
            }
            if (($type === 'radio' || $type === 'checkbox') && !$input->hasAttribute('checked')) {
                continue;
            }
            $post[$input->getAttribute('name')] = $input->getAttribute('value');
        }

        $tryagain = $xpath->query('//input[@type="submit" and @name="' . $prefix . '-tryagain"]');
        $this->assertCount(1, $tryagain);
        $post[$prefix . '-tryagain'] = $tryagain->item(0)->getAttribute('value');

        return $post;
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
     * Return the display options after the current behaviour has adjusted them.
     *
     * @return \question_display_options Adjusted display options.
     */
    private function adjusted_display_options(): \question_display_options {
        $options = clone $this->displayoptions;
        $this->get_question_attempt()->get_behaviour()->adjust_display_options($options);
        return $options;
    }

    /**
     * Assert that rendered output contains no feedback-popover trigger markup.
     *
     * @param \DOMXPath $xpath XPath for the rendered output.
     */
    private function assert_no_feedback_trigger_markup(\DOMXPath $xpath): void {
        $this->assertCount(0, $xpath->query('//*[@data-bs-toggle="popover"]'));
        $this->assertCount(0, $xpath->query('//*[@data-bs-content]'));
        $this->assertCount(0, $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), ' .
            '" feedbacktrigger ")]'));
    }

    /**
     * Assert that neither controls, accessible labels, nor local popovers disclose generated correctness.
     *
     * @param \DOMXPath $xpath Rendered question.
     */
    private function assert_no_semantic_result(\DOMXPath $xpath): void {
        $this->assertCount(0, $xpath->query('//*[contains(@class, "qtype-clozeonimage-state-")]'));
        $this->assertCount(0, $xpath->query('//*[@data-role="clozeonimage-result-state"]'));
        foreach (['correct', 'partiallycorrect', 'incorrect'] as $state) {
            $this->assertCount(0, $xpath->query(
                '//*[contains(@class, "qtype-clozeonimage-feedback-region")]' .
                '/descendant-or-self::*[contains(concat(" ", normalize-space(@class), " "), " ' . $state . ' ")]'
            ));
        }
        foreach ($xpath->query('//*[@data-bs-content and contains(@class, "qtype-clozeonimage-")]') as $trigger) {
            $popup = $this->xpath($trigger->getAttribute('data-bs-content'));
            $this->assertCount(0, $popup->query('//*[@data-clozeonimage-result-state]'));
        }
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
        global $CFG;
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
        $buttons = $xpath->query('//button[@data-action="clozeonimage-toggle-panorama"]');
        $this->assertCount(1, $buttons);
        $button = $buttons->item(0);
        $this->assertSame('button', $button->getAttribute('type'));
        $this->assertSame('false', $button->getAttribute('aria-pressed'));
        $this->assertTrue($button->hasAttribute('hidden'));
        $this->assertSame(get_string('panoramicview', 'qtype_clozeonimage'), $button->textContent);
        $this->assertSame(get_string('exitpanoramicview', 'qtype_clozeonimage'), $button->getAttribute('data-panorama-label'));
        $viewport = $xpath->query('//div[@class="qtype-clozeonimage-scroll"]')->item(0);
        $qa = $this->get_question_attempt();
        $this->assertSame('qtype_clozeonimage:wide:v1:' . sha1($CFG->wwwroot) .
            ':usage:' . $qa->get_usage_id() . ':slot:' . $qa->get_slot() . ':question:' . $qa->get_question_id(),
            $viewport->getAttribute('data-wide-key'));
        $this->assertSame($viewport->getAttribute('id'), $button->getAttribute('aria-controls'));
        $this->assertSame('qtype-clozeonimage-view-controls', $viewport->nextSibling->getAttribute('class'));
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
     * Every supported rendered family, with and without answer shuffling, in both appearances.
     *
     * @return array
     */
    public static function family_appearance_provider(): array {
        $cases = [];
        $types = [
            'shortanswer', 'shortanswer_case_sensitive', 'numerical', 'multichoice', 'multichoice_s',
            'multichoice_vertical', 'multichoice_shuffled_vertical',
            'multichoice_horizontal', 'multichoice_shuffled_horizontal',
            'multiresponse_vertical', 'multiresponse_shuffled_vertical',
            'multiresponse_horizontal', 'multiresponse_shuffled_horizontal',
        ];
        foreach ($types as $type) {
            foreach (self::control_appearance_provider() as [$appearance, $class]) {
                $cases[$type . ' ' . $appearance] = [$type, $appearance, $class];
            }
        }
        return $cases;
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('family_appearance_provider')]
    public function test_all_control_families_inherit_appearance_through_check_and_review(
        string $type,
        int $appearance,
        string $class
    ): void {
        $question = $this->make_question($appearance, $type);
        $this->start_attempt_at_question($question, 'adaptive', 1);
        $response = $question->get_correct_response();
        foreach (['editable', 'checked', 'review'] as $phase) {
            if ($phase === 'checked') {
                $this->process_submission($response + ['-submit' => 1]);
                $this->displayoptions->correctness = true;
            } else if ($phase === 'review') {
                $this->finish();
            }
            $this->render();
            $xpath = $this->xpath($this->currentoutput);
            $composition = $xpath->query('//div[@class="qtype-clozeonimage-composition ' . $class . '"]');
            $this->assertCount(1, $composition, $phase);
            $controls = $xpath->query('.//input[not(@type="hidden")] | .//select', $composition->item(0));
            $this->assertGreaterThan(0, $controls->length, $type . ' ' . $phase);
            foreach ($controls as $control) {
                $this->assertSame(
                    $phase === 'review',
                    $control->hasAttribute('readonly') || $control->hasAttribute('disabled'),
                    $type . ' ' . $phase
                );
            }
            $this->assertCount(1, $xpath->query('.//div[@style="left:12px;top:12px;"]', $composition->item(0)));
            $this->assertCount(1, $xpath->query('.//img[contains(@style, "width:321px")]', $composition->item(0)));
        }
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
            'vertical Multichoice' => [
                'multichoice_vertical',
                '//input[@type="radio" and @data-role="clozeonimage-multichoice-choice"]',
                3,
            ],
            'horizontal Multichoice' => [
                'multichoice_horizontal',
                '//input[@type="radio" and @data-role="clozeonimage-multichoice-choice"]',
                3,
            ],
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
     * Editable non-dropdown Multichoice layouts that support local clearing.
     *
     * @return array<string, array{string}>
     */
    public static function clear_choice_layout_provider(): array {
        return [
            'vertical persisted layout' => ['multichoice_vertical'],
            'horizontal persisted layout' => ['multichoice_horizontal'],
            'vertical in-memory integer layout' => ['multichoice_vertical_integer'],
            'horizontal shuffled layout' => ['multichoice_horizontal_shuffled'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('editable_clear_provider')]
    public function test_editable_multichoice_unanswered_has_local_clear_sentinel(
        string $subquestiontype,
        string $behaviour
    ): void {
        $this->start_attempt_at_question(
            $this->make_question(\qtype_clozeonimage::CONTROL_APPEARANCE_TRANSLUCENT, $subquestiontype),
            $behaviour,
            1
        );

        $this->render();
        $xpath = $this->xpath($this->currentoutput);
        $regions = $xpath->query('//*[@data-region="clozeonimage-multichoice"]');
        $this->assertCount(1, $regions);
        $region = $regions->item(0);
        $choices = $xpath->query('.//input[@data-role="clozeonimage-multichoice-choice"]', $region);
        $sentinels = $xpath->query('.//input[@data-role="clozeonimage-clear-choice-sentinel"]', $region);
        $buttons = $xpath->query('.//button[@data-action="clozeonimage-clear-choice"]', $region);

        $this->assertCount(3, $choices);
        $this->assertCount(1, $sentinels);
        $this->assertCount(1, $buttons);
        $sentinel = $sentinels->item(0);
        $button = $buttons->item(0);
        $this->assertSame($choices->item(0)->getAttribute('name'), $sentinel->getAttribute('name'));
        $this->assertSame('-1', $sentinel->getAttribute('value'));
        $this->assertSame('hidden', $sentinel->getAttribute('type'));
        $this->assertFalse($sentinel->hasAttribute('checked'));
        $this->assertFalse($sentinel->hasAttribute('tabindex'));
        $this->assertFalse($sentinel->hasAttribute('disabled'));
        $this->assertCount(3, $xpath->query('.//input[@type="radio"]', $region));
        foreach ($choices as $choice) {
            $this->assertSame('radio', $choice->getAttribute('type'));
            $this->assertFalse($choice->hasAttribute('checked'));
            $this->assertFalse($choice->hasAttribute('disabled'));
            $this->assertFalse($choice->hasAttribute('tabindex'));
        }
        $this->assertSame('button', $button->getAttribute('type'));
        $this->assertTrue($button->hasAttribute('hidden'));
        $this->assertSame(
            get_string('clearchoiceforsubquestion', 'qtype_clozeonimage', 1),
            $button->getAttribute('title')
        );
        $this->assertStringContainsString(
            get_string('clearchoiceforsubquestion', 'qtype_clozeonimage', 1),
            $button->textContent
        );
        $this->assertCount(1, $xpath->query('.//span[@aria-hidden="true" and text()="C"]', $button));
    }

    /**
     * Editable radio layouts use the same initial focus rule across behaviours.
     *
     * @return array<string, array{string,string}>
     */
    public static function editable_clear_provider(): array {
        $cases = [];
        foreach (['interactive', 'adaptive', 'adaptivenopenalty', 'immediatefeedback', 'deferredfeedback'] as $behaviour) {
            foreach (self::clear_choice_layout_provider() as $name => [$type]) {
                $cases[$behaviour . ' ' . $name] = [$type, $behaviour];
            }
        }
        return $cases;
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('editable_clear_provider')]
    public function test_selected_editable_multichoice_starts_with_clear_hidden_until_focus(
        string $subquestiontype,
        string $behaviour
    ): void {
        $this->start_attempt_at_question(
            $this->make_question(\qtype_clozeonimage::CONTROL_APPEARANCE_TRANSLUCENT, $subquestiontype),
            $behaviour,
            1
        );
        $this->process_submission(['sub1_answer' => '1']);

        $this->render();
        $xpath = $this->xpath($this->currentoutput);
        $sentinel = $xpath->query('//input[@data-role="clozeonimage-clear-choice-sentinel"]')->item(0);
        $button = $xpath->query('//button[@data-action="clozeonimage-clear-choice"]')->item(0);

        $this->assertNotNull($sentinel);
        $this->assertNotNull($button);
        $this->assertSame('hidden', $sentinel->getAttribute('type'));
        $this->assertFalse($sentinel->hasAttribute('tabindex'));
        $this->assertFalse($sentinel->hasAttribute('checked'));
        $this->assertTrue($sentinel->hasAttribute('disabled'));
        $this->assertTrue($button->hasAttribute('hidden'));
        $this->assertCount(1, $xpath->query(
            '//input[@data-role="clozeonimage-multichoice-choice" and @checked and not(@disabled)]'
        ));
    }

    /**
     * Exercise the hidden fallback through real Moodle POST processing, including response zero.
     *
     * @param string $subquestiontype Radio layout.
     * @param string $behaviour Editable behaviour.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('editable_clear_provider')]
    public function test_clear_sentinel_survives_moodle_integer_post_processing(
        string $subquestiontype,
        string $behaviour
    ): void {
        $this->start_attempt_at_question(
            $this->make_question(\qtype_clozeonimage::CONTROL_APPEARANCE_TRANSLUCENT, $subquestiontype),
            $behaviour,
            1
        );
        $this->process_submission(['sub1_answer' => '1']);
        $this->assertSame(PARAM_INT, $this->get_question_attempt()->get_question()->get_expected_data()['sub1_answer']);
        $inputname = $this->quba->get_field_prefix($this->slot) . 'sub1_answer';
        foreach (['-1', '0', '2', '-1'] as $response) {
            $this->render();
            $xpath = $this->xpath($this->currentoutput);
            $sentinel = $xpath->query('//input[@data-role="clozeonimage-clear-choice-sentinel"]')->item(0);
            $this->assertSame('hidden', $sentinel->getAttribute('type'));
            // Mirror Clear/choiceChanged. For zero, also test selection before the AMD module loads:
            // the enabled fallback precedes the selected radio, so PHP must receive the latter value.
            if ($response === '2') {
                $sentinel->setAttribute('disabled', 'disabled');
            } else {
                $sentinel->removeAttribute('disabled');
            }
            foreach ($xpath->query('//input[@data-role="clozeonimage-multichoice-choice"]') as $choice) {
                $choice->removeAttribute('checked');
                if ($choice->getAttribute('value') === $response) {
                    $choice->setAttribute('checked', 'checked');
                }
            }
            $pairs = ['slots=' . $this->slot];
            foreach ($xpath->query('//input[@name and not(@disabled)]') as $input) {
                $type = $input->getAttribute('type');
                if ($type === 'submit' || ($type === 'radio' && !$input->hasAttribute('checked'))) {
                    continue;
                }
                $pairs[] = urlencode($input->getAttribute('name')) . '=' . urlencode($input->getAttribute('value'));
            }
            parse_str(implode('&', $pairs), $post);
            $this->assertSame($response, $post[$inputname]);
            $this->quba->process_all_actions(time(), $post);
            $this->assertSame($response, $this->get_question_attempt()->get_last_qt_var('sub1_answer'));
            $subquestion = $this->get_question_attempt()->get_question()->subquestions[1];
            $this->assertSame($response !== '-1', $subquestion->is_complete_response(['answer' => $response]));
        }
    }

    public function test_clear_choice_controls_are_local_to_each_multichoice_subquestion(): void {
        $question = $this->make_question(
            \qtype_clozeonimage::CONTROL_APPEARANCE_TRANSLUCENT,
            'multichoice_vertical'
        );
        $this->add_second_multichoice_subquestion($question);
        $this->start_attempt_at_question($question, 'deferredfeedback', 1);
        $this->process_submission([
            'sub1_answer' => '1',
            'sub2_answer' => '0',
        ]);

        $this->render();
        $xpath = $this->xpath($this->currentoutput);
        $regions = $xpath->query('//*[@data-region="clozeonimage-multichoice"]');
        $this->assertCount(2, $regions);
        $names = [];
        $ids = [];
        foreach ($regions as $region) {
            $sentinels = $xpath->query('.//input[@data-role="clozeonimage-clear-choice-sentinel"]', $region);
            $buttons = $xpath->query('.//button[@data-action="clozeonimage-clear-choice"]', $region);
            $this->assertCount(1, $sentinels);
            $this->assertCount(1, $buttons);
            $names[] = $sentinels->item(0)->getAttribute('name');
            $ids[] = $sentinels->item(0)->getAttribute('id');
            $this->assertTrue($buttons->item(0)->hasAttribute('hidden'));
        }
        $this->assertCount(2, array_unique($names));
        $this->assertCount(2, array_unique($ids));

        $this->process_submission([
            'sub1_answer' => '-1',
            'sub2_answer' => '0',
        ]);
        $this->assertSame('-1', $this->get_question_attempt()->get_last_qt_var('sub1_answer'));
        $this->assertSame('0', $this->get_question_attempt()->get_last_qt_var('sub2_answer'));
        $this->assertFalse($question->is_complete_response($this->get_question_attempt()->get_last_qt_data()));
    }

    /**
     * Subquestion types that must not receive local single-choice clearing controls.
     *
     * @return array<string, array{string}>
     */
    public static function no_clear_choice_control_provider(): array {
        return [
            'Short Answer' => ['shortanswer'],
            'Numerical' => ['numerical'],
            'dropdown Multichoice' => ['multichoice'],
            'vertical Multiple Response' => ['multiresponse_vertical'],
            'horizontal Multiple Response' => ['multiresponse_horizontal'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('no_clear_choice_control_provider')]
    public function test_other_subquestion_types_have_no_local_clear_choice_control(
        string $subquestiontype
    ): void {
        $this->start_attempt_at_question(
            $this->make_question(\qtype_clozeonimage::CONTROL_APPEARANCE_TRANSLUCENT, $subquestiontype),
            'deferredfeedback',
            1
        );

        $this->render();
        $xpath = $this->xpath($this->currentoutput);
        $this->assertCount(0, $xpath->query('//*[@data-role="clozeonimage-clear-choice-sentinel"]'));
        $this->assertCount(0, $xpath->query('//*[@data-action="clozeonimage-clear-choice"]'));
    }

    public function test_readonly_multichoice_has_no_actionable_local_clear_choice_control(): void {
        $this->start_attempt_at_question(
            $this->make_question(
                \qtype_clozeonimage::CONTROL_APPEARANCE_TRANSLUCENT,
                'multichoice_vertical'
            ),
            'deferredfeedback',
            1
        );
        $this->process_submission(['sub1_answer' => '1']);
        $this->displayoptions->readonly = 0x10; // Interactive's special truthy Try again state.

        $this->render();
        $xpath = $this->xpath($this->currentoutput);
        $this->assertCount(0, $xpath->query('//*[@data-role="clozeonimage-clear-choice-sentinel"]'));
        $this->assertCount(0, $xpath->query('//*[@data-action="clozeonimage-clear-choice"]'));
        $this->assertCount(3, $xpath->query('//input[@type="radio" and @disabled]'));
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
     * @return array<string, array{string,array<string,int|string>,string,string,string}>
     */
    public static function review_state_surface_provider(): array {
        return [
            'Short Answer' => [
                'shortanswer', ['sub1_answer' => 'wrong'], 'incorrect', 'form-control', 'incorrect',
            ],
            'case-sensitive Short Answer' => [
                'shortanswer_case_sensitive', ['sub1_answer' => 'wrong'], 'incorrect', 'form-control', 'incorrect',
            ],
            'Numerical' => ['numerical', ['sub1_answer' => '999'], 'incorrect', 'form-control', 'incorrect'],
            'dropdown Multichoice' => [
                'multichoice', ['sub1_answer' => 0], 'correct', 'form-select', 'correct',
            ],
            'vertical Multichoice' => [
                'multichoice_vertical', ['sub1_answer' => 0], 'correct', 'qtype-clozeonimage-choice', 'correct',
            ],
            'horizontal Multichoice' => [
                'multichoice_horizontal', ['sub1_answer' => 0], 'correct', 'qtype-clozeonimage-choice', 'correct',
            ],
            'vertical Multiple Response' => [
                'multiresponse_vertical', ['sub1_choice0' => 1], 'partiallycorrect',
                'qtype-clozeonimage-choice', 'partiallycorrect',
            ],
            'horizontal Multiple Response' => [
                'multiresponse_horizontal', ['sub1_choice0' => 1], 'partiallycorrect',
                'qtype-clozeonimage-choice', 'partiallycorrect',
            ],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('review_state_surface_provider')]
    public function test_review_uses_global_border_state_and_local_background_state(
        string $subquestiontype,
        array $response,
        string $globalstate,
        string $localclass,
        string $expectedlocalstate
    ): void {
        $xpath = $this->xpath($this->render_review($subquestiontype, $response));
        $regions = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), ' .
            '" qtype-clozeonimage-state-' . $globalstate . ' ")]');
        $this->assertCount(1, $regions);
        $region = $regions->item(0);
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
        $this->assertStringNotContainsString(' feedbacktrigger ', ' ' . $surface->getAttribute('class') . ' ');
    }

    public function test_multiresponse_review_uses_global_and_local_partial_state_for_incomplete_positive_set(): void {
        $xpath = $this->xpath($this->render_review('multiresponse_vertical', ['sub1_choice0' => 1]));
        $regions = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), ' .
            '" qtype-clozeonimage-state-partiallycorrect ")]');
        $this->assertCount(1, $regions);
        $region = $regions->item(0);
        $this->assertCount(1, $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), ' .
            '" qtype-clozeonimage-choice ") and contains(concat(" ", normalize-space(@class), " "), ' .
            '" partiallycorrect ")]', $region));
        $this->assertCount(0, $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), ' .
            '" qtype-clozeonimage-choice ") and contains(concat(" ", normalize-space(@class), " "), ' .
            '" correct ")]', $region));
        $this->assertStringContainsString(
            \question_state::$gradedpartial->default_string(true),
            $xpath->query('.//*[@id and contains(@id, "-label")]', $region)->item(0)->textContent
        );
    }

    /**
     * Multiple Response selections and their independent global and local states.
     *
     * @return array<string, array{string,array<string,int>,string,int,int,int}>
     */
    public static function multiresponse_local_state_provider(): array {
        return [
            'first positive only' => [
                'multiresponse_vertical', ['sub1_choice0' => 1], 'partiallycorrect', 0, 1, 0,
            ],
            'second positive only' => [
                'multiresponse_horizontal', ['sub1_choice2' => 1], 'partiallycorrect', 0, 1, 0,
            ],
            'incorrect only' => [
                'multiresponse_vertical', ['sub1_choice1' => 1], 'incorrect', 0, 0, 1,
            ],
            'complete positive set' => [
                'multiresponse_horizontal', ['sub1_choice0' => 1, 'sub1_choice2' => 1], 'correct', 2, 0, 0,
            ],
            'first positive and incorrect' => [
                'multiresponse_vertical', ['sub1_choice0' => 1, 'sub1_choice1' => 1], 'incorrect', 0, 1, 1,
            ],
            'second positive and incorrect' => [
                'multiresponse_horizontal', ['sub1_choice2' => 1, 'sub1_choice1' => 1], 'incorrect', 0, 1, 1,
            ],
            'complete positive set and incorrect' => [
                'multiresponse_vertical', [
                    'sub1_choice0' => 1,
                    'sub1_choice2' => 1,
                    'sub1_choice1' => 1,
                ], 'incorrect', 2, 0, 1,
            ],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('multiresponse_local_state_provider')]
    public function test_multiresponse_global_and_local_states_are_independent(
        string $subquestiontype,
        array $response,
        string $globalstate,
        int $correctcount,
        int $partialcount,
        int $incorrectcount
    ): void {
        $xpath = $this->xpath($this->render_review($subquestiontype, $response));
        $regions = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), ' .
            '" qtype-clozeonimage-state-' . $globalstate . ' ")]');
        $this->assertCount(1, $regions);
        $region = $regions->item(0);
        $selected = './/*[contains(concat(" ", normalize-space(@class), " "), ' .
            '" qtype-clozeonimage-choice ")][.//input[@checked]]';

        $this->assertCount($correctcount, $xpath->query(
            $selected . '[contains(concat(" ", normalize-space(@class), " "), " correct ")]',
            $region
        ));
        $this->assertCount($partialcount, $xpath->query(
            $selected . '[contains(concat(" ", normalize-space(@class), " "), " partiallycorrect ")]',
            $region
        ));
        $this->assertCount($incorrectcount, $xpath->query(
            $selected . '[contains(concat(" ", normalize-space(@class), " "), " incorrect ")]',
            $region
        ));
        $this->assertCount(0, $xpath->query(
            './/*[contains(concat(" ", normalize-space(@class), " "), " qtype-clozeonimage-choice ")]' .
                '[not(.//input[@checked])]' .
                '[contains(concat(" ", normalize-space(@class), " "), " correct ") or ' .
                'contains(concat(" ", normalize-space(@class), " "), " partiallycorrect ") or ' .
                'contains(concat(" ", normalize-space(@class), " "), " incorrect ")]',
            $region
        ));
    }

    public function test_multiresponse_local_state_uses_answer_fractions_after_shuffling(): void {
        $question = $this->make_question(
            \qtype_clozeonimage::CONTROL_APPEARANCE_TRANSLUCENT,
            'multiresponse_horizontal_shuffled'
        );
        $this->start_attempt_at_question($question, 'deferredfeedback', 1);
        $response = [];
        $positivecount = 0;
        $negativeadded = false;
        foreach ($question->subquestions[1]->get_order($this->get_question_attempt()) as $value => $answerid) {
            $fraction = $question->subquestions[1]->answers[$answerid]->fraction;
            if ($fraction > 0) {
                $response['sub1_choice' . $value] = 1;
                $positivecount++;
            } else if ($fraction < 0 && !$negativeadded) {
                $response['sub1_choice' . $value] = 1;
                $negativeadded = true;
            }
        }
        $this->assertSame(2, $positivecount);
        $this->assertTrue($negativeadded);

        $this->process_submission($response);
        $this->finish();
        $this->displayoptions->correctness = true;
        $this->displayoptions->feedback = true;
        $this->render();
        $xpath = $this->xpath($this->currentoutput);
        $regions = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), ' .
            '" qtype-clozeonimage-state-incorrect ")]');
        $this->assertCount(1, $regions);
        $region = $regions->item(0);
        $this->assertCount(2, $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), ' .
            '" qtype-clozeonimage-choice ") and contains(concat(" ", normalize-space(@class), " "), ' .
            '" correct ")]', $region));
        $this->assertCount(1, $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), ' .
            '" qtype-clozeonimage-choice ") and contains(concat(" ", normalize-space(@class), " "), ' .
            '" incorrect ")]', $region));
        $this->assertCount(0, $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), ' .
            '" qtype-clozeonimage-choice ") and contains(concat(" ", normalize-space(@class), " "), ' .
            '" partiallycorrect ")]', $region));
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

    /**
     * Multiple Response layouts and appearances used to verify local-state paint structure.
     *
     * @return array<string, array{string,int,string,string}>
     */
    public static function multiresponse_incorrect_paint_structure_provider(): array {
        return [
            'vertical translucent' => [
                'multiresponse_vertical', \qtype_clozeonimage::CONTROL_APPEARANCE_TRANSLUCENT,
                'qtype-clozeonimage-appearance-translucent', 'div',
            ],
            'vertical opaque' => [
                'multiresponse_vertical', \qtype_clozeonimage::CONTROL_APPEARANCE_OPAQUE,
                'qtype-clozeonimage-appearance-opaque', 'div',
            ],
            'horizontal translucent' => [
                'multiresponse_horizontal', \qtype_clozeonimage::CONTROL_APPEARANCE_TRANSLUCENT,
                'qtype-clozeonimage-appearance-translucent', 'td',
            ],
            'horizontal opaque' => [
                'multiresponse_horizontal', \qtype_clozeonimage::CONTROL_APPEARANCE_OPAQUE,
                'qtype-clozeonimage-appearance-opaque', 'td',
            ],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('multiresponse_incorrect_paint_structure_provider')]
    public function test_multiresponse_negative_choice_has_paintable_incorrect_wrapper(
        string $subquestiontype,
        int $appearance,
        string $appearanceclass,
        string $wrappertag
    ): void {
        $question = $this->make_question($appearance, $subquestiontype);
        $question->subquestions[1]->shuffleanswers = false;
        $this->assertLessThan(0, $question->subquestions[1]->answers[14]->fraction);
        $this->start_attempt_at_question($question, 'deferredfeedback', 1);
        $this->process_submission(['sub1_choice1' => 1]);
        $this->finish();
        $this->displayoptions->correctness = true;

        $this->render();
        $xpath = $this->xpath($this->currentoutput);
        $compositions = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), ' .
            '" qtype-clozeonimage-composition ") and contains(concat(" ", normalize-space(@class), " "), ' .
            '" ' . $appearanceclass . ' ")]');
        $this->assertCount(1, $compositions);
        $incorrectchoices = $xpath->query(
            './/' . $wrappertag . '[contains(concat(" ", normalize-space(@class), " "), ' .
            '" qtype-clozeonimage-choice ") and contains(concat(" ", normalize-space(@class), " "), ' .
            '" incorrect ") and .//input[@type="checkbox" and @checked]]',
            $compositions->item(0)
        );
        $this->assertCount(1, $incorrectchoices);
        $this->assertCount(1, $xpath->query(
            './span[contains(concat(" ", normalize-space(@class), " "), ' .
            '" qtype-clozeonimage-choice-control ")]',
            $incorrectchoices->item(0)
        ));
        $this->assertCount(0, $xpath->query(
            './/*[contains(concat(" ", normalize-space(@class), " "), ' .
            '" qtype-clozeonimage-choice ") and not(.//input[@checked]) and (' .
            'contains(concat(" ", normalize-space(@class), " "), " correct ") or ' .
            'contains(concat(" ", normalize-space(@class), " "), " partiallycorrect ") or ' .
            'contains(concat(" ", normalize-space(@class), " "), " incorrect "))]',
            $compositions->item(0)
        ));
    }

    public function test_multiresponse_selected_zero_weight_choice_has_local_incorrect_state(): void {
        $xpath = $this->xpath($this->render_review('multiresponse_zero', ['sub1_choice0' => 1]));
        $this->assertCount(1, $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), ' .
            '" qtype-clozeonimage-state-incorrect ")]'));
        $selectedchoices = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), ' .
            '" qtype-clozeonimage-choice ")][.//input[@checked]]');
        $this->assertCount(1, $selectedchoices);
        $this->assertStringNotContainsString(' correct ', ' ' . $selectedchoices->item(0)->getAttribute('class') . ' ');
        $this->assertStringContainsString(' incorrect ', ' ' . $selectedchoices->item(0)->getAttribute('class') . ' ');
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

    public function test_explanatory_feedback_without_correctness_or_marks_has_no_state(): void {
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
        $this->displayoptions->marks = \question_display_options::MAX_ONLY;

        $this->render();
        $xpath = $this->xpath($this->currentoutput);
        $this->assertCount(1, $xpath->query('//button[contains(concat(" ", normalize-space(@class), " "), ' .
            '" qtype-clozeonimage-review-surface ")]'));
        $this->assertCount(0, $xpath->query('//*[contains(@class, "qtype-clozeonimage-state-")]'));
        $popup = $this->xpath($xpath->query('//*[@data-bs-content]')->item(0)->getAttribute('data-bs-content'));
        $this->assertCount(0, $popup->query('//*[@data-clozeonimage-result-state]'));
        $this->assertStringNotContainsString(
            \question_state::$gradedwrong->default_string(true),
            $xpath->query('//button[contains(concat(" ", normalize-space(@class), " "), ' .
                '" qtype-clozeonimage-review-surface ")]')->item(0)->textContent
        );
    }

    /**
     * Editable controls whose retained responses must not create mark-only feedback popovers.
     *
     * @return array<string, array{string,array<string,string>,string}>
     */
    public static function editable_answer_entry_feedback_provider(): array {
        return [
            'Short Answer' => ['shortanswer', ['sub1_answer' => 'frog'], '//input[@type="text"]'],
            'Numerical' => ['numerical', ['sub1_answer' => '3.14'], '//input[@type="text"]'],
            'dropdown Multichoice' => ['multichoice', ['sub1_answer' => '1'], '//select'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('editable_answer_entry_feedback_provider')]
    public function test_editable_answer_entry_suppresses_mark_only_feedback_popover(
        string $subquestiontype,
        array $response,
        string $controlxpath
    ): void {
        $this->start_attempt_at_question(
            $this->make_question(\qtype_clozeonimage::CONTROL_APPEARANCE_TRANSLUCENT, $subquestiontype),
            'interactive',
            1
        );
        $this->process_submission($response);

        $options = $this->adjusted_display_options();
        $this->assertFalse($options->readonly);
        $this->assertFalse((bool) $options->correctness);
        $this->assertFalse((bool) $options->feedback);
        $this->assertFalse((bool) $options->rightanswer);
        $this->assertSame(\question_display_options::MARK_AND_MAX, $options->marks);

        $this->render();
        $xpath = $this->xpath($this->currentoutput);
        $controls = $xpath->query($controlxpath);
        $this->assertCount(1, $controls);
        $this->assertFalse($controls->item(0)->hasAttribute('readonly'));
        $this->assertFalse($controls->item(0)->hasAttribute('disabled'));
        $this->assert_no_feedback_trigger_markup($xpath);
    }

    /**
     * Available mark display modes.
     *
     * @return array<string, array{int}>
     */
    public static function marks_mode_provider(): array {
        return ['hidden' => [0], 'maximum only' => [1], 'earned and maximum' => [2]];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('marks_mode_provider')]
    public function test_interactive_marks_do_not_disclose_retained_answer_during_editing(int $marks): void {
        $question = $this->make_question();
        $this->add_second_multichoice_subquestion($question);
        $question->subquestions[2]->shuffleanswers = false;
        $question->hints = [new \question_hint_with_parts(1, 'Hint', FORMAT_HTML, false, false)];
        $this->start_attempt_at_question($question, 'interactive', 1);
        $this->displayoptions->marks = $marks;
        $this->displayoptions->correctness = true;
        $this->displayoptions->feedback = false;
        $this->displayoptions->rightanswer = false;
        foreach (['initial', 'checked', 'tryagain', 'exhausted', 'review'] as $stage) {
            if ($stage === 'checked' || $stage === 'exhausted') {
                $this->process_submission(['sub1_answer' => 'frog', 'sub2_answer' => 1, '-submit' => 1]);
            } else if ($stage === 'tryagain') {
                $this->process_submission(['-tryagain' => 1]);
                $this->assertSame('frog', $this->get_question_attempt()->get_last_qt_var('sub1_answer'));
            } else if ($stage === 'review') {
                $this->finish();
                $this->displayoptions->readonly = true;
                $this->displayoptions->correctness = false;
            }
            $options = $this->adjusted_display_options();
            $this->assertSame($stage === 'exhausted', (bool) $options->correctness);
            if ($stage === 'checked') {
                $this->assertSame(\qbehaviour_interactive::TRY_AGAIN_VISIBLE, $options->readonly);
            }
            $this->render();
            $xpath = $this->xpath($this->currentoutput);
            if (!$options->correctness) {
                $this->assert_no_semantic_result($xpath);
            }
            $input = $xpath->query('//input[@type="text"]')->item(0);
            $popup = $input->getAttribute('data-bs-content');
            $this->assertSame($marks === \question_display_options::MAX_ONLY, str_contains(
                $popup,
                get_string('markedoutofmax', 'question', format_float(1, $options->markdp))
            ));
            $currentresult = in_array($stage, ['checked', 'exhausted', 'review']);
            $this->assertSame($marks === \question_display_options::MARK_AND_MAX && $currentresult, str_contains(
                $popup,
                get_string('markoutofmax', 'question', (object) [
                    'mark' => format_float(1, $options->markdp), 'max' => format_float(1, $options->markdp),
                ])
            ));
        }
    }

    /**
     * Text responses whose graded result remains focusable while Try again is pending.
     *
     * @return array<string, array{string,string}>
     */
    public static function interactive_text_focus_provider(): array {
        return [
            'correct Short Answer' => ['shortanswer', 'frog'],
            'incorrect Short Answer' => ['shortanswer', 'wrong'],
            'correct Numerical' => ['numerical', '3.14'],
            'incorrect Numerical' => ['numerical', '999'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('interactive_text_focus_provider')]
    public function test_interactive_text_focus_lifecycle(string $subquestiontype, string $response): void {
        $question = $this->make_question(\qtype_clozeonimage::CONTROL_APPEARANCE_TRANSLUCENT, $subquestiontype);
        $this->add_second_multichoice_subquestion($question);
        $question->subquestions[2]->shuffleanswers = false;
        $question->hints = [new \question_hint_with_parts(1, 'Hint', FORMAT_HTML, false, false)];
        $this->start_attempt_at_question($question, 'interactive', 1);
        $this->process_submission(['sub1_answer' => $response, 'sub2_answer' => 1, '-submit' => 1]);
        $this->assertSame('interactivecountback', $this->get_question_attempt()->get_behaviour_name());

        $options = $this->adjusted_display_options();
        $this->assertSame(\qbehaviour_interactive::TRY_AGAIN_VISIBLE, $options->readonly);
        $this->assertFalse((bool) $options->correctness);
        $this->assertTrue((bool) $options->feedback);
        $this->assertFalse((bool) $options->rightanswer);
        $this->assertSame(\question_display_options::MARK_AND_MAX, $options->marks);

        $this->render();
        $xpath = $this->xpath($this->currentoutput);
        $inputs = $xpath->query('//input[@type="text"]');
        $this->assertCount(1, $inputs);
        $input = $inputs->item(0);
        $this->assertTrue($input->hasAttribute('readonly'));
        $this->assertFalse($input->hasAttribute('disabled'));
        $this->assertFalse($input->hasAttribute('tabindex'));
        $this->assertSame($response, $input->getAttribute('value'));
        $this->assertSame('true', $input->getAttribute('data-clozeonimage-awaiting-retry'));
        $this->assert_no_semantic_result($xpath);
        $this->assertSame('popover', $input->getAttribute('data-bs-toggle'));
        $this->assertSame('hover focus', $input->getAttribute('data-bs-trigger'));
        $this->assertNotSame('', $input->getAttribute('data-bs-content'));

        $this->process_submission(['-tryagain' => 1]);
        $this->assertFalse($this->adjusted_display_options()->readonly);
        $this->render();
        $xpath = $this->xpath($this->currentoutput);
        $input = $xpath->query('//input[@type="text"]')->item(0);
        $this->assertFalse($input->hasAttribute('readonly'));
        $this->assertFalse($input->hasAttribute('disabled'));
        $this->assertFalse($input->hasAttribute('data-clozeonimage-awaiting-retry'));
        $this->assert_no_feedback_trigger_markup($xpath);

        $this->finish();
        $this->render();
        $input = $this->xpath($this->currentoutput)->query('//input[@type="text"]')->item(0);
        $this->assertTrue($input->hasAttribute('readonly'));
        $this->assertFalse($input->hasAttribute('data-clozeonimage-awaiting-retry'));
    }

    /**
     * Locked text results must use the same focus guard for every independently enabled popup section.
     *
     * @return array<string, array{string,string,string,string}>
     */
    public static function readonly_result_focus_provider(): array {
        $cases = [];
        foreach (['interactive', 'immediatefeedback'] as $behaviour) {
            foreach (['shortanswer' => 'toad', 'numerical' => '3.14'] as $type => $response) {
                foreach (['maximum', 'marks', 'feedback', 'rightanswer'] as $option) {
                    $cases[$behaviour . ' ' . $type . ' ' . $option] = [$behaviour, $type, $response, $option];
                }
            }
        }
        return $cases;
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('readonly_result_focus_provider')]
    public function test_readonly_result_retains_keyboard_and_popover_contract(
        string $behaviour,
        string $type,
        string $response,
        string $option
    ): void {
        $question = $this->make_question(\qtype_clozeonimage::CONTROL_APPEARANCE_TRANSLUCENT, $type);
        $this->add_second_multichoice_subquestion($question);
        $question->subquestions[2]->shuffleanswers = false;
        $question->hints = [new \question_hint_with_parts(1, 'Hint', FORMAT_HTML, false, false)];
        $this->start_attempt_at_question($question, $behaviour, 1);
        $submission = ['sub1_answer' => $response, 'sub2_answer' => 1, '-submit' => 1];
        $this->process_submission($submission);
        if ($behaviour === 'interactive') {
            $this->assertSame(\qbehaviour_interactive::TRY_AGAIN_VISIBLE, $this->adjusted_display_options()->readonly);
            $this->process_submission(['-tryagain' => 1]);
            $this->process_submission($submission);
        }
        $this->assertTrue($this->get_question_attempt()->get_state()->is_finished());
        $this->displayoptions->correctness = false;
        $this->displayoptions->marks = match ($option) {
            'maximum' => \question_display_options::MAX_ONLY,
            'marks' => \question_display_options::MARK_AND_MAX,
            default => \question_display_options::HIDDEN,
        };
        $this->displayoptions->feedback = $option === 'feedback';
        $this->displayoptions->rightanswer = $option === 'rightanswer';
        $this->assertTrue((bool) $this->adjusted_display_options()->readonly);
        $this->render();
        $xpath = $this->xpath($this->currentoutput);
        $input = $xpath->query('//input[@type="text"]')->item(0);
        $this->assertTrue($input->hasAttribute('readonly'));
        $this->assertFalse($input->hasAttribute('disabled'));
        $this->assertFalse($input->hasAttribute('tabindex'));
        $this->assertFalse($input->hasAttribute('data-clozeonimage-awaiting-retry'));
        $this->assertStringContainsString('form-control', $input->getAttribute('class'));
        $this->assertStringContainsString('qtype-clozeonimage-feedback-region', $input->parentNode->getAttribute('class'));
        $this->assertSame($response, $input->getAttribute('value'));
        $this->assertSame('popover', $input->getAttribute('data-bs-toggle'));
        $this->assertSame('hover focus', $input->getAttribute('data-bs-trigger'));
        $this->assertNotSame('', $input->getAttribute('data-bs-content'));
        $this->assert_no_semantic_result($xpath);
    }

    /**
     * Choice renderers and responses used to exercise the complete Interactive lifecycle.
     *
     * @return array<string, array{string,array<string,int>,array<string,int>,string,int}>
     */
    public static function interactive_choice_feedback_lifecycle_provider(): array {
        return [
            'vertical Multichoice' => [
                'multichoice_vertical',
                ['sub1_answer' => 1],
                ['sub1_answer' => 0],
                '//input[@data-role="clozeonimage-multichoice-choice"]',
                3,
            ],
            'horizontal Multiple Response' => [
                'multiresponse_horizontal',
                ['sub1_choice0' => 1, 'sub1_choice1' => 1],
                ['sub1_choice0' => 1, 'sub1_choice1' => 0, 'sub1_choice2' => 1, 'sub1_choice3' => 0],
                '//input[@type="checkbox"]',
                4,
            ],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('interactive_choice_feedback_lifecycle_provider')]
    public function test_interactive_choice_feedback_lifecycle(
        string $subquestiontype,
        array $wrongresponse,
        array $correctresponse,
        string $controlxpath,
        int $controlcount
    ): void {
        $question = $this->make_question(
            \qtype_clozeonimage::CONTROL_APPEARANCE_TRANSLUCENT,
            $subquestiontype
        );
        $question->subquestions[1]->shuffleanswers = false;
        $question->hints = [new \question_hint_with_parts(1, 'Hint', FORMAT_HTML, false, false)];
        $this->start_attempt_at_question($question, 'interactive', 1);

        $this->render();
        $this->assert_no_feedback_trigger_markup($this->xpath($this->currentoutput));

        $this->process_submission($wrongresponse + ['-submit' => 1]);
        $options = $this->adjusted_display_options();
        $this->assertSame(\qbehaviour_interactive::TRY_AGAIN_VISIBLE, $options->readonly);
        $this->assertTrue((bool) $options->feedback);

        $this->render();
        $xpath = $this->xpath($this->currentoutput);
        $controls = $xpath->query($controlxpath);
        $this->assertCount($controlcount, $controls);
        foreach ($controls as $control) {
            $this->assertTrue($control->hasAttribute('disabled'));
        }
        $this->assertCount(1, $xpath->query('//button[contains(concat(" ", normalize-space(@class), " "), ' .
            '" qtype-clozeonimage-review-surface ") and @data-bs-toggle="popover"]'));

        $this->process_submission(['-tryagain' => 1]);
        $options = $this->adjusted_display_options();
        $this->assertFalse($options->readonly);
        $this->assertFalse((bool) $options->correctness);
        $this->assertFalse((bool) $options->feedback);
        $this->assertFalse((bool) $options->rightanswer);
        $this->assertSame(\question_display_options::MARK_AND_MAX, $options->marks);

        $this->render();
        $xpath = $this->xpath($this->currentoutput);
        $controls = $xpath->query($controlxpath);
        $this->assertCount($controlcount, $controls);
        foreach ($controls as $control) {
            $this->assertFalse($control->hasAttribute('disabled'));
        }
        $this->assert_no_feedback_trigger_markup($xpath);
        $this->assertCount(0, $xpath->query('//button[contains(concat(" ", normalize-space(@class), " "), ' .
            '" qtype-clozeonimage-review-surface ")]'));

        $this->process_submission($correctresponse + ['-submit' => 1]);
        $options = $this->adjusted_display_options();
        $this->assertTrue($options->readonly);

        $this->render();
        $xpath = $this->xpath($this->currentoutput);
        $this->assertCount(1, $xpath->query('//button[contains(concat(" ", normalize-space(@class), " "), ' .
            '" qtype-clozeonimage-review-surface ") and @data-bs-toggle="popover"]'));
    }

    public function test_interactive_clearwrong_retained_correct_choice_has_no_stale_feedback(): void {
        $question = $this->make_question(
            \qtype_clozeonimage::CONTROL_APPEARANCE_TRANSLUCENT,
            'multichoice_vertical'
        );
        $question->subquestions[1]->shuffleanswers = false;
        $this->add_second_multichoice_subquestion($question);
        $question->subquestions[2]->shuffleanswers = false;
        $question->hints = [new \question_hint_with_parts(1, 'Hint', FORMAT_HTML, false, true)];
        $this->start_attempt_at_question($question, 'interactive', 1);

        // Server-rendered selections start without focus and therefore with Clear hidden.
        $this->process_submission(['sub1_answer' => 0, 'sub2_answer' => 1]);
        $this->render();
        $xpath = $this->xpath($this->currentoutput);
        $this->assertCount(2, $xpath->query('//button[@data-action="clozeonimage-clear-choice" and @hidden]'));

        $this->process_submission([
            'sub1_answer' => 0,
            'sub2_answer' => 1,
            '-submit' => 1,
        ]);
        $this->assertSame(
            \qbehaviour_interactive::TRY_AGAIN_VISIBLE,
            $this->adjusted_display_options()->readonly
        );
        $this->render();
        $xpath = $this->xpath($this->currentoutput);
        $this->assertCount(0, $xpath->query('//button[@data-action="clozeonimage-clear-choice"]'));
        $this->assertCount(6, $xpath->query('//input[@data-role="clozeonimage-multichoice-choice" and @disabled]'));

        $this->process_submission([
            'sub1_answer' => 0,
            'sub2_answer' => -1,
            '-tryagain' => 1,
        ]);
        $this->assertFalse($this->adjusted_display_options()->readonly);

        $this->render();
        $xpath = $this->xpath($this->currentoutput);
        $this->assert_no_feedback_trigger_markup($xpath);
        $this->assertCount(6, $xpath->query(
            '//input[@data-role="clozeonimage-multichoice-choice" and not(@disabled)]'
        ));
        $buttons = $xpath->query('//button[@data-action="clozeonimage-clear-choice"]');
        $this->assertCount(2, $buttons);
        $this->assertTrue($buttons->item(0)->hasAttribute('hidden'));
        $this->assertTrue($buttons->item(1)->hasAttribute('hidden'));
        $this->assertCount(1, $xpath->query('//input[@data-role="clozeonimage-multichoice-choice" and @checked and @value="0"]'));
        $this->assertTrue($this->get_question_attempt()->get_last_step()->has_behaviour_var('tryagain'));

        // A changed saved response also waits for focus after rendering.
        $this->process_submission(['sub1_answer' => 2, 'sub2_answer' => 2]);
        $this->render();
        $xpath = $this->xpath($this->currentoutput);
        $this->assertCount(2, $xpath->query('//button[@data-action="clozeonimage-clear-choice" and @hidden]'));
        $this->assertCount(2, $xpath->query('//input[@data-role="clozeonimage-multichoice-choice" and @checked and @value="2"]'));
    }

    public function test_interactive_clearwrong_preserves_unanswered_multichoice_through_actual_post(): void {
        $this->start_attempt_at_question($this->make_interactive_clearwrong_question(), 'interactive', 1);

        $this->process_submission([
            'sub1_answer' => 2,
            'sub2_answer' => 1,
            'sub3_answer' => 2,
            '-submit' => 1,
        ]);
        $this->assertSame(
            \qbehaviour_interactive::TRY_AGAIN_VISIBLE,
            $this->adjusted_display_options()->readonly
        );

        $this->render();
        $prefix = $this->quba->get_field_prefix($this->slot);
        $post = $this->try_again_post_from_current_output();
        $this->assertSame('2', $post[$prefix . 'sub1_answer']);
        $this->assertSame('-1', $post[$prefix . 'sub2_answer']);
        $this->assertSame('-1', $post[$prefix . 'sub3_answer']);

        $this->quba->process_all_actions(time(), $post);
        $questionattempt = $this->get_question_attempt();
        $stepdata = $questionattempt->get_last_step()->get_qt_data();
        $this->assertSame('2', $stepdata['sub1_answer']);
        $this->assertSame('-1', $stepdata['sub2_answer']);
        $this->assertSame('-1', $stepdata['sub3_answer']);
        $this->assertSame('2', $questionattempt->get_last_qt_var('sub1_answer'));
        $this->assertSame('-1', $questionattempt->get_last_qt_var('sub2_answer'));
        $this->assertSame('-1', $questionattempt->get_last_qt_var('sub3_answer'));

        $this->render();
        $xpath = $this->xpath($this->currentoutput);
        $correctname = $prefix . 'sub1_answer';
        $this->assertCount(1, $xpath->query(
            '//input[@data-role="clozeonimage-multichoice-choice" and @name="' . $correctname .
                '" and @value="2" and @checked and not(@disabled)]'
        ));
        $this->assertCount(3, $xpath->query('//button[@data-action="clozeonimage-clear-choice" and @hidden]'));

        foreach ([2, 3] as $index) {
            $inputname = $prefix . 'sub' . $index . '_answer';
            $this->assertCount(0, $xpath->query(
                '//input[@data-role="clozeonimage-multichoice-choice" and @name="' . $inputname .
                    '" and @checked]'
            ));
            $sentinels = $xpath->query(
                '//input[@data-role="clozeonimage-clear-choice-sentinel" and @name="' . $inputname . '"]'
            );
            $this->assertCount(1, $sentinels);
            $this->assertSame('-1', $sentinels->item(0)->getAttribute('value'));
            $this->assertSame('hidden', $sentinels->item(0)->getAttribute('type'));
            $this->assertFalse($sentinels->item(0)->hasAttribute('checked'));
            $this->assertFalse($sentinels->item(0)->hasAttribute('tabindex'));
            $this->assertFalse($sentinels->item(0)->hasAttribute('disabled'));

            $buttons = $xpath->query(
                '//*[@data-region="clozeonimage-multichoice" and .//input[@name="' . $inputname . '"]]' .
                    '//button[@data-action="clozeonimage-clear-choice"]'
            );
            $this->assertCount(1, $buttons);
            $this->assertTrue($buttons->item(0)->hasAttribute('hidden'));
        }
    }

    public function test_marks_only_readonly_review_retains_feedback_popover(): void {
        $this->start_attempt_at_question($this->make_question(), 'deferredfeedback', 1);
        $this->process_submission(['sub1_answer' => 'frog']);
        $this->finish();
        $this->displayoptions->correctness = false;
        $this->displayoptions->feedback = false;
        $this->displayoptions->rightanswer = false;
        $this->displayoptions->marks = \question_display_options::MARK_AND_MAX;

        $this->render();
        $xpath = $this->xpath($this->currentoutput);
        $triggers = $xpath->query('//input[@type="text" and @readonly and @data-bs-toggle="popover"]');
        $this->assertCount(1, $triggers);
        $feedback = $triggers->item(0)->getAttribute('data-bs-content');
        $this->assertStringContainsString(get_string('markoutofmax', 'question', (object) [
            'mark' => '1.00',
            'max' => '1.00',
        ]), $feedback);
        $this->assert_no_semantic_result($xpath);
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
        $this->assertCount(0, $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), ' .
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

    /**
     * Editable control families that expose the previous result after an Adaptive Check.
     *
     * @return array<string, array{string,array<string,int|string>,string,int}>
     */
    public static function adaptive_feedback_control_provider(): array {
        return [
            'Short Answer' => ['shortanswer', ['sub1_answer' => 'wrong'], '//input[@type="text"]', 1],
            'case-sensitive Short Answer' => [
                'shortanswer_case_sensitive', ['sub1_answer' => 'wrong'], '//input[@type="text"]', 1,
            ],
            'Numerical' => ['numerical', ['sub1_answer' => '999'], '//input[@type="text"]', 1],
            'dropdown Multichoice' => ['multichoice', ['sub1_answer' => 1], '//select', 1],
            'vertical Multichoice' => [
                'multichoice_vertical', ['sub1_answer' => 1],
                '//input[@data-role="clozeonimage-multichoice-choice"]', 3,
            ],
            'horizontal Multichoice' => [
                'multichoice_horizontal', ['sub1_answer' => 1],
                '//input[@data-role="clozeonimage-multichoice-choice"]', 3,
            ],
            'vertical Multiple Response' => [
                'multiresponse_vertical', ['sub1_choice0' => 1], '//input[@type="checkbox"]', 4,
            ],
            'horizontal Multiple Response' => [
                'multiresponse_horizontal', ['sub1_choice0' => 1], '//input[@type="checkbox"]', 4,
            ],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('adaptive_feedback_control_provider')]
    public function test_adaptive_feedback_uses_enabled_plugin_owned_native_triggers(
        string $subquestiontype,
        array $response,
        string $controlxpath,
        int $controlcount
    ): void {
        $this->start_attempt_at_question(
            $this->make_question(\qtype_clozeonimage::CONTROL_APPEARANCE_TRANSLUCENT, $subquestiontype),
            'adaptive',
            1
        );

        $this->render();
        $this->assert_no_feedback_trigger_markup($this->xpath($this->currentoutput));

        $this->process_submission($response + ['-submit' => 1]);
        $this->displayoptions->correctness = true;
        $this->displayoptions->feedback = true;
        $options = $this->adjusted_display_options();
        $this->assertFalse($options->readonly);
        $this->assertTrue((bool) $options->correctness);
        $this->assertTrue((bool) $options->feedback);
        $this->assertFalse((bool) $options->rightanswer);
        $this->assertSame(\question_display_options::MARK_AND_MAX, $options->marks);

        $this->render();
        $xpath = $this->xpath($this->currentoutput);
        $controls = $xpath->query($controlxpath);
        $this->assertCount($controlcount, $controls);
        $ischoicegroup = str_contains($subquestiontype, 'vertical') ||
            str_contains($subquestiontype, 'horizontal');
        foreach ($controls as $control) {
            $this->assertFalse($control->hasAttribute('disabled'));
            $this->assertFalse($control->hasAttribute('readonly'));
            $this->assertStringNotContainsString(' feedbacktrigger ', ' ' . $control->getAttribute('class') . ' ');
            if ($ischoicegroup) {
                $this->assertStringNotContainsString(
                    ' qtype-clozeonimage-feedback-trigger ',
                    ' ' . $control->getAttribute('class') . ' '
                );
                $this->assertFalse($control->hasAttribute('data-bs-toggle'));
                $this->assertFalse($control->hasAttribute('data-bs-content'));
            } else {
                $this->assertStringContainsString(
                    ' qtype-clozeonimage-feedback-trigger ',
                    ' ' . $control->getAttribute('class') . ' '
                );
                $this->assertSame('popover', $control->getAttribute('data-bs-toggle'));
                $this->assertNotSame('', $control->getAttribute('data-bs-content'));
            }
        }
        $this->assertCount(0, $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), ' .
            '" feedbacktrigger ")]'));
        $this->assertCount(0, $xpath->query('//button[contains(concat(" ", normalize-space(@class), " "), ' .
            '" qtype-clozeonimage-review-surface ")]'));
        $this->assertGreaterThanOrEqual(1, $xpath->query('//*[@data-role="clozeonimage-result-state"]')->length);

        if ($ischoicegroup) {
            $grouptriggers = $xpath->query(
                '//*[contains(concat(" ", normalize-space(@class), " "), ' .
                '" qtype-clozeonimage-feedback-region ") and ' .
                'contains(concat(" ", normalize-space(@class), " "), ' .
                '" qtype-clozeonimage-feedback-trigger ") and @data-bs-toggle="popover"]'
            );
            $this->assertCount(1, $grouptriggers);
            $this->assertNotSame('', $grouptriggers->item(0)->getAttribute('data-bs-content'));
            $this->assertCount(0, $xpath->query(
                '//*[contains(concat(" ", normalize-space(@class), " "), ' .
                '" qtype-clozeonimage-choice ")]//*[@data-bs-toggle="popover"]'
            ));
        }

        if ($subquestiontype === 'multichoice') {
            $selectclass = ' ' . $controls->item(0)->getAttribute('class') . ' ';
            $this->assertStringContainsString(' incorrect ', $selectclass);
            $this->assertStringNotContainsString(' correct ', $selectclass);
            $this->assertStringNotContainsString(' partiallycorrect ', $selectclass);
            $selectedoptions = $xpath->query('//select/option[@selected]');
            $this->assertCount(1, $selectedoptions);
            $this->assertCount(0, $xpath->query(
                '//select/option[contains(concat(" ", normalize-space(@class), " "), " correct ") or ' .
                'contains(concat(" ", normalize-space(@class), " "), " incorrect ") or ' .
                'contains(concat(" ", normalize-space(@class), " "), " partiallycorrect ")]'
            ));
            $this->assertCount(1, $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), ' .
                '" qtype-clozeonimage-state-incorrect ")]'));
        }
    }

    /**
     * Adaptive behaviours sharing focus-based Clear interactions.
     *
     * @return array<string, array{string}>
     */
    public static function adaptive_behaviour_provider(): array {
        return ['adaptive' => ['adaptive'], 'no penalties' => ['adaptivenopenalty']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('adaptive_behaviour_provider')]
    public function test_adaptive_checked_multichoice_waits_for_focus_to_show_clear(string $behaviour): void {
        $this->start_attempt_at_question(
            $this->make_question(
                \qtype_clozeonimage::CONTROL_APPEARANCE_TRANSLUCENT,
                'multichoice_vertical'
            ),
            $behaviour,
            1
        );
        $this->process_submission(['sub1_answer' => 1, '-submit' => 1]);
        $this->displayoptions->correctness = true;
        $this->displayoptions->feedback = true;

        $this->render();
        $xpath = $this->xpath($this->currentoutput);
        $this->assertCount(1, $xpath->query(
            '//input[@data-role="clozeonimage-multichoice-choice" and @value="1" and @checked]'
        ));
        $this->assertCount(1, $xpath->query(
            '//*[@data-region="clozeonimage-multichoice" and ' .
            'contains(concat(" ", normalize-space(@class), " "), ' .
            '" qtype-clozeonimage-feedback-trigger ")]'
        ));
        $clearbuttons = $xpath->query('//button[@data-action="clozeonimage-clear-choice"]');
        $this->assertCount(1, $clearbuttons);
        $this->assertTrue($clearbuttons->item(0)->hasAttribute('hidden'));
        $this->assertCount(1, $xpath->query('//*[@data-region="clozeonimage-multichoice" and ' .
            'contains(@class, "qtype-clozeonimage-state-incorrect")]'));

        $this->process_submission(['sub1_answer' => 2]);
        $this->displayoptions->correctness = false;
        $this->displayoptions->feedback = false;
        $this->render();
        $xpath = $this->xpath($this->currentoutput);
        $this->assert_no_feedback_trigger_markup($xpath);
        $this->assertCount(1, $xpath->query(
            '//input[@data-role="clozeonimage-multichoice-choice" and @value="2" and @checked]'
        ));
        $clearbuttons = $xpath->query('//button[@data-action="clozeonimage-clear-choice"]');
        $this->assertCount(1, $clearbuttons);
        $this->assertTrue($clearbuttons->item(0)->hasAttribute('hidden'));

        $this->process_submission(['sub1_answer' => 0, '-submit' => 1]);
        $this->displayoptions->correctness = true;
        $this->displayoptions->feedback = true;
        $this->render();
        $xpath = $this->xpath($this->currentoutput);
        $this->assertCount(1, $xpath->query('//*[@data-region="clozeonimage-multichoice" and ' .
            'contains(@class, "qtype-clozeonimage-state-correct")]'));
        $this->assertCount(1, $xpath->query('//button[@data-action="clozeonimage-clear-choice" and @hidden]'));
        $this->assertCount(1, $xpath->query('//input[@data-role="clozeonimage-multichoice-choice" and @checked and @value="0"]'));
    }

    /**
     * Local results across the behaviours that expose graded popovers.
     *
     * @return array<string, array{string,string,string,array<string,int|string>}>
     */
    public static function unified_result_provider(): array {
        $responses = [
            'shortanswer' => ['correct' => ['sub1_answer' => 'frog'],
                'partiallycorrect' => ['sub1_answer' => 'toad'], 'incorrect' => ['sub1_answer' => 'wrong']],
            'numerical' => ['correct' => ['sub1_answer' => '3.14'],
                'partiallycorrect' => ['sub1_answer' => '3.1'], 'incorrect' => ['sub1_answer' => '999']],
            'multichoice' => ['correct' => ['sub1_answer' => 0],
                'partiallycorrect' => ['sub1_answer' => 1], 'incorrect' => ['sub1_answer' => 2]],
            'multichoice_vertical' => ['correct' => ['sub1_answer' => 0],
                'partiallycorrect' => ['sub1_answer' => 1], 'incorrect' => ['sub1_answer' => 2]],
            'multiresponse_horizontal' => ['correct' => ['sub1_choice0' => 1, 'sub1_choice2' => 1],
                'partiallycorrect' => ['sub1_choice0' => 1], 'incorrect' => ['sub1_choice1' => 1]],
        ];
        $cases = [];
        foreach (
            ['interactive', 'adaptive', 'adaptivenopenalty', 'immediatefeedback',
                'deferredfeedback', 'immediatecbm', 'deferredcbm'] as $behaviour
        ) {
            foreach ($responses as $type => $states) {
                foreach ($states as $state => $response) {
                    $cases[$behaviour . ' ' . $type . ' ' . $state] = [$behaviour, $type, $state, $response];
                }
            }
        }
        return $cases;
    }

    /**
     * Independent option combinations for every control family and every supported behaviour.
     *
     * @return array<string, array{string,string,string,array,bool,int,bool,bool}>
     */
    public static function independent_review_options_provider(): array {
        $cases = [];
        foreach (self::unified_result_provider() as $name => [$behaviour, $type, $state, $response]) {
            if ($state !== 'partiallycorrect' || ($behaviour !== 'deferredfeedback' && $type !== 'shortanswer')) {
                continue;
            }
            foreach ([false, true] as $correctness) {
                foreach ([0, 1, 2] as $marks) {
                    foreach ([false, true] as $feedback) {
                        foreach ([false, true] as $rightanswer) {
                            $key = $name . ' ' . (int) $correctness . $marks . (int) $feedback . (int) $rightanswer;
                            $cases[$key] = [$behaviour, $type, $state, $response,
                                $correctness, $marks, $feedback, $rightanswer];
                        }
                    }
                }
            }
        }
        return $cases;
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('independent_review_options_provider')]
    public function test_independent_effective_review_options(
        string $behaviour,
        string $type,
        string $state,
        array $response,
        bool $correctness,
        int $marks,
        bool $feedback,
        bool $rightanswer
    ): void {
        $question = $this->make_question(\qtype_clozeonimage::CONTROL_APPEARANCE_TRANSLUCENT, $type);
        $subq = $question->subquestions[1];
        if (str_starts_with($type, 'multichoice')) {
            $subq->answers[14]->fraction = 0.5;
        } else if ($type === 'numerical') {
            $subq->answers[15]->fraction = 0.5;
        }
        $subq->defaultmark = 2.5;
        foreach ($subq->answers as $answer) {
            $answer->feedback = 'Selected response explanation';
        }
        $question->generalfeedback = 'Whole question explanation';
        $question->hints = [new \question_hint_with_parts(1, 'Hint', FORMAT_HTML, false, false)];
        $this->start_attempt_at_question($question, $behaviour, 2.5);
        $submission = $response;
        if (str_contains($behaviour, 'cbm')) {
            $submission['-certainty'] = 2;
        }
        if (!str_starts_with($behaviour, 'deferred')) {
            $submission['-submit'] = 1;
        }
        $this->process_submission($submission);
        if (str_starts_with($behaviour, 'deferred')) {
            $this->finish();
        }
        $this->displayoptions->correctness = $correctness;
        $this->displayoptions->marks = $marks;
        $this->displayoptions->feedback = $feedback;
        $this->displayoptions->rightanswer = $rightanswer;
        $this->displayoptions->generalfeedback = true;
        $this->displayoptions->markdp = 3;
        $options = $this->adjusted_display_options();
        $this->render();
        $xpath = $this->xpath($this->currentoutput);
        if ($options->correctness) {
            $this->assert_current_result($state);
        } else {
            $this->assert_no_semantic_result($xpath);
        }
        $triggers = $xpath->query('//*[@data-bs-content and contains(@class, "qtype-clozeonimage-")]');
        $sectioncount = (int) (bool) $options->correctness + (int) (bool) $options->feedback +
            (int) (bool) $options->rightanswer + (int) ($options->marks !== 0);
        $this->assertCount($sectioncount ? 1 : 0, $triggers);
        $popuphtml = $sectioncount ? $triggers->item(0)->getAttribute('data-bs-content') : '';
        $this->assertSame((bool) $options->feedback, str_contains($popuphtml, 'Selected response explanation'));
        $this->assertSame((bool) $options->rightanswer, str_contains(
            $popuphtml,
            get_string('correctansweris', 'qtype_shortanswer', '')
        ));
        $this->assertStringNotContainsString('Whole question explanation', $popuphtml);
        $this->assertSame((bool) $options->generalfeedback, str_contains($this->currentoutput, 'Whole question explanation'));
        $maxtext = get_string('markedoutofmax', 'question', format_float(2.5, 3));
        $fraction = $type === 'shortanswer' ? 0.8 : 0.5;
        $marktext = get_string('markoutofmax', 'question', (object) [
            'mark' => format_float($fraction * 2.5, 3), 'max' => format_float(2.5, 3),
        ]);
        $this->assertSame($marks === \question_display_options::MAX_ONLY, str_contains($popuphtml, $maxtext));
        $this->assertSame($marks === \question_display_options::MARK_AND_MAX, str_contains($popuphtml, $marktext));
        if ($sectioncount) {
            $popup = $this->xpath($popuphtml);
            $this->assertCount($sectioncount, $popup->query('//*[contains(@class, "qtype-clozeonimage-feedback-section")]'));
        }
    }

    /**
     * Assert that the first subquestion's popup, outline hook, and accessible status agree.
     *
     * @param string $state Expected local grade state.
     */
    private function assert_current_result(string $state): void {
        $xpath = $this->xpath($this->currentoutput);
        $region = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), ' .
            '" qtype-clozeonimage-feedback-region ")]')->item(0);
        $this->assertNotNull($region);
        $this->assertStringContainsString('qtype-clozeonimage-state-' . $state, $region->getAttribute('class'));
        $triggers = $xpath->query('descendant-or-self::*[@data-bs-toggle="popover"]', $region);
        $this->assertCount(1, $triggers);
        $popup = $this->xpath($triggers->item(0)->getAttribute('data-bs-content'));
        $states = $popup->query('//*[@data-clozeonimage-result-state]');
        $this->assertCount(1, $states);
        $this->assertSame($state, $states->item(0)->getAttribute('data-clozeonimage-result-state'));
        $this->assertGreaterThan(0, $xpath->query('.//*[@data-role="clozeonimage-result-state"]', $region)->length);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('unified_result_provider')]
    public function test_unified_result_across_behaviours(
        string $behaviour,
        string $type,
        string $state,
        array $response
    ): void {
        $question = $this->make_question(\qtype_clozeonimage::CONTROL_APPEARANCE_TRANSLUCENT, $type);
        if (str_starts_with($type, 'multichoice')) {
            $question->subquestions[1]->answers[14]->fraction = 0.5;
        } else if ($type === 'numerical') {
            $question->subquestions[1]->answers[15]->fraction = 0.5;
        }
        $this->add_second_multichoice_subquestion($question);
        $question->subquestions[2]->shuffleanswers = false;
        $question->hints = [new \question_hint_with_parts(1, 'Hint', FORMAT_HTML, false, false)];
        $this->start_attempt_at_question($question, $behaviour, 1);
        $this->render();
        // CBM's native certainty-help popover is ancillary, not a subquestion result.
        $xpath = $this->xpath($this->currentoutput);
        $this->assertCount(0, $xpath->query('//*[contains(@class, "qtype-clozeonimage-feedback-trigger")]'));
        $this->assertCount(0, $xpath->query('//*[contains(@class, "qtype-clozeonimage-state-")]'));

        // Keep the composite incorrect so Interactive offers another try, even for a correct local answer.
        $submission = $response + ['sub2_answer' => 1];
        if (str_contains($behaviour, 'cbm')) {
            $submission['-certainty'] = 2;
        }
        if (!str_starts_with($behaviour, 'deferred')) {
            $submission['-submit'] = 1;
        }
        $this->process_submission($submission);
        if (str_starts_with($behaviour, 'deferred')) {
            $this->finish();
        }
        $this->displayoptions->correctness = true;
        $this->displayoptions->feedback = true;
        $this->render();
        $options = $this->adjusted_display_options();
        $xpath = $this->xpath($this->currentoutput);
        if ($options->correctness) {
            $this->assert_current_result($state);
        } else {
            $this->assert_no_semantic_result($xpath);
        }
        if ($options->readonly) {
            $this->assertCount(0, $xpath->query('//button[@data-action="clozeonimage-clear-choice"]'));
            $this->assertCount(0, $xpath->query('//input[@data-role="clozeonimage-clear-choice-sentinel"]'));
        }
        if (in_array($type, ['shortanswer', 'numerical'])) {
            $input = $xpath->query('//input[@type="text"]')->item(0);
            $this->assertFalse($input->hasAttribute('disabled'));
            $this->assertSame((bool) $options->readonly, $input->hasAttribute('readonly'));
        }
        if ($behaviour === 'interactive') {
            $this->assertSame(\qbehaviour_interactive::TRY_AGAIN_VISIBLE, $options->readonly);
            $this->assertFalse((bool) $options->correctness);
            $this->assertTrue((bool) $options->feedback);
            $this->assertFalse((bool) $options->rightanswer);
            $this->assertCount(1, $xpath->query('//input[contains(@name, "-tryagain") and not(@disabled)]'));

            $this->process_submission(['-tryagain' => 1]);
            $this->render();
            $xpath = $this->xpath($this->currentoutput);
            $this->assert_no_feedback_trigger_markup($xpath);
            $this->assertCount(0, $xpath->query('//*[contains(@class, "qtype-clozeonimage-state-")]'));

            // Exhaust the final try and verify the same model on locked results.
            $this->process_submission($submission);
            $this->assertTrue($this->adjusted_display_options()->readonly);
            $this->render();
            $this->assert_current_result($state);
            $xpath = $this->xpath($this->currentoutput);
            $this->assertCount(0, $xpath->query('//button[@data-action="clozeonimage-clear-choice"]'));

            $this->finish();
            $this->displayoptions->readonly = true;
            $this->displayoptions->correctness = false;
            $this->render();
            $this->assert_no_semantic_result($this->xpath($this->currentoutput));
            $this->displayoptions->correctness = true;
            $this->render();
            $this->assert_current_result($state);
        }
    }

    /**
     * Result states displayed on an editable Adaptive dropdown.
     *
     * @return array<string, array{string,?float,string}>
     */
    public static function adaptive_dropdown_state_provider(): array {
        return [
            'correct' => ['0', null, 'correct'],
            'partially correct' => ['1', 0.5, 'partiallycorrect'],
            'incorrect' => ['1', null, 'incorrect'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('adaptive_dropdown_state_provider')]
    public function test_adaptive_dropdown_carries_local_state_on_select_only(
        string $response,
        ?float $answerfraction,
        string $expectedstate
    ): void {
        $question = $this->make_question(
            \qtype_clozeonimage::CONTROL_APPEARANCE_TRANSLUCENT,
            'multichoice'
        );
        if ($answerfraction !== null) {
            $question->subquestions[1]->answers[14]->fraction = $answerfraction;
        }
        $this->start_attempt_at_question($question, 'adaptive', 1);
        $this->process_submission(['sub1_answer' => $response, '-submit' => 1]);
        $this->displayoptions->correctness = true;
        $this->displayoptions->feedback = true;

        $this->render();
        $xpath = $this->xpath($this->currentoutput);
        $selects = $xpath->query('//select[contains(concat(" ", normalize-space(@class), " "), ' .
            '" ' . $expectedstate . ' ")]');
        $this->assertCount(1, $selects);
        $select = $selects->item(0);
        $this->assertFalse($select->hasAttribute('disabled'));
        $this->assertCount(1, $xpath->query('.//option[@selected]', $select));
        $this->assertCount(0, $xpath->query(
            './/option[contains(concat(" ", normalize-space(@class), " "), " correct ") or ' .
            'contains(concat(" ", normalize-space(@class), " "), " incorrect ") or ' .
            'contains(concat(" ", normalize-space(@class), " "), " partiallycorrect ")]',
            $select
        ));
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
