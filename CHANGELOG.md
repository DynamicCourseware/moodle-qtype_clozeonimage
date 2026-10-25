## 1.3.0 — Stable

- Prepare the first Moodle Marketplace release: version `2026102400`, maturity `MATURITY_STABLE`, supporting Moodle 5.2 and 5.3. This release preparation changes metadata and documentation only; the last schema step remains `2026101902`.
- Consolidate format-2 compositions with optional images, rich blocks, image associations, independent layers, adaptive controls and independent Sticky Left / Right / Top / Bottom. Sticky viewports retain internal scrolling and hide only at zero visible width or height.
- Include insertion of 1–3 blocks above or below each selected block, automatic width without the redundant Auto label, and the approved Backspace SVG for Clear selection. The 24 × 24 px button retains its behaviour and accessible name.
- Record successful clean installation and targeted authoring, preview, grading, duplication, XML and backup/restore checks with files on Moodle 5.2.4 and 5.3.0. See VALIDATION.md for coverage and limitations; the final release ZIP has not yet been validated.
- Legacy format-1 questions, including those from earlier releases, are unsupported and are not automatically migrated. Preserve existing installations and backups and recreate questions in a separate format-2 installation before transitioning.

Historical development entries below describe their respective snapshots.

## UI refinements — 2026-10-20

- Replace the Clear selection glyph with a thicker decorative SVG arrow, retaining its size, behaviour and translated accessible name/help.
- Remove the redundant Auto width label and its full-row styling; keep the grey read-only width control, help and automatic/manual sizing unchanged.

## 1.3.0-dev.20261019.2 — Bottom and block authoring controls

- Add independent Sticky Bottom, including Top + Bottom, with symmetric geometry and zero-area hiding.
- Preserve adaptive block expansion and real native scrolling without parasitic cross-axis bars.
- Insert 1–3 blocks above or below every selected original block, or into an empty list.
- Group background and maintenance controls at the end of Blocks; use Moodle secondary maintenance buttons.
- Decorate existing block numbers with their block background and use ← for accessible Clear selection.
- Persist Bottom in all row operations, Moodle XML, copying and backup/restore; default to off for existing blocks.

## 1.3.0-dev.20261019.1 — Sticky scrollbar and visibility corrections

- Reserve native scrollbar chrome without changing reference content width or causing unnecessary cross-axis bars.
- Keep both real overflows accessible, including adaptive answer growth and shrinking.
- Replace the 300/200 px hiding thresholds with zero visible width or height, retaining focus, answers and scroll offsets.

## 1.3.0-dev.20261019 — Optional sticky blocks

- Add independent Left, Right and Top settings to ordinary blocks.
- Keep natural geometry and teacher layers; constrain sticky viewports to the visible composition.
- Scroll oversized content internally; hide reduced blocks at 200 px or less vertically / 300 px or less horizontally with explicit focus handling.
- Persist options through editing, XML, copying and backup/restore. Existing blocks default to off.

# Release notes

## 1.3.0-dev.20261018 — development snapshot (not stable)

- Package the autonomous composition working tree for independent testing, with alpha maturity and plugin version `2026101800`. No compatibility migration from earlier releases is provided; use a dedicated test installation.
- Remove obsolete Framed-view assumptions from the retained feedback browser regression and consolidate current validation/authoring guidance. Keep the shared geometry, adaptive sizing, manual scrolling and first-click focus protection. Historical entries below describe earlier packages, not the current feature set.

- Preserve the submitted form object while normalising pixel fields. Moodle receives the newly saved question ID for its subsequent custom-field save, including initial creation and new versions. Add a regression for this editor contract.

### Coordinate interface consolidation

- Confirm pending image deletions using Moodle’s standard dialogue on either save action, retaining the chosen submitter and all unsaved data on cancellation. Align the positioning instruction and Show composition control; clarify Reset coordinates and space the insertion command using the standard secondary button style.

