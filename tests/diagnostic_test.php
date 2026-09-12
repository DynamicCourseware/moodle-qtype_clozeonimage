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
require_once($CFG->dirroot . '/question/type/edit_question_form.php');
require_once($CFG->dirroot . '/question/type/clozeonimage/edit_clozeonimage_form.php');

/**
 * Tests for author-facing Cloze source diagnostics.
 *
 * @package    qtype_clozeonimage
 * @copyright  2026 DynamicCourseware.org (Dominique Bauer)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\qtype_clozeonimage::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\qtype_clozeonimage_edit_form::class)]
final class diagnostic_test extends \advanced_testcase {
    /**
     * Valid sources, including every supported long-form family and its alias.
     *
     * @return array<string, array{string}>
     */
    public static function valid_sources_provider(): array {
        $shortanswer = '=Paris~Marseille';
        $numerical = '=42:0~0';
        $choice = '=Paris~Marseille';
        $multiresponse = '=Paris~=Lyon~Marseille';

        return [
            'SHORTANSWER' => ["{1:SHORTANSWER:{$shortanswer}}"],
            'SA alias' => ["{1:SA:{$shortanswer}}"],
            'MW alias' => ["{1:MW:{$shortanswer}}"],
            'SHORTANSWER_C' => ["{1:SHORTANSWER_C:{$shortanswer}}"],
            'SAC alias' => ["{1:SAC:{$shortanswer}}"],
            'MWC alias' => ["{1:MWC:{$shortanswer}}"],
            'NUMERICAL' => ["{1:NUMERICAL:{$numerical}}"],
            'NM alias' => ["{1:NM:{$numerical}}"],
            'MULTICHOICE' => ["{1:MULTICHOICE:{$choice}}"],
            'MC alias' => ["{1:MC:{$choice}}"],
            'MULTICHOICE_V' => ["{1:MULTICHOICE_V:{$choice}}"],
            'MCV alias' => ["{1:MCV:{$choice}}"],
            'MULTICHOICE_H' => ["{1:MULTICHOICE_H:{$choice}}"],
            'MCH alias' => ["{1:MCH:{$choice}}"],
            'MULTICHOICE_S' => ["{1:MULTICHOICE_S:{$choice}}"],
            'MCS alias' => ["{1:MCS:{$choice}}"],
            'MULTICHOICE_VS' => ["{1:MULTICHOICE_VS:{$choice}}"],
            'MCVS alias' => ["{1:MCVS:{$choice}}"],
            'MULTICHOICE_HS' => ["{1:MULTICHOICE_HS:{$choice}}"],
            'MCHS alias' => ["{1:MCHS:{$choice}}"],
            'MULTIRESPONSE' => ["{1:MULTIRESPONSE:{$multiresponse}}"],
            'MR alias' => ["{1:MR:{$multiresponse}}"],
            'MULTIRESPONSE_H' => ["{1:MULTIRESPONSE_H:{$multiresponse}}"],
            'MRH alias' => ["{1:MRH:{$multiresponse}}"],
            'MULTIRESPONSE_S' => ["{1:MULTIRESPONSE_S:{$multiresponse}}"],
            'MRS alias' => ["{1:MRS:{$multiresponse}}"],
            'MULTIRESPONSE_HS' => ["{1:MULTIRESPONSE_HS:{$multiresponse}}"],
            'MRHS alias' => ["{1:MRHS:{$multiresponse}}"],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('valid_sources_provider')]
    public function test_valid_sources_have_no_diagnostics(string $source): void {
        [$wrapped, $diagnostics] = \qtype_clozeonimage::diagnose_subquestion_source($source);

        $this->assertNotNull($wrapped);
        $this->assertSame([], $diagnostics);
    }

    public function test_blank_intermediate_row_has_no_diagnostic(): void {
        [$wrapped, $diagnostics] = \qtype_clozeonimage::diagnose_subquestion_source(" \n ");

        $this->assertNull($wrapped);
        $this->assertSame([], $diagnostics);
    }

    /**
     * Structurally invalid sources.
     *
     * @return array<string, array{string,string}>
     */
    public static function structural_sources_provider(): array {
        return [
            'missing closing brace' => [
                '{1:SHORTANSWER:=Paris~Marseille',
                'noclozesubquestion',
            ],
            'unknown type' => [
                '{1:UNKNOWN:=Paris~Marseille}',
                'noclozesubquestion',
            ],
            'zero expressions' => [
                'This is not a Cloze expression.',
                'noclozesubquestion',
            ],
            'two expressions' => [
                '{1:SHORTANSWER:=Paris}{1:NUMERICAL:=42}',
                'multipleclozesubquestions',
            ],
            'surrounding prose' => [
                'Before {1:SHORTANSWER:=Paris~Marseille} after',
                'clozesubquestionextratext',
            ],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('structural_sources_provider')]
    public function test_structural_diagnostics_use_honest_plugin_messages(
        string $source,
        string $expectedstring
    ): void {
        [, $diagnostics] = \qtype_clozeonimage::diagnose_subquestion_source($source);

        $this->assertSame([get_string($expectedstring, 'qtype_clozeonimage')], $diagnostics);
    }

    /**
     * Semantically invalid sources and their core validation messages.
     *
     * @return array<string, array{string,string}>
     */
    public static function semantic_sources_provider(): array {
        return [
            'invalid numerical answer' => [
                '{1:NUMERICAL:=abc}',
                get_string('answermustbenumberorstar', 'qtype_numerical'),
            ],
            'no full-credit answer' => [
                '{1:SHORTANSWER:Paris~Marseille}',
                get_string('fractionsnomax', 'question'),
            ],
            'one multichoice choice' => [
                '{1:MULTICHOICE:=Paris}',
                get_string('notenoughanswers', 'qtype_multichoice', 2),
            ],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('semantic_sources_provider')]
    public function test_semantic_diagnostics_preserve_core_wording(
        string $source,
        string $expectedmessage
    ): void {
        [$wrapped, $diagnostics] = \qtype_clozeonimage::diagnose_subquestion_source($source);

        $this->assertNotNull($wrapped);
        $this->assertSame([$expectedmessage], $diagnostics);
    }

    public function test_distinct_semantic_diagnostics_are_preserved_in_order(): void {
        [, $diagnostics] = \qtype_clozeonimage::diagnose_subquestion_source('{1:NUMERICAL:abc}');

        $this->assertSame([
            get_string('answermustbenumberorstar', 'qtype_numerical'),
            get_string('fractionsnomax', 'question'),
        ], $diagnostics);
    }

    public function test_repeated_semantic_diagnostics_are_deduplicated(): void {
        [, $diagnostics] = \qtype_clozeonimage::diagnose_subquestion_source('{1:NUMERICAL:=abc~=def}');

        $this->assertSame([get_string('answermustbenumberorstar', 'qtype_numerical')], $diagnostics);
    }

    /**
     * Create a real edit form and a valid draft background image.
     *
     * @param array $request Optional request data used while constructing the form.
     * @return array{0:\qtype_clozeonimage_edit_form,1:stdClass,2:int,3:stdClass}
     */
    private function make_form(array $request = []): array {
        global $PAGE, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        $PAGE->set_url(new \moodle_url('/'));

        $course = $this->getDataGenerator()->create_course();
        $qbank = $this->getDataGenerator()->create_module('qbank', ['course' => $course->id]);
        $context = \context_module::instance($qbank->cmid);
        $category = question_get_default_category($context->id, true);

        $draftitemid = file_get_unused_draft_itemid();
        get_file_storage()->create_file_from_string((object) [
            'contextid' => \context_user::instance($USER->id)->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => $draftitemid,
            'filepath' => '/',
            'filename' => 'background.png',
        ], base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='
        ));

        $question = (object) [
            'qtype' => 'clozeonimage',
            'contextid' => $context->id,
            'createdby' => $USER->id,
            'category' => $category->id,
            'questiontext' => ['text' => 'Question text', 'format' => FORMAT_HTML],
            'options' => (object) [
                'questions' => [],
                'positions' => [],
                'questionrows' => [],
            ],
            'formoptions' => (object) [
                'movecontext' => null,
                'repeatelements' => true,
            ],
            'inputs' => null,
        ];

        $_POST = $request;
        $form = new \qtype_clozeonimage_edit_form(
            new \moodle_url('/'),
            $question,
            $category,
            new \core_question\local\bank\question_edit_contexts($context)
        );
        $_POST = [];

        return [$form, $category, $draftitemid, $question];
    }

    /**
     * Replace the form fixture's draft background image.
     *
     * @param int $draftitemid Draft item ID.
     * @param string $filename File name.
     * @param string $mimetype Stored MIME type.
     * @param string $content File content.
     */
    private function replace_draft_image(
        int $draftitemid,
        string $filename,
        string $mimetype,
        string $content
    ): void {
        global $USER;

        $contextid = \context_user::instance($USER->id)->id;
        $filestorage = get_file_storage();
        foreach (
            $filestorage->get_area_files(
                $contextid,
                'user',
                'draft',
                $draftitemid,
                'id',
                false
            ) as $file
        ) {
            $file->delete();
        }
        $filestorage->create_file_from_string((object) [
            'contextid' => $contextid,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => $draftitemid,
            'filepath' => '/',
            'filename' => $filename,
            'mimetype' => $mimetype,
        ], $content);
    }

    /**
     * Image-width validation cases for raster and vector draft files.
     *
     * @return array<string, array{string,string,string,int,string|null,int}>
     */
    public static function image_width_policy_provider(): array {
        $narrowpng = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAGQAAAAUCAIAAAD0og/CAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAAHUlE' .
            'QVRYhe3BMQEAAADCoPVPbQo/oAAAAAAAgIcBF4QAAX2PK6sAAAAASUVORK5CYII='
        );
        $widepng = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAZAAAAAUCAIAAAAsi/zhAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAALklE' .
            'QVRYnO3BgQAAAADDoPlTn+AGVQEAAAAAAAAAAAAAAAAAAAAAAAAAAAAA8Axd1AABBaOXoQAAAABJRU5ErkJggg=='
        );
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="400" height="20" ' .
            'viewBox="0 0 400 20"><rect width="400" height="20"/></svg>';

        return [
            'narrow raster intrinsic width accepted' => [
                'narrow.png', 'image/png', $narrowpng, 100, null, 100,
            ],
            'narrow raster enlargement rejected' => [
                'narrow.png', 'image/png', $narrowpng, 101, 'raster', 100,
            ],
            'ordinary raster minimum accepted' => [
                'wide.png', 'image/png', $widepng, 200, null, 400,
            ],
            'ordinary raster below minimum rejected' => [
                'wide.png', 'image/png', $widepng, 199, 'raster', 400,
            ],
            'ordinary raster above intrinsic width rejected' => [
                'wide.png', 'image/png', $widepng, 401, 'raster', 400,
            ],
            'SVG below minimum rejected' => [
                'vector.svg', 'image/svg+xml', $svg, 199, 'vector', 400,
            ],
            'SVG minimum accepted' => [
                'vector.svg', 'image/svg+xml', $svg, 200, null, 400,
            ],
            'SVG far above reported width accepted' => [
                'vector.svg', 'image/svg+xml', $svg, 5000, null, 400,
            ],
            'SVGZ above reported width accepted' => [
                'vector.svgz', 'image/svg+xml', gzencode($svg), 5000, null, 400,
            ],
            'SVG MIME with raster filename uses vector policy' => [
                'vector.png', 'image/svg+xml', $svg, 5000, null, 400,
            ],
            'raster MIME with SVG filename uses raster policy' => [
                'raster.svg', 'image/png', $widepng, 5000, 'raster', 400,
            ],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('image_width_policy_provider')]
    public function test_image_width_policy_uses_stored_mimetype(
        string $filename,
        string $mimetype,
        string $content,
        int $displaywidth,
        ?string $expectederror,
        int $reportedwidth
    ): void {
        [$form, $category, $draftitemid] = $this->make_form();
        $this->replace_draft_image($draftitemid, $filename, $mimetype, $content);

        $errors = $form->validation([
            'category' => $category->id,
            'questiontext' => ['text' => 'Question text', 'format' => FORMAT_HTML],
            'bgimage' => $draftitemid,
            'displaywidth' => $displaywidth,
            'subquestion' => ['{1:SHORTANSWER:=Paris~Marseille}'],
        ], []);

        if ($expectederror === null) {
            $this->assertArrayNotHasKey('displaywidthgroup', $errors);
        } else if ($expectederror === 'vector') {
            $this->assertSame(
                get_string('displaywidthminimum', 'qtype_clozeonimage', 200),
                $errors['displaywidthgroup']
            );
        } else {
            $this->assertSame(
                get_string('displaywidthrange', 'qtype_clozeonimage', (object) [
                    'min' => min(200, $reportedwidth),
                    'max' => $reportedwidth,
                ]),
                $errors['displaywidthgroup']
            );
        }
    }

    /**
     * Access the underlying QuickForm object.
     */
    private function get_quickform(\qtype_clozeonimage_edit_form $form): \MoodleQuickForm {
        $property = new \ReflectionProperty(\qtype_clozeonimage_edit_form::class, '_form');
        return $property->getValue($form);
    }

    /**
     * Expected accessible diagnostic HTML for a sparse form row.
     */
    private static function expected_diagnostic(int $rowno, string $message): string {
        return \html_writer::span(
            get_string('subquestionerrorprefix', 'qtype_clozeonimage', $rowno) . ' ',
            'visually-hidden'
        ) . $message;
    }

    public function test_control_appearance_form_default_and_reconstruction(): void {
        global $OUTPUT;

        [$form, , , $question] = $this->make_form();
        $quickform = $this->get_quickform($form);
        $this->assertTrue($quickform->elementExists('controlappearance'));
        $appearanceelement = $quickform->getElement('controlappearance');
        $appearanceelement->updateAttributes(['id' => 'id_controlappearance']);
        $appearancecontext = $appearanceelement->export_for_template($OUTPUT);

        $this->assertSame(
            [\qtype_clozeonimage::CONTROL_APPEARANCE_TRANSLUCENT],
            $appearanceelement->getValue()
        );
        $this->assertSame(
            [
                [
                    'value' => \qtype_clozeonimage::CONTROL_APPEARANCE_TRANSLUCENT,
                    'text' => get_string('controlappearancetranslucent', 'qtype_clozeonimage'),
                ],
                [
                    'value' => \qtype_clozeonimage::CONTROL_APPEARANCE_OPAQUE,
                    'text' => get_string('controlappearanceopaque', 'qtype_clozeonimage'),
                ],
            ],
            array_map(
                static fn(array $option): array => [
                    'value' => $option['value'],
                    'text' => $option['text'],
                ],
                $appearancecontext['options']
            )
        );

        $question->options->controlappearance = \qtype_clozeonimage::CONTROL_APPEARANCE_OPAQUE;
        $form->set_data($question);

        $this->assertSame(
            [\qtype_clozeonimage::CONTROL_APPEARANCE_OPAQUE],
            $quickform->getElement('controlappearance')->getValue()
        );
    }

    public function test_control_appearance_preview_class(): void {
        [$translucentform] = $this->make_form();
        $translucentpreview = $translucentform->render();
        $this->assertStringContainsString('id="qtype-clozeonimage-preview-area"', $translucentpreview);
        $this->assertStringContainsString(
            'qtype-clozeonimage-appearance-translucent',
            $translucentpreview
        );

        [$opaqueform] = $this->make_form([
            'controlappearance' => \qtype_clozeonimage::CONTROL_APPEARANCE_OPAQUE,
        ]);
        $opaquepreview = $opaqueform->render();
        $this->assertStringContainsString('qtype-clozeonimage-appearance-opaque', $opaquepreview);
    }

    public function test_sparse_row_has_specific_source_and_anchor_accessible_names(): void {
        [$form] = $this->make_form(['nosubquestions' => 7]);
        $row = $this->get_quickform($form)->getElement('subquestionrow[3]');
        $sourcehtml = '';
        $anchorhtml = '';
        $radiolabels = [];

        foreach ($row->getElements() as $element) {
            if ($element->getName() === 'subquestion[3]') {
                $sourcehtml = $element->toHtml();
            } else if (
                $element instanceof \HTML_QuickForm_html &&
                    str_contains($element->toHtml(), 'qtype-clozeonimage-anchor-area')
            ) {
                $anchorhtml = $element->toHtml();
            } else if ($element->getName() === 'anchor[3]') {
                $radiohtml = $element->toHtml();
                preg_match('/aria-label="([^"]+)"/', $radiohtml, $matches);
                $radiolabels[] = $matches[1] ?? '';
            }
        }

        $this->assertStringContainsString(
            'aria-label="' . get_string('sourceforsubquestion', 'qtype_clozeonimage', 4) . '"',
            $sourcehtml
        );
        $this->assertStringContainsString(
            'aria-label="' . get_string('anchorforsubquestion', 'qtype_clozeonimage', 4) . '"',
            $anchorhtml
        );
        $this->assertSame([
            get_string('anchorposition0', 'qtype_clozeonimage'),
            get_string('anchorposition1', 'qtype_clozeonimage'),
            get_string('anchorposition2', 'qtype_clozeonimage'),
            get_string('anchorposition3', 'qtype_clozeonimage'),
            get_string('anchorposition4', 'qtype_clozeonimage'),
            get_string('anchorposition5', 'qtype_clozeonimage'),
            get_string('anchorposition6', 'qtype_clozeonimage'),
            get_string('anchorposition7', 'qtype_clozeonimage'),
            get_string('anchorposition8', 'qtype_clozeonimage'),
        ], $radiolabels);
    }

    public function test_save_validation_uses_sparse_visible_row_numbers(): void {
        [$form, $category, $draftitemid] = $this->make_form();
        $sources = [
            0 => '{1:SHORTANSWER:=Paris~Marseille}',
            3 => '{1:NUMERICAL:=abc}',
            6 => '{1:MULTICHOICE:=Paris}',
        ];

        $errors = $form->validation([
            'category' => $category->id,
            'questiontext' => ['text' => 'Question text', 'format' => FORMAT_HTML],
            'bgimage' => $draftitemid,
            'displaywidth' => 1,
            'subquestion' => $sources,
        ], []);

        $this->assertArrayNotHasKey('subquestionrow[0]', $errors);
        $this->assertSame(
            self::expected_diagnostic(4, get_string('answermustbenumberorstar', 'qtype_numerical')),
            $errors['subquestionrow[3]']
        );
        $this->assertSame(
            self::expected_diagnostic(7, get_string('notenoughanswers', 'qtype_multichoice', 2)),
            $errors['subquestionrow[6]']
        );
        $this->assertSame(
            get_string('answermustbenumberorstar', 'qtype_numerical'),
            preg_replace('/<span class="visually-hidden">.*?<\/span>/', '', $errors['subquestionrow[3]'])
        );
    }

    public function test_update_preview_matches_save_and_omits_invalid_control(): void {
        $sources = [
            0 => '{1:SHORTANSWER:=Paris~Marseille}',
            3 => '{1:NUMERICAL:=abc}',
            6 => '{1:MULTICHOICE:=Paris~Marseille}',
        ];
        [$form, $category, $draftitemid] = $this->make_form([
            'updatepreview' => 1,
            'nosubquestions' => 7,
            'bgimage' => 0,
            'displaywidth' => 1,
            'subquestion' => $sources,
            'xleft' => [0 => 12, 3 => 72, 6 => 132],
            'ytop' => [0 => 12, 3 => 72, 6 => 132],
            'anchor' => [0 => 4, 3 => 4, 6 => 4],
        ]);
        $quickform = $this->get_quickform($form);
        $previewerror = $quickform->getElementError('subquestionrow[3]');

        $savedata = [
            'category' => $category->id,
            'questiontext' => ['text' => 'Question text', 'format' => FORMAT_HTML],
            'bgimage' => $draftitemid,
            'displaywidth' => 1,
            'subquestion' => $sources,
        ];
        $saveerrors = $form->validation($savedata, []);

        $this->assertSame($saveerrors['subquestionrow[3]'], $previewerror);
        $this->assertSame(
            self::expected_diagnostic(4, get_string('answermustbenumberorstar', 'qtype_numerical')),
            $previewerror
        );
        $renderedform = $form->render();
        $this->assertStringContainsString(
            '<span class="visually-hidden">Subquestion 4: </span>' .
                get_string('answermustbenumberorstar', 'qtype_numerical'),
            $renderedform
        );
        $this->assertSame(2, substr_count($renderedform, 'qtype-clozeonimage-preview-subquestion'));
        $this->assertStringContainsString('data-row-index="0"', $renderedform);
        $this->assertStringNotContainsString('data-row-index="3"', $renderedform);
        $this->assertStringContainsString('data-row-index="6"', $renderedform);
    }

    public function test_column_headings_contain_help_without_standalone_instruction(): void {
        [$form] = $this->make_form();
        $quickform = $this->get_quickform($form);
        $html = $form->render();

        $this->assertFalse($quickform->elementExists('subquestionhelp'));
        $this->assertStringContainsString(get_string('sourcecolumn_help', 'qtype_clozeonimage'), $html);
        $this->assertStringContainsString(get_string('left_help', 'qtype_clozeonimage'), $html);
        $this->assertStringContainsString(get_string('top_help', 'qtype_clozeonimage'), $html);
        $this->assertStringContainsString(get_string('anchor_help', 'qtype_clozeonimage'), $html);
        $this->assertSame(1, substr_count($html, get_string('sourcecolumn_help', 'qtype_clozeonimage')));
        $this->assertStringContainsString(
            'alt="' . get_string('backgroundimagealt', 'qtype_clozeonimage') .
                '" class="qtype-clozeonimage-preview-image"',
            $html
        );
        $this->assertStringNotContainsString(
            'alt="" class="qtype-clozeonimage-preview-image"',
            $html
        );
    }
}
