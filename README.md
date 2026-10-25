# Cloze on Image

**Cloze on Image** (`qtype_clozeonimage`) is a Moodle question type that places standard Moodle Cloze subquestions inside an autonomous composition of rich content blocks and optional images.

**Cloze on Image 1.3.0 Stable — composition format 2.** Supports Moodle 5.2 and 5.3, with clean-installation and targeted functional validation on Moodle 5.2.4 and 5.3.0.

**Legacy format-1 questions are unsupported and are not automatically migrated.** This includes questions from earlier releases such as 1.1.0. Schema upgrades retain old records and files, but do not guarantee compatible rendering, editing, grading or review of existing attempts. Administrators with legacy questions should preserve the existing installation and recoverable database/file backups, recreate questions in a separate format-2 installation, and verify them before transitioning. Do not treat 1.3.0 as a compatible in-place upgrade for legacy question banks.

The composition has its own dimensions, aspect ratio and CSS background colour. Images are optional, independently movable elements. Standard Moodle Cloze controls still handle responses, grading, feedback and review.

## Displayed content and grading

Cloze expressions are ordered **Question text → displayed blocks in their current order → Additional text**, then left to right within each zone. At least one valid Cloze expression is required across the displayed content; no individual zone is mandatory. Moodle's Cloze engine still handles responses, marks, feedback and review.

**Show composition** is checked by default. An active composition can contain blocks without images, images without blocks, or both. Informational blocks are allowed. At least one active Cloze expression must exist in Question text, active blocks or Additional text. An image-only composition therefore needs a Cloze expression in one of the two text zones.

Disabling the composition hides its elements and excludes block responses from grading and student file access. Images, rich content, files, coordinates, associations and settings are retained. Question text and Additional text stay active. The teacher preview marks the composition inactive but remains editable.

Format 2 is explicit in storage and XML. XML import requires format 2; importing legacy XML or restoring an old backup is not a migration mechanism. Recreate legacy questions using the new form. Do not downgrade the database. See [VALIDATION.md](VALIDATION.md) for the scope of validation.

## Features

- An independent composition with optional multiple images and rich blocks.
- Formatted content blocks, with or without standard Moodle Cloze expressions, positioned over or around the image.
- Drag positioning in the editing preview.
- Ctrl-click blocks or images (Cmd-click on Mac) to select a temporary preview group, marked with a small check in each block's upper-left corner. Drag any selected block to move the group by the same offset. Ctrl/Cmd-click again to deselect a block; Escape or a click on empty preview space clears the group. Clicking or dragging an unselected block without the modifier clears the group and acts on that block alone. The blue bar always resizes only its own block. Preview selection is independent of the table's insertion/deletion checkboxes and is cleared when the form is rebuilt; positions are saved only when the question is saved.
- Reorder whole blocks with Moodle's standard mouse and keyboard move controls, preserving editor content and focus.
- Insert 1, 2 or 3 blocks immediately above or below each selected block, including non-adjacent selections, or insert the first blocks into an empty list.
- Independent **Sticky Left / Right / Top / Bottom** options for ordinary blocks, with composition confinement and internal scrolling when needed.
- Resize ordinary blocks with the mouse, or enter their exact **Width** in CSS pixels; height follows content automatically. Width includes 12 px padding on every side and a 1 px border. All blocks have 8 px rounded corners; their border is light grey with translucent/opaque backgrounds and transparent with a transparent background. Controls, result outlines and feedback remain unclipped.
- Resize each image proportionally with its blue edge or **Width (px)**; only associated blocks follow its anchor transformation.
- A discreet 1 px grey border outlines the entire composition in the editor and student view, without changing its coordinates. The editor reserves 16 px of scrollable space beyond the composition's right edge for resize grips.
- Manual **Left** and **Top** coordinates.
- Positive and negative coordinates are supported.
- Controls may be positioned partly or completely outside the image.
- Nine **Anchor** positions; optional stable association to an image, independent of layer order.
- Independent **Block background** and **Answer field background** settings, with Transparent, Translucent and Opaque modes.
- Optional formatted **Additional text**, including Cloze expressions.
- Standard Moodle attempt, grading, feedback, and review behaviour.
- Moodle backup and restore support.
- Moodle XML import and export support.
- Moodle question copying and versioning support.

## Requirements

Requires Moodle 5.2 and Moodle's standard **Embedded answers (Cloze)** question type (`qtype_multianswer`), with minimum version `2026042000` for both in `version.php`. Compatibility is validated for the Moodle **5.2 and 5.3 branches**, at the exact releases below; this is not a claim about future Moodle versions.

