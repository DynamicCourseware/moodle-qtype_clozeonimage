<?php
// This file is part of Moodle - https://moodle.org/
// Moodle is free software under the GNU GPL v3 or later.
namespace qtype_clozeonimage;

use qtype_clozeonimage\local\composition;

/**
 * Autonomous composition persistence and active-zone contracts.
 * @package qtype_clozeonimage
 * @copyright 2026 DynamicCourseware.org (Dominique Bauer)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(composition::class)]
final class composition_test extends \advanced_testcase {
    /** Four configurations, including preserved but inactive blocks/images. */
    public static function configurations(): array {
        return ['blocks only' => [true, false, true], 'images only' => [true, true, false],
            'mixed' => [true, true, true], 'inactive' => [false, true, true]];
    }

    /** Build only new question data in the dedicated PHPUnit environment. */
    private function form(bool $active, bool $images, bool $blocks): array {
        global $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->get_plugin_generator('mod_quiz')->create_instance(['course' => $course->id]);
        $context = \context_module::instance($quiz->cmid);
        $category = $this->getDataGenerator()->get_plugin_generator('core_question')->create_question_category([
            'contextid' => $context->id]);
        $f = (object)['category' => $category->id . ',' . $context->id, 'name' => 'Composition format 2',
            'questiontext' => ['text' => '{2:SA(9.50rem):=Paris}', 'format' => FORMAT_HTML],
            'generalfeedback' => ['text' => '', 'format' => FORMAT_HTML],
            'aftertext' => ['text' => '{3:NM(150px):=42:0}', 'format' => FORMAT_HTML],
            'penalty' => 0.3333333, 'status' => 'ready', 'idnumber' => null,
            'showcomposition' => (int)$active, 'composition' => json_encode(composition::defaults()),
            'subquestion' => $blocks ? [['text' => '{4:SAC(8em,16em):=Paris}', 'format' => FORMAT_HTML],
                ['text' => '<p>Information only</p>', 'format' => FORMAT_HTML]] : [],
            'xleft' => $blocks ? [-100, 200] : [], 'ytop' => $blocks ? [-80, 500] : [],
            'blockwidth' => $blocks ? [320, 320] : [], 'anchor' => $blocks ? [8, 0] : [],
            'roworigin' => $blocks ? [1, 2] : [],
            'blockkey' => $blocks ? [str_repeat('c', 32), str_repeat('d', 32)] : [],
            'relativeimage' => $blocks && $images ? [str_repeat('a', 32), ''] : [],
            'relativex' => [1.5, 0], 'relativey' => [-.5, 0], 'blocklayer' => [9, 8],
            'stickybottom' => [1, 0], 'stickytop' => [1, 0], 'stickyhorizontal' => [3, 1],
            'blockappearance' => 2, 'controlappearance' => 0,
            'hint' => [], 'hintclearwrong' => [], 'hintshownumcorrect' => []];
        if ($images) {
            foreach ([str_repeat('a', 32), str_repeat('b', 32)] as $i => $key) {
                $draft = file_get_unused_draft_itemid();
                get_file_storage()->create_file_from_string(['contextid' => \context_user::instance($USER->id)->id,
                    'component' => 'user', 'filearea' => 'draft', 'itemid' => $draft, 'filepath' => '/',
                    'filename' => 'diagram.svg'], '<svg xmlns="http://www.w3.org/2000/svg" width="600" height="400"></svg>');
                $f->sceneimage[$i] = $draft;
                $f->imagekey[$i] = $key;
                $f->imagename[$i] = 'Image ' . $i;
                $f->imageleft[$i] = $i * 400;
                $f->imagetop[$i] = -100;
                $f->imagewidth[$i] = 300;
                $f->imageheight[$i] = 200;
                $f->imagelayer[$i] = $i;
            }
        }
        return [$f, $context, $course, $quiz];
    }

    /** Missing options in existing data must remain non-sticky. */
    public function test_missing_sticky_options_default_to_off(): void {
        [$form] = $this->form(true, false, true);
        unset($form->stickybottom, $form->stickytop, $form->stickyhorizontal);
        $saved = \question_bank::get_qtype('clozeonimage')->save_question((object)['qtype' => 'clozeonimage'], $form);
        foreach (\question_bank::load_question_data($saved->id)->options->positions as $position) {
            $this->assertEquals(0, $position->stickybottom);
            $this->assertEquals(0, $position->stickytop);
            $this->assertEquals(0, $position->stickyhorizontal);
        }
    }

    /** Core's question editor reuses the submitted object when saving custom fields. */
    public function test_save_updates_the_original_form_id_for_custom_fields(): void {
        [$form] = $this->form(true, false, true);
        $form->id = 0;
        $form->xleft[0] = -123.456;
        $form->xleft_px = ['-123', '200'];
        $question = (object)['qtype' => 'clozeonimage'];
        $handler = \qbank_customfields\customfield\question_handler::create();
        foreach (['initial creation', 'new question version'] as $stage) {
            $previousid = $form->id;
            $saved = \question_bank::get_qtype('clozeonimage')->save_question($question, $form);
            $this->assertEquals($saved->id, $form->id, $stage . ': caller receives the new question ID');
            $this->assertNotEquals($previousid, $form->id, $stage . ': do not keep zero or the old version ID');
            // This is the next operation in Moodle's question editor, even with no custom fields.
            $handler->instance_form_save($form);
            $question = \question_bank::load_question_data($saved->id);
            $this->assertEquals(-123.456, $question->options->positions[1]->xleft);
            $this->assertSame('{4:SAC(8em,16em):=Paris}', $question->options->positions[1]->content);
        }
    }

    /** Empty display fields also restore losslessly for submissions without JavaScript or blur. */
    public function test_blank_geometry_submission_without_browser_events(): void {
        [$form] = $this->form(true, true, true);
        $form->compositionwidth = 1200.25;
        $form->compositionheight = 900.125;
        $form->compositionwidth_px = '';
        $form->compositionheight_px = '';
        $form->relativeimage[0] = '';
        $form->xleft[0] = -123.456;
        $form->xleft_px = ['', '200'];
        $form->blockwidth[0] = 320.25;
        $form->blockwidth_px = ['', '320'];
        $form->imageleft[0] = 12.345;
        $form->imageleft_px = ['', '400'];
        $form->blocklayer[0] = '';
        $form->blocklayer_current = [-12345];
        $form->imagelayer[0] = '';
        $form->imagelayer_current = [-23456];
        $id = \question_bank::get_qtype('clozeonimage')->save_question((object)['qtype' => 'clozeonimage'], $form)->id;
        $data = \question_bank::load_question_data($id);
        $geometry = composition::geometry($data->options);
        $this->assertEquals(1200.25, $geometry['width']);
        $this->assertEquals(900.125, $geometry['height']);
        $this->assertEquals(-123.456, $data->options->positions[1]->xleft);
        $this->assertEquals(320.25, $data->options->positions[1]->width);
        $this->assertEquals(-12345, $data->options->positions[1]->layer);
        $images = array_values($data->options->images);
        $this->assertEquals(12.345, $images[0]->xleft);
        $this->assertEquals(-23456, $images[0]->layer);
        $this->assertSame('{4:SAC(8em,16em):=Paris}', $data->options->positions[1]->content);
    }

    /** Persist independent images and source, then render and grade only active zones. */
    #[\PHPUnit\Framework\Attributes\DataProvider('configurations')]
    public function test_configurations(bool $active, bool $images, bool $blocks): void {
        global $PAGE;
        [$form, $context] = $this->form($active, $images, $blocks);
        $qtype = \question_bank::get_qtype('clozeonimage');
        $saved = $qtype->save_question((object)['qtype' => 'clozeonimage'], $form);
        $data = \question_bank::load_question_data($saved->id);
        $this->assertEquals(2, $data->options->formatversion);
        $this->assertCount($images ? 2 : 0, $data->options->images);
        $this->assertCount($blocks ? 2 : 0, $data->options->positions);
        $this->assertEquals($active && $blocks ? 9 : 5, $data->defaultmark);
        $question = \question_bank::make_question($data);
        $this->assertEquals(1, $question->grade_response($question->get_correct_response())[0]);
        $usage = \question_engine::make_questions_usage_by_activity('qtype_clozeonimage', $context);
        $usage->set_preferred_behaviour('deferredfeedback');
        $usage->add_question($question);
        $usage->start_all_questions();
        $PAGE->set_context($context);
        $PAGE->set_url('/question/preview.php');
        $html = $usage->get_question_attempt(1)->render(new \question_display_options(), 1);
        $this->assertSame($active, str_contains($html, 'data-composition='));
        $this->assertSame($active && $blocks, str_contains($html, 'Information only'));
        foreach ($data->options->images as $image) {
            $files = get_file_storage()->get_area_files($context->id, 'qtype_clozeonimage', 'image', $image->id, 'id', false);
            $this->assertCount(1, $files);
            $this->assertSame($active, $question->check_file_access($usage->get_question_attempt(1),
                new \question_display_options(), 'qtype_clozeonimage', 'image', [$image->id, 'diagram.svg'], false));
        }
        if ($blocks && $images) {
            $this->assertSame(str_repeat('a', 32), $data->options->positions[1]->relativeimage);
            $this->assertEquals(1.5, $data->options->positions[1]->relativex);
        }
    }

    /** Indexed QuickForm defaults must retain image-relative anchors and independent layers. */
    public function test_form_reopens_associations(): void {
        global $CFG, $PAGE;
        require_once($CFG->dirroot . '/question/type/clozeonimage/edit_clozeonimage_form.php');
        [$data, $context] = $this->form(true, true, true);
        $data->xleft[0] = 123.45678901;
        $data->ytop[0] = -123.5;
        $data->blockwidth[0] = 320.25;
        $saved = \question_bank::get_qtype('clozeonimage')->save_question((object)['qtype' => 'clozeonimage'], $data);
        $question = \question_bank::load_question_data($saved->id);
        $question->formoptions = (object)['canmove' => false, 'cansaveasnew' => true, 'canedit' => true,
            'movecontext' => null, 'repeatelements' => true];
        $question->beingcopied = false;
        $question->inputs = null;
        $PAGE->set_url('/');
        $form = new \qtype_clozeonimage_edit_form(new \moodle_url('/'), $question, $question->categoryobject,
            new \core_question\local\bank\question_edit_contexts($context));
        $form->set_data($question);
        $dom = new \DOMDocument();
        @$dom->loadHTML($form->render());
        $xpath = new \DOMXPath($dom);
        foreach (['relativex[0]' => '1.50000000', 'relativey[0]' => '-0.50000000', 'blocklayer[0]' => '9',
                'blockkey[0]' => str_repeat('c', 32)] as $name => $value) {
            $this->assertSame($value, $xpath->query('//input[@name="' . $name . '"]')->item(0)->getAttribute('value'));
        }
        foreach (['xleft_px[0]' => '123', 'ytop_px[0]' => '-124', 'blockwidth_px[0]' => '320',
                'xleft[0]' => '123.45678901', 'ytop[0]' => '-123.5'] as $name => $value) {
            $input = $xpath->query('//input[@name="' . $name . '"]')->item(0);
            $this->assertEquals((float)$value, (float)$input->getAttribute('value'));
            if (str_contains($name, '_px')) {
                $this->assertSame('1', $input->getAttribute('step'));
                $this->assertMatchesRegularExpression('/^-?\\d+$/', $input->getAttribute('min'));
                $this->assertSame(1, $xpath->query('//label[@for="' . $input->getAttribute('id') . '"]')->length);
            }
        }
        $this->assertSame(1, $xpath->query('//input[@name="stickybottom[0]" and @type="checkbox" and @checked]')->length);
        $this->assertSame(1, $xpath->query('//input[@name="stickytop[0]" and @type="checkbox" and @checked]')->length);
        $this->assertSame(1, $xpath->query('//input[@name="stickyright[0]" and @type="checkbox" and @checked]')->length);
        $this->assertSame(1, $xpath->query('//input[@name="stickyleft[0]" and @type="checkbox" and @checked]')->length);
        $this->assertSame(0, $xpath->query('//input[@name="stickytop[1]" and @type="checkbox" and @checked]')->length);
        $this->assertSame(str_repeat('a', 32), $xpath->query(
            '//select[@name="relativeimage[0]"]/option[@selected]')->item(0)->getAttribute('value'));
    }

    /** An existing usage keeps its version, files and responses after composition deactivation. */
    public function test_attempt_version_isolation(): void {
        [$form, $context] = $this->form(true, true, true);
        $type = \question_bank::get_qtype('clozeonimage');
        $saved = $type->save_question((object)['qtype' => 'clozeonimage'], clone $form);
        $question = \question_bank::load_question($saved->id);
        $usage = \question_engine::make_questions_usage_by_activity('qtype_clozeonimage', $context);
        $usage->set_preferred_behaviour('deferredfeedback');
        $usage->add_question($question);
        $usage->start_all_questions();
        $response = $question->get_correct_response();
        $usage->process_action(1, $response);
        \question_engine::save_questions_usage_by_activity($usage);
        $form->showcomposition = 0;
        $next = $type->save_question(\question_bank::load_question_data($saved->id), clone $form);
        $this->assertNotEquals($saved->id, $next->id);
        $this->assertEquals(5, \question_bank::load_question($next->id)->defaultmark);
        $usage = \question_engine::load_questions_usage_by_activity($usage->get_id());
        $qa = $usage->get_question_attempt(1);
        $this->assertEquals($saved->id, $qa->get_question()->id);
        $this->assertEquals(9, $qa->get_question()->defaultmark);
        $this->assertEquals($response, $qa->get_last_qt_data());
        $usage->finish_all_questions();
        $this->assertEquals(1, $usage->get_question_fraction(1));
        \question_engine::save_questions_usage_by_activity($usage);
        $review = \question_engine::load_questions_usage_by_activity($usage->get_id());
        $this->assertEquals(1, $review->get_question_fraction(1));
        $this->assertTrue(composition::active($review->get_question_attempt(1)->get_question()));
        $form->showcomposition = 1;
        $reactivated = $type->save_question(\question_bank::load_question_data($next->id), $form);
        $this->assertEquals(9, \question_bank::load_question($reactivated->id)->defaultmark);
        $this->assertCount(2, \question_bank::load_question_data($reactivated->id)->options->images);
    }

    /** Copy/XML/Moodle backup restore preserve images, local keys, units and inactive contents. */
    #[\PHPUnit\Framework\Attributes\DataProvider('active_states')]
    public function test_exchanges(bool $active): void {
        global $CFG;
        require_once($CFG->dirroot . '/question/format/xml/format.php');
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
        [$form, $context, $course, $quiz] = $this->form($active, true, true);
        $form->xleft[0] = 123.456;
        $form->ytop[0] = -123.5;
        $form->blockwidth[0] = 320.25;
        $form->blocklayer[0] = -9999;
        $form->imagelayer[0] = 9999;
        $qtype = \question_bank::get_qtype('clozeonimage');
        $save = fn($f) => \question_bank::load_question_data($qtype->save_question((object)['qtype' => 'clozeonimage'], $f)->id);
        $original = $save(clone $form);
        $verify = function($data) use ($original, $active) {
            $this->assertSame($original->options->composition, $data->options->composition);
            $this->assertEquals((int)$active, $data->options->showcomposition);
            $this->assertStringContainsString('9.50rem', $data->questiontext);
            $this->assertStringContainsString('8em,16em', $data->options->positions[1]->content);
            $this->assertSame(str_repeat('a', 32), $data->options->positions[1]->relativeimage);
            $this->assertEquals(123.456, $data->options->positions[1]->xleft);
            $this->assertEquals(-123.5, $data->options->positions[1]->ytop);
            $this->assertEquals(320.25, $data->options->positions[1]->width);
            $this->assertEquals(-9999, $data->options->positions[1]->layer);
            $this->assertEquals(1, $data->options->positions[1]->stickybottom);
            $this->assertEquals(1, $data->options->positions[1]->stickytop);
            $this->assertEquals(3, $data->options->positions[1]->stickyhorizontal);
            $this->assertEquals(0, $data->options->positions[2]->stickybottom);
            $this->assertEquals(0, $data->options->positions[2]->stickytop);
            $this->assertEquals(1, $data->options->positions[2]->stickyhorizontal);
            $this->assertCount(2, $data->options->images);
            foreach ($data->options->images as $image) {
                $this->assertCount(1, get_file_storage()->get_area_files($data->contextid,
                    'qtype_clozeonimage', 'image', $image->id, 'id', false));
            }
        };
        $verify($save(clone $form));
        $format = new \qformat_xml();
        $xml = $format->writequestion($original);
        $parsed = (new \core\xml_parser())->parse('<quiz>' . $xml . '</quiz>');
        foreach (['0', '1', '3', '2garbage'] as $version) {
            $unsupported = $parsed['quiz']['#']['question'][0];
            $unsupported['#']['formatversion'][0]['#'] = $version;
            $this->assertFalse($qtype->import_from_xml($unsupported, null, $format));
        }
        foreach (['2147483648', '-2147483649', '1.5', '1e2'] as $bad) {
            foreach (['block', 'image'] as $kind) {
                $invalid = $parsed['quiz']['#']['question'][0];
                if ($kind === 'block') {
                    $invalid['#']['subquestions'][0]['#']['subquestion'][0]['#']['layer'][0]['#'] = $bad;
                } else {
                    $invalid['#']['images'][0]['#']['image'][0]['#']['layer'][0]['#'] = $bad;
                }
                $this->assertFalse($qtype->import_from_xml($invalid, null, $format), $kind . ' layer ' . $bad);
            }
        }
        $imported = $qtype->import_from_xml($parsed['quiz']['#']['question'][0], null, $format);
        $this->assertNotFalse($imported);
        $imported->category = $form->category;
        $verify($save($imported));
        set_config('enableasyncbackup', 0);
        quiz_add_quiz_question($original->id, $quiz);
        \core_courseformat\formatactions::cm($course)->duplicate($quiz->cmid);
        $count = 0;
        foreach (get_fast_modinfo($course)->get_instances_of('quiz') as $candidate) {
            if ($candidate->instance != $quiz->id && $candidate->name === $quiz->name . ' (copy)') {
                $count++;
                $structure = \mod_quiz\question\bank\qbank_helper::get_question_structure($candidate->instance, $candidate->context);
                $verify(\question_bank::load_question_data($structure[1]->questionid));
            }
        }
        $this->assertEquals(1, $count);
    }

    /** Active and disabled data both remain portable. */
    public static function active_states(): array {
        return [[true], [false]];
    }

    /** Informative blocks alone do not create a gradable question. */
    public function test_informative_content_is_not_a_response(): void {
        [$form] = $this->form(true, false, true);
        $form->questiontext['text'] = '';
        $form->aftertext['text'] = '';
        $form->subquestion[0]['text'] = '<!-- {1:NM:=42:0} -->';
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('nosubquestions', 'qtype_clozeonimage'));
        \question_bank::get_qtype('clozeonimage')->save_question((object)['qtype' => 'clozeonimage'], $form);
    }
}
