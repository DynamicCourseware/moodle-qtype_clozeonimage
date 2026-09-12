# Cloze on Image

**Cloze on Image** (`qtype_clozeonimage`) is a Moodle question type that places standard Moodle Cloze subquestions directly over or around a background image.

It is intended for questions in which the position of an answer field relative to an image is important. The answer controls remain ordinary Moodle Cloze controls, so standard Short Answer, Numerical, Multichoice, Multiple Response, grading, feedback, and review behaviour are retained.

The background image and positioned answer controls together form the question composition. Cloze on Image does not draw arrows, lines, rectangles, labels, or other graphics; any such content must be prepared as part of the background image.

## Features

- One background image per question.
- Standard Moodle Cloze subquestions positioned over or around the image.
- Drag positioning in the editing preview.
- Manual **Left** and **Top** coordinates.
- Positive and negative coordinates are supported.
- Controls may be positioned partly or completely outside the image.
- Nine **Anchor** positions for preserving the intended spatial relationship when the image width is changed.
- **Translucent** or **Opaque** backgrounds for positioned controls and related feedback surfaces.
- Optional formatted **Text after image**.
- Standard Moodle attempt, grading, feedback, and review behaviour.
- Moodle backup and restore support.
- Moodle XML import and export support.
- Moodle question copying and versioning support.

## Requirements

The plugin metadata currently requires Moodle 5.2 or later.

The current version has been tested with Moodle 5.2.2+ (Build: 20260818) and Moodle 5.3 development builds (Build: 20260818).

No external library, service, or third-party Moodle plugin is required.

## Installation

1. Copy the `clozeonimage` directory to:

   `question/type/clozeonimage`

2. Go to **Site administration > Notifications**, or run Moodle's normal command-line upgrade process.
3. Complete the installation.
4. In a question bank, create a new question and select **Cloze on Image**.

## Creating a question

A Cloze on Image form presents the principal fields in this order:

- **Question name** and **Question text**.
- **Image**, **Image width (px)**, and **Control appearance**.
- **Position subquestions**, including the interactive positioning preview.
- A subquestion table with columns for **No.**, **Left**, **Top**, **Anchor**, and **Cloze subquestion source**.
- **Add 3 more subquestions** and optional formatted **Text after image**.

A typical authoring workflow is:

1. Enter the **Question name** and the **Question text** containing the instructions or other information to appear before the image.
2. Upload one background **Image**.
3. Review or change the **Image width (px)** and select the **Control appearance**.
4. Enter one complete Moodle Cloze expression in each populated **Cloze subquestion source** row.
5. Click **Update preview** to render the corresponding Moodle answer controls.
6. Position each control by dragging it in the preview or by editing its **Left** and **Top** coordinates.
7. Select an appropriate **Anchor** for each control.
8. Optionally enter formatted **Text after image**.
9. Save the question.

The form initially provides three subquestion rows. Additional rows can be added three at a time.

Blank rows are ignored. Leaving an intermediate row blank does not renumber later populated rows.

The positioning preview uses the available width of its form section and renders the actual Moodle answer controls, allowing their approximate size and spatial relationship to the image to be seen while positioning.

## Background image and image width

Exactly one background image is used for each question.

The image establishes the coordinate origin for positioning, but it is not a boundary. Answer controls may extend beyond any edge of the image.

The image picker accepts the web-image file types supported by Moodle. The initial automatic fitting described below applies only when an image is newly selected or replaced.

### Raster images

For a newly selected or replacement raster image, Cloze on Image fits the initial displayed width to the available editing area when necessary. A small raster image is not enlarged automatically.

The normal minimum permitted width is 200 pixels and the maximum is the image's intrinsic width. If the raster image is intrinsically narrower than 200 pixels, its intrinsic width is both the effective minimum and maximum. Raster images cannot be enlarged beyond their intrinsic width.

### SVG and SVGZ images

For a newly selected or replacement SVG or SVGZ image, Cloze on Image chooses an initial width that fits the available editing area. The minimum permitted width is 200 pixels, and there is no arbitrary maximum. Teachers may deliberately enter a much larger displayed width when appropriate.

### Saved image width

After a question is saved, its chosen displayed image width is preserved when the question is reopened. Updating the preview with the same image also preserves the submitted width.

The composition does not continuously resize in response to later browser-window, drawer, or zoom changes. Automatic fitting is an initial convenience for newly selected and replacement images, not an ongoing responsive-resizing mechanism.

Changing the image width recalculates control positions according to their selected **Anchors**.