Validation targets are Moodle **5.2.4** (`2026042004.00`) and **5.3.0** (`2026100500.00`), both Build: 20261005, with PHP 8.3.6 and MariaDB 11.4.12. Clean installations and targeted functional tests passed using the inventoried candidate. See [VALIDATION.md](VALIDATION.md) for the results and coverage limits. These results validate the candidate functionality, not a final 1.3.0 release ZIP; that archive has not yet been generated or validated.

Moodle's own PHP requirements apply (PHP 8.3 or later for these branches). This plugin declares no separate lower PHP requirement. Only PHP 8.3.6 is tested here. Moodle 5.2.0 and other patch levels, other PHP/database combinations, browsers other than Chromium and themes other than Boost have not been independently verified for this release. No compatibility with future Moodle versions is claimed.

No external library, service, or third-party Moodle plugin is required.

## Installation

1. Copy the `clozeonimage` directory to:

   `question/type/clozeonimage`

   This path is relative to Moodle's web root. For installations with a `public` directory, use `public/question/type/clozeonimage`.

2. Go to **Site administration > Notifications**, or run Moodle's normal command-line upgrade process.
3. Complete the installation.
4. In a question bank, create a new question and select **Cloze on Image**.

The plugin version is `2026102400`, release `1.3.0`, maturity `MATURITY_STABLE`; the last schema step remains `2026101902` (Sticky Bottom, disabled by default). Release preparation changes metadata and documentation only. Earlier schema steps added Top and the independent Left/Right bit mask, also defaulting to off. The composition format stores format identification, a base rectangle and settings, an image table, stable element keys and image-relative block anchors. Existing position coordinates gain fractional precision; existing values are retained. No legacy content conversion or deletion occurs; retained data does not imply legacy compatibility.

## Creating a question

A Cloze on Image form presents the principal fields in this order:

- **Question name** and **Question text**.
- **Composition**: positioning instruction, interactive preview, **Validate and update preview**, dimensions/ratio lock, **Background color**, and **Show composition** with its help.
- **Images**: numbered image pickers, positions, proportional sizing, layers and deferred removal.
- **Blocks**: editor-first rows labelled **Block 1**, **Block 2**, etc., followed by pixel positions/width, the nine-point anchor selector, image association, layer and four independent sticky options.
- **Insert** controls (quantity, Above/Below, selected blocks), then **Answer field background**, **Block background**, and **Reset coordinates / Delete selected blocks**; then formatted **Additional text** and feedback options.

Image deletion is confirmed using Moodle's standard dialog on either save action, listing the affected image labels. Cancel retains the complete unsaved form; confirmation continues the chosen save action once. Preview, block insertion and form cancellation do not delete saved images.

A typical authoring workflow is:

1. Enter the **Question name** and the **Question text** containing the instructions or other information to appear before the image.
2. Enable or disable **Show composition**; optionally insert images or add more image pickers with **Blanks for 1 more image**.
3. Set the composition dimensions, ratio and colour, then the block and answer backgrounds.
4. For each block, use its Moodle rich text editor for text, images, and optional Cloze expressions.
5. Click **Validate and update preview** to validate the content and render the corresponding Moodle answer controls.
6. Position each block by dragging its rectangle in the preview or editing **Left** and **Top**. Drag its right edge to change the width, or enter **Width** with the keyboard (40–4194303 pixels).
7. Select an **Anchor** for the whole block. Its height adapts to content; text and answer fields are never scaled.
8. Optionally enter formatted **Additional text**.
9. Save the question.

The form initially provides three block rows. To add more, select existing block checkboxes and insert 1, 2 or 3 blocks **Above** or **Below** each selected block. The selection is taken once at the click; inserted rows do not become targets in the same operation. With an empty list, **Insert** creates the chosen number without a selection.

Blank rows are ignored. Leaving an intermediate row blank does not renumber later populated rows.

Use the Moodle move handle beside a row number to reorder blocks. Drag it with the mouse,
or focus it and press Enter or Space to choose a destination in Moodle's move dialogue.
The formatted content, coordinates, width and Anchor travel together, and preview numbers follow the new order.
Updating the preview or adding rows retains the order; changes are saved only when the question is saved.

Saving an edited question creates a new question version. Existing quiz attempts retain their original
question version and answers, including attempts still in progress when the edit is saved.
For a quiz configured to use the latest question version, Moodle's **Each attempt builds on the last** setting
determines what happens next:

| Setting | Next attempt |
| --- | --- |
| **No** | Uses the latest question version, without carrying forward the previous answers. |
| **Yes** | Builds on the preceding attempt, retaining that attempt's question version and answers. |

This applies whether the edit was saved before or after the preceding attempt finished. Reordering does not
rewrite earlier attempts or their review pages.

The positioning preview uses the available width of its form section and renders the actual Moodle answer controls, allowing their approximate size and spatial relationship to the image to be seen while positioning.