- Reorganise the composition form around its preview; retain numeric field widths, use translated Block labels and standard theme command styles. Restore cleared geometry fields losslessly and empty layers from their last valid value, including server-side submission. Keep diagonal composition resize cursors throughout each gesture.
- Add precise top-left composition resizing and confirmed Reset coordinates; recrop the editor without altering internal coordinates or editors.
- Use compact integer fields with technical pixel bounds of ±4194303 and signed 32-bit layer validation. Map layers to isolated positive rendering ranks above the background.
- Remove Framed view, its controls, gestures and preference storage. Keep shared measurements, fixed-corner placement, active-field scrolling and first-click protection.


### Autonomous composition, format 2

- Make composition visibility independent of images. Centralise active-zone parsing, grading, rendering and file access; retain disabled elements and files.
- Store a base rectangle with explicit origin, ratio lock and CSS colour. Grow it during editing; permit explicit reduction without cropping. Runtime content expansion is temporary and uses the shared fixed-corner geometry.
- Add independently positioned, proportionally resized images, each with a standard Moodle draft/filepicker and stable local identity. Add optional image-relative block anchors and independent layers; retain response ordering.
- Extend preview group selection to images and blocks without double movement. Preserve fractional coordinates, nine anchors and no-jump association changes/deletion.
- Preserve the new properties/files through copy, question versions, XML and Moodle backup/restore. Add schema steps 2026101700/2026101701/2026101702 without migrating or deleting old content. Old development formats require recreated questions.

- Organise the form into Composition dimensions/colour, numbered Images and editor-first Blocks, with grouped Moodle help and compact labelled properties. Image labels remain independent of stable associations.
- Separate integer pixel presentation from precise submitted geometry; reject manual decimals without truncation. Retain fractional block widths in the schema and avoid re-reading rounded CSS positions/proportions during authoring. Preserve source `em`/`rem` decimals and unchanged values through preview, errors, rebuilding and save/reopen.
- Validate image and block layers as signed 32-bit integers, including XML/restore; keep the composition background at rank zero below every element. No existing out-of-range layer was found on the isolated or 8353 sites.

### Earlier post-rc2 changes

- Extend explicit SHORTANSWER, SHORTANSWER_C and NUMERICAL field widths (including core aliases) to `em` and `rem`, with decimal values and same-unit adaptive bounds. Resolve `em` from the actual field font and `rem` from the page root, and remeasure after font/size changes. Preserve existing pixel/default sizing, outer dimensions, anchors and manual block widths. Validate relative values between 2.5 and 125 units without silently capping their resolved pixels; retain source units through editing, copy, XML and backup/restore. Native CSS supplies fixed/minimum relative widths without JavaScript.

- Preserve the first answer click after horizontal page scrolling in Boost: veto its false mobile drawer dismissal during desktop answer focus via Moodle's cancelable drawer event. Keep normal mobile/manual drawer actions, scrolling and feedback behavior unchanged; do not refocus the field or move its DOM. Complete the canceled Moodle pending operation.

- Make negative-coordinate blocks and protruding text/images accessible in normal student view using the complete composition bounds. Preserve authored coordinates and the editor. Keep the complete union’s top-left fixed relative to its scroll track during adaptive growth and shrinking in the ordinary student view, without retaining historical empty space. Keep active answers visible using separate minimal native scrolling; retain manual scrolling, focus and selection. Exclude temporary feedback decorations from origin calculations.

- Rename the background section **Background image** and its picker **Insert image**. Reuse Moodle `core/dropzone` for local image selection and drag-and-drop, with a left-aligned, theme-primary **Browse repositories** button below the dropzone. Remove the redundant legacy drop message/arrows and type-description display only after successful enhancement; show the selected file as **Current background image: filename** using a translatable label and the existing file link, without the legacy trailing separator or an empty label. Retain the unmodified standard fallback. Preserve the standard draft, repository/type/size checks, same-name replacement, preview refresh and save/cancel lifecycle; keep the standard picker as an initialisation fallback. No TinyMCE runtime dependency or separate URL-import mechanism. Existing rc1/rc2 packages are unchanged.

## 1.2.0-rc2 — candidate prepared 2026-10-14 (not published)

