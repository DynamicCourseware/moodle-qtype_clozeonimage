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

$string['addmoresubquestions'] = 'Blanks for 3 more blocks';
$string['aftertext'] = 'Additional text';
$string['aftertext_help'] = 'Optional formatted text displayed after the composition, or after Question text when there is no image. Cloze expressions are supported, including fixed and adaptive field widths such as SHORTANSWER(150px), SHORTANSWER(9.5rem) and NUMERICAL(8em,16em).';
$string['anchor'] = 'Anchor';
$string['anchorforsubquestion'] = 'Anchor for block {$a}';
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
$string['bgimage_help'] = 'Optional background image. Blocks are displayed and evaluated only when this image is present; Question text and Additional text are always displayed.';
$string['cannotexportcorruptpositionmetadata'] = 'This Cloze on Image question cannot be exported because its stored position data are inconsistent.';
$string['cannotexportmissingchild'] = 'This Cloze on Image question cannot be exported because one or more of its subquestions are missing.';
$string['clearmychoice'] = 'Clear selection';
$string['currentbackgroundimage'] = 'Current background image:';
$string['clozesubquestionextratext'] = 'The row contains text outside the single Cloze expression.';
$string['controlappearance'] = 'Answer field background';
$string['blockappearance'] = 'Block background';
$string['blockappearance_help'] = 'Choose a transparent, translucent white or opaque white background for whole blocks. This is independent of the answer field background.';
$string['controlappearancetransparent'] = 'Transparent';
$string['controlappearance_help'] = 'Choose a transparent, translucent white or opaque white background for answer fields in Question text, blocks and Additional text. Text and result outlines remain fully visible.';
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
$string['imageheader'] = 'Background image';
$string['invalidnumericalresponse'] = 'Please enter a valid number.';
$string['invalidnumericalresponses'] = 'One or more Numerical responses are invalid.';
$string['left'] = 'Left (px)';
$string['leftposition'] = 'Left position for block {$a}';
$string['missingsubquestionedit'] = 'The saved subquestion for this row is missing, and its original source cannot be recovered. Enter a valid replacement Cloze subquestion before saving.';
$string['multipleclozesubquestions'] = 'More than one Cloze expression was found.';
$string['nobgimage'] = 'Please upload an image.';
$string['noclozesubquestion'] = 'No complete Cloze expression was recognised.';
$string['nosubquestions'] = 'Enter at least one valid Cloze expression in the displayed content: Question text, visible blocks, or Additional text.';
$string['numbercolumn'] = 'Order';
$string['pluginname'] = 'Cloze on Image';
$string['pluginname_help'] = 'Create a Cloze question with a composition of rich content blocks and optional images.';
$string['pluginname_link'] = 'question/type/clozeonimage';
$string['pluginnameadding'] = 'Adding a Cloze on Image question';
$string['pluginnameediting'] = 'Editing a Cloze on Image question';
$string['pluginnamesummary'] = 'Places rich content blocks and optional images in an independent composition with ordinary Cloze responses.';
$string['previewempty'] = 'Add images or content blocks to the composition.';
$string['previewheader'] = 'Composition';
$string['privacy:metadata'] = 'The Cloze on Image question type does not store personal data.';
$string['sourceforsubquestion'] = 'Content for block {$a}';
$string['subquestion'] = 'Block';
$string['subquestionerrorprefix'] = 'Block {$a}:';
$string['subquestionno'] = 'Block {$a}';
$string['subquestionsheader'] = 'Blocks';
$string['top'] = 'Top (px)';
$string['topposition'] = 'Top position for block {$a}';
$string['updatepreview'] = 'Validate and update preview';
$string['invalidroworder'] = 'The subquestion order is invalid. Reload the question and try again.';

$string['blockwidth'] = 'Width (px)';
$string['blockwidthautomatic_help'] = 'Width fits the single Cloze control automatically. Add text or an image to use the remembered manual width.';
$string['blockwidthfor'] = 'Width in pixels for block {$a}';
$string['invalidblockwidth'] = 'Block width must be a whole number between 40 and 4194303 pixels.';
$string['invalidblock'] = 'Invalid content block: {$a}';