## Width of individual text and numerical fields

SHORTANSWER, SHORTANSWER_C and NUMERICAL accept optional parameters in their Cloze type header, in **Question text**, displayed blocks and **Additional text**:

| Example | Behaviour |
| --- | --- |
| `{1:SHORTANSWER:=Paris}` | Existing stable answer-based size, with no random increase or adaptation to the student's text. |
| `{1:SHORTANSWER(150px):=Paris}` | Fixed **outer** field width of 150 CSS pixels, including padding and borders. |
| `{1:SHORTANSWER(150px,300px):=Paris}` | Starts at 150 px, grows with the text entered, and shrinks on deletion between 150 and 300 px. |
| `{1:SHORTANSWER(10em):=Paris}` | Fixed outer width of ten times the field’s actual font size. |
| `{1:SHORTANSWER(9.5rem):=Paris}` | Fixed outer width of 9.5 times the page root font size, independent of the field’s font size. |
| `{1:NUMERICAL(8em,16em):=42:0}` | Adapts between 8 and 16 times the numerical field’s font size. |
| `{1:SHORTANSWER_C(8rem,16rem):=Paris}` | Adapts between 8 and 16 times the page root font size. |

One dimension sets a fixed width; two dimensions set an adaptive minimum and maximum. The unpublished three-part syntax with an asterisk is not accepted.

The same syntax works with SHORTANSWER_C and NUMERICAL, and their standard aliases SA/MW, SAC/MWC and NM. Use whole pixel values from **40 to 2000 px**, or values from **2.5 to 125 em/rem**, with decimal fractions written using a dot (for example `9.5rem`). Units must be lowercase, with no spaces inside the parameter. Both adaptive bounds must have the same unit and the maximum must be at least the minimum. Invalid syntax, units, ranges and reversed bounds produce translated validation errors. Relative limits are in the requested unit, not pixels: at a 16 px reference size they correspond to 40–2000 px; at 40 px they correspond to 100–5000 px. There is no silent cap on the resolved pixel size. Explicit dimensions never use the expected answers to size the field, and never impose a character limit. At the maximum width the text scrolls inside the native input. Adaptive sizing also applies to restored responses and readonly correction/review, using the student's response only; without JavaScript fixed widths and adaptive minimum widths are respected through native CSS in the requested unit, with internal scrolling.

These dimensions apply to the **answer field**, not its block. A block's minimum follows its widest rendered control plus its normal padding and borders. Automatic blocks follow their single control; ordinary blocks retain the teacher's requested manual width and only grow beyond it when necessary. Height/wrapping and the selected outer anchor follow the existing layout rules. An ordinary block can shrink back from an adaptive enlargement to its requested width, but never below that width merely because text was deleted.

Only active Cloze headers are interpreted. Similar text in answers, feedback or HTML comments is left untouched. The original source, including units and decimal spelling, is retained through editing, reorder, copy, XML and Moodle backup/restore. The grading engine receives ordinary Cloze expressions, without width parameters. This is a Cloze on Image extension, not syntax accepted by Moodle's standard Embedded answers question type. Previously literal complete expressions using this newly reserved header syntax become active when parsed by this version.

The editing preview shows empty controls at their fixed or initial width; it does not simulate typing into disabled preview fields. Use an actual attempt to try adaptive typing. This does not introduce automatic content validation on editor blur.

SHORTANSWER, SHORTANSWER_C and NUMERICAL fields inherit the surrounding content's font family, size, weight and style. An explicitly authored text colour also applies to the field; unformatted fields retain their existing appearance. For example, `<span style="font-size:40px;color:orange">{1:SHORTANSWER:=Hello#Hello}</span>` produces an orange 40 px response. This is a Cloze on Image enhancement, independent of standard Moodle Cloze. It applies in the positioning preview, attempts and review. Single-expression automatic blocks retain these formatting wrappers without restoring their paragraph margins or padding. Result outlines and feedback popovers keep their own styles, and Moodle's HTML cleaning remains in effect.

Calculated widths follow the rendered font. All explicit dimensions include padding and borders. Explicit `px` widths remain unchanged: 150 px is still 150 px with a 40 px font. An `em` follows the field’s computed font size; a `rem` follows the page root font size. Each view uses its own actual styles. Adaptive bounds are resolved in the browser and updated when their reference font size changes; saved source is never converted to pixels. Adaptive fields measure the student's text using the field's computed font, including its weight, and remeasure after webfont loading; their height follows the font's line height. At the maximum, long text scrolls horizontally inside the input. Block minimum widths and anchors follow the resulting field dimensions without reducing a remembered manual block width.