- Consolidate the post-rc1 changes below as plugin version `2026101400`, maturity `MATURITY_RC`; no new schema step. The rc1 package remains unchanged.
- Validate the complete final suite on Moodle 5.2.4 and 5.3.0: 945 tests / 21,412 assertions on each, plus JavaScript, AMD/CSS and post-rc1 browser regressions on both versions. Correct stale README width guidance; no runtime regression fix was needed during consolidation. See VALIDATION.md for test-script corrections, reused evidence and limits.

- Keep the **C** button unchanged visually, with a translated **Clear my choice** tooltip. Name it accessibly using that action and the associated radio group's existing announced label; explicitly link it to the group without using a mismatched technical subquestion number. Preserve local clearing and keyboard/focus behaviour.
- Add temporary Ctrl/Cmd-click group selection in the positioning preview, with a non-interactive upper-left check mark. Apply one common movement delta to selected blocks and their Left/Top fields, retaining relative positions, anchors and widths. Keep resizing local to its block and table selection independent; clear preview selection on Escape, empty preview clicks or an ordinary click/drag on an unselected block.
- Let SHORTANSWER, SHORTANSWER_C and NUMERICAL inherit authored font family, size, weight, style and text colour in preview and student rendering, including Framed view. Preserve automatic-block formatting wrappers without restoring their spacing. Keep unformatted inputs, result outlines, feedback popovers and Moodle HTML cleaning unchanged. This is a plugin-specific enhancement, not a Moodle core fix. Explicit pixel widths remain unchanged; adaptive measurements use the rendered font and already refresh after webfont loading.
- Add a 16 px grey double-arrow cue at the centre of block/image resize bars, hidden on hover and throughout block resizing, and standard Moodle positioning help. Keep the resize cursor throughout the gesture, including away from the bar. Preserve the blue bars, full-height hit areas and resize cursors.
- Accept fixed `TYPE(150px)` and adaptive `TYPE(150px,300px)` widths for SHORTANSWER, SHORTANSWER_C, NUMERICAL and their core aliases in all three content zones. Widths are outer CSS-pixel dimensions, validated between 40 and 2000 px; explicit sizing uses no expected-answer length and imposes no character limit.
- Adapt to input/paste/deletion, restored responses and readonly feedback. Reuse block minimum sizing, wrapping and anchors without reducing a teacher's manual block width. Keep the existing stable calculation for expressions without parameters.
- Extract only active expression headers before core Cloze parsing; preserve original source, answers, feedback, comments and portability. No schema change, migration or grading change. The existing candidate ZIP is not replaced.
- Preserve saved and submitted block anchors when Cloze source is invalid, through validation, correction and saving.
- Align each numerical validation message with its field's left edge and add a decorative upward arrow, excluded from accessible descriptions. Keep messages on one line, positioned below their controls outside layout flow: they can overflow without enlarging blocks or moving other content. Preserve translated messages, stacking above neighbouring blocks and pointer access through messages, including Framed view.

## 1.2.0-rc1 — candidate prepared 2026-10-13 (not published)

- Rename the preview button to **Validate and update preview**, retaining its translation key and explicit button-driven behaviour. Update help and authoring documentation.

- Show a pointer cursor on readonly feedback text fields, including SHORTANSWER and NUMERICAL, and on choice feedback surfaces. Preserve editable-field cursors, text selection and existing feedback interactions.

- Keep numerical validation messages above neighbouring blocks, including opaque backgrounds and Framed view, without raising entire blocks or intercepting pointer input. Preserve each field's error description through feedback-popover insertion, dismissal and reset, including simultaneous numerical errors.

- Insert 1, 2 or 3 empty blocks above each selected row using the existing block checkboxes. Preserve editor contents and files, positions, widths, anchors and order; clear selection and focus the first new editor. Rebuild only the draft form until question save.
- Restore native TinyMCE autosave for all block editors. Keep live editors during atomic moves; use standard non-saving form rebuilds for deletion and fallback reordering without individual editor destruction. Stable IDs follow block identity rather than rank, including inserted rows, and focus returns after rebuild. Rebuilds require a server round trip and reset Undo. Retain native recovery limits (initially empty editors, no recovery of form structure); no custom autosave controller or Moodle core changes.

