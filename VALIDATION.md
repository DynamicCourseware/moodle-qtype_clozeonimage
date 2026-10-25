## 1.3.0 Stable — release validation

Release metadata: `2026102400` / `1.3.0` / `MATURITY_STABLE`. The checks below used the inventoried development candidate `2026101902` / `1.3.0-dev.20261019.2`; the stable-release preparation changes only version metadata and documentation. Runtime behaviour, database definitions, upgrade steps and compiled JavaScript are unchanged. The final 1.3.0 release ZIP has not yet been generated or validated.

### Clean installation and targeted functional results

- Disposable Moodle **5.2.4** (`2026042004.00`, port 8652) and **5.3.0** (`2026100500.00`, port 8653), using PHP 8.3.6, MariaDB 11.4.12 and Chromium/Boost. The existing installations and working source were preserved.
- Moodle's standard CLI plugin installation passed on both clean cores, including dependencies and database schema consistency with `db/install.xml`. The candidate inventory contained 100 files, all 13 AMD source/build modules and eight autoloaded PHP helpers. Required runtime files and the privacy provider were present.
- Two representative questions covered images, embedded block files, numerical/text/radio answers, image-associated geometry, ordinary blocks and sticky settings. Browser creation, save, reopen, edit and preview checks passed on both versions.
- Correct and incorrect answers passed grading checks through Moodle's question APIs, including response processing and finalisation. Standard Moodle copying, XML export/import with embedded files and activity backup/restore preserved question content, settings, geometry, associations and file hashes. Restored questions also passed grading checks.
- Existing targeted composition and block/file exchange PHPUnit tests passed: **8 tests / 697 assertions per Moodle version**, **16 tests / 1,394 assertions total**. These are additional targeted checks, not a rerun of every historical suite below.
- Authenticated Chromium checks opened four restored questions across both sites. All eight image requests returned HTTP 200 with matching SHA-256 hashes; images decoded successfully. Browser checks used normal authentication and remained on the disposable sites.
- No release-blocking functional defect was identified. The tested runtime files remained identical to the inventoried candidate.

### Test interpretation and limits

- Image-associated coordinates can differ by a few millionths of a pixel when reconstructed from stored `DECIMAL(20,8)` relative coordinates. The authoring harness uses a tolerance derived from image dimensions and that precision only for affected coordinates; unrelated values remain exact. This was not loss of question information.
- Backup/restore comparisons normalise CRLF to LF only in restored HTML. Three harness format checks cast `FORMAT_HTML` to integer to match the loaded representation. All other comparisons, including geometry, settings, associations and file hashes, remain exact. These changes corrected test assumptions, not plugin code or question data.
- Grading used Moodle APIs rather than a complete browser-driven student quiz submission in this clean-installation run. Backup/restore covered a quiz activity on the same site, not cross-site or full-course restoration. File setup used Moodle APIs; repository upload dialogues were not independently retested.
- Historical sticky, authoring and student-attempt results below provide additional evidence for unchanged functionality. Other browser/theme combinations, screen readers, PHP/database versions, Moodle patch releases and load/performance scenarios were not independently validated in this run.
- The approved Backspace icon was visually accepted on 8552, 8553 and 8353. Completed targeted Chromium mouse/keyboard, focus and accessible-name checks passed. The final dedicated vertical-layout Chromium check on 8353 remained incomplete because administrator credentials were unavailable; visual approval and the completed checks are not a claim that this scenario ran.
- Legacy format-1 questions are outside the supported scope. No migration or production-upgrade compatibility is claimed for their rendering, editing, grading or attempt review. Schema retention of old data does not establish functional compatibility; legacy XML is not an import-based conversion path. Administrators should preserve their existing environment and backups and recreate questions separately before transitioning.

Local evidence: `/home/dbauer/clozeonimage-local/clean-install-tests/20261024-212300/evidence/`, particularly `plugin-install-20261025-013246/results.json`, the targeted PHPUnit logs under `functional/`, `functional/exchanges-52.json`, `functional/exchanges-53.json` and `functional/restored-files-browser.json`. These local records are evidence references, not required runtime files or publicly hosted reports.

The sections below are historical validation records. Their snapshot names, packaging statements and earlier UI descriptions apply to those earlier artifacts, not to the final 1.3.0 ZIP.

## Clear arrow and automatic-width label — 2026-10-20