The full-height blue resize bars keep their existing hit areas and `ew-resize` cursors. A 16 px grey horizontal double arrow marks the midpoint and disappears on hover and throughout resizing. Block resizing keeps the `ew-resize` cursor until release or cancellation, even away from the bar; `grabbing` is reserved for moving blocks. The standard Moodle help icon beside **Composition** describes block movement, block/image resizing and the coordinate fields. Automatic single-expression blocks continue to hide their block resize bar.

## Composition geometry and images

The internal coordinate system is independent of images. Storage contains **x, y, width, height** for the base rectangle, not just dimensions. Negative base origins allow enlargement to the left or above without rewriting any element coordinate. Width and Height describe that complete stored rectangle. The default is 4:3, locked, white; a new visible editor measures its available width once. Later window or drawer resizing does not change authored dimensions.

In editing, moving/resizing content beyond the base grows it and updates Width/Height. Moving content back does not shrink it. Enter smaller dimensions or drag the lower-right composition handle to reduce it explicitly; the result still contains every permanent element. When locked, the union expands to the chosen ratio, placing any surplus on the right or bottom. Neither images nor blocks are scaled by composition resizing.

In attempts, the saved rectangle is immutable. Adaptive responses and permanent overflowing text/images can temporarily extend its union in any direction, then shrink back to the base. The complete displayed union keeps a constant top-left margin in its scroll track, in the ordinary view. Screen scrolling remains free. A separate minimal scroll adjustment exposes the active field/caret during typing; it never changes authored coordinates. Popovers, numerical messages and focus/result outlines are excluded from the union.

The **Composition** section starts with the positioning instruction, preview and **Validate and update preview**, followed by **Dimensions** (aspect ratio and lock, then Width/Height), **Background color** and **Show composition**. The **Images** section contains independent file pickers. **Blocks** puts each rich editor before its position/width/anchor row and its association/layer row; a single Moodle help button explains these properties.

All authoring pixel fields display whole numbers with `(px)` in their labels and increments of 1. Calculated values round to the nearest integer for display only; exact half values round away from zero (−123.5 displays −124). Unchanged rounded fields retain their precise internal geometry through saving and form reconstruction. Deliberately editing a field applies the entered integer; manual decimals are rejected. The compact field width is not a numeric limit of 9999. This presentation rule does not alter decimal `em`/`rem` values in Cloze source.

Layers are integers from **−2147483648 to 2147483647**, checked in the form, saving, XML import and backup restoration. Higher values appear in front; negative layers remain above the composition background. Equal-layer elements retain deterministic document order (images before blocks, stable image record order and logical block order). Layer values never change answer order or numbering. Image deletion is deferred until saving and detaches its blocks without moving them; cancelling preserves the saved image. With images checked for deletion, **Save changes** and **Save changes and continue editing** ask for confirmation listing the current image labels. Cancel keeps every unsaved form value; Continue performs the originally chosen save action. Preview updates and other form commands do not ask for image-deletion confirmation.

Each image has a stable question-scoped key, Left (px), Top (px), Width (px) and Layer. The form identifies images as **Image 1**, **Image 2**, etc.; these display numbers never replace the stable keys used for block associations. Its height always follows the source proportions. At insertion it fits inside the composition without exceeding intrinsic dimensions. Later resizing may enlarge it explicitly. The geometry limit is 4194303 CSS pixels per dimension/coordinate; this is independent of answer-field width limits.

Each numbered image picker reuses Moodle `core/dropzone` and the standard repository picker. Local selection, dropping, **Browse repositories**, current-file links, same-name replacement, standard type/size checks and the fallback remain available independently for each image. Only one file belongs to each picker; **Blanks for 1 more image** creates another. Uploading affects the user's draft only. Removing an image detaches its associated blocks at their current visible positions. Cancel leaves the saved version and its files intact.

**Position relative to** selects Composition or an image by stable identity. Composition blocks do not follow images. Associated blocks store their anchor as fractional image coordinates, including values outside 0..1; moving/resizing that image moves the anchor without scaling the block or its text/controls. Changing association preserves the visible rectangle. Left/Top represent absolute internal block coordinates, including fractional or negative values; their visible fields use the whole-pixel presentation described above.

Mixed Ctrl/Cmd selection moves images and blocks together. A selected block attached to a selected image follows that image once. Moving an attached block alone updates its fractional anchor. Resize grips affect their own element; resizing an image also repositions its attached blocks. Layer numbers control painting (larger means in front); new images start behind blocks. Layers never change response numbering, keyboard traversal or grading.

### Anchors

Each block has one of nine Anchor positions:

- Top left
- Top centre
- Top right
- Middle left
- Centre
- Middle right
- Bottom left
- Bottom centre
- Bottom right

The Anchor identifies a point on the block's outer rectangle, including padding. It stays fixed as the block changes size, and follows an image only when the block is explicitly associated with that image.