$string['showcomposition'] = 'Show composition';
$string['showcomposition_help'] = 'Display the composition and evaluate its active Cloze expressions. Turning this off retains all images, blocks and settings, but excludes the blocks from display and grading. An image is optional.';
$string['removebgimage'] = 'Remove background image';
$string['inactiveblocks'] = 'The composition is inactive: its images and blocks are retained for editing, but are not displayed or evaluated.';

$string['selectallblocks'] = 'Select or deselect all blocks';
$string['selectblock'] = 'Select block {$a}';
$string['deleteselectedblocks'] = 'Delete selected blocks';
$string['deleteblocksconfirm'] = 'Delete {$a} selected blocks? This change is saved only when you save the question.';

$string['numberofblocks'] = 'Number of blocks';
$string['insertabove'] = 'Above';
$string['insertbelow'] = 'Below';
$string['insertplacement'] = 'Insertion position';
$string['selectedblocks'] = 'selected blocks';
$string['insertblocks'] = 'Insert';
$string['stickybottom'] = 'Bottom';

$string['previewheader_help'] = '<ul><li>Drag blocks or images to move them.</li><li>Ctrl-click (Cmd-click on Mac) to select or deselect blocks and images, then drag a selected element to move the group. Press Escape or click empty preview space to clear the selection. Clicking an unselected block without Ctrl/Cmd returns to moving that block alone.</li><li>Drag the blue bar on the right of a block to change only its width.</li><li>Drag the blue bar on the right of an image to resize it proportionally.</li><li>Use Left, Top, Width and Anchor to adjust the layout precisely. Use either composition corner handle (arrow keys when focused) or its Width and Height fields to resize the surface without scaling its content. Reset coordinates moves the origin to (0, 0) without changing the appearance; nothing is saved until you save the question.</li></ul>';
$string['invalidfieldwidth'] = 'Invalid answer field width. Use TYPE(150px), TYPE(8em,16em) or TYPE(9.5rem) with SHORTANSWER, SHORTANSWER_C or NUMERICAL (including their aliases). Use whole numbers for px, or decimals with a dot for em/rem. Use lowercase units and no spaces inside the parameter.';
$string['fieldwidthmixedunits'] = 'Both answer field width bounds must use the same unit: px, em or rem.';
$string['fieldwidthrange'] = 'Each answer field width must be between {$a->min} and {$a->max} {$a->unit}.';
$string['fieldwidthorder'] = 'The maximum answer field width must be greater than or equal to the minimum.';

$string['composition'] = 'Composition';
$string['aspectratio'] = 'Aspect ratio';
$string['compositionwidth'] = 'Width (px)';
$string['compositionheight'] = 'Height (px)';
$string['lockaspectratio'] = 'Lock aspect ratio';
$string['compositionbackground'] = 'Background color';
$string['relativeimage'] = 'Position relative to';
$string['layer'] = 'Layer';
$string['sceneimagename'] = 'Image {$a}';
$string['imagekey'] = 'Image identity';
$string['imagename'] = 'Name';
$string['imageleft'] = 'Left (px)';
$string['imagetop'] = 'Top (px)';
$string['imagewidth'] = 'Width (px)';
$string['imageheight'] = 'Height (px)';
$string['imagelayer'] = 'Layer';
$string['removeimage'] = 'Delete {$a} on save';
$string['addimage'] = 'Blanks for 1 more image';
$string['invalidcomposition'] = 'Invalid composition geometry or element identity. Enter positive dimensions up to 4194303 pixels, finite coordinates and a valid background colour.';
$string['invalidcompositionratio'] = 'The composition dimensions do not match the locked aspect ratio.';
$string['invalidcompositionimage'] = 'Choose one supported web image with readable dimensions within the upload limit.';

$string['currentcompositionimage'] = 'Current image:';

$string['compositioncustom'] = 'Custom';

$string['dimensions'] = 'Dimensions';
$string['imagesheader'] = 'Images';
$string['integerpixels'] = 'Enter a whole number of pixels (no decimal places).';
$string['invalidlayer'] = 'Layer must be a whole number from -2147483648 to 2147483647.';
$string['subquestionsheader_help'] = 'Each block may contain formatted text, images and one or more Cloze expressions. Informative blocks without an answer are allowed; the displayed question must contain at least one active Cloze expression.