- UI-only change: decorative 16px SVG with a 2.5px stroke inside the unchanged 24px Clear selection button; remove the Auto note and its full-row styling. No version/schema, sizing or interaction-handler change.
- Moodle 5.2.4 (8552) and 5.3 (8553): targeted renderer, block, diagnostic and pixel-field PHPUnit tests pass: **821 tests / 18,817 assertions each**.
- **166 JavaScript tests** pass (appearance, interactive, pixel fields and preview cursors). Grunt AMD/ESLint/CSS, changed PHP syntax and diff whitespace checks pass. The rebuilt form AMD source map matches its source.
- Chromium/Boost on both copies: mouse and Enter/Space clearing, focus return, translated accessible name/title, decorative non-focusable SVG, unchanged button size, left-pointing shape in RTL; captures inspected.
- Authoring: automatic/manual transitions, retained manual value in form submission, grey/read-only automatic width, hidden resize grip and fitted preview, editable manual width, preview reconstruction, all four coordinate controls on one wide row and natural narrow wrapping. Draft edits cancelled; existing questions not saved.
- The same browser checks pass after deployment to 8353. Its backup was verified by an independent SQL restore; original records in 19 business tables, original files and the delivered ZIP were checked before and after the browser run. Only its caches were purged; no schema update was performed.
- Permanent evidence and repeatable scoped browser checks: `/home/dbauer/clozeonimage-local/changes/ui-arrow-auto-20261020-000809/`. Other browsers and assistive technologies were not independently exercised for this refinement.

## Bottom and block controls — 2026-10-19

Permanent environment: `/home/dbauer/clozeonimage-local/`. Version `2026101902` / `1.3.0-dev.20261019.2`.

- Isolated 8552 (Moodle 5.2.4) and 8553 (Moodle 5.3.0): full plugin PHPUnit suites pass, 982 tests / 25,564 assertions each.
- JavaScript source/build regressions pass, including 18 sticky tests and geometric symmetry across all 16 checkbox combinations. AMD, ESLint, CSS and changed PHP syntax checks pass.
- Browser: all 16 combinations; small and oversized blocks; adaptive growth/shrink without an unnecessary horizontal bar; real native 15px scrollbars; zero-area transitions; retained responses, selection, focus and internal scroll offsets; keyboard access; clear-selection arrow.
- Browser authoring: non-adjacent Above/Below insertion, keyboard reorder, server validation rebuild, all existing row values and TinyMCE identities, background modes, narrow/wide controls, empty-list insertion, save/reopen. New inserted blocks receive unique keys and explicit neutral associations/layers.
- Actual student attempts in the new Bottom quizzes pass with adaptive text, dropdowns, radios, clear selection and correct grading.
- 8353 deployment: verified SQL restoration in an independent database before upgrade; original rows in 19 business tables and every original file checked. Runtime and student examples passed after deployment.
- 8353 lacked its entire `vendor/` directory before this task. The missing dependencies were copied from validated 8553 with identical `composer.lock`, and 8,727 files verified. Existing Moodle source files and lock files were not changed.
- Existing number badges inspected visually; backgrounds follow their blocks and remain outside reference geometry. Maintenance buttons have identical Moodle secondary styling.
- Fractional page scrolling is rounded upward only in the zero-area test so that an actual remaining 0.48px is correctly treated as positive.
- New fixture categories use Moodle's selectable default child category. Existing questions and examples are not repurposed.

Evidence and deployment records: `changes/bottom-before-20261019-212631/`; reusable browser scripts and fixture generator: `validation-tools/` under the permanent root. Earlier validation records below remain historical.

# Sticky scrollbar correction validation — 1.3.0-dev.20261019.1

This correction supersedes the 300/200 px visibility rules described in the historical
20261019 validation below. Sticky remains active down to positive visible dimensions;
it hides only at zero visible width or height.

- Isolated 8552 / 8553: Chromium with real space-consuming native scrollbars (15 px measured),
  vertical-only / horizontal-only / actual two-axis overflow, adaptive growth and clearing,
  no residual scrollbar space, positive dimensions below the old cutoffs down to 1 px,
  zero and reappearance, preserved scroll offsets, reference content width and coordinates.
- All eight flag combinations, actual composition-container scrolling, focus/selection,
  hidden-block Tab exclusion, arrow-key scrolling, wheel chaining, small blocks and idle
  measurement stability are covered by the updated browser regressions. Captures inspected.
- 290 JavaScript unit/regression tests pass, including 14 source/compiled sticky geometry
  tests. Grunt AMD/ESLint and CSS checks pass; changed PHP language/version files pass syntax
  checks. The sticky AMD module was rebuilt; no schema or persistence change is required.