Changing the Anchor by itself does **not** move the control. It affects position recalculation when the image width changes. When the block width changes, this anchor stays fixed, including when wrapping changes the block height.

## Backgrounds

Two independent settings change only background paint:

- **Block background**: Transparent (default), Translucent or Opaque, for whole positioned blocks, including text-only and image-only blocks.
- **Answer field background**: Transparent, Translucent (default) or Opaque, for answer fields in Question text, blocks and Additional text.

Translucent uses white at 78% alpha; Opaque uses solid white. The block and answer field backgrounds overlap naturally, without automatic compensation. Text, images, response values and coloured result outlines stay at full opacity. Correct, incorrect and partially correct responses retain their result outlines; the field background follows the selected white/transparent mode. Feedback popovers use solid white for readability, independently of these settings.

Both settings apply in the teacher positioning preview, student attempts and reviews. Changing either menu updates the positioning preview immediately; save the question to persist the change. Neither setting changes positions, dimensions or grading. Both are preserved in copies, Moodle XML and backup/restore. There is no special rendering path to preserve previous background palettes.

## Block content

Each populated **Content** editor may contain formatted text, images and zero or more Moodle Cloze expressions. Text before, between and after expressions can be formatted with Moodle's standard editor. A block containing only one expression automatically fits its control or complete choice group. Insignificant whitespace and editor wrappers do not prevent detection. This mode removes outer paragraph/control margins and block padding, while retaining the controls’ own padding and choice spacing. The block background remains an independent layer. **Width** shows its remembered manual value in an inactive grey field without an extra label, and the resize grip is hidden. Adding text or an image re-enables manual width immediately; **Validate and update preview** renders the edited content. Removing that content restores automatic sizing. Saving, reopening and reordering preserve the remembered manual width. Expressions inside a block are ordered by their position in the text. Empty rows are ignored. A text-only or image-only block is retained without creating a response or contributing to the mark. Images inserted by URL remain supported. **Browse repositories** in TinyMCE's image dialogue also lets authors select or upload images using Moodle's standard repositories. Each block keeps its own files through saving, reopening, reordering, copying, XML export/import and Moodle backup/restore. Images inserted by URL retain their URLs; only Moodle-managed files are packaged in exports and backups.

Numerical validation messages remain visible in front of neighbouring blocks in the ordinary view. Only the messages are raised; block stacking and geometry stay unchanged. Messages do not intercept mouse clicks on neighbouring controls, and each invalid field keeps its accessible error description when feedback popovers open or close.

The positioning preview applies the configured Moodle content filters, including mathematics, while retaining access to draft images and HTML cleaning. Block geometry follows asynchronous image and formula rendering; an anchor established by a position or width edit is retained as rendering finishes.

Expressions inside HTML comments (`<!-- … -->`) are inactive in all three content zones: they create no response, score or child number. Comments remain in saved editor content, including comment-only blocks, so removing the comment markers reactivates the expressions. At least one active Cloze expression must remain in the content displayed to the student.

Select individual block checkboxes, or use the header checkbox to select all, then choose **Delete selected blocks**. Moodle's confirmation shows the count. Confirming rebuilds the unsaved form without the selected rows and preview rectangles; saving the question persists the deletion. Cancelling the form preserves the saved question. Remaining blocks keep their files, coordinates, widths, anchors and relative order.

Select a quantity (1, 2 or 3), **Above** or **Below**, then **Insert**. The quantity applies to EACH selected block: two selected non-adjacent blocks and a quantity of three create six new blocks, immediately before or after their respective targets. Targets are snapshotted at the click; newly inserted blocks never become targets in that operation. Existing contents, files, geometry and editor identities are retained. Selection clears afterward and the first new editor receives focus. Each new editor has its own Moodle draft area. Insertion rebuilds the unsaved form; only Save persists it. With no blocks, Insert creates the first chosen number without a selection. With existing blocks and no selection, Insert and Delete remain disabled.

**Native TinyMCE autosave is enabled for block editors.** Where supported, `moveBefore` reorders the live editors without losing their iframe state or Undo history. Deletion and fallback reordering use Moodle's standard no-submit form rebuild, as insertion already does. Editors remain alive until navigation, so pending native callbacks do not access destroyed editors. Rebuilds preserve submitted text, file drafts and geometry, but require a server round trip and reset Undo history. Focus returns to the first inserted editor, the moved block's handle, or the surviving selection checkbox (Insert when none remain).

Editor IDs follow block identity rather than rank throughout an editing session and its rebuilds. Initial rows have repeatable identities; added rows use a per-form scope and an origin that is never reused within that form. A fresh editing session cannot assign a previous new block's draft to a newly inserted block. These are draft form fields, not a separate recovery system.