Left and Top are measured in pixels. Pixel fields display the nearest whole number; unchanged fields retain their precise internal values. Enter whole numbers to edit positions and dimensions. Width includes the block padding and border; a block containing only one Cloze expression is sized automatically.

SHORTANSWER, SHORTANSWER_C and NUMERICAL (including their aliases) accept TYPE(150px) for a fixed field or TYPE(150px,300px) for a field that grows and shrinks with the entered text. Use whole values from 40 to 2000 px, or decimal values from 2.5 to 125 em/rem. For example TYPE(10em), TYPE(9.5rem) or TYPE(8em,16em). An em follows the field font size; a rem follows the page root font size. Both bounds must use the same unit, with a maximum at least as large as the minimum. Use a dot for decimals and no spaces. Relative widths are not capped in pixels. These are outer field widths, not block widths or character limits. Blocks grow with adaptive answer controls; internal scrolling is needed only when the available visible area or real content overflow requires it.

Select blocks, choose 1, 2 or 3 and Above or Below, then Insert. The chosen number of new blocks is inserted immediately before or after EACH selected block, including non-adjacent selections. Only the selection at the moment of clicking is used; new blocks are never targets in the same operation. With no blocks, Insert creates the first chosen number of blocks without a selection. Existing content and editor identities are retained.

The nine anchors select which point of the outer block rectangle stays fixed when its size changes. Position relative to associates this point with the composition or an image. Associated blocks follow their image without scaling their text or controls.

Layer is an integer from -2147483648 to 2147483647. Higher values appear in front of lower values; negative values are allowed. Layers do not change answer order or numbering.

Sticky is optional for each ordinary block. Left, Right, Top and Bottom are independent checkboxes, all off by default. Left and Right can be enabled together: the block stays at its reference position whenever both limits allow it, and otherwise sticks to the appropriate edge. These options apply in question previews and attempts. The positioning editor always shows the reference position. Sticky does not change coordinates, anchors, image associations or layers. Top and Bottom can also be combined: they constrain the corresponding vertical edges using the normal block position. Top stops below the Moodle header and Bottom at the usable bottom edge; Left and Right stop at the corresponding usable edge, respecting drawers and the composition bounds.

A block taller than the available visible composition area is temporarily reduced and its content scrolls internally, without scaling text. Its normal height returns as space becomes available. With Left or Right enabled, a block wider than the available area uses internal horizontal scrolling without rewrapping or scaling its content. Space for native scrollbars is included when available, so one bar does not unnecessarily create the other. Both bars remain available for real overflow. A block remains visible while its visible width and height are both positive, however small. It is temporarily hidden only when either becomes zero. Answers and internal scroll positions are retained. Hidden blocks cannot receive Tab focus. If the active block becomes hidden, focus moves to the composition with an explanation; it returns when the block reappears only if you have not moved focus elsewhere.';

$string['resizecompositiontopleft'] = 'Resize composition from top left (arrow keys)';
$string['resizecompositionbottomright'] = 'Resize composition from bottom right (arrow keys)';
$string['resetcoordinates'] = 'Reset coordinates';
$string['resetcoordinatestitle'] = 'Reset the composition coordinates?';
$string['resetcoordinatesconfirm'] = 'The top-left corner of the composition will be moved to (0, 0). The Left and Top coordinates of images and blocks will be adjusted, but the appearance of the composition will remain unchanged.';
$string['geometrylimit'] = 'The operation exceeds the supported geometry range (positions from -4194303 to 4194303 px; positive dimensions up to 4194303 px). No changes were applied.';

$string['positioninstruction'] = 'Position images and blocks';
$string['blockcountunit'] = 'block(s)';

$string['confirmimagedeletion'] = 'You are about to delete {$a}. Do you want to continue?';

$string['sticky'] = 'Sticky';
$string['stickytop'] = 'Top';
$string['stickyleft'] = 'Left';
$string['stickyright'] = 'Right';
$string['stickyhidden'] = 'The active block is temporarily hidden because it has no visible area in the composition. Your answer is retained. Scroll back to the block to continue.';