- Keep every Cloze control at its natural size in both the positioning preview and student rendering, including shuffled variants. Use paragraph-compatible choice groups so adjacent responses wrap as complete units while paragraphs and explicit line breaks are preserved. Controls retain their dimensions; ordinary blocks use the widest control to set their minimum width, including padding, borders and room for result outlines. Preserve the outer anchor when an insufficient width is adjusted, including after deferred rendering. Explicit CSS offsets remain independent of this limit.

- Ignore Cloze expressions in HTML comments in every content zone, without losing their saved source or reserving child numbers/marks. Apply the same rule to validation and preview.
- Add accessible block selection and a standard Moodle Delete/Cancel confirmation. Delete locally until question save; preserve surviving block identities, files, order and geometry, including after reordering and adding rows.
- Match internal relative/absolute positioning in the author preview through narrowly validated offsets, retaining Moodle HTML cleaning, content filters and draft-file access controls. Match question paragraph margins.

- Give ordinary blocks 12 px padding on all four sides, 8 px rounded corners and a neutral 1 px border (transparent with transparent backgrounds). Width includes padding and border; anchors use the outer rectangle. Remove control-only bottom margins and baseline gaps in blocks, preserving explicit paragraph separation and zero padding for automatic blocks.
- Automatically fit single-expression blocks to the complete control or choice group, ignoring insignificant editor wrappers/whitespace and removing outer margins and block padding. Keep block/control background layers independent and controls, validation and feedback unclipped. Disable manual Width with an Auto indication, hide its grip, and retain the manual value through edits, reordering and saving.
- Count all stored blocks when reopening the form, including trailing text-only blocks without child responses.
- Add independent **Block background** (Transparent by default) and **Answer field background** (Translucent by default), each with Transparent, Translucent and Opaque modes. Extend the existing control setting rather than duplicating it.
- Use white at 78% alpha or solid white, with natural background layering and no opacity changes to content or result outlines. Apply answer backgrounds in all three zones; keep feedback popovers white and readable. Update the author preview immediately, and preserve both settings in copies, XML and backup/restore without changing geometry or grading.

- Add **Show composition**, enabled by default and preserved in copies, XML and backups. Hiding a composition preserves its image, block content/files, geometry and order while excluding block responses from grading. Keep background removal as a separate action and retain the authoring preview with an inactive-content notice.

- Support Cloze expressions in Question text and Additional text (renamed from Text after image), with the same response renderers, result outlines and feedback as positioned blocks.
- Preserve block coordinates and anchors when a hidden composition is saved or its image is reattached.
- Make the background image optional. Without it, retain blocks and their files in the editor but exclude them from display, response fields and grading; omit the empty composition and Framed view control.
- Accept text-only and image-only blocks, and compositions with zero blocks. Require at least one valid Cloze expression across the displayed content, ordered initial text, displayed blocks, then additional text.
- Preserve text-zone child counts, source content and files through copying, XML and backup/restore without rewriting existing questions or attempts.
- Disable only the feedback popover fade using Bootstrap's standard animation option to avoid stale transition callbacks when immediate feedback replaces an instance.

- Replace source-only rows with standard Moodle rich text editors, with or without Cloze expressions.
- Store formatted content, width and consecutive child counts on each positioned block, while retaining the core Cloze grading engine.
- Move and resize whole blocks in the positioning preview; expose precise Width entry for keyboard users. Height wraps naturally, without scaling answer controls or fonts.
- Reorder blocks with Moodle's standard mouse/keyboard controls and retain editor contents and focus, including browsers that need a non-saving form rebuild instead of an atomic DOM move.
- Preserve formatted content and dimensions in XML and Moodle backup data. Add fields without deleting or rewriting existing questions or attempts; 1.1.0 positions naturally become single-expression blocks.
- Render configured mathematical filters alongside draft images in the editing preview, retaining HTML cleaning and standard file access. Preserve edited block anchors and update composition height after asynchronous rendering.
- Enable TinyMCE **Browse repositories** for block images, with standard Moodle draft files, access checks, copying, XML and backup/restore. Preserve URL images and existing image rendering and positioning.
- Outline the complete image/block composition with a 1 px pale grey border without changing content geometry. Reserve 16 px outside the editor composition in its horizontal scroll area.
- Add a full-height blue grip at the background image's right edge, sharing the width field's limits, aspect ratio and anchor adjustments. Keep multi-step mouse resizing free of cumulative position rounding.
- Editing rectangles, numbers and resize grips are absent from student output. Framed view is unchanged.
- Candidate version: `2026101300`, maturity `MATURITY_RC`; last schema step: `2026101102`. New block data is not compatible with plugin 1.1.0; downgrade is unsupported. No separate legacy appearance is maintained. Saving an old question can activate previously literal Cloze expressions in the initial/additional text and change its new version's maximum mark.
- Validate fresh installations and the complete plugin suite on stable Moodle 5.2.4 and 5.3.0: 935 tests / 20,986 assertions on each. See [VALIDATION.md](VALIDATION.md) for browser checks, reused evidence and unverified combinations.
- Resolve stylesheet lint warnings; serve unchanged radio/checkbox SVG marks through Moodle's standard pix mechanism. Include compiled AMD modules in the candidate package.