**Moodle's native recovery limits still apply.** In the tested native Moodle implementation, Tiny restores autosaved text only into editors that are initially empty. Text already submitted by a form rebuild is retained, but subsequent unsaved changes to a now-populated editor are not restored on reload by the native module. Recovery also depends on the usual Moodle draft lifetime and submit/cancel behaviour. The plugin does not recover unsaved form order, deletions, coordinates or inserted rows after reopening a fresh form. Reloading a saved question or an earlier POST can legitimately restore a locally deleted row. Save the question to preserve the complete form. See [integration validation](VALIDATION.md).

Ordinary blocks have **12 px padding on every side**, an 8 px radius and a 1 px neutral border (transparent in transparent mode). Width includes padding and border. Automatic single-expression blocks retain zero block padding; result outlines do not change this geometry.

All Cloze response controls keep their natural dimensions independently of block Width, including shuffled choices. Adjacent expressions in one paragraph sit side by side when space permits and wrap between complete controls when it does not. Separate paragraphs and explicit line breaks remain separate. Vertical choice groups remain vertical. Ordinary blocks cannot be made narrower than their widest control or complete choice group plus padding and borders. The padding reserves room for result outlines before and after grading. The Width field and resize grip apply the same limit; explicit paragraphs remain separate. These rules apply equally in the positioning preview, attempts, review. The minimum is recalculated when rendered controls change size, including deferred mathematical and font rendering. Increasing an insufficient width preserves the selected outer anchor; saving/reopening retains the adjusted geometry. Deliberate CSS offsets of text, equations or images may still extend outside a block and do not determine this minimum.


The positioning preview retains validated `position: static/relative/absolute` and numeric `top/right/bottom/left` offsets through Moodle HTML cleaning. This supports text, mathematics and images displaced within or beyond a block, while keeping the whole block draggable. Fixed positioning, arbitrary CSS expressions and scripts are not enabled by this preview support. Preview paragraph margins match Moodle question rendering.

Use **Validate and update preview** after editing content. Leaving an editor does not automatically validate or refresh the preview. Coordinates, width, anchor and order remain local to the form until the normal question save. Preview rectangles, block numbers and resize grips are editing aids and are absent from student attempts and reviews.

An invalid Cloze expression does not clear the block's saved or selected anchor. Correct the source and validate again; the anchor remains selected through saving and reopening.

During an attempt, numerical validation messages align with the left edge of their own field and show a small decorative upward arrow. They stay on one line and can overflow the block without enlarging it or moving its other fields or text. This also applies when text precedes the field or a block contains several fields. Messages remain above neighbouring blocks without intercepting clicks, in the ordinary view. Screen readers receive the existing translated message without the arrow. Without JavaScript, each message stays below its control and the arrow stays hidden.

For example:

```text
F = {1:NUMERICAL:=100:0} kN
City: {1:MULTICHOICE:=Paris~Rome}; animal: {1:SHORTANSWER:=Owl~Dog}
```

Detailed answer syntax, including partial-credit percentages, feedback, wildcards, and numerical tolerances, follows the standard Moodle Cloze syntax.

## Supported Cloze subquestion types

Cloze on Image uses Moodle's core Cloze parser and supports the following core Cloze families and aliases:

| Type | Canonical syntax | Aliases |
| --- | --- | --- |
| Short Answer, case-insensitive | `SHORTANSWER` | `SA`, `MW` |
| Short Answer, case-sensitive | `SHORTANSWER_C` | `SAC`, `MWC` |
| Numerical | `NUMERICAL` | `NM` |
| Dropdown Multichoice | `MULTICHOICE` | `MC` |
| Shuffled dropdown Multichoice | `MULTICHOICE_S` | `MCS` |
| Vertical radio Multichoice | `MULTICHOICE_V` | `MCV` |
| Shuffled vertical radio Multichoice | `MULTICHOICE_VS` | `MCVS` |
| Horizontal radio Multichoice | `MULTICHOICE_H` | `MCH` |
| Shuffled horizontal radio Multichoice | `MULTICHOICE_HS` | `MCHS` |
| Vertical Multiple Response | `MULTIRESPONSE` | `MR` |
| Shuffled vertical Multiple Response | `MULTIRESPONSE_S` | `MRS` |
| Horizontal Multiple Response | `MULTIRESPONSE_H` | `MRH` |
| Shuffled horizontal Multiple Response | `MULTIRESPONSE_HS` | `MRHS` |

## Attempts, feedback, and review

Students interact with standard Moodle controls generated by the Cloze subquestions.

Moodle's normal grading, validation, correctness, feedback, and review behaviour is retained.