The answer controls themselves are not scaled when the image is resized.

## Positioning and Anchors

**Left** and **Top** specify the position of the upper-left corner of the answer control relative to the upper-left corner of the image.

- `Left = 0`, `Top = 0` corresponds to the upper-left corner of the image.
- Coordinates are integers and may be positive or negative.
- Controls may be positioned partly or completely outside the image.
- Controls may overlap if the author places them at overlapping coordinates.
- The composition extends downward when needed to accommodate controls positioned below the image.

Dragging a control and editing its **Left** and **Top** values modify the same stored position. The numeric fields also provide a keyboard-accessible alternative to dragging.

### Anchors

Each answer control has one of nine Anchor positions:

- Top left
- Top centre
- Top right
- Middle left
- Centre
- Middle right
- Bottom left
- Bottom centre
- Bottom right

The Anchor identifies the point of the answer control that should remain proportionally aligned with the image when the displayed image width is changed.

Changing the Anchor by itself does **not** move the control. It affects the next position recalculation after a change in image width.

## Control appearance

**Control appearance** determines the backgrounds used for positioned answer controls and associated feedback surfaces:

- **Translucent** is the default and allows more of the background image to remain visible.
- **Opaque** uses solid backgrounds for stronger separation from the image.

This setting is reflected in the positioning preview, student attempts, and review. It does not change the answer controls, grading, feedback content, or interaction behaviour.

## Cloze subquestion sources

Each populated **Cloze subquestion source** field must contain exactly one complete Moodle Cloze expression, with no surrounding prose.

For example:

```text
{1:SHORTANSWER:=Paris~Marseille}
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

Short Answer and Numerical controls use their normal calculated Cloze width. Cloze on Image does not provide a separate author-defined width setting for individual answer controls.

## Text before and after the image

The standard Moodle **Question text** is displayed before the image.

Optional formatted **Text after image** is displayed after the image composition.

## Backup, restore, copying, and Moodle XML

Cloze on Image supports normal Moodle portability mechanisms.

Backup and restore preserve the complete question, including the Question text, background image, image width, Control appearance, subquestions, positions, Anchors, and Text after image.

Moodle XML import and export preserve the portable question data and associated files.

Normal Moodle question copying and versioning are also supported.

## Accessibility

Cloze on Image includes several accessibility measures:

- **Left** and **Top** provide a keyboard alternative to pointer dragging.
- The **Left**, **Top**, **Anchor**, and **Cloze subquestion source** fields have row-specific accessible names.
- The nine Anchor positions have meaningful names rather than relying only on their visual 3 × 3 arrangement.
- Validation diagnostics preserve row identification for screen readers.
- Student answer fields remain native Moodle form controls.

The background image has a generic alternative identifying its role as the background image for a Cloze on Image question, but this does not describe the content of an arbitrary diagram. Because Cloze on Image questions can inherently depend on spatial relationships, authors should include essential equivalent information in **Question text** whenever possible. Where interpreting those spatial relationships is itself the learning outcome and cannot reasonably be represented equivalently, an alternative assessment path may be appropriate.

## Limitations

Current limitations include:

- One background image per question.
- No drawing tools for arrows, lines, boxes, labels, hotspots, or other graphics. These must be prepared as part of the background image before upload.
- Each subquestion row accepts one complete Cloze expression only.
- Text immediately before or after an individual positioned Cloze control is not supported.
- No separate author-defined width setting is provided for individual Short Answer or Numerical controls.
- The layout uses pixel-based positioning and does not continuously rescale a saved composition to the browser window. It may not adapt optimally to every narrow screen or heavily customized Moodle theme.
- Controls can intentionally extend outside the image and may overlap one another or surrounding content if positioned poorly.
- The generic image alternative identifies the image's role but does not replace an equivalent textual description of essential visual information.
- Creating questions requires familiarity with Moodle Cloze syntax.

## Documentation and support

Documentation, examples, and additional guidance are available in the [Cloze on Image course on DynamicCourseware.org](https://dynamiccourseware.org/course/view.php?id=191).

Source code is available in the [GitHub repository](https://github.com/DynamicCourseware/moodle-qtype_clozeonimage).

Bugs and feature requests can be reported through the [GitHub issue tracker](https://github.com/DynamicCourseware/moodle-qtype_clozeonimage/issues).

## License

This plugin is distributed under the **GNU General Public License v3 or later**.

Copyright © 2026 DynamicCourseware.org.

## Maintainer

**Dominique Bauer**<br>
DynamicCourseware.org