- The same targeted browser scenarios pass on 8353 after a verified backup and deployment.
  Original business data and files were compared before and after, including 3,666 questions
  and 2,138 block positions; only the cache generation and test-account login metadata differ.
- Full PHPUnit evidence below remains applicable to unchanged storage and exchanges;
  the full suite was not rerun for this JavaScript/help-only correction.
- `tests/sticky_scrollbars_browser.cjs ROOT PORT` adds the targeted native-scrollbar checks;
  `tests/sticky_browser.cjs ROOT PORT` checks axes, focus and zero-area boundaries.

Limits: tested with Chromium/Boost on the same Moodle 5.2.4 and 5.3.0 copies. At a composition
edge where scrollbar chrome itself cannot fit, real remaining overflow stays scrollable;
no axis is suppressed to conceal a required bar.

---

# Sticky validation — 1.3.0-dev.20261019

This section records the sticky changes made after the preserved 20261018 snapshot below.
The existing ZIP was neither rebuilt nor overwritten.

- Moodle 5.2.4 (8552) and 5.3.0 (8553), restored/recreated in isolated copies; separate
  site databases and dataroots. PHPUnit uses the corrected `sttest_` prefix and its own
  dataroot in each copy, never the site's tables or files.
- Full plugin PHPUnit: **963 tests, 22,654 assertions, zero failures on each version**.
  Includes all combinations of the horizontal bit mask, Top, defaults, validation and
  row reconstruction, copy/version lifecycle, XML and Moodle backup/restore.
- **288 JavaScript tests pass**, including source and compiled sticky geometry, independent
  axes, both horizontal limits, negative origins, edges, inclusive thresholds and small blocks.
- All 40 PHP files pass syntax checks; Grunt AMD/ESLint and CSS checks pass. Changed
  `layout`, `sticky` and `field_width` modules were compiled in an isolated build tree.
- Chromium/Boost previews cover all eight flag combinations, independent/image-associated
  blocks, positive/negative origins, narrow/large windows, returning to reference geometry,
  immutable coordinates/layers/composition bounds, and actual container and page scrolling.
- Dedicated wide/tall and small examples check both internal scrollbars, fixed natural
  content width, both directions across 300 px / 200 px, composition exit, input/selection,
  hidden-control Tab exclusion, focus restoration without stealing moved focus, native
  arrow-key scrolling and wheel chaining at an internal boundary. Idle measurements are
  checked to prevent an adaptive-field/sticky observer loop.
- Editor checks cover the three independent checkboxes in Left / Right / Top order,
  narrow grouped wrapping, server validation, preview rebuilding, keyboard reordering
  with the server fallback, blank insertion, save/reopen and reference-only placement.
- Actual student attempts cover adaptive growth/shrinkage, authored font-size changes,
  dropdowns, radios, both internal scrollbars, Moodle drawers and correct grading.
  Screenshots were visually inspected. New accounts and questions keep previous examples
  and attempts intact.

Reproduction uses `node tests/sticky_browser.cjs ROOT PORT` and
`node tests/sticky_interactions_browser.cjs ROOT PORT`. ROOT is the private isolated
fixture directory containing Playwright, `credentials.json`, `idsPORT.json` and the
local `latest-editor.php` helper; credentials are never included in the plugin.
`COI_STICKY_STUDENT` selects a fresh dedicated learner for a repeated graded attempt.
Run each `tests/*_test.js` directly with Node for the unit regression count. Browser
fixtures and verification logs are retained with the deployment backup.

Deployment to the reconstructed 8353 instance completed after a verified SQL restore and
SHA-256 verification of the original plugin and file archives. The same preview and real
student/editor browser scenarios pass after deployment. Original rows in 19 business tables,
including 3,187 questions and 1,852 block positions, are unchanged; `course.cacherev` is the
sole excluded field because Moodle increments it during the requested cache purge. Existing
positions default to both sticky columns zero. Only 8353 caches were purged for deployment.
Dedicated examples are in course 12 / quiz activity 58 on `http://localhost:8353`.
The recovered working copies, logs, screenshots and recovery assets are retained under
`/home/dbauer/clozeonimage-backups/sticky-8353-20261019-153716`.

Limits: visual validation used Chromium and standard Boost on these two Moodle releases;
other browsers, themes and assistive technologies were not independently tested.
The broader historical development-snapshot compatibility limits below still apply.

---