The local **Backspace icon button** clears only its associated radio-choice response. Its tooltip is **Clear selection**. The decorative SVG has a left-pointing, tag-shaped outline containing an X; it is not mirrored in right-to-left languages and is excluded from the accessible name. Its accessible name combines that translated action with the exact name Moodle announces for the choice group, rather than a technical subquestion number. The button remains 24 × 24 px, with unchanged keyboard activation and return of focus to the local choices.

SHORTANSWER, SHORTANSWER_C and NUMERICAL use the stable calculated width when no width parameter is supplied. One explicit dimension gives a fixed outer field width; two dimensions enable adaptation to the response between those bounds. Block Width controls text wrapping, not field size. A block’s effective minimum accommodates its widest control plus padding and borders, preserving its anchor; an adaptive field can enlarge the block when needed without reducing its remembered manual width.

In normal student view, the displayed composition includes its saved base rectangle and the complete extent of permanent images and block content, including negative coordinates and deliberately offset content. The full extent reserves space and remains reachable using native scrolling. Saved coordinates, anchors and the teacher positioning preview are unchanged.

In the ordinary view, the top-left of the complete persistent composition stays fixed relative to its scroll track, at a constant leading margin. Every adaptive width/height change recomputes the bounds and the image offset, both when growing and when shrinking. No space is retained from a formerly larger answer. In normal view the initial margin is retained (8 px on initially negative axes, zero otherwise). Coordinates and relative positions within the composition do not change.

Active-answer visibility is separate: minimal native scrolling exposes a fitting block, or prioritises the field and insertion point when the block is oversized. It never changes the composition origin. Voluntary scrolling remains free while not typing. Focus, caret and selection are retained. Images and fonts loaded later update the measured extent. Popovers, numerical validation messages and focus/result outlines do not determine the composition origin.

## Text before and after the image

The standard Moodle **Question text** is displayed before the image.

Optional formatted **Additional text** is displayed after the composition, or directly after Question text when the composition is disabled. Both text zones support Cloze expressions with the same response controls, feedback popovers and result outlines as the blocks.

## Backup, restore, copying, and Moodle XML

Cloze on Image supports normal Moodle portability mechanisms.

Backup and restore preserve the complete question, including composition geometry/colour, all image files and stable keys, block associations and layers, visibility, rich content, field-width units and anchors. Restored/copied database IDs are remapped; local stable keys remain scoped to the new question version. Inactive composition contents are included.

Moodle XML import and export preserve the portable question data and associated files.

Normal Moodle question copying and versioning are also supported.

## Accessibility

Cloze on Image includes several accessibility measures:

- **Left** and **Top** provide a keyboard alternative to pointer dragging.
- The **Left**, **Top**, **Width**, **Anchor**, and **Content** fields have row-specific accessible names.
- The nine Anchor positions have meaningful names rather than relying only on their visual 3 × 3 arrangement.
- Validation diagnostics preserve row identification for screen readers.
- Student answer fields remain native Moodle form controls.
- View transitions and Escape handling preserve or restore an appropriate focus target.

Each image uses its author-supplied name as alternative text. Choose a meaningful name; this alone may not describe the relationships in a complex diagram. Because Cloze on Image questions can inherently depend on spatial relationships, authors should include essential equivalent information in **Question text** whenever possible. Where interpreting those spatial relationships is itself the learning outcome and cannot reasonably be represented equivalently, an alternative assessment path may be appropriate.

## Limitations

Current limitations include:

- Legacy format-1 questions from earlier releases and development builds are unsupported and are not migrated. Preserve the existing environment and recreate questions separately before transitioning.
- No drawing tools for arrows, lines, boxes, hotspots, or other vector graphics. These must be prepared as part of the background image before upload.
- Per-field width parameters are specific to Cloze on Image; they are not recognised by standard Moodle Cloze.
- The layout uses pixel-based positioning and does not continuously rescale a saved composition to the browser window. Native scrolling provides horizontal navigation; authors should still check their questions on narrow screens and with their site's theme.
- Controls can intentionally extend outside the image and may overlap one another or surrounding content if positioned poorly.
- Image numbers do not replace an equivalent textual explanation of essential spatial relationships.
- Creating questions requires familiarity with Moodle Cloze syntax.

## Documentation and support