## 1.1.0 — 2026-10-09

### Subquestion reordering

- Reorder editable subquestions using Moodle's standard `core/sortable_list` mouse and keyboard interaction.
- Keep each source, Left/Top position and Anchor together; update preview numbering and retain focus after a move.
- Retain the order when refreshing the preview or adding rows. Changes remain local to the form until the question is saved.
- Validate submitted row origins on the server and preserve the original child question identities when creating a new version, including sparse rows and mixed question types.
- Use the translated **Order** column heading and align move handles and one-, two- and three-digit row numbers with clear spacing on wide and narrow screens.

### Compatibility and upgrade

- Tested on official stable Moodle 5.2.4 (`2026042004.00`) and Moodle 5.3 (`2026100500.00`), both Build: 20261005, with PHP 8.3.6 and MariaDB 11.4.12.
- Validated fresh installation and upgrade from `v1.0.0`, preserving question data, images, positions, anchors, answers and existing attempts.
- Verified edits before and after attempt completion with **Each attempt builds on the last** both disabled and enabled, while the quiz follows the latest question version. Existing attempts and reviews are preserved; subsequent attempts follow Moodle's version-selection rules.
- Verified saving/reopening, Moodle XML export/import and backup/restore after mixed-type reordering, plus real authoring forms in Chromium with Boost on both stable Moodle releases.
- Requires Moodle 5.2 and `qtype_multianswer`, minimum version `2026042000`, as in 1.0.0.
- Plugin version: `2026100900`; maturity: `MATURITY_STABLE`. Run Moodle's normal upgrade process; no new database schema step is introduced.
- Includes AMD sources and compiled JavaScript. Framed view is unchanged.

## 1.0.0

First stable release prepared for publication.

### Question authoring and assessment

- Position standard Moodle Cloze answer controls over or around one background image.
- Drag controls in the authoring preview or enter Left and Top coordinates, including negative positions.
- Choose among nine anchors for position recalculation when the displayed image width changes.
- Use translucent or opaque control backgrounds and optional formatted text after the image.
- Retain standard Cloze answer types, grading, feedback, and review behaviour.
- Preserve question content and positioning through backup, restore, copying, versioning, and Moodle XML import/export.

### Framed view

- Navigate the composition horizontally without scaling the image or answer controls.
- Coordinate overflowing Cloze on Image questions on the same page.
- Remember the view choice per question during an active attempt using browser session storage.
- Provide keyboard access, visible frame focus, context-preserving transitions, and Escape handling that dismisses feedback before leaving Framed view.

### Distribution

- Include the GPL v3 license text and retain attribution for code adapted from Moodle core.
- Include source AMD modules and built JavaScript.
- Document Framed view, accessibility measures, requirements, and limitations in the README.

### Compatibility and upgrade

- Requires Moodle 5.2 (`2026042000`) and the core Cloze question type (`qtype_multianswer`, minimum version `2026042000`).
- Tested in development on Moodle 5.2.2+ and a Moodle 5.3 development build. Compatibility with the final Moodle 5.3 release is not yet established.
- Plugin version: `2026092700`; maturity: `MATURITY_STABLE`.
- Complete Moodle's normal upgrade process when updating a development installation. No new database schema step is introduced for 1.0.0.
