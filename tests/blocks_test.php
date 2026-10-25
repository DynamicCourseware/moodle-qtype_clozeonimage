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

use question_bank;

defined('MOODLE_INTERNAL') || die();
question_bank::get_qtype('clozeonimage');

/**
 * Content block persistence and integration with the standard Cloze engine.
 *
 * @package qtype_clozeonimage
 * @copyright 2026 DynamicCourseware.org (Dominique Bauer)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\qtype_clozeonimage::class)]
final class blocks_test extends \advanced_testcase {
    /** Detection uses parsed content, leaving answers and feedback to the core parser. */
    public function test_automatic_block_detection(): void {
        foreach (['{#1}', '<p>&nbsp;<span><strong>{#1}</strong></span><br></p>',
            '<div><p> </p><p>{#1}</p><p><br></p></div>', '<!-- caption --><p>{#1}</p>'] as $html) {
            $this->assertTrue(\qtype_clozeonimage::is_automatic_block($html, 1), $html);
        }
        foreach (['', '<p>{#1} kN</p>', '<p>F = {#1}</p>', '{#1}<img src="test.png" alt="">',
            '<video></video>{#1}', '<hr>{#1}', '<table><tr><td>{#1}</td></tr></table>',
            '{#1}{#2}', '<p>&lt;img&gt; {#1}</p>'] as $html) {
            $this->assertFalse(\qtype_clozeonimage::is_automatic_block($html, 1), $html);
        }
        $this->assertFalse(\qtype_clozeonimage::is_automatic_block('{#1}', 0));
        $this->assertFalse(\qtype_clozeonimage::is_automatic_block('{#1}', 2));
        foreach (['NUMERICAL', 'SHORTANSWER', 'SHORTANSWER_C', 'MULTICHOICE', 'MULTICHOICE_V',
                'MULTICHOICE_H', 'MULTIRESPONSE', 'MULTIRESPONSE_H'] as $type) {
            [$parsed, $diagnostics] = \qtype_clozeonimage::diagnose_block_source('<p>{1:' . $type . ':=42~0}</p>');
            $this->assertEmpty($diagnostics);
            $this->assertTrue(\qtype_clozeonimage::is_automatic_block(
                $parsed->questiontext['text'], count($parsed->options->questions)), $type);
        }
    }

    /** Every supported canonical family, including the shuffled variants. */
    public static function flow_family_provider(): array {
        $types = ['NUMERICAL', 'SHORTANSWER', 'SHORTANSWER_C', 'MULTICHOICE', 'MULTICHOICE_S',
            'MULTICHOICE_V', 'MULTICHOICE_VS', 'MULTICHOICE_H', 'MULTICHOICE_HS',
            'MULTIRESPONSE', 'MULTIRESPONSE_S', 'MULTIRESPONSE_H', 'MULTIRESPONSE_HS'];
        return array_combine($types, array_map(fn($type) => [$type], $types));
    }

    /** Valid inline controls must not cause the browser to close or reconstruct source paragraphs. */
    #[\PHPUnit\Framework\Attributes\DataProvider('flow_family_provider')]
    public function test_control_flow_preserves_paragraphs_and_grading(string $type): void {
        global $CFG, $PAGE;
        require_once($CFG->dirroot . '/question/type/clozeonimage/edit_clozeonimage_form.php');
        [$original, $data, $context] = $this->fixture();
        $expression = '{1:' . $type . ':=3~2~4}';
        $sources = ['<p><span style="font-size:40px;color:orange">' . $expression . '</span></p>',
            '<p>' . $expression . $expression . '</p>',
            '<p>' . $expression . '</p><p>' . $expression . '</p>',
            '<p>' . $expression . '<br>' . $expression . '</p>',
            '<p>Before ' . $expression . ' between ' . $expression . ' after</p>',
            '<p>Mix ' . $expression . ' with {1:NUMERICAL:=42:0} end</p>'];
        $data->subquestion = array_map(fn($text) => ['text' => $text, 'format' => FORMAT_HTML], $sources);
        foreach (['xleft', 'ytop', 'anchor'] as $field) {
            $data->$field = array_fill(0, 6, 0);
        }
        $data->blockwidth = array_fill(0, 6, 700);
        $data->roworigin = range(1, 6);
        $question = question_bank::load_question_data(
            question_bank::get_qtype('clozeonimage')->save_question(clone $original, $data)->id);
        $this->assertEquals(11, $question->defaultmark);
        $runtime = question_bank::make_question($question);
        $usage = \question_engine::make_questions_usage_by_activity('qtype_clozeonimage', $context);
        $usage->set_preferred_behaviour('deferredfeedback');
        $usage->add_question($runtime);
        $usage->start_all_questions();
        $this->assertEquals(1, $runtime->grade_response($runtime->get_correct_response())[0]);
        $PAGE->set_context($context);
        $PAGE->set_url(new \moodle_url('/'));
        $studenthtml = $usage->get_question_attempt(1)->render(new \question_display_options(), 1);
        $question->formoptions = (object)['canmove' => false, 'cansaveasnew' => true, 'canedit' => true,
            'movecontext' => null, 'repeatelements' => true];
        $question->beingcopied = false;
        $question->inputs = null;
        $form = new \qtype_clozeonimage_edit_form(new \moodle_url('/'), $question, $question->categoryobject,
            new \core_question\local\bank\question_edit_contexts($context));
        $form->set_data($question);
        foreach ([$studenthtml, $form->render()] as $html) {
            $dom = new \DOMDocument();
            @$dom->loadHTML($html);
            $xpath = new \DOMXPath($dom);
            $blocks = $xpath->query('//*[contains(concat(" ",normalize-space(@class)," ")," qtype-clozeonimage-block ")]');
            $this->assertCount(6, $blocks);
            foreach ($blocks as $index => $block) {
                $controls = $xpath->query('.//span[contains(concat(" ",@class," ")," qtype-clozeonimage-control ")]', $block);
                $this->assertCount($index === 0 ? 1 : 2, $controls);
                $automatictextfield = $index === 0 && in_array($type, ['NUMERICAL', 'SHORTANSWER', 'SHORTANSWER_C']);
                $this->assertCount($index === 0 ? (int)$automatictextfield : ($index === 2 ? 2 : 1),
                    $xpath->query('./p', $block));
                if ($automatictextfield) {
                    // Keep inherited typography through wrappers that CSS removes from the box tree.
                    $this->assertStringContainsString('qtype-clozeonimage-auto-wrapper',
                        $xpath->query('./p', $block)->item(0)->getAttribute('class'));
                    $wrapper = $xpath->query('./p/span', $block)->item(0);
                    $this->assertStringContainsString('font-size:40px', $wrapper->getAttribute('style'));
                    $this->assertStringContainsString('qtype-clozeonimage-authored-color', $wrapper->getAttribute('class'));
                }
                $this->assertCount($index === 3 ? 1 : 0, $xpath->query('./p/br', $block));
                foreach ($controls as $control) {
                    $this->assertCount(0, $xpath->query('.//div | .//fieldset | .//table | .//p', $control));
                    $this->assertGreaterThan(0, $xpath->query('.//input | .//select', $control)->length);
                    foreach ($xpath->query('.//*[@role="radiogroup" or @role="group"]', $control) as $group) {
                        $this->assertTrue($group->hasAttribute('aria-label') || $group->hasAttribute('aria-labelledby'));
                    }
                }
            }
        }
    }

    /** The browser must receive the saved outer anchor when enforcing an effective minimum. */
    public function test_runtime_exposes_each_saved_block_anchor(): void {
        global $PAGE;
        [$question, , $context] = $this->fixture();
        $runtime = question_bank::load_question($question->id);
        $usage = \question_engine::make_questions_usage_by_activity('qtype_clozeonimage', $context);
        $usage->set_preferred_behaviour('deferredfeedback');
        $usage->add_question($runtime);
        $usage->start_all_questions();
        $PAGE->set_context($context);
        $PAGE->set_url(new \moodle_url('/'));
        $dom = new \DOMDocument();
        @$dom->loadHTML($usage->get_question_attempt(1)->render(new \question_display_options(), 1));
        $xpath = new \DOMXPath($dom);
        $blocks = $xpath->query('//*[contains(concat(" ",@class," ")," qtype-clozeonimage-block ")]');
        $this->assertCount(3, $blocks);
        foreach ([0, 4, 8] as $index => $anchor) {
            $this->assertSame((string)$anchor, $blocks->item($index)->getAttribute('data-anchor'));
        }
    }

    /** Source diagnostics must not overwrite either a saved anchor or the teacher's submitted choice. */
    public function test_invalid_source_preserves_saved_and_submitted_anchors(): void {
        global $CFG, $PAGE;
        require_once($CFG->dirroot . '/question/type/clozeonimage/edit_clozeonimage_form.php');
        [$saved, $data, $context] = $this->fixture();
        $PAGE->set_context($context);
        $PAGE->set_url(new \moodle_url('/'));
        $previous = $_POST;
        try {
            foreach (['{1:SHORTANSWER(150px,100px):=Paris}', '{1:NUMERICAL:=not-a-number}',
                    '{1:SHORTANSWER:=Paris'] as $invalid) {
                foreach ([false, true] as $submitted) {
                    $question = unserialize(serialize($saved));
                    $question->formoptions = (object)['canmove' => false, 'cansaveasnew' => true, 'canedit' => true,
                        'movecontext' => null, 'repeatelements' => true];
                    $question->beingcopied = false;
                    $question->inputs = null;
                    $question->options->positions[2]->content = $invalid;
                    $_POST = [];
                    if ($submitted) {
                        $_POST = ['_qf__qtype_clozeonimage_edit_form' => 1, 'sesskey' => sesskey(),
                            'updatepreview' => 1, 'nosubquestions' => 3, 'subquestion' => $data->subquestion,
                            'anchor' => [0, 6, 8], 'xleft' => $data->xleft, 'ytop' => $data->ytop];
                        $_POST['subquestion'][1]['text'] = $invalid;
                    }
                    $form = new \qtype_clozeonimage_edit_form(new \moodle_url('/'), $question, $question->categoryobject,
                        new \core_question\local\bank\question_edit_contexts($context));
                    $form->set_data($question);
                    $dom = new \DOMDocument();
                    @$dom->loadHTML($form->render());
                    $xpath = new \DOMXPath($dom);
                    foreach ([0, $submitted ? 6 : 4, 8] as $row => $anchor) {
                        $checked = $xpath->query('//input[@name="anchor[' . $row . ']" and @checked]');
                        $this->assertCount(1, $checked, $invalid);
                        $this->assertSame((string)$anchor, $checked->item(0)->getAttribute('value'));
                    }
                }
            }
        } finally {
            $_POST = $previous;
        }
    }

    /** Insertion uses a non-saving Moodle form rebuild, including independent editor drafts. */
    public function test_insert_blocks_rebuild_preserves_drafts_without_saving(): void {
        global $CFG, $DB, $PAGE;
        require_once($CFG->dirroot . '/question/type/clozeonimage/edit_clozeonimage_form.php');
        [$original, $data, $context] = $this->fixture(true);
        $questioncount = $DB->count_records('question');
        $PAGE->set_context($context);
        $PAGE->set_url(new \moodle_url('/'));
        $original->formoptions = (object)['canmove' => false, 'cansaveasnew' => true, 'canedit' => true,
            'movecontext' => null, 'repeatelements' => true];
        $original->beingcopied = false;
        $original->inputs = null;
        $previous = $_POST;
        try {
            $_POST = array_merge(array_intersect_key((array)$data, array_flip(['category', 'name', 'questiontext',
                'generalfeedback', 'penalty', 'status', 'idnumber', 'subquestion', 'xleft', 'ytop',
                'blockwidth', 'anchor', 'roworigin', 'displaywidth', 'bgimage', 'aftertext', 'hint',
                'hintclearwrong', 'hintshownumcorrect'])), ['_qf__qtype_clozeonimage_edit_form' => 1, 'sesskey' => sesskey(),
                'nosubquestions' => 3, 'roworigincount' => 3, 'insertblocks' => 'Insert',
                'insertblockcount' => 2, 'insertbefore' => '0,2']);
            $form = new \qtype_clozeonimage_edit_form(new \moodle_url('/'), $original, $original->categoryobject,
                new \core_question\local\bank\question_edit_contexts($context));
            $form->set_data($original);
            $this->assertTrue($form->no_submit_button_pressed());
            $this->assertNull($form->get_data());
            $this->assertSame($questioncount, $DB->count_records('question'));
            foreach ([2 => 0, 3 => 1, 6 => 2] as $new => $old) {
                foreach (['subquestion', 'roworigin', 'xleft', 'ytop', 'blockwidth', 'anchor'] as $field) {
                    $this->assertSame($data->{$field}[$old], $_POST[$field][$new], $field);
                }
            }
            $dom = new \DOMDocument();
            @$dom->loadHTML($form->render());
            $xpath = new \DOMXPath($dom);
            $editors = $xpath->query('//*[@id="qtype-clozeonimage-rows"]//textarea');
            $this->assertCount(7, $editors);
            $ids = [];
            $drafts = [];
            foreach ($editors as $index => $editor) {
                $ids[] = $editor->getAttribute('id');
                $draft = $xpath->query('//input[@name="subquestion[' . $index . '][itemid]"]')->item(0);
                $drafts[] = $draft->getAttribute('value');
                $this->assertGreaterThan(0, (int)end($drafts));
            }
            $this->assertCount(7, array_unique($ids));
            $this->assertCount(7, array_unique($drafts));
        } finally {
            $_POST = $previous;
        }
    }

    /** Deletion/reorder rebuilds retain editor identity, file drafts and geometry without saving. */
    public function test_delete_reorder_rebuild_retains_identity_without_saving(): void {
        global $CFG, $DB, $PAGE;
        require_once($CFG->dirroot . '/question/type/clozeonimage/edit_clozeonimage_form.php');
        [$original, $data, $context] = $this->fixture(true);
        $questioncount = $DB->count_records('question');
        $PAGE->set_context($context);
        $PAGE->set_url(new \moodle_url('/'));
        $original->formoptions = (object)['canmove' => false, 'cansaveasnew' => true, 'canedit' => true,
            'movecontext' => null, 'repeatelements' => true];
        $original->beingcopied = false;
        $original->inputs = null;
        $previous = $_POST;
        try {
            $_POST = array_merge(array_intersect_key((array)$data, array_flip(['category', 'name', 'questiontext',
                'generalfeedback', 'penalty', 'status', 'idnumber', 'subquestion', 'xleft', 'ytop',
                'blockwidth', 'anchor', 'roworigin', 'displaywidth', 'bgimage', 'aftertext', 'hint',
                'hintclearwrong', 'hintshownumcorrect'])), ['_qf__qtype_clozeonimage_edit_form' => 1, 'sesskey' => sesskey(),
                'nosubquestions' => 3, 'roworigincount' => 3, 'rebuildblocks' => 'Rebuild',
                'blockorder' => '2,0', 'blockinitialcount' => 3, 'blockdraftscope' => str_repeat('a', 32)]);
            $form = new \qtype_clozeonimage_edit_form(new \moodle_url('/'), $original, $original->categoryobject,
                new \core_question\local\bank\question_edit_contexts($context));
            $form->set_data($original);
            $this->assertTrue($form->no_submit_button_pressed());
            $this->assertNull($form->get_data());
            $this->assertSame($questioncount, $DB->count_records('question'));
            foreach ([0 => 2, 1 => 0] as $new => $old) {
                foreach (['subquestion', 'roworigin', 'xleft', 'ytop', 'blockwidth', 'anchor'] as $field) {
                    $this->assertSame($data->{$field}[$old], $_POST[$field][$new], $field);
                }
            }
            $dom = new \DOMDocument();
            @$dom->loadHTML($form->render());
            $xpath = new \DOMXPath($dom);
            $editors = $xpath->query('//*[@id="qtype-clozeonimage-rows"]//textarea');
            $this->assertCount(2, $editors);
            $ids = [];
            $drafts = [];
            foreach ($editors as $index => $editor) {
                $ids[] = $editor->getAttribute('id');
                $draft = $xpath->query('//input[@name="subquestion[' . $index . '][itemid]"]')->item(0);
                $drafts[] = $draft->getAttribute('value');
                $this->assertGreaterThan(0, (int)end($drafts));
            }
            $this->assertSame(['id_coi_q' . $original->id . '_initial_3',
                'id_coi_q' . $original->id . '_initial_1'], $ids);
            $this->assertSame([(string)$data->subquestion[2]['itemid'],
                (string)$data->subquestion[0]['itemid']], $drafts);
            $this->assertCount(2, array_unique($ids));
            $this->assertCount(2, array_unique($drafts));
        } finally {
            $_POST = $previous;
        }
    }

    /** @var string[] Single, formatted and mixed blocks. */
    private const CONTENTS = [
        '{1:NUMERICAL:=100:0#Correct force}',
        '<p><strong>Force</strong> F = {1:NUMERICAL:=100:0#Correct force} <em>kN</em></p>',
        '<p>City {1:MULTICHOICE:=Paris#Correct city~Rome#Wrong city}, ' .
            'animal {1:SHORTANSWER:=Owl#Correct animal~Dog#Wrong animal}, ' .
            'count {1:NUMERICAL:=42:0#Correct count}.</p>',
    ];

    /** Create real saved blocks with an image, using the ordinary question save API. */
    private function fixture(bool $withfiles = false): array {
        global $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $bank = $this->getDataGenerator()->get_plugin_generator($withfiles ? 'mod_quiz' : 'mod_qbank')
            ->create_instance(['course' => $course->id]);
        $context = \context_module::instance($bank->cmid);
        $category = $this->getDataGenerator()->get_plugin_generator('core_question')->create_question_category([
            'contextid' => $context->id,
        ]);
        $draft = file_get_unused_draft_itemid();
        get_file_storage()->create_file_from_string((object)[
            'contextid' => \context_user::instance($USER->id)->id, 'component' => 'user', 'filearea' => 'draft',
            'itemid' => $draft, 'filepath' => '/', 'filename' => 'blocks.png',
        ], base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aS1kAAAAASUVORK5CYII='));
        $form = (object)[
            'category' => $category->id . ',' . $context->id, 'name' => 'Content blocks',
            'questiontext' => ['text' => 'Answer in the image.', 'format' => FORMAT_HTML, 'itemid' => 0],
            'generalfeedback' => ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0],
            'penalty' => 0.3333333, 'status' => 'ready', 'idnumber' => null,
            'subquestion' => array_map(fn($text) => ['text' => $text, 'format' => FORMAT_HTML], self::CONTENTS),
            'xleft' => [10, -20, 300], 'ytop' => [20, 120, 220], 'blockwidth' => [160, 280, 440],
            'anchor' => [0, 4, 8], 'roworigin' => [1, 2, 3], 'displaywidth' => 800,
            'bgimage' => $draft, 'aftertext' => ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0],
            'hint' => [], 'hintclearwrong' => [], 'hintshownumcorrect' => [],
        ];
        if ($withfiles) {
            foreach ($form->subquestion as $index => &$source) {
                $draftid = file_get_unused_draft_itemid();
                $file = get_file_storage()->create_file_from_string((object)[
                    'contextid' => \context_user::instance($USER->id)->id, 'component' => 'user', 'filearea' => 'draft',
                    'itemid' => $draftid, 'filepath' => '/block' . $index . '/', 'filename' => 'image.png',
                ], 'image bytes for block ' . $index);
                $source['text'] .= '<img alt="Block image" width="60" src="' .
                    \moodle_url::make_draftfile_url($draftid, $file->get_filepath(), $file->get_filename()) . '">' .
                    '<img alt="External image" src="https://example.invalid/external.png">';
                $source['itemid'] = $draftid;
            }
            unset($source);
        }
        $saved = question_bank::get_qtype('clozeonimage')->save_question(
            (object)['qtype' => 'clozeonimage', 'createdby' => $USER->id, 'status' => 'ready', 'idnumber' => null], $form);
        return [question_bank::load_question_data($saved->id), $form, $context, $course];
    }

    /** Width source and slot mapping survive saves, copies, XML and Moodle backup/restore. */
    #[\PHPUnit\Framework\Attributes\DataProvider('explicit_width_units')]
    public function test_explicit_field_widths_round_trip(bool $relative): void {
        global $CFG, $PAGE;
        require_once($CFG->dirroot . '/question/format/xml/format.php');
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
        [$original, $form, $context, $course] = $this->fixture(true);
        $form = $this->prepare_file_edit($original, $form, $context);
        $parameters = $relative ? ['9.50rem,18rem', '8em', '10em,20em', '12.5rem'] :
            ['150px,300px', '160px', '170px,310px', '180px'];
        $form->questiontext = ['text' => 'Before {2:SA(' . $parameters[0] . '):=Paris}', 'format' => FORMAT_HTML];
        $form->subquestion[0]['text'] = str_replace('NUMERICAL:', 'NUMERICAL(' . $parameters[1] . '):', $form->subquestion[0]['text']);
        $form->subquestion[1]['text'] = str_replace('NUMERICAL:', 'NM(' . $parameters[2] . '):', $form->subquestion[1]['text']);
        $form->aftertext = ['text' => 'After {3:SAC(' . $parameters[3] . '):=Owl}', 'format' => FORMAT_HTML];
        $qtype = question_bank::get_qtype('clozeonimage');
        $saved = question_bank::load_question_data($qtype->save_question(clone $original, clone $form)->id);
        $expected = [1 => ['min' => 150, 'max' => 300, 'adaptive' => true],
            2 => ['min' => 160, 'max' => 160, 'adaptive' => false],
            3 => ['min' => 170, 'max' => 310, 'adaptive' => true], 4 => null, 5 => null, 6 => null,
            7 => ['min' => 180, 'max' => 180, 'adaptive' => false]];
        if ($relative) {
            $expected[1] = ['min' => 9.5, 'max' => 18.0, 'adaptive' => true, 'unit' => 'rem'];
            $expected[2] = ['min' => 8.0, 'max' => 8.0, 'adaptive' => false, 'unit' => 'em'];
            $expected[3] = ['min' => 10.0, 'max' => 20.0, 'adaptive' => true, 'unit' => 'em'];
            $expected[7] = ['min' => 12.5, 'max' => 12.5, 'adaptive' => false, 'unit' => 'rem'];
        }
        $verify = function($data) use ($expected, $parameters, $context, $PAGE) {
            $runtime = question_bank::make_question($data);
            $this->assertEquals($expected, $runtime->fieldwidths);
            $this->assertEquals(10, $runtime->defaultmark);
            $this->assertStringContainsString('SA(' . $parameters[0] . ')', $data->questiontext);
            $this->assertStringContainsString('SAC(' . $parameters[3] . ')', $data->options->aftertext);
            $this->assertStringContainsString('NUMERICAL(' . $parameters[1] . ')', $data->options->positions[1]->content);
            $usage = \question_engine::make_questions_usage_by_activity('qtype_clozeonimage', $context);
            $usage->set_preferred_behaviour('deferredfeedback');
            $usage->add_question($runtime);
            $usage->start_all_questions();
            $this->assertEquals(1, $runtime->grade_response($runtime->get_correct_response())[0]);
            $PAGE->set_context($context);
            $PAGE->set_url('/question/preview.php');
            $html = $usage->get_question_attempt(1)->render(new \question_display_options(), 1);
            $dom = new \DOMDocument();
            @$dom->loadHTML($html);
            $xpath = new \DOMXPath($dom);
            foreach ([1, 2, 3, 7] as $slot) {
                $input = $xpath->query('//input[contains(@name, "_sub' . $slot . '_answer")]')->item(0);
                $this->assertNotNull($input);
                $this->assertFalse($input->hasAttribute('size'));
                $this->assertFalse($input->hasAttribute('maxlength'));
                $this->assertStringContainsString('width:' . $expected[$slot]['min'] . ($expected[$slot]['unit'] ?? 'px') . ';', $input->getAttribute('style'));
            }
            foreach ($data->options->positions as $row => $position) {
                $files = get_file_storage()->get_area_files($data->contextid, 'qtype_clozeonimage',
                    'blockcontent', $position->id, 'id', false);
                $this->assertCount(1, $files);
                $this->assertSame(sha1('image bytes for block ' . ($row - 1)), reset($files)->get_contenthash());
            }
        };
        $verify($saved);
        $copyform = $this->prepare_file_edit($saved, $form, $context);
        $seed = clone $saved;
        $seed->id = 0;
        $copy = question_bank::load_question_data($qtype->save_question($seed, $copyform)->id);
        $verify($copy);
        $format = new \qformat_xml();
        $xml = $format->writequestion($saved);
        $parsed = (new \core\xml_parser())->parse('<quiz>' . $xml . '</quiz>');
        $imported = $qtype->import_from_xml($parsed['quiz']['#']['question'][0], null, $format);
        $imported->category = $form->category;
        $imported->name = 'Width XML round trip';
        $xmlquestion = $qtype->save_question((object)['qtype' => 'clozeonimage'], $imported);
        $verify(question_bank::load_question_data($xmlquestion->id));
        set_config('enableasyncbackup', 0);
        $quiz = $this->getDataGenerator()->get_plugin_generator('mod_quiz')->create_instance(['course' => $course->id]);
        quiz_add_quiz_question($saved->id, $quiz);
        \core_courseformat\formatactions::cm($course)->duplicate($quiz->cmid);
        $restoredcount = 0;
        foreach (get_fast_modinfo($course)->get_instances_of('quiz') as $candidate) {
            if ($candidate->instance != $quiz->id && $candidate->name === $quiz->name . ' (copy)') {
                $restoredcount++;
                $structure = \mod_quiz\question\bank\qbank_helper::get_question_structure($candidate->instance, $candidate->context);
                $verify(question_bank::load_question_data($structure[1]->questionid));
            }
        }
        $this->assertSame(1, $restoredcount);
        $form = $this->prepare_file_edit($saved, $form, $context);
        foreach (['subquestion', 'xleft', 'ytop', 'blockwidth', 'anchor', 'roworigin'] as $field) {
            [$form->{$field}[0], $form->{$field}[1]] = [$form->{$field}[1], $form->{$field}[0]];
        }
        $reordered = question_bank::load_question_data($qtype->save_question(clone $saved, clone $form)->id);
        $runtime = question_bank::make_question($reordered);
        $this->assertSame($expected[3]['min'], $runtime->fieldwidths[2]['min']);
        $this->assertSame($expected[2]['min'], $runtime->fieldwidths[3]['min']);
        $this->assertStringContainsString('NM(' . $parameters[2] . ')', $reordered->options->positions[1]->content);
        $files = get_file_storage()->get_area_files($reordered->contextid, 'qtype_clozeonimage',
            'blockcontent', $reordered->options->positions[1]->id, 'id', false);
        $this->assertSame(sha1('image bytes for block 1'), reset($files)->get_contenthash());
    }

    /** Pixel and relative source both survive real file-backed lifecycle operations. */
    public static function explicit_width_units(): array {
        return ['pixels' => [false], 'em and rem' => [true]];
    }

    /** HTML comments keep their source but never reserve a child slot, mark or response. */
    public function test_commented_expressions_save_reopen_and_reactivate(): void {
        global $PAGE;
        [$original, $form, $context] = $this->fixture();
        $qtype = question_bank::get_qtype('clozeonimage');
        $comment = '<!-- {99:NUMERICAL:=999:0} -->';
        $form->questiontext = ['text' => $comment . '<p>Initial {2:SHORTANSWER:=yes}</p>', 'format' => FORMAT_HTML];
        $form->subquestion[0]['text'] = '<p>' . $comment . '{3:NUMERICAL:=42:0}</p>';
        $form->subquestion[1]['text'] = $comment;
        $form->subquestion[2]['text'] = '<!-- {1:NOTATYPE:broken} -->';
        $form->aftertext = ['text' => '<p>{4:SHORTANSWER:=end}</p>' . $comment, 'format' => FORMAT_HTML];
        $saved = question_bank::load_question_data($qtype->save_question(clone $original, clone $form)->id);
        $this->assertEquals(9, $saved->defaultmark);
        $this->assertCount(3, $saved->options->questions);
        $this->assertEquals([0, 1, -1], array_values($saved->options->questionrows));
        $this->assertEquals([1, 0, 0], array_values(array_column($saved->options->positions, 'slotcount')));
        $this->assertStringContainsString($comment, $saved->questiontext);
        $this->assertStringContainsString($comment, $saved->options->aftertext);
        $this->assertSame($comment, $saved->options->positions[2]->content);
        $runtime = question_bank::make_question($saved);
        $this->assertSame([1], $runtime->intro->slots);
        $this->assertSame([2], $runtime->blocks[0]->slots);
        $this->assertSame([3], $runtime->additional->slots);
        $this->assertSame('', $runtime->blocks[1]->text);
        $this->assertEquals(1, $runtime->grade_response($runtime->get_correct_response())[0]);
        $usage = \question_engine::make_questions_usage_by_activity('qtype_clozeonimage', $context);
        $usage->set_preferred_behaviour('deferredfeedback');
        $usage->add_question($runtime);
        $usage->start_all_questions();
        $PAGE->set_context($context);
        $PAGE->set_url('/question/preview.php');
        $html = $usage->get_question_attempt(1)->render(new \question_display_options(), 1);
        $this->assertStringNotContainsString('sub4_answer', $html);
        $this->assertStringNotContainsString('999', $html);
        $form->questiontext['text'] = str_replace(['<!-- ', ' -->'], '', $form->questiontext['text']);
        $form->subquestion[1]['text'] = str_replace(['<!-- ', ' -->'], '', $comment);
        $form->aftertext['text'] = str_replace(['<!-- ', ' -->'], '', $form->aftertext['text']);
        $reactivated = question_bank::load_question_data($qtype->save_question(clone $saved, clone $form)->id);
        $this->assertEquals(306, $reactivated->defaultmark);
        $this->assertCount(6, $reactivated->options->questions);
        $this->assertEquals([0, 0, 1, 2, -1, -1], array_values($reactivated->options->questionrows));
        foreach (['questiontext', 'aftertext'] as $field) {
            $form->$field = ['text' => $comment, 'format' => FORMAT_HTML];
        }
        foreach ($form->subquestion as &$source) {
            $source['text'] = $comment;
        }
        unset($source);
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('nosubquestions', 'qtype_clozeonimage'));
        $qtype->save_question(clone $reactivated, $form);
    }

    /** Blocks and all their children move together; previous versions and responses remain intact. */
    public function test_reorder_blocks_save_reopen_and_grade(): void {
        global $DB, $PAGE;
        [$original, $form, $context] = $this->fixture();
        $this->assertSame([1 => 1, 2 => 2, 3 => 3, 4 => 3, 5 => 3], $original->options->questionrows);
        $this->assertCount(5, $original->options->questions);
        $this->assertEquals([1, 1, 3], array_column(array_values($original->options->positions), 'slotcount'));
        $oldchildren = array_values($original->options->questions);
        $old = question_bank::make_question($original);
        $usage = \question_engine::make_questions_usage_by_activity('qtype_clozeonimage', $context);
        $usage->set_preferred_behaviour('deferredfeedback');
        $slot = $usage->add_question($old);
        $usage->start_all_questions();
        $response = $usage->get_question_attempt($slot)->get_question()->get_correct_response();
        $usage->process_action($slot, $response);
        $usage->finish_all_questions();
        \question_engine::save_questions_usage_by_activity($usage);
        $this->assertEquals(1, $usage->get_question_fraction($slot));

        $edited = clone $form;
        unset($edited->parsedsubquestions, $edited->parsedblocks);
        $edited->roworigin = [3, 1, 2];
        foreach (['subquestion', 'xleft', 'ytop', 'blockwidth', 'anchor'] as $field) {
            $edited->$field = [$form->{$field}[2], $form->{$field}[0], $form->{$field}[1]];
        }
        $saved = question_bank::get_qtype('clozeonimage')->save_question(clone $original, $edited);
        $reopened = question_bank::load_question_data($saved->id);
        $this->assertSame([1 => 1, 2 => 1, 3 => 1, 4 => 2, 5 => 3], $reopened->options->questionrows);
        foreach ([2, 0, 1] as $i => $oldindex) {
            $block = $reopened->options->positions[$i + 1];
            $this->assertSame(self::CONTENTS[$oldindex], $block->content);
            $this->assertEquals($form->blockwidth[$oldindex], $block->width);
            $this->assertEquals($form->xleft[$oldindex], $block->xleft);
            $this->assertEquals($form->ytop[$oldindex], $block->ytop);
            $this->assertEquals($form->anchor[$oldindex], $block->anchor);
        }
        foreach ([2, 3, 4, 0, 1] as $index => $oldindex) {
            $child = $reopened->options->questions[$index + 1];
            $this->assertSame($oldchildren[$oldindex]->qtype, $child->qtype);
            $this->assertEquals(
                $DB->get_field('question_versions', 'questionbankentryid', ['questionid' => $oldchildren[$oldindex]->id]),
                $DB->get_field('question_versions', 'questionbankentryid', ['questionid' => $child->id])
            );
            $this->assertTrue($DB->record_exists('question', ['id' => $oldchildren[$oldindex]->id]));
        }
        $oldusage = \question_engine::load_questions_usage_by_activity($usage->get_id());
        $this->assertEquals($original->id, $oldusage->get_question_attempt($slot)->get_question()->id);
        $this->assertEquals(1, $oldusage->get_question_fraction($slot));
        $new = question_bank::make_question($reopened);
        $usage2 = \question_engine::make_questions_usage_by_activity('qtype_clozeonimage', $context);
        $usage2->set_preferred_behaviour('deferredfeedback');
        $slot2 = $usage2->add_question($new);
        $usage2->start_all_questions();
        $usage2->process_action($slot2, $usage2->get_question_attempt($slot2)->get_question()->get_correct_response());
        $usage2->finish_all_questions();
        $this->assertEquals(1, $usage2->get_question_fraction($slot2));
        $PAGE->set_context($context);
        $PAGE->set_url('/question/preview.php');
        $display = new \question_display_options();
        $display->readonly = true;
        $display->feedback = \question_display_options::VISIBLE;
        $display->correctness = \question_display_options::VISIBLE;
        $html = $usage2->get_question_attempt($slot2)->render($display, 1);
        $this->assertSame(3, substr_count($html,
            'class="qtype-clozeonimage-subquestion qtype-clozeonimage-block qtype-clozeonimage-region'));
        $this->assertSame(1, substr_count($html, 'qtype-clozeonimage-block-auto'));
        foreach (['<strong>Force</strong>', '<em>kN</em>', 'Correct city', 'Correct animal', 'Correct force'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        foreach (['qtype-clozeonimage-resize', 'qtype-clozeonimage-image-resize',
                'qtype-clozeonimage-preview-number', 'data-drag-type'] as $editing) {
            $this->assertStringNotContainsString($editing, $html);
        }
    }

    /** Reopening the real Moodle form must use saved widths, not repeat-element defaults. */
    public function test_edit_form_reopens_block_widths_and_contents(): void {
        global $CFG, $PAGE;
        require_once($CFG->dirroot . '/question/type/clozeonimage/edit_clozeonimage_form.php');
        [$question, , $context] = $this->fixture();
        $PAGE->set_url(new \moodle_url('/'));
        $question->formoptions = (object)['canmove' => false, 'cansaveasnew' => false, 'canedit' => true,
            'movecontext' => null, 'repeatelements' => true];
        $question->beingcopied = false;
        $question->inputs = null;
        $form = new \qtype_clozeonimage_edit_form(new \moodle_url('/'), $question, $question->categoryobject,
            new \core_question\local\bank\question_edit_contexts($context));
        $form->set_data($question);
        $dom = new \DOMDocument();
        @$dom->loadHTML($form->render());
        $xpath = new \DOMXPath($dom);
        foreach ([160, 280, 440] as $index => $width) {
            $this->assertSame((string)$width,
                $xpath->query('//input[@name="blockwidth[' . $index . ']"]')->item(0)->getAttribute('value'));
            $this->assertSame(self::CONTENTS[$index],
                $xpath->query('//textarea[@name="subquestion[' . $index . '][text]"]')->item(0)->textContent);
        }
    }

    /** Trailing text-only blocks must keep an editable row even beyond the last response. */
    public function test_form_keeps_trailing_text_only_blocks(): void {
        global $CFG, $PAGE;
        require_once($CFG->dirroot . '/question/type/clozeonimage/edit_clozeonimage_form.php');
        [$question, $data, $context] = $this->fixture();
        unset($data->parsedsubquestions, $data->parsedblocks);
        for ($i = 3; $i < 8; $i++) {
            $data->subquestion[$i] = ['text' => '<p>Caption ' . $i . '</p>', 'format' => FORMAT_HTML];
            $data->xleft[$i] = 50;
            $data->ytop[$i] = 50 * $i;
            $data->blockwidth[$i] = 370 + $i;
            $data->anchor[$i] = 4;
            $data->roworigin[$i] = $i + 1;
        }
        $saved = question_bank::get_qtype('clozeonimage')->save_question($question, $data);
        $question = question_bank::load_question_data($saved->id);
        $PAGE->set_url(new \moodle_url('/'));
        $question->formoptions = (object)['canmove' => false, 'cansaveasnew' => false, 'canedit' => true,
            'movecontext' => null, 'repeatelements' => true];
        $question->beingcopied = false;
        $question->inputs = null;
        $form = new \qtype_clozeonimage_edit_form(new \moodle_url('/'), $question, $question->categoryobject,
            new \core_question\local\bank\question_edit_contexts($context));
        $form->set_data($question);
        $dom = new \DOMDocument();
        @$dom->loadHTML($form->render());
        $xpath = new \DOMXPath($dom);
        for ($i = 3; $i < 8; $i++) {
            $this->assertSame((string)(370 + $i),
                $xpath->query('//input[@name="blockwidth[' . $i . ']"]')->item(0)->getAttribute('value'));
            $this->assertSame('<p>Caption ' . $i . '</p>',
                $xpath->query('//textarea[@name="subquestion[' . $i . '][text]"]')->item(0)->textContent);
        }
    }

    /** XML keeps formatted content, widths and mixed children instead of flattening blocks. */
    public function test_xml_block_round_trip(): void {
        global $CFG;
        require_once($CFG->dirroot . '/question/format/xml/format.php');
        [$question] = $this->fixture();
        $format = new \qformat_xml();
        $xml = $format->writequestion($question);
        $parsed = (new \core\xml_parser())->parse('<quiz>' . $xml . '</quiz>');
        $imported = question_bank::get_qtype('clozeonimage')->import_from_xml(
            $parsed['quiz']['#']['question'][0], null, $format);
        $this->assertIsObject($imported);
        $this->assertCount(5, $imported->parsedsubquestions);
        $this->assertCount(3, $imported->parsedblocks);
        foreach (self::CONTENTS as $index => $source) {
            $this->assertSame($source, $imported->parsedblocks[$index]->content);
            $this->assertSame((int)FORMAT_HTML, $imported->parsedblocks[$index]->contentformat);
        }
        $this->assertSame([160.0, 280.0, 440.0], array_column($imported->parsedblocks, 'width'));
    }

    /** A real quiz duplication exercises Moodle backup and restore with multi-expression blocks. */
    public function test_blocks_survive_backup_restore(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
        [$question, , , $course] = $this->fixture();
        set_config('enableasyncbackup', 0);
        $quiz = $this->getDataGenerator()->get_plugin_generator('mod_quiz')->create_instance([
            'course' => $course->id,
        ]);
        quiz_add_quiz_question($question->id, $quiz);
        \core_courseformat\formatactions::cm($course)->duplicate($quiz->cmid);
        $quizzes = get_fast_modinfo($course)->get_instances_of('quiz');
        $this->assertCount(2, $quizzes);
        foreach ($quizzes as $candidate) {
            if ($candidate->instance == $quiz->id) {
                continue;
            }
            $structure = \mod_quiz\question\bank\qbank_helper::get_question_structure(
                $candidate->instance, $candidate->context);
            $restored = question_bank::load_question_data($structure[1]->questionid);
            $this->assertCount(5, $restored->options->questions);
            $this->assertEquals([1, 1, 3], array_column(array_values($restored->options->positions), 'slotcount'));
            $this->assertSame(self::CONTENTS, array_column(array_values($restored->options->positions), 'content'));
            $this->assertEquals([160, 280, 440], array_column(array_values($restored->options->positions), 'width'));
            $this->assertEquals([0, 4, 8], array_column(array_values($restored->options->positions), 'anchor'));
        }
    }

    /** Prepare editor drafts as the standard edit/copy workflow does. */
    private function prepare_file_edit($question, $form, $context): \stdClass {
        $form = clone $form;
        unset($form->parsedblocks, $form->parsedsubquestions);
        foreach ($question->options->positions as $row => $position) {
            $draftid = 0;
            $text = file_prepare_draft_area($draftid, $context->id, 'qtype_clozeonimage',
                'blockcontent', $position->id, ['subdirs' => true], $position->content);
            $form->subquestion[$row - 1] = ['text' => $text, 'format' => FORMAT_HTML, 'itemid' => $draftid];
        }
        return $form;
    }

    /** Check exact file bytes, independent block associations, portable URLs and external URLs. */
    private function assert_block_files($question, array $order = [0, 1, 2]): void {
        foreach (array_values($question->options->positions) as $index => $position) {
            $original = $order[$index];
            $file = get_file_storage()->get_file($question->contextid, 'qtype_clozeonimage',
                'blockcontent', $position->id, '/block' . $original . '/', 'image.png');
            $this->assertNotFalse($file);
            $this->assertSame('image bytes for block ' . $original, $file->get_content());
            $this->assertStringContainsString('@@PLUGINFILE@@/block' . $original . '/image.png', $position->content);
            $this->assertStringContainsString('https://example.invalid/external.png', $position->content);
            $this->assertStringNotContainsString('draftfile.php', $position->content);
        }
    }

    /** Deletion saves only surviving rows and files, including deletion of the last block. */
    public function test_delete_blocks_preserves_remaining_files_and_old_version(): void {
        [$original, $form, $context] = $this->fixture(true);
        $edited = $this->prepare_file_edit($original, $form, $context);
        $edited->roworigin = [3, 1];
        foreach (['subquestion', 'xleft', 'ytop', 'blockwidth', 'anchor'] as $field) {
            $values = $edited->$field;
            $edited->$field = [$values[2], $values[0]];
        }
        $qtype = question_bank::get_qtype('clozeonimage');
        $saved = question_bank::load_question_data($qtype->save_question(clone $original, clone $edited)->id);
        $this->assertCount(2, $saved->options->positions);
        $this->assertCount(4, $saved->options->questions);
        $this->assert_block_files($saved, [2, 0]);
        $this->assert_block_files($original);
        $this->assertEquals([300, 10], array_column(array_values($saved->options->positions), 'xleft'));
        $this->assertEquals([220, 20], array_column(array_values($saved->options->positions), 'ytop'));
        $this->assertEquals([440, 160], array_column(array_values($saved->options->positions), 'width'));
        $this->assertEquals([8, 0], array_column(array_values($saved->options->positions), 'anchor'));
        $edited->questiontext = ['text' => '{1:SHORTANSWER:=yes}', 'format' => FORMAT_HTML];
        foreach (['subquestion', 'xleft', 'ytop', 'blockwidth', 'anchor', 'roworigin'] as $field) {
            $edited->$field = [];
        }
        $empty = question_bank::load_question_data($qtype->save_question(clone $saved, $edited)->id);
        $this->assertEmpty($empty->options->positions);
        $this->assertCount(1, $empty->options->questions);
        $this->assertEquals([0], array_values($empty->options->questionrows));
        $this->assert_block_files($original);
        $this->assert_block_files($saved, [2, 0]);
    }

    /** Files follow reordered editors, copies own their files, and old versions retain their images. */
    public function test_block_images_reorder_copy_and_file_access(): void {
        global $PAGE;
        [$original, $form, $context] = $this->fixture(true);
        $this->assert_block_files($original);
        $edited = $this->prepare_file_edit($original, $form, $context);
        $edited->roworigin = [3, 1, 2];
        foreach (['subquestion', 'xleft', 'ytop', 'blockwidth', 'anchor'] as $field) {
            $values = $edited->$field;
            $edited->$field = [$values[2], $values[0], $values[1]];
        }
        $saved = question_bank::get_qtype('clozeonimage')->save_question(clone $original, $edited);
        $reordered = question_bank::load_question_data($saved->id);
        $this->assert_block_files($reordered, [2, 0, 1]);
        $this->assert_block_files($original);
        $copyform = $this->prepare_file_edit($reordered, $edited, $context);
        $copyform->roworigin = [1, 2, 3];
        $seed = clone $reordered;
        $seed->id = 0;
        $savedcopy = question_bank::get_qtype('clozeonimage')->save_question($seed, $copyform);
        $copy = question_bank::load_question_data($savedcopy->id);
        $this->assert_block_files($copy, [2, 0, 1]);
        $this->assertNotEquals($reordered->questionbankentryid, $copy->questionbankentryid);
        $runtime = question_bank::make_question($copy);
        $usage = \question_engine::make_questions_usage_by_activity('qtype_clozeonimage', $context);
        $usage->set_preferred_behaviour('deferredfeedback');
        $slot = $usage->add_question($runtime);
        $usage->start_all_questions();
        $qa = $usage->get_question_attempt($slot);
        $display = new \question_display_options();
        $PAGE->set_context($context);
        $PAGE->set_url('/question/preview.php');
        $html = $qa->render($display, 1);
        $this->assertStringContainsString('/qtype_clozeonimage/blockcontent/', $html);
        $this->assertStringNotContainsString('@@PLUGINFILE@@', $html);
        foreach ($copy->options->positions as $position) {
            $this->assertTrue($runtime->check_file_access($qa, $display, 'qtype_clozeonimage',
                'blockcontent', [$position->id, 'image.png'], false));
        }
        $this->assertFalse($runtime->check_file_access($qa, $display, 'qtype_clozeonimage',
            'blockcontent', [$original->options->positions[1]->id, 'image.png'], false));
        question_bank::get_qtype('clozeonimage')->delete_question($copy->id, $context->id);
        foreach ($copy->options->positions as $position) {
            $this->assertEmpty(get_file_storage()->get_area_files($context->id, 'qtype_clozeonimage',
                'blockcontent', $position->id, 'id', false));
        }
        $this->assert_block_files($original);
        $this->assert_block_files($reordered, [2, 0, 1]);
    }

    /** The rendered edit form and its initial preview share valid standard Moodle drafts. */
    #[\PHPUnit\Framework\Attributes\DataProvider('preview_filter_provider')]
    public function test_block_images_reopen_in_real_form(bool $math, bool $slasharguments): void {
        global $CFG, $PAGE, $USER;
        require_once($CFG->dirroot . '/question/type/clozeonimage/edit_clozeonimage_form.php');
        [$question, , $context] = $this->fixture(true);
        $CFG->slasharguments = $slasharguments;
        filter_set_global_state('mathjaxloader', $math ? TEXTFILTER_ON : TEXTFILTER_DISABLED);
        \core_filters\filter_manager::reset_caches();
        foreach ($question->options->positions as $position) {
            $position->content .= '<p style="position:relative;left:24px;top:-6px">Formula \( \frac{1}{2} \)</p>' .
                '<img src="javascript:alert(1)" onerror="alert(2)"><script>alert(3)</script>';
        }
        $PAGE->set_url(new \moodle_url('/'));
        $question->formoptions = (object)['canmove' => false, 'cansaveasnew' => true, 'canedit' => true,
            'movecontext' => null, 'repeatelements' => true];
        $question->beingcopied = false;
        $question->inputs = null;
        $form = new \qtype_clozeonimage_edit_form(new \moodle_url('/'), $question, $question->categoryobject,
            new \core_question\local\bank\question_edit_contexts($context));
        $form->set_data($question);
        $dom = new \DOMDocument();
        @$dom->loadHTML($form->render());
        $xpath = new \DOMXPath($dom);
        $this->assertSame($math ? 3 : 0,
            $xpath->query('//div[@data-row-index]//span[contains(@class, "filter_mathjaxloader_equation")]')->length);
        $this->assertSame(3, $xpath->query('//div[@data-row-index]//p[contains(@style, "position:relative") and ' .
            'contains(@style, "left:24px") and contains(@style, "top:-6px")]')->length);
        $this->assertSame(0, $xpath->query('//div[@data-row-index]//script')->length);
        $this->assertSame(0, $xpath->query('//div[@data-row-index]//*[@onerror]')->length);
        $this->assertSame(0, $xpath->query('//div[@data-row-index]//img[starts-with(@src,"javascript:")]')->length);
        foreach ([0, 1, 2] as $index) {
            $draftid = (int)$xpath->query('//input[@name="subquestion[' . $index . '][itemid]"]')
                ->item(0)->getAttribute('value');
            $this->assertGreaterThan(0, $draftid);
            $this->assertNotFalse(get_file_storage()->get_file(\context_user::instance($USER->id)->id,
                'user', 'draft', $draftid, '/block' . $index . '/', 'image.png'));
            $source = $xpath->query('//textarea[@name="subquestion[' . $index . '][text]"]')->item(0)->textContent;
            $this->assertStringContainsString('/' . $draftid . '/block' . $index . '/image.png', rawurldecode($source));
            $preview = $xpath->query('//div[@data-row-index="' . $index . '"]//img[@alt="Block image"]')->item(0);
            $this->assertNotNull($preview);
            $this->assertStringContainsString('/' . $draftid . '/block' . $index . '/image.png',
                rawurldecode($preview->getAttribute('src')));
            $this->assertStringNotContainsString('brokenfile.php', $preview->getAttribute('src'));
            $this->assertStringContainsString('draftfile.php', $preview->getAttribute('src'));
            $this->assertSame('60', $preview->getAttribute('width'));
        }
    }

    /** Filters and both Moodle file URL forms must coexist in the real preview. */
    public static function preview_filter_provider(): array {
        return ['unfiltered slash URLs' => [false, true], 'math slash URLs' => [true, true],
            'math query URLs' => [true, false]];
    }

    /** Real XML import and activity backup/restore retain each block's files in new areas. */
    public function test_block_images_xml_and_backup_restore(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/question/format/xml/format.php');
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
        [$question, , $context, $course] = $this->fixture(true);
        $format = new \qformat_xml();
        $xml = $format->writequestion($question);
        $filename = make_request_directory() . '/block-images.xml';
        file_put_contents($filename, '<quiz>' . $xml . '</quiz>');
        $format->setContexts([$context]);
        $format->setCourse($course);
        $format->setCategory($question->categoryobject);
        $format->setFilename($filename);
        $format->setRealfilename('block-images.xml');
        $format->setMatchgrades('error');
        $format->setStoponerror(true);
        $format->set_display_progress(false);
        $this->assertTrue($format->importprocess());
        $imported = question_bank::load_question_data(reset($format->questionids));
        $this->assertNotEquals($question->id, $imported->id);
        $this->assert_block_files($imported);
        set_config('enableasyncbackup', 0);
        $quizzes = get_fast_modinfo($course)->get_instances_of('quiz');
        $quizcm = reset($quizzes);
        $quiz = $DB->get_record('quiz', ['id' => $quizcm->instance], '*', MUST_EXIST);
        quiz_add_quiz_question($question->id, $quiz);
        \core_courseformat\formatactions::cm($course)->duplicate($quizcm->id);
        foreach (get_fast_modinfo($course)->get_instances_of('quiz') as $candidate) {
            if ($candidate->id === $quizcm->id) {
                continue;
            }
            $structure = \mod_quiz\question\bank\qbank_helper::get_question_structure(
                $candidate->instance, $candidate->context);
            $restored = question_bank::load_question_data($structure[1]->questionid);
            $this->assertNotEquals($question->id, $restored->id);
            $this->assert_block_files($restored);
        }
    }

    /** Server rejects malformed sources and widths even when callers bypass the editor. */
    public function test_invalid_block_width_rejected_before_save(): void {
        global $DB;
        [$question, $form] = $this->fixture();
        $before = $DB->count_records('question');
        $form->blockwidth[1] = '24.5';
        try {
            question_bank::get_qtype('clozeonimage')->save_question(clone $question, $form);
            $this->fail('Invalid width was accepted');
        } catch (\moodle_exception $e) {
            $this->assertSame('invalidblockwidth', $e->errorcode);
        }
        $this->assertSame($before, $DB->count_records('question'));
    }
    /** Displayed zones share one sequence; image removal keeps blocks active; explicit disabling retains files and excludes responses. */
    #[\PHPUnit\Framework\Attributes\DataProvider('inactive_composition_provider')]
    public function test_all_zones_and_inactive_blocks(bool $removeimage): void {
        global $PAGE, $DB;
        [$original, $form, $context] = $this->fixture(true);
        $form = $this->prepare_file_edit($original, $form, $context);
        $qtype = question_bank::get_qtype('clozeonimage');
        $form->questiontext = ['text' => '<p>Initial {2:SHORTANSWER:=start#Initial feedback}</p>', 'format' => FORMAT_HTML];
        $form->aftertext['text'] = '<p>Additional {3:NUMERICAL:=7:0#Additional feedback}</p>';
        $form->subquestion[0]['text'] = str_replace(self::CONTENTS[0], '<h3>Image-only caption</h3>',
            $form->subquestion[0]['text']);
        $saved = $qtype->save_question(clone $original, $form);
        $data = question_bank::load_question_data($saved->id);
        $this->assertEquals([0, 2, 3, 3, 3, -1], array_values($data->options->questionrows));
        $this->assertEquals([0, 1, 3], array_column($data->options->positions, 'slotcount'));
        $this->assertEquals(9, $data->defaultmark);
        $this->assert_block_files($data);
        $usage = \question_engine::make_questions_usage_by_activity('qtype_clozeonimage', $context);
        $usage->set_preferred_behaviour('deferredfeedback');
        $slot = $usage->add_question(question_bank::make_question($data));
        $usage->start_all_questions();
        $response = $usage->get_question_attempt($slot)->get_question()->get_correct_response();
        $usage->process_action($slot, $response);
        \question_engine::save_questions_usage_by_activity($usage);
        $PAGE->set_context($context);
        $PAGE->set_url('/question/preview.php');
        $html = $usage->get_question_attempt($slot)->render(new \question_display_options(), 1);
        $this->assertStringContainsString('Image-only caption', $html);
        $this->assertLessThan(strpos($html, 'Image-only caption'), strpos($html, 'Initial'));
        $this->assertLessThan(strpos($html, 'Additional'), strpos($html, 'Image-only caption'));
        $form = $this->prepare_file_edit($data, $form, $context);
        $form->removebgimage = (int)$removeimage;
        $form->showcomposition = (int)$removeimage;
        $hidden = question_bank::load_question_data($qtype->save_question(clone $data, $form)->id);
        $this->assertEquals((int)$removeimage, $hidden->options->showcomposition);
        $this->assertCount($removeimage ? 0 : 1, get_file_storage()->get_area_files($hidden->contextid,
            'qtype_clozeonimage', 'bgimage', $hidden->id, 'id', false));
        $this->assertEquals($removeimage ? 9 : 5, $hidden->defaultmark);
        $this->assertEquals($removeimage ? [0, 2, 3, 3, 3, -1] : [0, -1], array_values($hidden->options->questionrows));
        $this->assertEquals($removeimage ? [0, 1, 3] : [0, 0, 0], array_column($hidden->options->positions, 'slotcount'));
        $this->assertCount(3, \qtype_clozeonimage::block_sources($hidden));
        $this->assert_block_files($hidden);
        foreach (['xleft', 'ytop', 'width', 'anchor', 'content'] as $field) {
            $this->assertEquals(array_column($data->options->positions, $field),
                array_column($hidden->options->positions, $field));
        }
        $runtime = question_bank::make_question($hidden);
        $this->assertSame($removeimage, $runtime->check_file_access(null, null, 'qtype_clozeonimage', 'blockcontent',
            [reset($hidden->options->positions)->id, 'image.png'], false));
        $newusage = \question_engine::make_questions_usage_by_activity('qtype_clozeonimage', $context);
        $newusage->set_preferred_behaviour('deferredfeedback');
        $newslot = $newusage->add_question(question_bank::make_question($hidden));
        $newusage->start_all_questions();
        $qa = $newusage->get_question_attempt($newslot);
        $this->assertCount($removeimage ? 6 : 2, $qa->get_question()->get_correct_response());
        $newusage->process_action($newslot, $qa->get_question()->get_correct_response());
        $newusage->finish_all_questions();
        $this->assertEquals(1, $newusage->get_question_fraction($newslot));
        $html = $qa->render(new \question_display_options(), 1);
        $this->assertSame($removeimage, str_contains($html, 'qtype-clozeonimage-composition'));
        $this->assertStringNotContainsString('clozeonimage-toggle-panorama', $html);
        $this->assertSame($removeimage, str_contains($html, 'Image-only caption'));
        $this->assertStringContainsString('Initial', $html);
        $this->assertStringContainsString('Additional', $html);
        $oldusage = \question_engine::load_questions_usage_by_activity($usage->get_id());
        $oldusage->finish_all_questions();
        $this->assertEquals(1, $oldusage->get_question_fraction($slot));
        $this->assertCount(6, $oldusage->get_question_attempt($slot)->get_question()->subquestions);
        $form = $this->prepare_file_edit($hidden, $form, $context);
        unset($form->removebgimage);
        $form->showcomposition = 1;
        $restored = question_bank::load_question_data($qtype->save_question(clone $hidden, $form)->id);
        $this->assertEquals(9, $restored->defaultmark);
        $this->assertEquals([0, 2, 3, 3, 3, -1], array_values($restored->options->questionrows));
        $this->assert_block_files($restored);
        $this->assertTrue($DB->record_exists('question', ['id' => $data->id]));
    }

    /** Either remove just the image or hide the composition while retaining its image. */
    public static function inactive_composition_provider(): array {
        return [[true], [false]];
    }

    /** Each active zone can supply the only expression, independently of whether an image exists. */
    #[\PHPUnit\Framework\Attributes\DataProvider('displayed_zone_provider')]
    public function test_displayed_zone_validation(string $zone, bool $image, bool $show): void {
        global $PAGE;
        [$original, $form, $context] = $this->fixture();
        $form->questiontext = ['text' => '', 'format' => FORMAT_HTML];
        $form->aftertext['text'] = '';
        $form->subquestion = [];
        $form->roworigin = [];
        unset($form->roworigin);
        $form->removebgimage = !$image;
        $form->showcomposition = (int)$show;
        if ($zone === 'block') {
            $form->subquestion = [['text' => '{1:NUMERICAL:=42:0}', 'format' => FORMAT_HTML]];
        } else {
            $form->{$zone}['text'] = '{1:NUMERICAL:=42:0}';
        }
        $qtype = question_bank::get_qtype('clozeonimage');
        if ($zone === 'block' && !$show) {
            $this->expectException(\moodle_exception::class);
            $this->expectExceptionMessage(get_string('nosubquestions', 'qtype_clozeonimage'));
        }
        $data = question_bank::load_question_data($qtype->save_question(clone $original, $form)->id);
        $this->assertCount(1, $data->options->questions);
        $usage = \question_engine::make_questions_usage_by_activity('qtype_clozeonimage', $context);
        $usage->set_preferred_behaviour('deferredfeedback');
        $slot = $usage->add_question(question_bank::make_question($data));
        $usage->start_all_questions();
        $PAGE->set_context($context);
        $PAGE->set_url('/question/preview.php');
        $html = $usage->get_question_attempt($slot)->render(new \question_display_options(), 1);
        $this->assertSame($show, str_contains($html, 'qtype-clozeonimage-composition'));
        $this->assertStringNotContainsString('clozeonimage-toggle-panorama', $html);
        $this->assertStringNotContainsString('{1:NUMERICAL', $html);
    }

    /** All image/visibility combinations, including rejection of a Cloze expression in an invisible block. */
    public static function displayed_zone_provider(): array {
        $cases = [];
        foreach (['questiontext', 'aftertext', 'block'] as $zone) {
            foreach (self::zone_image_provider() as [$image, $show]) {
                $cases[] = [$zone, $image, $show];
            }
        }
        return $cases;
    }
    /** All three file areas and inactive blocks survive real XML import and activity backup/restore. */
    #[\PHPUnit\Framework\Attributes\DataProvider('zone_image_provider')]
    public function test_zones_files_xml_copy_and_restore(bool $image, bool $show, int $block = 2, int $answer = 0): void {
        global $CFG, $DB, $USER;
        require_once($CFG->dirroot . '/question/format/xml/format.php');
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
        [$old, $form, $context, $course] = $this->fixture(true);
        $form = $this->prepare_file_edit($old, $form, $context);
        $form->subquestion[0]['text'] = str_replace(self::CONTENTS[0], '', $form->subquestion[0]['text']);
        $form->removebgimage = !$image;
        $form->showcomposition = (int)$show;
        foreach (['questiontext', 'aftertext'] as $field) {
            $draft = file_get_unused_draft_itemid();
            $file = get_file_storage()->create_file_from_string((object)[
                'contextid' => \context_user::instance($USER->id)->id, 'component' => 'user', 'filearea' => 'draft',
                'itemid' => $draft, 'filepath' => '/', 'filename' => $field . '.png',
            ], 'bytes of ' . $field);
            $form->$field = ['text' => '<p>' . $field . ' {1:SHORTANSWER:=yes}</p><img alt="' . $field .
                '" src="' . \moodle_url::make_draftfile_url($draft, '/', $file->get_filename()) . '">',
                'format' => FORMAT_HTML, 'itemid' => $draft];
        }
        $qtype = question_bank::get_qtype('clozeonimage');
        $form->blockappearance = $block;
        $form->controlappearance = $answer;
        $question = question_bank::load_question_data($qtype->save_question(clone $old, $form)->id);
        $check = function($data) use ($image, $show, $block, $answer) {
            $this->assertEquals($block, $data->options->blockappearance);
            $this->assertEquals($answer, $data->options->controlappearance);
            $this->assertFalse(\qtype_clozeonimage::has_corrupt_position_metadata($data));
            $this->assertEquals((int)$show, $data->options->showcomposition);
            $this->assertCount($image ? 1 : 0, get_file_storage()->get_area_files($data->contextid,
                'qtype_clozeonimage', 'bgimage', $data->id, 'id', false));
            $this->assertCount($show ? 6 : 2, $data->options->questions);
            $this->assertEquals($show ? [0, 2, 3, 3, 3, -1] : [0, -1], array_values($data->options->questionrows));
            $this->assert_block_files($data);
            foreach (['questiontext' => 'question', 'aftertext' => 'qtype_clozeonimage'] as $area => $component) {
                $file = get_file_storage()->get_file($data->contextid, $component, $area, $data->id, '/', $area . '.png');
                $this->assertNotFalse($file);
                $this->assertSame('bytes of ' . $area, $file->get_content());
            }
        };
        $check($question);
        $copyform = $this->prepare_file_edit($question, $form, $context);
        foreach (['questiontext' => 'question', 'aftertext' => 'qtype_clozeonimage'] as $area => $component) {
            $draft = 0;
            $text = $area === 'questiontext' ? $question->questiontext : $question->options->aftertext;
            $text = file_prepare_draft_area($draft, $context->id, $component, $area, $question->id,
                ['subdirs' => true], $text);
            $copyform->$area = ['text' => $text, 'format' => FORMAT_HTML, 'itemid' => $draft];
        }
        $seed = clone $question;
        $seed->id = 0;
        $copy = question_bank::load_question_data($qtype->save_question($seed, $copyform)->id);
        $check($copy);
        $format = new \qformat_xml();
        $filename = make_request_directory() . '/zones.xml';
        file_put_contents($filename, '<quiz>' . $format->writequestion($question) . '</quiz>');
        $format->setContexts([$context]);
        $format->setCourse($course);
        $format->setCategory($question->categoryobject);
        $format->setFilename($filename);
        $format->setRealfilename('zones.xml');
        $format->setMatchgrades('error');
        $format->setStoponerror(true);
        $format->set_display_progress(false);
        $this->assertTrue($format->importprocess());
        $imported = question_bank::load_question_data(reset($format->questionids));
        $check($imported);
        set_config('enableasyncbackup', 0);
        $quizzes = get_fast_modinfo($course)->get_instances_of('quiz');
        $quizcm = reset($quizzes);
        $quiz = $DB->get_record('quiz', ['id' => $quizcm->instance], '*', MUST_EXIST);
        quiz_add_quiz_question($question->id, $quiz);
        \core_courseformat\formatactions::cm($course)->duplicate($quizcm->id);
        $restoredcount = 0;
        foreach (get_fast_modinfo($course)->get_instances_of('quiz') as $candidate) {
            if ($candidate->id === $quizcm->id) {
                continue;
            }
            $structure = \mod_quiz\question\bank\qbank_helper::get_question_structure($candidate->instance, $candidate->context);
            $restored = question_bank::load_question_data($structure[1]->questionid);
            $this->assertNotEquals($question->id, $restored->id);
            $check($restored);
            $restoredcount++;
        }
        $this->assertSame(1, $restoredcount);
    }

    /** Every independent background combination survives copy, XML and real backup/restore. */
    #[\PHPUnit\Framework\Attributes\DataProvider('background_provider')]
    public function test_background_combinations_round_trip(int $block, int $answer): void {
        $this->test_zones_files_xml_copy_and_restore(true, true, $block, $answer);
    }

    public static function background_provider(): array {
        $cases = [];
        foreach ([2, 0, 1] as $block) {
            foreach ([2, 0, 1] as $answer) {
                $cases["block $block answer $answer"] = [$block, $answer];
            }
        }
        return $cases;
    }

    /** Both normal and inactive compositions must round trip. */
    public static function zone_image_provider(): array {
        return [[true, true], [false, true], [true, false], [false, false]];
    }
}
