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

use core\xml_parser;
use question_bank;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/editlib.php');
require_once($CFG->dirroot . '/question/format/xml/format.php');

/**
 * Tests for Cloze on Image Moodle XML export.
 *
 * @package    qtype_clozeonimage
 * @copyright  2026 DynamicCourseware.org (Dominique Bauer)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\qtype_clozeonimage::class)]
final class xml_test extends \advanced_testcase {
    /**
     * Create a complete saved question for XML export.
     *
     * @param int $controlappearance Stored control appearance.
     * @return array Fixture data.
     */
    private function create_export_fixture(int $controlappearance = 0): array {
        global $DB;

        $generator = $this->getDataGenerator();
        $questiongenerator = $generator->get_plugin_generator('core_question');
        $course = $generator->create_course();
        $qbank = $generator->get_plugin_generator('mod_qbank')->create_instance(['course' => $course->id]);
        $context = \context_module::instance($qbank->cmid);
        $category = $questiongenerator->create_question_category(['contextid' => $context->id]);

        $parent = $questiongenerator->create_question('multianswer', 'twosubq', ['category' => $category->id]);
        $multianswer = $DB->get_record('question_multianswer', ['question' => $parent->id], '*', MUST_EXIST);
        $childids = array_values(array_filter(array_map('intval', explode(',', $multianswer->sequence))));

        question_bank::get_qtype('clozeonimage');
        $source = '{1:NUMERICAL:=42:0}';
        $numerical = \qtype_clozeonimage::parse_subquestion_source($source);
        $numerical->id = 0;
        $numerical->name = $parent->name;
        $numerical->parent = $parent->id;
        $numerical->category = $category->id . ',1';
        $numerical = question_bank::get_qtype($numerical->qtype)->save_question($numerical, clone($numerical));
        $childids[] = $numerical->id;

        $DB->delete_records('question_multianswer', ['question' => $parent->id]);
        $DB->update_record('question', (object) [
            'id' => $parent->id,
            'qtype' => 'clozeonimage',
            'questiontext' => '<p>Instructions before the image.</p>',
            'questiontextformat' => FORMAT_HTML,
            'defaultmark' => 3,
        ]);

        $aftertext = '<p>Text after the image. <a href="@@PLUGINFILE@@/notes.txt">Notes</a></p>';
        $DB->insert_record('qtype_clozeonimage', (object) [
            'questionid' => $parent->id,
            'sequence' => implode(',', $childids),
            'displaymode' => 0,
            'displaywidth' => 1234,
            'controlappearance' => $controlappearance,
            'aftertext' => $aftertext,
            'aftertextformat' => FORMAT_HTML,
        ]);

        $positionvalues = [
            1 => [-20, 84, 0],
            4 => [1800, 2500, 4],
            7 => [75, -30, 8],
        ];
        foreach ($positionvalues as $no => [$xleft, $ytop, $anchor]) {
            $DB->insert_record('qtype_clozeonimage_pos', (object) [
                'questionid' => $parent->id,
                'no' => $no,
                'xleft' => $xleft,
                'ytop' => $ytop,
                'anchor' => $anchor,
            ]);
        }

        $backgroundcontent = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='
        );
        $aftertextfilecontent = 'Embedded aftertext file.';
        $fs = get_file_storage();
        $fs->create_file_from_string((object) [
            'contextid' => $context->id,
            'component' => 'qtype_clozeonimage',
            'filearea' => 'bgimage',
            'itemid' => $parent->id,
            'filepath' => '/',
            'filename' => 'background.png',
        ], $backgroundcontent);
        $fs->create_file_from_string((object) [
            'contextid' => $context->id,
            'component' => 'qtype_clozeonimage',
            'filearea' => 'aftertext',
            'itemid' => $parent->id,
            'filepath' => '/',
            'filename' => 'notes.txt',
        ], $aftertextfilecontent);

        return [
            'course' => $course,
            'category' => $category,
            'parentid' => $parent->id,
            'childids' => $childids,
            'positions' => $positionvalues,
            'aftertext' => $aftertext,
            'backgroundcontent' => $backgroundcontent,
            'aftertextfilecontent' => $aftertextfilecontent,
        ];
    }

    /**
     * Parse one exported question fragment.
     *
     * @param string $xml Exported question XML.
     * @return array Parsed question node.
     */
    private function parse_question_xml(string $xml): array {
        $parsed = (new xml_parser())->parse("<?xml version=\"1.0\"?><quiz>{$xml}</quiz>");
        return $parsed['quiz']['#']['question'][0];
    }

    /**
     * Configure a real Moodle XML import into a target category.
     *
     * @param string $questionxml Exported question XML fragment.
     * @param stdClass $course Target course.
     * @param stdClass $category Target question category.
     * @param context $context Target question-bank context.
     * @return qformat_xml Configured XML importer.
     */
    private function create_importer(
        string $questionxml,
        \stdClass $course,
        \stdClass $category,
        \context $context
    ): \qformat_xml {
        $filename = make_request_directory() . '/clozeonimage.xml';
        file_put_contents($filename, "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<quiz>\n{$questionxml}</quiz>\n");

        $format = new \qformat_xml();
        $format->setContexts([$context]);
        $format->setCourse($course);
        $format->setCategory($category);
        $format->setFilename($filename);
        $format->setRealfilename('clozeonimage.xml');
        $format->setMatchgrades('error');
        $format->setStoponerror(true);
        $format->set_display_progress(false);
        return $format;
    }

    /**
     * Remove the site-specific question ID comment before semantic XML comparison.
     *
     * @param string $xml Exported question XML.
     * @return string Normalised XML.
     */
    private function normalise_question_id_comment(string $xml): string {
        return (string) preg_replace('/(?<=<!-- question: )[0-9]+(?=  -->)/', '0', $xml);
    }

    /**
     * Make one structural part of an otherwise valid export malformed.
     *
     * @param string $xml Valid exported question XML.
     * @param string $case Mutation identifier.
     * @return string Malformed question XML.
     */
    private function make_structurally_invalid_xml(string $xml, string $case): string {
        $document = new \DOMDocument();
        $document->loadXML("<?xml version=\"1.0\"?><quiz>{$xml}</quiz>");
        $xpath = new \DOMXPath($document);

        switch ($case) {
            case 'missingbackgroundimage':
                $file = $xpath->query('/quiz/question/backgroundimage/file')->item(0);
                $file->parentNode->removeChild($file);
                break;
            case 'multiplebackgroundimages':
                $file = $xpath->query('/quiz/question/backgroundimage/file')->item(0);
                $secondfile = $file->cloneNode(true);
                $secondfile->setAttribute('name', 'second-background.png');
                $file->parentNode->appendChild($secondfile);
                break;
            case 'nosubquestions':
                $subquestions = $xpath->query('/quiz/question/subquestions')->item(0);
                while ($subquestions->firstChild) {
                    $subquestions->removeChild($subquestions->firstChild);
                }
                break;
            case 'duplicaterownumber':
                $secondno = $xpath->query('/quiz/question/subquestions/subquestion[2]/no')->item(0);
                $secondno->nodeValue = '1';
                break;
            case 'zerorownumber':
                $xpath->query('/quiz/question/subquestions/subquestion[1]/no')->item(0)->nodeValue = '0';
                break;
            case 'negativerownumber':
                $xpath->query('/quiz/question/subquestions/subquestion[1]/no')->item(0)->nodeValue = '-1';
                break;
            case 'nonintegerrownumber':
                $xpath->query('/quiz/question/subquestions/subquestion[1]/no')->item(0)->nodeValue = 'one';
                break;
            case 'missingsource':
                $source = $xpath->query('/quiz/question/subquestions/subquestion[1]/source')->item(0);
                $source->parentNode->removeChild($source);
                break;
            case 'blanksource':
                $xpath->query('/quiz/question/subquestions/subquestion[1]/source/text')->item(0)->nodeValue = '   ';
                break;
            case 'sourcewithoutcloze':
                $xpath->query('/quiz/question/subquestions/subquestion[1]/source/text')->item(0)->nodeValue =
                    'This row contains no Cloze subquestion.';
                break;
            case 'sourcewithmultiplecloze':
                $xpath->query('/quiz/question/subquestions/subquestion[1]/source/text')->item(0)->nodeValue =
                    '{1:SHORTANSWER:=One}{1:NUMERICAL:=2}';
                break;
        }

        return $document->saveXML($xpath->query('/quiz/question')->item(0));
    }

    /**
     * Change or remove one scalar value in an otherwise valid export.
     *
     * @param string $xml Valid exported question XML.
     * @param string $path XPath selecting the scalar element.
     * @param string|null $value Replacement value, or null to remove the element.
     * @return string Mutated question XML.
     */
    private function mutate_xml_value(string $xml, string $path, ?string $value): string {
        $document = new \DOMDocument();
        $document->loadXML("<?xml version=\"1.0\"?><quiz>{$xml}</quiz>");
        $xpath = new \DOMXPath($document);
        $node = $xpath->query($path)->item(0);
        $this->assertNotNull($node, 'The XML mutation path must select an element.');

        if ($value === null) {
            $node->parentNode->removeChild($node);
        } else {
            $node->nodeValue = $value;
        }

        return $document->saveXML($xpath->query('/quiz/question')->item(0));
    }

    /**
     * Assert that real Moodle XML import rejects a question without persisting any of it.
     *
     * @param string $invalidxml Malformed question XML.
     */
    private function assert_import_rejected_without_persistence(string $invalidxml): void {
        global $DB;

        $generator = $this->getDataGenerator();
        $targetcourse = $generator->create_course();
        $targetqbank = $generator->get_plugin_generator('mod_qbank')->create_instance([
            'course' => $targetcourse->id,
        ]);
        $targetcontext = \context_module::instance($targetqbank->cmid);
        $targetcategory = $generator->get_plugin_generator('core_question')->create_question_category([
            'contextid' => $targetcontext->id,
        ]);

        $beforecounts = [
            'questions' => $DB->count_records('question'),
            'question bank entries' => $DB->count_records('question_bank_entries'),
            'question versions' => $DB->count_records('question_versions'),
            'plugin options' => $DB->count_records('qtype_clozeonimage'),
            'positions' => $DB->count_records('qtype_clozeonimage_pos'),
            'all files' => $DB->count_records('files'),
            'plugin files' => $DB->count_records('files', ['component' => 'qtype_clozeonimage']),
        ];

        $importer = $this->create_importer($invalidxml, $targetcourse, $targetcategory, $targetcontext);
        ob_start();
        $importresult = $importer->importprocess();
        $output = ob_get_clean();

        $this->assertFalse($importresult);
        $this->assertNotSame('', trim($output));
        $this->assertEmpty($importer->questionids);
        $aftercounts = [
            'questions' => $DB->count_records('question'),
            'question bank entries' => $DB->count_records('question_bank_entries'),
            'question versions' => $DB->count_records('question_versions'),
            'plugin options' => $DB->count_records('qtype_clozeonimage'),
            'positions' => $DB->count_records('qtype_clozeonimage_pos'),
            'all files' => $DB->count_records('files'),
            'plugin files' => $DB->count_records('files', ['component' => 'qtype_clozeonimage']),
        ];
        foreach ($beforecounts as $recordtype => $beforecount) {
            $this->assertSame($beforecount, $aftercounts[$recordtype], 'Unexpected persisted ' . $recordtype . '.');
        }

        $targetquestions = $DB->get_records_sql(
            "SELECT q.id
                                                   FROM {question} q
                                                   JOIN {question_versions} qv ON qv.questionid = q.id
                                                   JOIN {question_bank_entries} qbe
                                                     ON qbe.id = qv.questionbankentryid
                                                  WHERE qbe.questioncategoryid = ?",
            [$targetcategory->id]
        );
        $this->assertEmpty($targetquestions);
    }

    /**
     * Structural malformed-XML cases.
     *
     * @return array Test cases.
     */
    public static function structural_invalid_xml_provider(): array {
        return [
            'missing background image' => ['missingbackgroundimage'],
            'multiple background images' => ['multiplebackgroundimages'],
            'no subquestions' => ['nosubquestions'],
            'duplicate visible row number' => ['duplicaterownumber'],
            'zero row number' => ['zerorownumber'],
            'negative row number' => ['negativerownumber'],
            'non-integer row number' => ['nonintegerrownumber'],
            'missing source' => ['missingsource'],
            'blank source' => ['blanksource'],
            'source without Cloze' => ['sourcewithoutcloze'],
            'source with multiple Cloze subquestions' => ['sourcewithmultiplecloze'],
        ];
    }

    /**
     * Malformed coordinate and display values.
     *
     * @return array Test cases.
     */
    public static function invalid_value_xml_provider(): array {
        return [
            'missing xleft' => ['/quiz/question/subquestions/subquestion[1]/xleft', null],
            'missing ytop' => ['/quiz/question/subquestions/subquestion[1]/ytop', null],
            'decimal xleft' => ['/quiz/question/subquestions/subquestion[1]/xleft', '12.5'],
            'decimal ytop' => ['/quiz/question/subquestions/subquestion[1]/ytop', '-12.5'],
            'alphabetic coordinate' => ['/quiz/question/subquestions/subquestion[1]/xleft', 'abc'],
            'mixed coordinate' => ['/quiz/question/subquestions/subquestion[1]/ytop', '10abc'],
            'empty coordinate' => ['/quiz/question/subquestions/subquestion[1]/xleft', ''],
            'minus-only coordinate' => ['/quiz/question/subquestions/subquestion[1]/ytop', '-'],
            'plus-only coordinate' => ['/quiz/question/subquestions/subquestion[1]/xleft', '+'],
            'whitespace-only coordinate' => ['/quiz/question/subquestions/subquestion[1]/xleft', '   '],
            'anchor below range' => ['/quiz/question/subquestions/subquestion[1]/anchor', '-1'],
            'anchor above range' => ['/quiz/question/subquestions/subquestion[1]/anchor', '9'],
            'decimal anchor' => ['/quiz/question/subquestions/subquestion[1]/anchor', '4.5'],
            'alphabetic anchor' => ['/quiz/question/subquestions/subquestion[1]/anchor', 'centre'],
            'empty anchor' => ['/quiz/question/subquestions/subquestion[1]/anchor', ''],
            'whitespace-only anchor' => ['/quiz/question/subquestions/subquestion[1]/anchor', '   '],
            'missing anchor' => ['/quiz/question/subquestions/subquestion[1]/anchor', null],
            'decimal display width' => ['/quiz/question/displaywidth', '900.5'],
            'alphanumeric display width' => ['/quiz/question/displaywidth', '900px'],
            'empty display width' => ['/quiz/question/displaywidth', ''],
            'whitespace-only display width' => ['/quiz/question/displaywidth', '   '],
            'negative display width' => ['/quiz/question/displaywidth', '-1'],
            'invalid display mode integer' => ['/quiz/question/displaymode', '2'],
            'nonnumeric display mode' => ['/quiz/question/displaymode', 'fit'],
            'empty display mode' => ['/quiz/question/displaymode', ''],
            'whitespace-only display mode' => ['/quiz/question/displaymode', '   '],
        ];
    }

    /**
     * A complete question exports portable plugin data and both file areas.
     */
    public function test_xml_export(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $fixture = $this->create_export_fixture();

        $questiondata = question_bank::load_question_data($fixture['parentid']);
        $xml = (new \qformat_xml())->writequestion($questiondata);
        $questionxml = $this->parse_question_xml($xml);

        $this->assertSame(1, substr_count($xml, '<question type="clozeonimage">'));
        $this->assertSame(1, substr_count($xml, '<defaultgrade>'));
        $this->assertStringNotContainsString('<question type="shortanswer">', $xml);
        $this->assertStringNotContainsString('<question type="multichoice">', $xml);
        $this->assertStringNotContainsString('<question type="numerical">', $xml);
        foreach (['sequence', 'questionid', 'positionid', 'contextid', 'childid'] as $forbiddentag) {
            $this->assertStringNotContainsString('<' . $forbiddentag . '>', $xml);
        }

        $this->assertSame('0', $questionxml['#']['displaymode'][0]['#']);
        $this->assertSame('1234', $questionxml['#']['displaywidth'][0]['#']);
        $this->assertSame('0', $questionxml['#']['controlappearance'][0]['#']);

        $backgroundfile = $questionxml['#']['backgroundimage'][0]['#']['file'][0];
        $this->assertSame('background.png', $backgroundfile['@']['name']);
        $this->assertSame('/', $backgroundfile['@']['path']);
        $this->assertSame('base64', $backgroundfile['@']['encoding']);
        $this->assertSame($fixture['backgroundcontent'], base64_decode($backgroundfile['#']));

        $aftertext = $questionxml['#']['aftertext'][0];
        $this->assertSame('html', $aftertext['@']['format']);
        $this->assertSame($fixture['aftertext'], $aftertext['#']['text'][0]['#']);
        $aftertextfile = $aftertext['#']['file'][0];
        $this->assertSame('notes.txt', $aftertextfile['@']['name']);
        $this->assertSame('/', $aftertextfile['@']['path']);
        $this->assertSame('base64', $aftertextfile['@']['encoding']);
        $this->assertSame($fixture['aftertextfilecontent'], base64_decode($aftertextfile['#']));

        $rows = $questionxml['#']['subquestions'][0]['#']['subquestion'];
        $this->assertCount(3, $rows);
        $expectedsources = [
            '{1:SHORTANSWER:Dog#Wrong, silly!~=Owl#Well done!~*#Wrong answer}',
            '{1:MULTICHOICE:Bow-wow#You seem to have a dog obsessions!'
                . '~Wiggly worm#Now you are just being ridiculous!~=Pussy-cat#Well done!}',
            '{1:NUMERICAL:=42:0}',
        ];
        foreach ($rows as $index => $row) {
            $no = (int) $row['#']['no'][0]['#'];
            [$xleft, $ytop, $anchor] = $fixture['positions'][$no];
            $this->assertSame(array_keys($fixture['positions'])[$index], $no);
            $this->assertSame($expectedsources[$index], $row['#']['source'][0]['#']['text'][0]['#']);
            $this->assertSame($xleft, (int) $row['#']['xleft'][0]['#']);
            $this->assertSame($ytop, (int) $row['#']['ytop'][0]['#']);
            $this->assertSame($anchor, (int) $row['#']['anchor'][0]['#']);
        }
    }

    /**
     * Category export includes the parent only, never its wrapped children.
     */
    public function test_category_export_does_not_export_wrapped_children(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $fixture = $this->create_export_fixture();

        $format = new \qformat_xml();
        $format->setCourse($fixture['course']);
        $format->setCategory($fixture['category']);
        $format->set_display_progress(false);
        $xml = $format->exportprocess(false);

        $this->assertSame(1, substr_count($xml, '<question type="clozeonimage">'));
        $this->assertSame(1, substr_count($xml, '<!-- question:'));
        $this->assertStringNotContainsString('<question type="shortanswer">', $xml);
        $this->assertStringNotContainsString('<question type="multichoice">', $xml);
        $this->assertStringNotContainsString('<question type="numerical">', $xml);
        foreach ($fixture['childids'] as $childid) {
            $this->assertStringNotContainsString('<!-- question: ' . $childid . '  -->', $xml);
        }
    }

    /**
     * Stored control appearances.
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
     * Exported XML imports as a new complete question and re-exports equivalently.
     *
     * @param int $controlappearance Stored control appearance.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('control_appearance_provider')]
    public function test_xml_export_import_persistence_round_trip(int $controlappearance): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $fixture = $this->create_export_fixture($controlappearance);

        $sourcequestion = question_bank::load_question_data($fixture['parentid']);
        $sourcexml = (new \qformat_xml())->writequestion($sourcequestion);
        $sourcequestionxml = $this->parse_question_xml($sourcexml);
        $this->assertSame(
            (string) $controlappearance,
            $sourcequestionxml['#']['controlappearance'][0]['#']
        );

        $generator = $this->getDataGenerator();
        $targetcourse = $generator->create_course();
        $targetqbank = $generator->get_plugin_generator('mod_qbank')->create_instance([
            'course' => $targetcourse->id,
        ]);
        $targetcontext = \context_module::instance($targetqbank->cmid);
        $targetcategory = $generator->get_plugin_generator('core_question')->create_question_category([
            'contextid' => $targetcontext->id,
        ]);

        $importer = $this->create_importer($sourcexml, $targetcourse, $targetcategory, $targetcontext);
        $this->assertTrue($importer->importprocess());
        $this->assertCount(1, $importer->questionids);
        $importedparentid = (int) reset($importer->questionids);
        $this->assertNotSame((int) $fixture['parentid'], $importedparentid);

        $importedquestion = question_bank::load_question_data($importedparentid);
        $this->assertSame('clozeonimage', $importedquestion->qtype);
        $this->assertSame('<p>Instructions before the image.</p>', $importedquestion->questiontext);
        $this->assertSame(3.0, (float) $importedquestion->defaultmark);
        $this->assertSame(0, (int) $importedquestion->options->displaymode);
        $this->assertSame(1234, (int) $importedquestion->options->displaywidth);
        $this->assertSame($controlappearance, (int) $importedquestion->options->controlappearance);
        $this->assertSame($fixture['aftertext'], $importedquestion->options->aftertext);
        $this->assertSame((int) FORMAT_HTML, (int) $importedquestion->options->aftertextformat);

        $importedchildids = array_values(array_filter(array_map(
            'intval',
            explode(',', $importedquestion->options->sequence)
        )));
        $this->assertCount(3, $importedchildids);
        $this->assertEmpty(array_intersect($fixture['childids'], $importedchildids));
        $this->assertSame([1, 2, 3], array_keys($importedquestion->options->questions));
        $this->assertSame([1 => 1, 2 => 4, 3 => 7], $importedquestion->options->questionrows);
        $this->assertSame(
            ['shortanswer', 'multichoice', 'numerical'],
            array_values(array_map(static fn($child) => $child->qtype, $importedquestion->options->questions))
        );
        foreach ($importedchildids as $childid) {
            $this->assertSame(
                $importedparentid,
                (int) $DB->get_field('question', 'parent', ['id' => $childid], MUST_EXIST)
            );
        }

        $this->assertSame([1, 4, 7], array_keys($importedquestion->options->positions));
        foreach ($fixture['positions'] as $no => [$xleft, $ytop, $anchor]) {
            $position = $importedquestion->options->positions[$no];
            $this->assertSame($xleft, (int) $position->xleft);
            $this->assertSame($ytop, (int) $position->ytop);
            $this->assertSame($anchor, (int) $position->anchor);
        }

        $fs = get_file_storage();
        $backgroundfile = $fs->get_file(
            $targetcontext->id,
            'qtype_clozeonimage',
            'bgimage',
            $importedparentid,
            '/',
            'background.png'
        );
        $this->assertNotFalse($backgroundfile);
        $this->assertSame($fixture['backgroundcontent'], $backgroundfile->get_content());
        $aftertextfile = $fs->get_file(
            $targetcontext->id,
            'qtype_clozeonimage',
            'aftertext',
            $importedparentid,
            '/',
            'notes.txt'
        );
        $this->assertNotFalse($aftertextfile);
        $this->assertSame($fixture['aftertextfilecontent'], $aftertextfile->get_content());

        $runtimequestion = question_bank::make_question($importedquestion);
        $runtimequestion->start_attempt(new \question_attempt_step(), 1);
        $correctmultichoice = $runtimequestion->subquestions[2]->get_correct_response();
        $this->assertNotNull($correctmultichoice);
        [$fraction, $state] = $runtimequestion->grade_response([
            'sub1_answer' => 'Owl',
            'sub2_answer' => reset($correctmultichoice),
            'sub3_answer' => '42',
        ]);
        $this->assertSame(1.0, $fraction);
        $this->assertEquals(\question_state::$gradedright, $state);

        $reexportedxml = (new \qformat_xml())->writequestion($importedquestion);
        $this->assertXmlStringEqualsXmlString(
            '<quiz>' . $this->normalise_question_id_comment($sourcexml) . '</quiz>',
            '<quiz>' . $this->normalise_question_id_comment($reexportedxml) . '</quiz>'
        );
    }

    /**
     * Structurally malformed XML is rejected before any question data is persisted.
     *
     * @param string $case XML mutation identifier.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('structural_invalid_xml_provider')]
    public function test_structurally_invalid_xml_is_rejected_without_persistence(string $case): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $fixture = $this->create_export_fixture();
        $validxml = (new \qformat_xml())->writequestion(question_bank::load_question_data($fixture['parentid']));
        $invalidxml = $this->make_structurally_invalid_xml($validxml, $case);

        $this->assert_import_rejected_without_persistence($invalidxml);
    }

    /**
     * Malformed coordinate and display values are rejected without partial persistence.
     *
     * @param string $path XPath selecting the value to mutate.
     * @param string|null $value Replacement value, or null to remove the element.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalid_value_xml_provider')]
    public function test_invalid_xml_values_are_rejected_without_persistence(string $path, ?string $value): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $fixture = $this->create_export_fixture();
        $validxml = (new \qformat_xml())->writequestion(question_bank::load_question_data($fixture['parentid']));
        $invalidxml = $this->mutate_xml_value($validxml, $path, $value);

        $this->assert_import_rejected_without_persistence($invalidxml);
    }

    /**
     * Signed/outside coordinates, all anchors, and retained display values survive import exactly.
     */
    public function test_valid_xml_value_domains_are_preserved(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $fixture = $this->create_export_fixture();
        $xml = (new \qformat_xml())->writequestion(question_bank::load_question_data($fixture['parentid']));
        $xml = $this->mutate_xml_value($xml, '/quiz/question/subquestions/subquestion[1]/xleft', '0');
        $xml = $this->mutate_xml_value($xml, '/quiz/question/subquestions/subquestion[1]/ytop', '0');
        $xml = $this->mutate_xml_value($xml, '/quiz/question/subquestions/subquestion[2]/xleft', '-25');
        $xml = $this->mutate_xml_value($xml, '/quiz/question/subquestions/subquestion[2]/ytop', '-300');
        $xml = $this->mutate_xml_value($xml, '/quiz/question/subquestions/subquestion[3]/xleft', '3000000');
        $xml = $this->mutate_xml_value($xml, '/quiz/question/subquestions/subquestion[3]/ytop', '4000000');
        $xml = $this->mutate_xml_value($xml, '/quiz/question/displaywidth', '0');
        $xml = $this->mutate_xml_value($xml, '/quiz/question/displaymode', '1');

        $generator = $this->getDataGenerator();
        $targetcourse = $generator->create_course();
        $targetqbank = $generator->get_plugin_generator('mod_qbank')->create_instance([
            'course' => $targetcourse->id,
        ]);
        $targetcontext = \context_module::instance($targetqbank->cmid);
        $targetcategory = $generator->get_plugin_generator('core_question')->create_question_category([
            'contextid' => $targetcontext->id,
        ]);

        $importer = $this->create_importer($xml, $targetcourse, $targetcategory, $targetcontext);
        $this->assertTrue($importer->importprocess());
        $importedparentid = (int) reset($importer->questionids);
        $options = $DB->get_record('qtype_clozeonimage', ['questionid' => $importedparentid], '*', MUST_EXIST);
        $positionrecords = $DB->get_records('qtype_clozeonimage_pos', ['questionid' => $importedparentid], 'no');
        $positions = [];
        foreach ($positionrecords as $position) {
            $positions[(int) $position->no] = $position;
        }

        $this->assertSame(0, (int) $options->displaywidth);
        $this->assertSame(1, (int) $options->displaymode);
        $this->assertSame([1, 4, 7], array_map('intval', array_keys($positions)));
        $this->assertSame([0, 0, 0], [
            (int) $positions[1]->xleft,
            (int) $positions[1]->ytop,
            (int) $positions[1]->anchor,
        ]);
        $this->assertSame([-25, -300, 4], [
            (int) $positions[4]->xleft,
            (int) $positions[4]->ytop,
            (int) $positions[4]->anchor,
        ]);
        $this->assertSame([3000000, 4000000, 8], [
            (int) $positions[7]->xleft,
            (int) $positions[7]->ytop,
            (int) $positions[7]->anchor,
        ]);
    }

    /**
     * Absent display elements retain their backward-compatible defaults.
     */
    public function test_absent_display_values_use_defaults(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $fixture = $this->create_export_fixture();
        $xml = (new \qformat_xml())->writequestion(question_bank::load_question_data($fixture['parentid']));
        $xml = $this->mutate_xml_value($xml, '/quiz/question/displaywidth', null);
        $xml = $this->mutate_xml_value($xml, '/quiz/question/displaymode', null);

        $generator = $this->getDataGenerator();
        $targetcourse = $generator->create_course();
        $targetqbank = $generator->get_plugin_generator('mod_qbank')->create_instance([
            'course' => $targetcourse->id,
        ]);
        $targetcontext = \context_module::instance($targetqbank->cmid);
        $targetcategory = $generator->get_plugin_generator('core_question')->create_question_category([
            'contextid' => $targetcontext->id,
        ]);

        $importer = $this->create_importer($xml, $targetcourse, $targetcategory, $targetcontext);
        $this->assertTrue($importer->importprocess());
        $options = $DB->get_record(
            'qtype_clozeonimage',
            ['questionid' => (int) reset($importer->questionids)],
            '*',
            MUST_EXIST
        );
        $this->assertSame(0, (int) $options->displaywidth);
        $this->assertSame(0, (int) $options->displaymode);
    }
}
