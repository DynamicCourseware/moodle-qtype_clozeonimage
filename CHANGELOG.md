# Release notes

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
