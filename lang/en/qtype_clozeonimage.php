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

/**
 * English strings for qtype_clozeonimage.
 *
 * @package    qtype_clozeonimage
 * @copyright  2026 DynamicCourseware.org (Dominique Bauer)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['addmoresubquestions'] = 'Add 3 more subquestions';
$string['aftertext'] = 'Text after image';
$string['aftertext_help'] = 'Optional text displayed after the image composition.';
$string['anchor'] = 'Anchor';
$string['anchor_help'] = 'Point of the answer field that remains aligned with the image when the image size changes.';
$string['anchorforsubquestion'] = 'Anchor for subquestion {$a}';
$string['anchorposition0'] = 'Top left';
$string['anchorposition1'] = 'Top centre';
$string['anchorposition2'] = 'Top right';
$string['anchorposition3'] = 'Middle left';
$string['anchorposition4'] = 'Centre';
$string['anchorposition5'] = 'Middle right';
$string['anchorposition6'] = 'Bottom left';
$string['anchorposition7'] = 'Bottom centre';
$string['anchorposition8'] = 'Bottom right';
$string['backgroundimagealt'] = 'Background image for the Cloze on Image question';
$string['bgimage'] = 'Image';
$string['bgimage_help'] = 'Upload the single image on which the Cloze answer controls will be positioned.';
$string['cannotexportcorruptpositionmetadata'] = 'This Cloze on Image question cannot be exported because its stored position data are inconsistent.';
$string['cannotexportmissingchild'] = 'This Cloze on Image question cannot be exported because one or more of its subquestions are missing.';
$string['clearchoiceforsubquestion'] = 'Clear choice for subquestion {$a}';
$string['clozesubquestionextratext'] = 'The row contains text outside the single Cloze expression.';
$string['controlappearance'] = 'Control appearance';
$string['controlappearance_help'] = 'Choose whether positioned answer controls use translucent or opaque backgrounds.';
$string['controlappearanceopaque'] = 'Opaque';
$string['controlappearancetranslucent'] = 'Translucent';
$string['corruptpositionmetadata'] = 'This question has inconsistent stored position data. Answer controls are shown at fallback positions that may not represent the intended layout.';
$string['corruptpositionmetadataedit'] = 'The stored position data for this question are inconsistent, so the original layout cannot be trusted. Saving is blocked to prevent subquestions or positions from being silently changed. Restore a known-good backup or inspect the stored data before trying to save this question.';
$string['displaywidthminimum'] = 'Enter an image width of at least {$a} pixels.';
$string['displaywidthminimumcompact'] = '{$a} px or more';
$string['displaywidthpx'] = 'Image width (px)';
$string['displaywidthrange'] = 'Enter an image width between {$a->min} and {$a->max} pixels.';
$string['displaywidthrangecompact'] = '{$a->min}–{$a->max} px';
$string['displaywidthsinglecompact'] = '{$a} px';
$string['feedbackforsubquestion'] = 'Feedback for subquestion {$a}';
$string['imageheader'] = 'Image';
$string['invalidnumericalresponse'] = 'Please enter a valid number.';
$string['invalidnumericalresponses'] = 'One or more Numerical responses are invalid.';
$string['left'] = 'Left';
$string['left_help'] = 'Horizontal position, in pixels, of the top-left corner of the positioned answer control relative to the left edge of the image. Negative values are allowed.';
$string['leftposition'] = 'Left position for subquestion {$a}';
$string['missingsubquestionedit'] = 'The saved subquestion for this row is missing, and its original source cannot be recovered. Enter a valid replacement Cloze subquestion before saving.';
$string['multipleclozesubquestions'] = 'More than one Cloze expression was found.';
$string['nobgimage'] = 'Please upload an image.';
$string['noclozesubquestion'] = 'No complete Cloze expression was recognised.';
$string['nosubquestions'] = 'Enter at least one valid Cloze subquestion.';
$string['numbercolumn'] = 'No.';
$string['pluginname'] = 'Cloze on Image';
$string['pluginname_help'] = 'Create a Cloze question whose answer controls are positioned on a single image.';
$string['pluginname_link'] = 'question/type/clozeonimage';
$string['pluginnameadding'] = 'Adding a Cloze on Image question';
$string['pluginnameediting'] = 'Editing a Cloze on Image question';
$string['pluginnamesummary'] = 'Places ordinary Cloze subquestions visually on a single image.';
$string['previewempty'] = 'Upload an image and enter at least one valid Cloze subquestion, then click Update preview.';
$string['previewheader'] = 'Position subquestions';
$string['privacy:metadata'] = 'The Cloze on Image question type does not store personal data.';
$string['sourcecolumn'] = 'Cloze subquestion source';
$string['sourcecolumn_help'] = 'Enter one complete Cloze subquestion in source-code form, for example {1:SHORTANSWER:=Paris~Marseille}. Do not enter surrounding prose.';
$string['sourceforsubquestion'] = 'Cloze subquestion source for subquestion {$a}';
$string['subquestion'] = 'Subquestion';
$string['subquestionerrorprefix'] = 'Subquestion {$a}:';
$string['subquestionno'] = 'Subquestion {$a}';
$string['subquestionsheader'] = 'Subquestions';
$string['top'] = 'Top';
$string['top_help'] = 'Vertical position, in pixels, of the top-left corner of the positioned answer control relative to the top edge of the image. Negative values are allowed.';
$string['topposition'] = 'Top position for subquestion {$a}';
$string['updatepreview'] = 'Update preview';