Documentation, examples, and additional guidance are available in the [Cloze on Image course on DynamicCourseware.org](https://dynamiccourseware.org/course/view.php?id=191).

Source code and the issue tracker are hosted in the [GitHub repository](https://github.com/DynamicCourseware/moodle-qtype_clozeonimage).

Bugs and feature requests can be reported through the [GitHub issue tracker](https://github.com/DynamicCourseware/moodle-qtype_clozeonimage/issues).

## License

This plugin is distributed under the **GNU General Public License v3 or later**.

See [COPYING.txt](COPYING.txt) for the full license. Files adapted from Moodle core retain their original copyright notices alongside the notices for Cloze on Image modifications.

Copyright © 2026 DynamicCourseware.org.

## Maintainer

**Dominique Bauer**<br>
DynamicCourseware.org

### Coordinate editing and technical limits

The editor crops its display to the complete current composition with a 20 px exterior gutter, without scaling or changing stored coordinates. During a drag the display translation is held steady; after release the frame is updated without changing element positions. Native scrolling remains free.

Both composition corners have resize handles, accessible by Tab and arrow keys (one pixel per key). The opposite corner stays fixed; the aspect lock and permanent content prevent cropping. Reset coordinates uses a confirmation, translates the exact composition origin to (0, 0), and moves images and independent blocks once. Associated block coordinates relative to their images are unchanged. Neither action saves the question or recreates its editors.

Pixel positions are limited to −4194303…4194303; dimensions to 4194303 px (positive, with the existing minimum of 40 px for ordinary block width and zero only for a not-yet-initialised image). These are technical rendering bounds: coordinate and dimension combinations remain below browser layout extents, while decimal internal geometry fits the database precision. Layers use signed 32-bit storage bounds. Their rendering uses compact positive ranks, with a separate rank-zero background inside an isolated stacking context. No automatic saturation changes submitted values. Compact fields show −99999 (px) or −9999 (Layer); longer values remain editable. Explicit Cloze field widths retain their separate 40–2000 px / 2.5–125 em/rem rules.

The ordinary student view is now the only view. No view preference is read or written; an old stored preference has no effect. All edges remain reachable by native scrolling. Shared geometry, active-field/caret visibility and the first-click drawer safeguard are retained.

Empty geometry fields may remain blank while typing. On leaving the field, pressing Enter, updating the preview or saving, an empty field restores the current precise model value in whole-pixel presentation without changing that model. Empty Layer fields restore their last valid value; server validation also checks these submitted fallback values. Composition corner handles use `nwse-resize` from hover through release or cancellation.

Rows are labelled **Block 1**, **Block 2**, etc., independently of Cloze response numbering. Existing composition number markers have a small rounded rectangle with the block background mode; their absolute positioning does not change block geometry. The end of **Blocks** contains quantity / Above–Below / Insert, then the existing Answer field background and Block background controls, then Reset coordinates and Delete selected blocks together. Both maintenance buttons use the Moodle secondary style and wrap on narrow screens. Confirmations and TinyMCE recovery behaviour are unchanged. The multiple-choice clear button uses the decorative Backspace SVG, with a translated “Clear selection” name and tooltip and the same appearance in left-to-right and right-to-left languages.


### Optional sticky blocks

Each ordinary block has **Sticky: Left / Right / Top / Bottom**, four independent checkboxes, all off by default.
Left and Right can be combined: geometry clamps the block against either enabled edge and returns
it to its normal position whenever the reference rectangle fits. This applies in question previews
and student attempts; the positioning editor retains reference positions for dragging and resizing.
Stored coordinates, anchors, image associations, composition bounds and teacher layers do not change.

Sticky uses the intersection of the composition and the visible area after Moodle navigation,
drawers and scrolling ancestors. Top follows normal scrolling before sticking below the header.
Bottom is its independent opposite edge, like Right horizontally; Top + Bottom can clamp against either edge.
A tall block becomes a vertical scroll area. With a horizontal option enabled, an oversized block
uses a narrower viewport over its unchanged content width, without rewrapping or scaling.
The viewport reserves the actual native scrollbar thickness where space permits; one scrollbar
therefore does not manufacture overflow on the other axis. Real overflow, including adaptive
answer growth beyond the available visible area, remains scrollable on either or both axes.
Adaptive controls still expand their block naturally when space permits, without an unnecessary horizontal bar. Reference dimensions never include
this temporary scrollbar chrome.

The block stays visible while both visible dimensions are strictly positive, even below 300 px
wide or 200 px high. It hides only at zero visible width or height, retaining answers and internal
scroll offsets, and reappears as soon as both dimensions are positive. Space becoming available
restores the normal rendered size. Native wheel, keyboard and touch scrolling remain available.

A hidden block is inert. If it held focus, the composition receives focus with an announcement;
the live answer and selection remain intact. Focus returns on reappearance only if the learner
has not moved focus elsewhere. Sticky adds no opaque background or layer override. Teachers
should check overlapping blocks using their chosen layers.

The options survive row operations, validation, copying, Moodle XML and backup/restore.
The 2026101902 upgrade adds default-zero `stickybottom`; the existing Top and horizontal values are preserved.

Horizontal storage is a bit mask: 0 off, 1 Left, 2 Right, 3 Left and Right. Earlier draft values 0/1/2
retain their meaning in stored examples and XML/backup exchanges. Top and Bottom are stored independently.