# Development snapshot validation — 1.3.0-dev.20261018

This alpha snapshot is intended for a dedicated test installation. No effort has been
made to assure backward compatibility with the previous published version or older
working formats. No content migration is provided. Do not upgrade production questions
or downgrade a database containing the new composition format.

## Final tested state

- Moodle 5.2.4, build 20261005, core version 2026042004.00.
- Moodle 5.3.0, build 20261005, core version 2026100500.00.
- PHP 8.3.6, MariaDB 11.4.12; Chromium with the standard Boost theme.
- Full plugin PHPUnit suite: **962 tests, 22,376 assertions, zero failures on each release**.
  Each run used its own isolated database, rc1test_ prefix, dedicated PHPUnit dataroot
  and local bootstrap; invocation included `--test-suffix _test.php`.
- **276 JavaScript regression tests** pass. The 8 real Bootstrap/Boost feedback browser
  tests pass with source and compiled AMD, in wide/narrow ordinary viewports.
- All 40 plugin/test PHP files pass syntax checks. Grunt AMD and CSS checks pass.
  All 12 AMD source files match their compiled source maps.

## Browser coverage on both releases

New disposable questions cover the four active-zone configurations, multiple images,
files displayed in attempts, stable block associations, preview/reopen, both save buttons
and image-deletion confirmation/Cancel/Escape with exactly one submitted save.

Checks cover rich TinyMCE contents and identities, keyboard reordering, its non-atomic
server-rebuild fallback, insertion/deletion/blank rows, local cancellation, mouse resize
and movement, nine anchors, composition corner resizing and Reset coordinates. Fractional
internal coordinates survive integer display, empty-field restoration, save/reopen and
validation; explicit em/rem source spelling is retained.

Student checks cover ordinary-view fixed-corner geometry, adaptive expansion/shrinking,
active caret visibility on narrow screens, free manual scrolling, and a real first mouse
click after horizontal scrolling for NUMERICAL and SHORTANSWER with multiple anchors.
All active answers in the representative configurations are graded correctly; completed
attempts and their reviews are checked. Wide/narrow form captures and browser errors are
checked. These tests do not modify existing examples.

## Targeted cleanup and test corrections

No active Framed-view implementation or development logging remained in runtime sources.
Retain the shared geometry, measurement, scroll, focus and adaptive-field code. Remove
obsolete per-column help strings after moving their width guidance into the actual Blocks
help. Historical release notes retain their historical descriptions; current guidance
and browser test instructions describe the ordinary view only.

The old help test expected removed column headers and separate help buttons. It now
asserts the single grouped help, complete guidance and accessible select-all checkbox.
The feedback test retains all 8 cases; wide/narrow viewports replace the abandoned mode.
Assertions that the old view command is absent remain. No JavaScript case was dropped
in this consolidation: 276 regression cases plus 8 feedback-browser cases.

External scenario corrections account for the new image-deletion confirmation, the actual
Moodle Check button, editor identities, precise-value/display fields, completion of server
rebuild navigation and the fixture's initial row count. Assertions for preserved data,
geometry, focus and grading remain. The initial full PHPUnit run had one obsolete-help
failure on each version; both final complete runs pass. Fresh-install browser creation then exposed a real save
regression: pixel normalisation replaced the submitted object, hiding the newly assigned
question ID from Moodle's subsequent custom-field save. Normalisation now preserves that
object. A regression checks initial creation and a new question version, including the
custom-field call and precise geometry. Full suites and real form creation/attempts on
both versions were rerun after this correction.

## Package and fresh installation

The delivery's MANIFEST.sha256 identifies every packaged byte; SHA256SUMS identifies the
ZIP and accompanying reports. The delivered VALIDATION.md additionally records the actual
installation of this ZIP into an empty, dedicated Moodle instance and the subsequent form
and student test. Local credentials, fixtures and test artefacts are not distributed.

## Limits and reused evidence

The full PHPUnit runs include copy/version lifecycle, grading, active-zone file access,
XML and backup/restore. The unchanged field-parser, native-control, typography and
repository-upload mechanisms retain the detailed development regressions and their
existing automated tests; this is not a repeat of every historical manual matrix.

Other Moodle patch releases, PHP/database versions, themes, browsers and screen readers
were not independently tested. Core repository availability (including URL downloader)
depends on site configuration. Native TinyMCE text recovery does not recover the whole
form's order/deletions; server reconstruction resets Undo. There is no production-upgrade
or old-format compatibility guarantee and no load/performance certification.
