// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Wide-view browser regressions using a local Moodle 5.3 Boost stylesheet and rendered-style fixture.
 * Requires Playwright/Chromium. Run: COI_THEME_CSS=/path/to/boost.css node tests/panorama_browser.js
 * No Moodle accounts, answers, or server state are changed by this test.
 *
 * @copyright  2026 DynamicCourseware.org (Dominique Bauer)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/* eslint-env node */

const assert = require('node:assert/strict');
const {readFileSync} = require('node:fs');
const path = require('node:path');
const {test} = require('node:test');
const {execFileSync} = require('node:child_process');
const {chromium} = require('playwright');

const scrollSelector = '.qtype-clozeonimage-scroll';
const toggleSelector = '[data-action="clozeonimage-toggle-panorama"]';
const activeClass = 'qtype-clozeonimage-panorama-active';
const baseline = '0e125d4678334e3a036525a259e1bdcfabbce8a6';
const baselineCss = execFileSync('git', ['show', `${baseline}:styles.css`], {cwd: path.join(__dirname, '..'), encoding: 'utf8'});
const baselineLayout = execFileSync('git', ['show', `${baseline}:amd/src/layout.js`],
    {cwd: path.join(__dirname, '..'), encoding: 'utf8'});
const imageSelector = '.qtype-clozeonimage-image';
const close = (actual, expected, message) => assert.ok(Math.abs(actual - expected) <= 1,
    `${message}: expected ${expected}, got ${actual}`);
const settle = page => page.waitForTimeout(350);
const snapshot = page => page.evaluate(() => {
    const viewport = document.querySelector('.qtype-clozeonimage-scroll');
    const image = document.querySelector('.qtype-clozeonimage-image').getBoundingClientRect();
    const rect = viewport.getBoundingClientRect();
    const gutter = parseFloat(getComputedStyle(viewport).getPropertyValue('--qtype-clozeonimage-panorama-gutter')) || 0;
    return {
        imageLeft: image.left, imageRight: image.right, imageWidth: image.width,
        left: rect.left, right: rect.right, width: rect.width, scroll: viewport.scrollLeft,
        innerLeft: rect.left + viewport.clientLeft + gutter, innerWidth: viewport.clientWidth - 2 * gutter,
        gutter,
        innerRight: rect.left + viewport.clientLeft + viewport.clientWidth - gutter,
        range: viewport.scrollWidth - viewport.clientWidth,
        overflow: document.scrollingElement.scrollWidth - document.documentElement.clientWidth,
        documentX: window.scrollX,
        relative: [...document.querySelectorAll('.qtype-clozeonimage-subquestion')].map(control => {
            const box = control.getBoundingClientRect();
            return [box.left - image.left, box.top - image.top];
        }),
        values: [...document.querySelectorAll('input, select, textarea')].map(control => [control.value, control.checked]),
    };
});
const scrollTo = async(page, value) => {
    await page.locator(scrollSelector).first().evaluate((element, x) => {
        element.scrollLeft = x;
    }, value);
    await settle(page);
};
const fixture = width => `<!doctype html><html><body class="pagelayout-standard limitedwidth uses-drawers">
    <div class="drawer drawer-left" data-region="fixed-drawer"><div class="drawercontent">Left drawer</div></div>
    <div class="drawer drawer-right" data-region="fixed-drawer"><div class="drawercontent">Right drawer</div></div>
    <div id="page-wrapper"><div id="page" class="drawers"><div class="main-inner"><div role="main">
    <form><div class="que clozeonimage" id="question-coi-1">
    <div class="info"><h3>Question 1</h3><button type="button">Flag</button></div>
    <div class="content"><div class="formulation"><div class="qtext" style="height:160px">Question text</div>
    <div class="qtype-clozeonimage-scroll" id="coi-viewport">
    <div class="qtype-clozeonimage-composition qtype-clozeonimage-appearance-opaque">
    <img class="qtype-clozeonimage-image" style="width:${width}px;max-width:none;height:auto"
        src="${'data:image/svg+xml,' + encodeURIComponent(
            `<svg xmlns="http://www.w3.org/2000/svg" width="${width}" height="340">` +
            '<rect width="100%" height="100%" fill="lightblue"/></svg>')}" >
    <div class="qtype-clozeonimage-subquestion" style="left:40px;top:30px"><input value="unchanged"></div>
    <div class="qtype-clozeonimage-subquestion" style="left:80px;top:80px">
        <select><option>A</option><option>B</option></select></div>
    <div class="qtype-clozeonimage-subquestion" style="left:120px;top:130px"><label><input type="radio" name="r">Radio</label>
        <label><input type="checkbox">Checkbox</label><button type="button">C</button><a href="#">Link</a></div>
    <div class="qtype-clozeonimage-subquestion" style="left:30px;top:180px"><textarea>Editable</textarea>
        <button type="button" class="qtype-clozeonimage-review-surface" style="left:220px;width:30px">Feedback</button></div>
    <div class="qtype-clozeonimage-subquestion" style="left:60px;top:370px"><input readonly value="Review"></div>
    </div></div><div class="qtype-clozeonimage-view-controls">
    <button type="button" class="btn btn-link btn-sm p-0 mt-2" data-action="clozeonimage-toggle-panorama"
        data-normal-label="Framed view" data-panorama-label="Exit framed view" aria-controls="coi-viewport"
        aria-pressed="false" hidden>Framed view</button></div>
    <button type="button">Check</button><button type="button">Try again</button></div></div></div></form>
    <div style="height:1000px"></div></div></div></div></div></body></html>`;

for (const directory of ['src', 'build']) {
    test(`${directory}: drag, control geometry, drawers, Info, resize, and native scrolling in Chromium`, async() => {
        const browser = await chromium.launch({headless: true});
        try {
            const errors = [];
            for (const width of [800, 1777]) {
                const page = await browser.newPage({viewport: {width: 1440, height: 1000}});
                page.on('pageerror', error => errors.push(error.message));
                // Prevent external font/image requests embedded in the local theme CSS.
                await page.route(/^https?:/, route => route.abort());
                await page.setViewportSize({width: 1440, height: 1000});
                await page.setContent(fixture(width));
                await page.addStyleTag({content: readFileSync(process.env.COI_THEME_CSS, 'utf8')});
                await page.addStyleTag({path: path.join(__dirname, '../styles.css')});
                const initial = await page.locator(imageSelector).boundingBox();
                await page.evaluate(() => {
                    window.define = (...args) => {
                        window.layout = args.at(-1)();
                    };
                    window.frameRequests = 0;
                    const requestFrame = window.requestAnimationFrame.bind(window);
                    window.requestAnimationFrame = callback => {
                        window.frameRequests++;
                        return requestFrame(callback);
                    };
                    window.submissions = 0;
                    document.querySelector('form').addEventListener('submit', event => {
                        event.preventDefault();
                        window.submissions++;
                    });
                });
                await page.addScriptTag({path: path.join(__dirname, '../amd', directory,
                    directory === 'src' ? 'layout.js' : 'layout.min.js')});
                await page.evaluate(() => window.layout.init());
                assert.equal(await page.locator('.qtype-clozeonimage-panorama-track').count(), 0, 'Normal by default');
                await page.locator(toggleSelector).click();
                await settle(page);
                const original = await snapshot(page);
                const surface = await page.locator(scrollSelector).evaluate(element => {
                    const style = getComputedStyle(element);
                    return [style.borderLeftWidth, style.borderRightWidth, style.paddingLeft, style.paddingRight, style.boxShadow];
                });
                assert.deepEqual(surface, ['1px', '1px', '0px', '0px', 'none'], 'Gutters use track geometry, not CSS padding');
                const borders = await page.locator(scrollSelector).evaluate(element => {
                    const properties = ['borderRight', 'borderLeft',
                        'borderTopLeftRadius', 'borderTopRightRadius', 'borderBottomLeftRadius', 'borderBottomRightRadius'];
                    const style = getComputedStyle(element);
                    const formulation = getComputedStyle(element.closest('.formulation'));
                    return {viewport: properties.map(property => style[property]),
                        formulation: properties.map(property => formulation[property])};
                });
                assert.deepEqual(borders.viewport, borders.formulation, 'Panorama border and radius exactly match formulation');
                for (const selector of ['.qtype-clozeonimage-panorama-anchor', '.qtype-clozeonimage-panorama-track',
                    '.qtype-clozeonimage-composition', imageSelector]) {
                    assert.equal(await page.locator(selector).evaluate(element => getComputedStyle(element).borderWidth),
                        '0px', `${selector} paints no additional border`);
                }

                assert.equal(original.gutter, 10, 'The scoped gutter is exactly 10 px');
                close(original.innerLeft - original.left, 11, 'Left border plus 10 px gutter');
                close(original.right - original.innerRight, 11, 'Right border plus 10 px gutter');
                close(original.imageWidth, width, 'Teacher width');
                close(original.overflow, 0, 'No document overflow');
                close(original.width, 1440, 'Panorama uses browser width with Info above Content');
                const desired = original.innerLeft + Math.max(Math.min(0, original.innerWidth - width),
                    Math.min(Math.max(0, original.innerWidth - width), initial.x - original.innerLeft));
                close(original.imageLeft, desired, 'Initial screen position is preserved or clamped');
                close(original.range, Math.abs(original.innerWidth - width), 'Exact native scroll range');
                for (const value of [0, original.range, original.range / 2]) {
                    await scrollTo(page, value);
                    const current = await snapshot(page);
                    close(current.imageWidth, width, 'Width after native pan');
                    assert.deepEqual(current.relative, original.relative, 'Image/control relative geometry');
                    close(current.overflow, 0, 'No page overflow after pan');
                    close(current.documentX, 0, 'No page horizontal movement');
                    if (value === 0) {
                        close(current.imageLeft, current.innerLeft, 'Thumb fully left: left union edge');
                    } else if (value === original.range) {
                        close(current.imageRight, current.innerRight, 'Thumb fully right: right union edge');
                    }
                }
                // Real pointer capture, including releasing outside the image.
                const image = await page.locator(imageSelector).boundingBox();
                const beforeDrag = await snapshot(page);
                const x = Math.max(400, image.x + 400);
                await page.mouse.move(x, image.y + 285);
                assert.equal(await page.locator(imageSelector).evaluate(element => getComputedStyle(element).cursor), 'grab');
                await page.mouse.down();
                assert.ok(await page.locator(scrollSelector).evaluate(element =>
                    element.classList.contains('qtype-clozeonimage-panorama-dragging')));
                await page.mouse.move(x + 80, image.y + 285, {steps: 5});
                await page.mouse.move(x + 80, image.y + image.height + 70);
                await page.mouse.up();
                const afterDrag = await snapshot(page);
                close(afterDrag.scroll, beforeDrag.scroll + (width < beforeDrag.innerWidth ? 80 : -80),
                    'Drag uses the appropriate thumb mapping');
                close(afterDrag.imageLeft, beforeDrag.imageLeft + 80, 'Dragging follows the pointer for either union size');
                assert.deepEqual(afterDrag.relative, original.relative);
                assert.deepEqual(afterDrag.values, original.values, 'Drag never changes answers');
                assert.equal(await page.evaluate(() => window.submissions), 0);
                // Put every control in reach, then ensure no control descendant captures a pan.
                await scrollTo(page, 0);
                for (const control of await page.locator('.qtype-clozeonimage-subquestion input, ' +
                        '.qtype-clozeonimage-subquestion select, .qtype-clozeonimage-subquestion textarea, ' +
                        '.qtype-clozeonimage-subquestion button, .qtype-clozeonimage-subquestion label, ' +
                        '.qtype-clozeonimage-subquestion a').all()) {
                    await control.dispatchEvent('pointerdown', {button: 0, isPrimary: true, pointerId: 2});
                    assert.equal(await page.locator(scrollSelector).evaluate(element =>
                        element.classList.contains('qtype-clozeonimage-panorama-dragging')), false);
                }
                await page.locator('.qtype-clozeonimage-subquestion input').first().fill('Edited normally');
                assert.equal(await page.locator('.qtype-clozeonimage-subquestion input').first().inputValue(), 'Edited normally');
                // Use actual Boost drawer CSS; both may be open independently.
                for (const [left, right] of [[true, false], [false, true], [true, true], [false, false]]) {
                    await page.evaluate(({left, right}) => {
                        document.querySelector('.drawer-left').classList.toggle('show', left);
                        document.querySelector('.drawer-right').classList.toggle('show', right);
                        document.getElementById('page').classList.toggle('show-drawer-left', left);
                        document.getElementById('page').classList.toggle('show-drawer-right', right);
                    }, {left, right});
                    await settle(page);
                    const current = await snapshot(page);
                    const leftRect = await page.locator('.drawer-left').boundingBox();
                    const rightRect = await page.locator('.drawer-right').boundingBox();
                    close(current.left, left ? leftRect.x + leftRect.width : 0, 'Measured left drawer edge');
                    close(current.right, right ? rightRect.x : 1440, 'Measured right drawer edge');
                    close(current.overflow, 0, 'Drawers do not cause question overflow');
                    assert.deepEqual(current.relative, original.relative);
                }
                // Info stays before Content in the semantic DOM and sits above it at desktop width.
                const structure = await page.locator('.que').evaluate(question => {
                    const info = question.querySelector(':scope > .info');
                    const content = question.querySelector(':scope > .content');
                    const panorama = question.querySelector('.qtype-clozeonimage-scroll');
                    return {
                        domOrder: info.nextElementSibling === content,
                        infoBottom: info.getBoundingClientRect().bottom,
                        contentTop: content.getBoundingClientRect().top,
                        panoramaTop: panorama.getBoundingClientRect().top,
                        contentWidth: content.getBoundingClientRect().width,
                        infoWidth: info.getBoundingClientRect().width,
                        questionWidth: question.getBoundingClientRect().width,
                    };
                });
                assert.ok(structure.domOrder, 'Info functionality retains its semantic DOM location');
                assert.ok(structure.infoBottom <= structure.contentTop, 'Info is above Content at desktop width');
                assert.ok(structure.infoBottom < structure.panoramaTop, 'Info never overlaps the panorama');
                close(structure.contentWidth, structure.questionWidth, 'Content uses the full question width');
                close(structure.infoWidth, structure.questionWidth, 'Info background spans the full question width');
                await page.locator('.info button').click();
                const beforeScroll = await snapshot(page);
                const info = await page.locator('.info').boundingBox();
                await page.evaluate(y => window.scrollTo(0, y), info.y + info.height + 20);
                await settle(page);
                assert.ok((await page.locator('.info').boundingBox()).y + info.height < 0, 'Info leaves the visible viewport');
                let current = await snapshot(page);
                close(current.width, beforeScroll.width, 'Info leaving view cannot change panorama width');
                close(current.imageLeft, beforeScroll.imageLeft, 'Vertical scrolling preserves image position');
                close(current.range, beforeScroll.range, 'Vertical scrolling preserves travel range');
                close(current.overflow, 0, 'No document overflow while vertically scrolled');
                assert.deepEqual(current.relative, original.relative);
                await page.evaluate(() => window.scrollTo(0, 0));
                await settle(page);
                close((await snapshot(page)).width, beforeScroll.width, 'Info returning cannot change panorama width');
                // A larger Info block pushes Content down instead of changing panoramic bounds.
                await page.locator('.info').evaluate(element => {
                    element.style.height = '450px';
                });
                await settle(page);
                const tallInfo = await page.locator('.info').boundingBox();
                const panorama = await page.locator(scrollSelector).boundingBox();
                assert.ok(tallInfo.y + tallInfo.height < panorama.y, 'Tall Info remains above the panorama');
                close(panorama.width, beforeScroll.width, 'Info size does not affect panorama width');
                await page.locator('.info').evaluate(element => {
                    element.style.height = '';
                });
                await settle(page);
                // CSS viewport changes cover desktop zoom's geometry effect (80%, 100%, 125%).
                for (const viewportWidth of [1800, 1440, 1152, 700, 1600]) {
                    const previous = await snapshot(page);
                    await page.setViewportSize({width: viewportWidth, height: 1000});
                    await settle(page);
                    current = await snapshot(page);
                    close(current.width, viewportWidth, 'Automatic viewport resize');
                    const resizedInfo = await page.locator('.info').boundingBox();
                    const resizedContent = await page.locator('.que > .content').boundingBox();
                    assert.ok(resizedInfo.y + resizedInfo.height <= resizedContent.y, 'Info stays above Content after resize');
                    close(resizedInfo.width, resizedContent.width, 'Info and Content stay full width at every breakpoint');
                    close(current.imageWidth, width, 'Resize never scales image');
                    const minimum = current.innerLeft + Math.min(0, current.innerWidth - width);
                    const maximum = current.innerLeft + Math.max(0, current.innerWidth - width);
                    close(current.imageLeft, Math.max(minimum, Math.min(maximum, previous.imageLeft)), 'Resize preserves position');
                    close(current.overflow, 0, 'Resize keeps page width normal');
                    assert.deepEqual(current.relative, original.relative);
                }
                await scrollTo(page, 0);
                const box = await page.locator(imageSelector).boundingBox();
                await page.mouse.move(Math.max(400, box.x + 400), box.y + 285);
                await page.mouse.wheel(120, 0);
                await settle(page);
                assert.ok((await snapshot(page)).scroll > 0, 'Native horizontal wheel works');
                await page.mouse.wheel(0, 250);
                await settle(page);
                assert.ok(await page.evaluate(() => window.scrollY > 0 || document.getElementById('page').scrollTop > 0),
                    'Vertical page scrolling continues');
                await page.evaluate(() => window.scrollTo(0, 0));
                await settle(page);
                await page.evaluate(() => window.layout.init());
                assert.equal(await page.locator('.qtype-clozeonimage-panorama-track').count(), 1, 'Repeated init is idempotent');
                // ResizeObserver responds to image changes, including W = usable width.
                await page.locator(imageSelector).evaluate((element, usable) => {
                    element.style.width = `${usable}px`;
                }, (await snapshot(page)).innerWidth);
                await settle(page);
                current = await snapshot(page);
                close(current.range, 0, 'Equal image and usable widths have no pan range');
                assert.deepEqual(current.relative, original.relative);
                await page.locator(imageSelector).evaluate(element => {
                    element.style.width = '800px';
                });
                await settle(page);
                close((await snapshot(page)).range, (await snapshot(page)).innerWidth - 800,
                    'Image resizing automatically restores small-image travel within the gutters');
                const currentImage = await page.locator(imageSelector).boundingBox();
                await page.mouse.move(currentImage.x + 600, currentImage.y + Math.min(285, currentImage.height - 10));
                await page.mouse.down();
                assert.ok(await page.locator(scrollSelector).evaluate(element =>
                    element.classList.contains('qtype-clozeonimage-panorama-dragging')), 'Gesture begins before cancellation');
                await page.locator(scrollSelector).dispatchEvent('pointercancel', {pointerId: 1});
                assert.equal(await page.locator(scrollSelector).evaluate(element =>
                    element.classList.contains('qtype-clozeonimage-panorama-dragging')), false, 'Cancelled gesture releases drag');
                await page.mouse.up();
                await settle(page);
                const frames = await page.evaluate(() => window.frameRequests);
                await settle(page);
                assert.equal(await page.evaluate(() => window.frameRequests), frames, 'No polling after geometry settles');
                await page.close();
            }
            assert.deepEqual(errors, []);
        } finally {
            await browser.close();
        }
    });
}

/** Measure normal layout with the tiny mode control excluded from the height comparison. */
const normalMetrics = page => page.evaluate(() => {
    const controls = document.querySelector('.qtype-clozeonimage-view-controls');
    controls.hidden = true;
    const geometry = ['.que', '.info', '.que > .content', '.formulation', '.qtype-clozeonimage-image'].map(selector => {
        const element = document.querySelector(selector);
        const rect = element.getBoundingClientRect();
        const style = getComputedStyle(element);
        return [rect.x + window.scrollX, rect.y + window.scrollY, rect.width, rect.height,
            style.marginTop, style.marginRight, style.marginBottom, style.marginLeft];
    });
    const overflow = document.scrollingElement.scrollWidth;
    controls.hidden = false;
    return {geometry, overflow};
});

/** Instrument activation-scoped resources to detect growth across repeated mode changes. */
const instrumentLifecycle = page => page.evaluate(() => {
    window.resourceCounts = {signals: 0, permanent: 0, resize: 0, mutation: 0};
    const add = EventTarget.prototype.addEventListener;
    EventTarget.prototype.addEventListener = function(type, callback, options) {
        if (options?.signal) {
            window.resourceCounts.signals++;
            add.call(options.signal, 'abort', () => window.resourceCounts.signals--, {once: true});
        } else if (this === document || this === window) {
            window.resourceCounts.permanent++;
        }
        return add.call(this, type, callback, options);
    };
    for (const [name, key] of [['ResizeObserver', 'resize'], ['MutationObserver', 'mutation']]) {
        const Observer = window[name];
        window[name] = class extends Observer {
            constructor(callback) {
                super(callback);
                this.targets = new Set();
            }
            observe(target, options) {
                if (!this.targets.has(target)) {
                    this.targets.add(target);
                    window.resourceCounts[key]++;
                }
                return super.observe(target, options);
            }
            unobserve(target) {
                if (this.targets.delete(target)) {
                    window.resourceCounts[key]--;
                }
                return super.unobserve(target);
            }
            disconnect() {
                window.resourceCounts[key] -= this.targets.size;
                this.targets.clear();
                super.disconnect();
            }
        };
    }
});

/** Load a real source/built module into the fixture's small AMD bridge. */
const loadController = async(page, directory, name) => {
    await page.evaluate(module => {
        window.define = (...args) => {
            window[module] = args.at(-1)(window.bootstrap);
        };
    }, name);
    await page.addScriptTag({path: path.join(__dirname, '../amd', directory,
        directory === 'src' ? `${name}.js` : `${name}.min.js`)});
    await page.evaluate(module => window[module].init(), name);
};

for (const directory of ['src', 'build']) {
    test(`${directory}: normal fidelity, reversible mode lifecycle, feedback Escape and independent AJAX state`, async() => {
        const browser = await chromium.launch({headless: true});
        try {
            for (const width of [800, 1777]) {
                const page = await browser.newPage({viewport: {width: 1440, height: 1000}});
                const reference = await browser.newPage({viewport: {width: 1440, height: 1000}});
                const errors = [];
                page.on('pageerror', error => errors.push(error.message));
                for (const target of [page, reference]) {
                    await target.route(/^https?:/, route => route.abort());
                    await target.setContent(fixture(width));
                    await target.addStyleTag({content: readFileSync(process.env.COI_THEME_CSS, 'utf8')});
                }
                await reference.addStyleTag({content: baselineCss});
                await reference.evaluate(() => {
                    window.define = (...args) => args.at(-1)().init();
                });
                await reference.addScriptTag({content: baselineLayout});
                await page.addStyleTag({path: path.join(__dirname, '../styles.css')});
                await instrumentLifecycle(page);
                await loadController(page, directory, 'layout');
                await settle(page);
                for (const viewportWidth of [1440, 700]) {
                    await page.setViewportSize({width: viewportWidth, height: 1000});
                    await reference.setViewportSize({width: viewportWidth, height: 1000});
                    await settle(page);
                    assert.deepEqual(await normalMetrics(page), await normalMetrics(reference),
                        `Normal ${width}px image matches publication baseline at ${viewportWidth}px viewport`);
                    const info = await page.locator('.info').boundingBox();
                    const content = await page.locator('.que > .content').boundingBox();
                    assert.ok(viewportWidth > 767 ? info.x + info.width <= content.x : info.y + info.height <= content.y,
                        'Info follows native desktop and narrow Moodle layout');
                    assert.equal(await page.locator(scrollSelector).evaluate(element => getComputedStyle(element).overflowX),
                        'visible', 'Normal mode uses natural document overflow');
                    assert.equal(await page.locator('.qtype-clozeonimage-panorama-track').count(), 0);
                }
                await page.setViewportSize({width: 1440, height: 1000});
                await reference.setViewportSize({width: 1440, height: 1000});
                // Normal drawer geometry also remains exactly baseline geometry.
                for (const target of [page, reference]) {
                    await target.evaluate(() => {
                        document.querySelector('.drawer-right').classList.add('show');
                        document.getElementById('page').classList.add('show-drawer-right');
                    });
                }
                await settle(page);
                assert.deepEqual(await normalMetrics(page), await normalMetrics(reference), 'Normal drawers match baseline');
                for (const target of [page, reference]) {
                    await target.evaluate(() => {
                        document.querySelector('.drawer-right').classList.remove('show');
                        document.getElementById('page').classList.remove('show-drawer-right');
                    });
                }
                await settle(page);
                const normal = await normalMetrics(page);
                const relative = (await snapshot(page)).relative;
                const resources = await page.evaluate(() => ({...window.resourceCounts}));
                let activeResources;
                for (let cycle = 0; cycle < 3; cycle++) {
                    await page.locator(toggleSelector).focus();
                    await page.keyboard.press(cycle % 2 ? 'Space' : 'Enter');
                    await settle(page);
                    assert.equal(await page.locator(toggleSelector).getAttribute('aria-pressed'), 'true');
                    assert.equal(await page.locator(toggleSelector).textContent(), 'Exit framed view');
                    assert.ok(await page.locator(toggleSelector).evaluate(element => element === document.activeElement));
                    assert.equal(await page.locator('.qtype-clozeonimage-panorama-track').count(), 1);
                    assert.equal(await page.locator('.qtype-clozeonimage-panorama-anchor').count(), 1);
                    assert.equal(await page.locator(toggleSelector).count(), 1);
                    assert.deepEqual((await snapshot(page)).relative, relative);
                    const observed = await page.evaluate(() => ({...window.resourceCounts}));
                    if (activeResources) {
                        assert.deepEqual(observed, activeResources, 'No growth in active observers/listeners');
                    }
                    activeResources = observed;
                    if (cycle === 1) {
                        await page.keyboard.press('Escape');
                    } else {
                        await page.locator(toggleSelector).click();
                    }
                    await settle(page);
                    assert.equal(await page.locator(toggleSelector).getAttribute('aria-pressed'), 'false');
                    assert.equal(await page.locator('.qtype-clozeonimage-panorama-track').count(), 0);
                    assert.equal(await page.locator('.qtype-clozeonimage-panorama-anchor').count(), 0);
                    assert.deepEqual(await normalMetrics(page), normal, 'Normal layout returns without cumulative geometry');
                    assert.deepEqual((await snapshot(page)).relative, relative);
                    assert.ok(await page.locator(toggleSelector).evaluate(element => element === document.activeElement));
                    assert.equal(await page.evaluate(() => window.scrollX), 0);
                    assert.deepEqual(await page.evaluate(() => ({...window.resourceCounts})), resources,
                        'Activation listeners and geometry observers are released on exit');
                    await page.evaluate(() => window.layout.init());
                }
                // Real Bootstrap popovers, real Clear controller, and non-submitting mode buttons.
                await page.addScriptTag({path: require.resolve('bootstrap/dist/js/bootstrap.bundle.js')});
                await page.evaluate(() => {
                    const radio = document.querySelector('input[type="radio"]');
                    const region = radio.closest('.qtype-clozeonimage-subquestion');
                    region.dataset.region = 'clozeonimage-multichoice';
                    radio.dataset.role = 'clozeonimage-multichoice-choice';
                    const sentinel = document.createElement('input');
                    sentinel.type = 'hidden';
                    sentinel.name = radio.name;
                    sentinel.value = '-1';
                    sentinel.dataset.role = 'clozeonimage-clear-choice-sentinel';
                    region.prepend(sentinel);
                    region.querySelector('button').dataset.action = 'clozeonimage-clear-choice';
                    const feedback = document.querySelector('input[readonly]');
                    feedback.classList.add('form-control', 'qtype-clozeonimage-feedback-trigger');
                    Object.assign(feedback.dataset, {bsToggle: 'popover', bsContent: 'Review feedback',
                        bsTrigger: 'hover focus', bsContainer: 'body'});
                    window.submissions = 0;
                    document.querySelector('form').addEventListener('submit', event => {
                        window.submissions++;
                        event.preventDefault();
                    });
                    document.querySelectorAll('.formulation > button').forEach(button => {
                        button.type = 'submit';
                    });
                });
                await loadController(page, directory, 'feedback');
                await loadController(page, directory, 'clearchoice');
                for (const active of [false, true]) {
                    if (active) {
                        await page.locator(toggleSelector).click();
                        await settle(page);
                        // Put controls in reach without changing their coordinates.
                        await scrollTo(page, 0);
                    }
                    await page.locator('input:not([type]):not([readonly])').fill('Preserved answer');
                    await page.locator('select').selectOption({label: 'B'});
                    await page.locator('input[type="checkbox"]').check();
                    await page.locator('input[type="radio"]').check();
                    await page.locator('[data-action="clozeonimage-clear-choice"]').click();
                    assert.equal(await page.locator('input[type="radio"]').isChecked(), false);
                    await page.getByRole('button', {name: 'Check', exact: true}).click();
                    await page.getByRole('button', {name: 'Try again', exact: true}).click();
                    assert.equal(await page.locator(toggleSelector).getAttribute('aria-pressed'), String(active),
                        'Check/Try again are operable in either view');
                    const input = page.locator('input[readonly]');
                    await input.focus();
                    await settle(page);
                    assert.equal(await page.locator('.popover.show').count(), 1);
                    await page.keyboard.press('Escape');
                    await settle(page);
                    assert.equal(await page.locator('.popover.show').count(), 0, 'First Escape dismisses feedback');
                    assert.equal(await page.locator(toggleSelector).getAttribute('aria-pressed'), String(active));
                    assert.ok(await input.evaluate(element => element === document.activeElement),
                        'Feedback dismissal retains focus');
                    if (active) {
                        await page.keyboard.press('Escape');
                        await settle(page);
                        assert.equal(await page.locator(toggleSelector).getAttribute('aria-pressed'), 'false');
                        assert.ok(await input.evaluate(element => element === document.activeElement), 'Exit retains answer focus');
                        assert.equal(await page.locator('.popover.show').count(), 0, 'Exit must not reopen dismissed feedback');
                        assert.equal(await page.locator('input:not([type]):not([readonly])').inputValue(), 'Preserved answer');
                    }
                }
                assert.equal(await page.evaluate(() => window.submissions), 4, 'Only Check/Try again submit');
                // Separate per-question AJAX state, followed by page-wide Escape exit.
                await page.evaluate(() => {
                    const first = document.querySelector('.que');
                    const second = first.cloneNode(true);
                    second.id = 'question-coi-2';
                    second.querySelector('.qtype-clozeonimage-image').style.width = '500px';
                    second.querySelector('.qtype-clozeonimage-scroll').id = 'coi-viewport-2';
                    second.querySelector('[data-action="clozeonimage-toggle-panorama"]')
                        .setAttribute('aria-controls', 'coi-viewport-2');
                    first.after(second);
                });
                await settle(page);
                const firstToggle = page.locator('#question-coi-1').locator(toggleSelector);
                const secondToggle = page.locator('#question-coi-2').locator(toggleSelector);
                await firstToggle.click();
                await settle(page);
                assert.equal(await secondToggle.getAttribute('aria-pressed'), 'false');
                const replacement = await reference.locator('.que').evaluate(element => element.outerHTML);
                await page.locator('#question-coi-1').evaluate((element, html) => {
                    element.outerHTML = html;
                }, replacement);
                await settle(page);
                assert.equal(await firstToggle.getAttribute('aria-pressed'), 'true',
                    'Same question ID restores panorama after AJAX');
                assert.equal(await secondToggle.getAttribute('aria-pressed'), 'false');
                await secondToggle.click();
                await settle(page);
                assert.equal(await page.locator(`.${activeClass}`).count(), 2);
                await firstToggle.focus();
                await page.keyboard.press('Escape');
                await settle(page);
                assert.equal(await firstToggle.getAttribute('aria-pressed'), 'false');
                assert.equal(await secondToggle.getAttribute('aria-pressed'), 'false', 'Escape exits both questions');
                assert.equal(await page.locator('.qtype-clozeonimage-panorama-track').count(), 0);
                assert.equal(await page.locator(toggleSelector).count(), 2);
                assert.deepEqual(errors, []);
                await page.close();
                await reference.close();
            }
        } finally {
            await browser.close();
        }
    });
}

/** Moodle's attempt usage is new even when responses are copied from a previous attempt. */
const wideKey = (usage, slot = 1) => `qtype_clozeonimage:wide:v1:fixture-site:usage:${usage}:slot:${slot}:question:42`;

/** Include the real Boost/quiz ancestor structure and structural attempt/review context. */
const quizFixture = (usage, review = false, width = 1777) => fixture(width)
    .replace('<body ', `<body id="page-mod-quiz-${review ? 'review' : 'attempt'}" `)
    .replace('<div role="main">', '<div id="page-content" class="pb-3 d-print-block"><div id="region-main-box">' +
        '<div id="region-main"><div role="main">')
    .replace('</body>', '</div></div></div></body>')
    .replace('<form>', `<form id="${review ? 'reviewform' : 'responseform'}"><div>`)
    .replace('</form>', `<input type="hidden" name="attempt" value="${usage}"></div></form>`)
    .replace('class="formulation"', 'class="formulation clearfix"')
    .replace('id="coi-viewport"', `id="coi-viewport" data-wide-key="${wideKey(usage)}"`);

/** Measure ownership all the way from the image through Boost's ancestors. */
const overflowMeasurements = page => page.evaluate(() => {
    const selectors = ['html', 'body', '#page-wrapper', '#page', '.main-inner', '#page-content', '#region-main-box',
        '#region-main', '[role="main"]', 'form', '.que', '.content', '.formulation', '.qtype-clozeonimage-panorama-anchor',
        '.qtype-clozeonimage-scroll', '.qtype-clozeonimage-panorama-track', '.qtype-clozeonimage-composition',
        '.qtype-clozeonimage-image', '.qtype-clozeonimage-subquestion'];
    return selectors.flatMap(selector => [...document.querySelectorAll(selector)].map(element => {
        const rect = element.getBoundingClientRect();
        return {selector, scroll: element.scrollWidth, client: element.clientWidth,
            left: rect.left, right: rect.right, width: rect.width, overflow: getComputedStyle(element).overflowX};
    }));
});

for (const directory of ['src', 'build']) {
    test(`${directory}: attempt session persistence, navigation, Review and inherited new attempts`, async() => {
        const browser = await chromium.launch({headless: true});
        try {
            const page = await browser.newPage({viewport: {width: 1440, height: 1000}});
            const errors = [];
            page.on('pageerror', error => errors.push(error.message));
            await page.route(/^https?:/, route => {
                const url = new URL(route.request().url());
                if (url.hostname !== 'coi.test') {
                    return route.abort();
                }
                if (url.searchParams.get('page') === '1') {
                    return route.fulfill({contentType: 'text/html', body: '<!doctype html><html>' +
                        '<body id="page-mod-quiz-attempt">Another quiz page without this question</body></html>'});
                }
                return route.fulfill({contentType: 'text/html', body: quizFixture(url.searchParams.get('attempt') || 101,
                    url.pathname.endsWith('review.php'))});
            });
            const initialise = async() => {
                await page.addStyleTag({path: process.env.COI_THEME_CSS});
                await page.addStyleTag({path: path.join(__dirname, '../styles.css')});
                await loadController(page, directory, 'layout');
                await settle(page);
            };
            const navigate = async(url) => {
                await page.goto(`http://coi.test/mod/quiz/${url}`);
                await initialise();
            };
            const toggle = page.locator(toggleSelector).first();
            const stored = () => page.evaluate(key => sessionStorage.getItem(key), wideKey(101));
            await navigate('attempt.php?attempt=101');
            assert.equal(await toggle.textContent(), 'Framed view');
            assert.equal(await toggle.getAttribute('aria-pressed'), 'false');
            await toggle.click();
            await settle(page);
            assert.equal(await toggle.textContent(), 'Exit framed view');
            assert.equal(await stored(), '1');
            await page.reload();
            await initialise();
            assert.equal(await toggle.getAttribute('aria-pressed'), 'true', 'Full reload restores current-attempt state');
            const fresh = quizFixture(101);
            for (const action of ['Check', 'Try again']) {
                await page.getByRole('button', {name: action, exact: true}).click();
                await page.locator('.que').evaluate((element, html) => {
                    const replacement = new DOMParser().parseFromString(html, 'text/html').querySelector('.que');
                    // No dependency on a transient question DOM ID.
                    replacement.id = 'replacement-' + Math.random();
                    element.replaceWith(replacement);
                }, fresh);
                await settle(page);
                assert.equal(await toggle.getAttribute('aria-pressed'), 'true', `${action}/AJAX restores stable usage key`);
            }
            await navigate('attempt.php?attempt=101&page=1');
            assert.equal(await page.locator(toggleSelector).count(), 0);
            await navigate('attempt.php?attempt=101&page=0');
            assert.equal(await toggle.getAttribute('aria-pressed'), 'true', 'Same-attempt page navigation restores Wide');
            await page.evaluate(({html, key}) => {
                const second = new DOMParser().parseFromString(html, 'text/html').querySelector('.que');
                second.id = 'second';
                second.querySelector('.qtype-clozeonimage-image').style.width = '500px';
                second.querySelector('.qtype-clozeonimage-scroll').dataset.wideKey = key;
                document.querySelector('.que').after(second);
            }, {html: fresh, key: wideKey(101, 2)});
            await settle(page);
            assert.equal(await page.locator('#second').locator(toggleSelector).getAttribute('aria-pressed'), 'false',
                'Another question in the same attempt remains Normal');
            await toggle.click();
            await settle(page);
            assert.equal(await stored(), null, 'Click exit removes session state');
            await toggle.click();
            await settle(page);
            await page.addScriptTag({path: require.resolve('bootstrap/dist/js/bootstrap.bundle.js')});
            await page.locator('input[readonly]').first().evaluate(element => {
                element.classList.add('form-control', 'qtype-clozeonimage-feedback-trigger');
                Object.assign(element.dataset, {bsToggle: 'popover', bsContent: 'Feedback',
                    bsTrigger: 'hover focus', bsContainer: 'body'});
            });
            await loadController(page, directory, 'feedback');
            await scrollTo(page, 0);
            await page.locator('input[readonly]').first().focus();
            await settle(page);
            assert.equal(await page.locator('.popover.show').count(), 1);
            await page.keyboard.press('Escape');
            await settle(page);
            assert.equal(await page.locator('.popover.show').count(), 0);
            assert.equal(await stored(), '1', 'Feedback consumes Escape without clearing stored Wide state');
            assert.equal(await toggle.getAttribute('aria-pressed'), 'true');
            await page.keyboard.press('Escape');
            assert.equal(await stored(), null, 'Escape exit removes session state');
            await settle(page);
            assert.equal(await page.locator('.popover.show').count(), 0, 'Focus restoration does not reopen feedback');
            await toggle.click();
            await settle(page);
            await navigate('review.php?attempt=101');
            assert.equal(await stored(), '1', 'Review cannot alter the original attempt state');
            assert.equal(await toggle.getAttribute('aria-pressed'), 'false', 'Review never restores attempt state');
            await toggle.click();
            await settle(page);
            assert.equal(await toggle.getAttribute('aria-pressed'), 'true', 'Manual Review Wide works');
            await page.keyboard.press('Escape');
            assert.equal(await toggle.getAttribute('aria-pressed'), 'false');
            assert.equal(await stored(), '1');
            await toggle.click();
            await page.reload();
            await initialise();
            assert.equal(await toggle.getAttribute('aria-pressed'), 'false', 'Review choice is page-local');
            await navigate('attempt.php?attempt=102');
            assert.equal(await page.locator('input:not([type])').first().inputValue(), 'unchanged',
                'New attempt can have exactly the same inherited answers');
            assert.equal(await toggle.getAttribute('aria-pressed'), 'false', 'New usage never inherits Wide');
            assert.equal(await page.evaluate(() => sessionStorage.length), 1, 'Only original attempt key exists');
            await page.evaluate(() => Object.defineProperty(window, 'sessionStorage', {
                get() {
                    throw new DOMException('Blocked by browser', 'SecurityError');
                },
            }));
            await toggle.click();
            await settle(page);
            assert.equal(await toggle.getAttribute('aria-pressed'), 'true', 'Blocked storage still permits Wide');
            await page.keyboard.press('Escape');
            assert.equal(await toggle.getAttribute('aria-pressed'), 'false');
            assert.deepEqual(errors, []);
        } finally {
            await browser.close();
        }
    });

    test(`${directory}: single and multiple horizontal overflow owners in attempt and Review`, async() => {
        // Native scrollbars are deliberately present; overlay-scrollbar defaults can mask geometry errors.
        const browser = await chromium.launch({headless: true, ignoreDefaultArgs: ['--hide-scrollbars']});
        try {
            for (const review of [false, true]) {
                const page = await browser.newPage({viewport: {width: 1440, height: 1000}});
                await page.route(/^https?:/, route => route.abort());
                await page.setContent(quizFixture(101, review));
                await page.addStyleTag({path: process.env.COI_THEME_CSS});
                await page.addStyleTag({path: path.join(__dirname, '../styles.css')});
                await loadController(page, directory, 'layout');
                const before = await snapshot(page);
                assert.ok(before.overflow > 300, 'Normal oversized image retains baseline document overflow');
                const normal = await page.locator('.que').evaluate(element => element.outerHTML);
                await page.evaluate(() => window.scrollTo(300, 0));
                assert.ok(await page.evaluate(() => window.scrollX) > 0);
                // Programmatic click avoids Playwright scrolling the button into view first.
                await page.locator(toggleSelector).evaluate(element => element.click());
                await settle(page);
                const active = await snapshot(page);
                const measurements = await overflowMeasurements(page);
                assert.equal(active.documentX, 0, 'Entering Wide resets prior document horizontal position');
                assert.equal(active.overflow, 0, JSON.stringify(measurements));
                assert.ok(active.range > 300, 'Oversized image has internal travel');
                assert.deepEqual(active.relative, before.relative);
                await scrollTo(page, 0);
                close((await snapshot(page)).imageLeft, active.innerLeft, 'Large image left reachable');
                await scrollTo(page, 10000);
                close((await snapshot(page)).imageRight, active.innerRight, 'Large image right reachable');
                await page.locator(toggleSelector).click();
                await settle(page);
                assert.equal((await snapshot(page)).overflow, before.overflow, 'Exit restores normal overflow');
                await page.locator(toggleSelector).click();
                await settle(page);
                await page.locator('.que').evaluate((element, html) => {
                    const second = new DOMParser().parseFromString(html, 'text/html').querySelector('.que');
                    second.id = 'oversized-normal';
                    second.querySelector('.qtype-clozeonimage-image').style.width = '3000px';
                    second.querySelector('.qtype-clozeonimage-scroll').dataset.wideKey = 'separate-question';
                    element.after(second);
                }, normal);
                await settle(page);
                const withA = await page.evaluate(() => document.scrollingElement.scrollWidth);
                assert.ok(withA > 3000, 'Normal Question B legitimately retains document overflow');
                await page.locator('.que').first().evaluate(element => {
                    element.style.display = 'none';
                });
                await settle(page);
                assert.equal(await page.evaluate(() => document.scrollingElement.scrollWidth), withA,
                    'Removing Wide Question A does not reduce the overflow owned by B');
                await page.locator('.que').first().evaluate(element => {
                    element.style.removeProperty('display');
                });
                await settle(page);
                assert.equal(await page.evaluate(() => document.scrollingElement.scrollWidth), withA);
                await page.evaluate(() => window.scrollTo(300, window.scrollY));
                await settle(page);
                await page.locator('#oversized-normal').locator(toggleSelector).evaluate(element => element.click());
                await settle(page);
                assert.equal((await snapshot(page)).overflow, 0,
                    'Switching every oversized question to Wide removes document overflow');
                assert.equal(await page.locator(`.${activeClass}`).count(), 2);
                const clipping = await page.evaluate(() => [document.documentElement, document.body].map(element =>
                    getComputedStyle(element).overflowX));
                assert.ok(clipping.every(value => !['hidden', 'clip'].includes(value)), 'No global clipping');
                await page.close();
            }
        } finally {
            await browser.close();
        }
    });
}

/** Server-style fixture with independent question/viewport IDs and per-slot storage keys. */
const coordinatedFixture = (usage, review, widths) => {
    const html = quizFixture(usage, review, widths[0]);
    const start = html.indexOf('<div class="que clozeonimage"');
    const end = html.indexOf('<input type="hidden" name="attempt"');
    const question = html.slice(start, end);
    const questions = widths.map((width, index) => question
        .replaceAll('question-coi-1', `question-coi-${index + 1}`)
        .replaceAll('coi-viewport', `coi-viewport-${index + 1}`)
        .replace(wideKey(usage), wideKey(usage, index + 1))
        .replace(`width:${widths[0]}px`, `width:${width}px`));
    return html.slice(0, start) + questions.join('') + html.slice(end);
};
const modeStates = page => page.locator(toggleSelector).evaluateAll(buttons =>
    buttons.map(button => button.getAttribute('aria-pressed') === 'true'));
const allImageGeometry = page => page.locator('.que.clozeonimage').evaluateAll(questions => questions.map(question => {
    const image = question.querySelector('.qtype-clozeonimage-image').getBoundingClientRect();
    return {width: image.width, relative: [...question.querySelectorAll('.qtype-clozeonimage-subquestion')].map(control => {
        const rect = control.getBoundingClientRect();
        return [rect.left - image.left, rect.top - image.top];
    })};
}));

for (const directory of ['src', 'build']) {
    test(`${directory}: coordinated Wide requests select real overflow owners and terminate`, async() => {
        const browser = await chromium.launch({headless: true, ignoreDefaultArgs: ['--hide-scrollbars']});
        try {
            const scenarios = [
                {widths: [1777], owners: [true]},
                {widths: [1777, 1600], owners: [true, true]},
                {widths: [1777, 600], owners: [true, false]},
                {widths: [1777, 600, 1600, 500], owners: [true, false, true, false]},
                {widths: [1777, 600, 1600, 500], unrelated: true},
                {widths: [1777, 600, 1600, 500], viewport: 1152},
                {widths: [1777, 600, 1600, 500], viewport: 1800},
                {widths: [1777, 600, 1600, 500], viewport: 700},
                // These same image widths fit at a sufficiently wide browser size.
                {widths: [1777, 600, 1600, 500], viewport: 4000},
                ...[[true, false], [false, true], [true, true]].map(drawers =>
                    ({widths: [1777, 600, 1600, 500], drawers})),
            ];
            for (const scenario of scenarios) {
                const page = await browser.newPage({viewport: {width: scenario.viewport || 1440, height: 1000}});
                const errors = [];
                page.on('pageerror', error => errors.push(error.message));
                await page.route(/^https?:/, route => route.abort());
                await page.setContent(coordinatedFixture(201, false, scenario.widths));
                await page.addStyleTag({path: process.env.COI_THEME_CSS});
                await page.addStyleTag({path: path.join(__dirname, '../styles.css')});
                if (scenario.unrelated) {
                    await page.locator('form').evaluate(form => form.insertAdjacentHTML('beforeend',
                        '<div class="que stack" id="unrelated"><div style="min-width:3000px">Unrelated question</div></div>'));
                }
                if (scenario.drawers) {
                    await page.evaluate(([left, right]) => {
                        document.querySelector('.drawer-left').classList.toggle('show', left);
                        document.querySelector('.drawer-right').classList.toggle('show', right);
                        document.getElementById('page').classList.toggle('show-drawer-left', left);
                        document.getElementById('page').classList.toggle('show-drawer-right', right);
                    }, scenario.drawers);
                }
                await instrumentLifecycle(page);
                await loadController(page, directory, 'layout');
                await settle(page);
                assert.deepEqual(await modeStates(page), scenario.widths.map(() => false), 'No load-time coordination');
                const geometry = await allImageGeometry(page);
                // Independent ground truth: these fixtures have no control extending beyond its image.
                const owners = await page.locator(imageSelector).evaluateAll(images => images.map(image =>
                    image.getBoundingClientRect().right + window.scrollX > document.scrollingElement.clientWidth + 1));
                if (scenario.owners) {
                    assert.deepEqual(owners, scenario.owners,
                        'Fixture geometry really distinguishes its large and fitting questions');
                }
                const unrelated = scenario.unrelated ?
                    await page.locator('#unrelated').evaluate(element => element.outerHTML) : null;
                if (owners.some(Boolean)) {
                    await page.evaluate(() => window.scrollTo(200, 0));
                }
                const first = page.locator(toggleSelector).first();
                await first.focus();
                await first.evaluate(element => element.click());
                await settle(page);
                const expected = owners.map((owner, index) => index === 0 || owner);
                assert.deepEqual(await modeStates(page), expected, JSON.stringify(scenario));
                assert.deepEqual(await allImageGeometry(page), geometry, 'All image/control coordinates remain unchanged');
                assert.equal(await first.evaluate(element => document.activeElement === element), true, 'Focus stays local');
                assert.equal(await page.locator('.qtype-clozeonimage-panorama-track').count(), expected.filter(Boolean).length);
                const overflow = (await snapshot(page)).overflow;
                if (scenario.unrelated) {
                    assert.ok(overflow > 1000, 'Unrelated overflow remains usable');
                    assert.equal(await page.locator('#unrelated').evaluate(element => element.outerHTML), unrelated);
                    assert.ok(await page.evaluate(() => [document.body, document.documentElement]
                        .every(element => !['hidden', 'clip'].includes(getComputedStyle(element).overflowX))));
                } else {
                    assert.equal(overflow, 0, 'All Cloze on Image document overflow is eliminated');
                }
                const resources = await page.evaluate(() => window.resourceCounts);
                const cycles = scenario.owners?.length === 4 ? 3 : 1;
                for (let cycle = 0; cycle < cycles; cycle++) {
                    // Exit returns all currently rendered Wide questions to Normal.
                    await first.evaluate(element => element.click());
                    await settle(page);
                    assert.deepEqual(await modeStates(page), expected.map(() => false));
                    await first.evaluate(element => element.click());
                    await settle(page);
                    assert.deepEqual(await modeStates(page), expected);
                    await page.evaluate(() => window.layout.init());
                    await settle(page);
                    assert.deepEqual(await page.evaluate(() => window.resourceCounts), resources,
                        'Coordination and repeated init do not accumulate observers or listeners');
                    assert.equal(await page.locator(toggleSelector).count(), scenario.widths.length);
                    assert.equal(await page.locator('.qtype-clozeonimage-panorama-anchor').count(),
                        expected.filter(Boolean).length);
                }
                // Escape also exits the page when focus is outside a question.
                await first.focus();
                await page.evaluate(() => document.activeElement.blur());
                await page.keyboard.press('Escape');
                await settle(page);
                assert.deepEqual(await modeStates(page), expected.map(() => false), 'Escape exits all rendered Wide questions');
                assert.deepEqual(errors, []);
                await page.close();
            }
        } finally {
            await browser.close();
        }
    });

    test(`${directory}: coordination respects local clipping, controls, and later layout changes`, async() => {
        const browser = await chromium.launch({headless: true});
        try {
            const page = await browser.newPage({viewport: {width: 1440, height: 1000}});
            await page.route(/^https?:/, route => route.abort());
            await page.setContent(coordinatedFixture(201, false, [1777, 1600, 500, 600]));
            await page.addStyleTag({path: process.env.COI_THEME_CSS});
            await page.addStyleTag({path: path.join(__dirname, '../styles.css')});
            await page.locator('#question-coi-2').locator(scrollSelector).evaluate(element => {
                Object.assign(element.style, {width: '300px', overflowX: 'auto'});
            });
            await page.locator('#question-coi-3 .qtype-clozeonimage-subquestion').first().evaluate(element => {
                element.style.left = '1800px';
            });
            await loadController(page, directory, 'layout');
            await settle(page);
            const geometry = await allImageGeometry(page);
            await page.locator(toggleSelector).first().click();
            await settle(page);
            assert.deepEqual(await modeStates(page), [true, false, true, false],
                'Contained image stays Normal; overflow from positioned controls is detected');
            assert.deepEqual(await allImageGeometry(page), geometry);
            assert.equal((await snapshot(page)).overflow, 0);
            await page.locator('#question-coi-4').locator(imageSelector).evaluate(element => {
                element.style.width = '2000px';
            });
            await page.setViewportSize({width: 1152, height: 1000});
            await page.evaluate(() => {
                document.querySelector('.drawer-right').classList.add('show');
                document.getElementById('page').classList.add('show-drawer-right');
            });
            await settle(page);
            assert.deepEqual(await modeStates(page), [true, false, true, false],
                'Resize, image/layout changes and drawers never initiate coordination');
            await page.locator('#question-coi-2').locator(toggleSelector).click();
            await settle(page);
            assert.deepEqual(await modeStates(page), [true, true, true, true], 'Next explicit request re-evaluates owners');
            await page.locator('#question-coi-4').locator(toggleSelector).click();
            await settle(page);
            assert.deepEqual(await modeStates(page), [false, false, false, false], 'Exit affects all rendered Wide questions');
            assert.ok((await snapshot(page)).overflow > 0, 'Explicit Normal overflow is allowed to return');
        } finally {
            await browser.close();
        }
    });

    test(`${directory}: automatically activated siblings persist per attempt, never into Review or new attempts`, async() => {
        const browser = await chromium.launch({headless: true});
        try {
            const page = await browser.newPage({viewport: {width: 1440, height: 1000}});
            await page.route(/^https?:/, route => {
                const url = new URL(route.request().url());
                if (url.hostname !== 'coi.test') {
                    return route.abort();
                }
                const body = url.searchParams.get('page') === '1' ? '<!doctype html><html><body>Another quiz page</body></html>' :
                    coordinatedFixture(url.searchParams.get('attempt'), url.pathname.endsWith('review.php'),
                        [1777, 600, 1600, 500]);
                return route.fulfill({contentType: 'text/html', body});
            });
            const initialise = async() => {
                await page.addStyleTag({path: process.env.COI_THEME_CSS});
                await page.addStyleTag({path: path.join(__dirname, '../styles.css')});
                await loadController(page, directory, 'layout');
                await settle(page);
            };
            const navigate = async(url) => {
                await page.goto(`http://coi.test/mod/quiz/${url}`);
                await initialise();
            };
            await navigate('attempt.php?attempt=201');
            await page.evaluate(key => sessionStorage.setItem(key, '1'), wideKey(201));
            await page.reload();
            await initialise();
            assert.deepEqual(await modeStates(page), [true, false, false, false], 'Restoration does not coordinate');
            const firstTrack = await page.locator('.qtype-clozeonimage-panorama-track').first().elementHandle();
            await page.locator('#question-coi-2').locator(toggleSelector).click();
            await settle(page);
            const expected = [true, true, true, false];
            assert.deepEqual(await modeStates(page), expected, 'Explicit fitting question still discovers oversized sibling');
            assert.equal(await firstTrack.evaluate(element => element.isConnected), true, 'Already Wide wrapper reused');
            const stored = () => page.evaluate(keys => keys.map(key => sessionStorage.getItem(key)),
                [1, 2, 3, 4].map(slot => wideKey(201, slot)));
            assert.deepEqual(await stored(), ['1', '1', '1', null]);
            for (const action of ['Check', 'Try again']) {
                await page.getByRole('button', {name: action, exact: true}).nth(2).click();
                await page.locator('#question-coi-3').evaluate((element, html) => {
                    element.replaceWith(new DOMParser().parseFromString(html, 'text/html').querySelector('#question-coi-3'));
                }, coordinatedFixture(201, false, [1777, 600, 1600, 500]));
                await settle(page);
                assert.deepEqual(await modeStates(page), expected, `${action}/AJAX restores automatic state`);
            }
            await page.reload();
            await initialise();
            assert.deepEqual(await modeStates(page), expected);
            await navigate('attempt.php?attempt=201&page=1');
            await navigate('attempt.php?attempt=201&page=0');
            assert.deepEqual(await modeStates(page), expected, 'Automatic state survives page navigation');
            await page.locator('#question-coi-3').locator(toggleSelector).click();
            assert.deepEqual(await stored(), [null, null, null, null], 'Exit removes all rendered Wide keys');
            await page.locator('#question-coi-2').locator(toggleSelector).click();
            await settle(page);
            await page.locator('#question-coi-2').locator(toggleSelector).focus();
            await page.keyboard.press('Escape');
            assert.deepEqual(await stored(), [null, null, null, null], 'Escape removes all rendered Wide keys');
            await page.locator('#question-coi-2').locator(toggleSelector).click();
            await settle(page);
            assert.deepEqual(await stored(), ['1', '1', '1', null]);
            await navigate('review.php?attempt=201');
            assert.deepEqual(await modeStates(page), [false, false, false, false]);
            await page.locator('#question-coi-2').locator(toggleSelector).click();
            await settle(page);
            assert.deepEqual(await modeStates(page), expected, 'Review manual request can coordinate page-local siblings');
            await page.locator('#question-coi-3').locator(toggleSelector).click();
            assert.deepEqual(await stored(), ['1', '1', '1', null], 'Review never writes attempt storage');
            await page.reload();
            await initialise();
            assert.deepEqual(await modeStates(page), [false, false, false, false]);
            await navigate('attempt.php?attempt=202');
            assert.deepEqual(await modeStates(page), [false, false, false, false],
                'New usage with inherited answers starts Normal');
        } finally {
            await browser.close();
        }
    });
}

/** Read per-question flags, including questions on other quiz pages. */
const storedFlags = (page, usage, slots = [1, 2, 3, 4]) => page.evaluate(keys =>
    keys.map(key => sessionStorage.getItem(key)), slots.map(slot => wideKey(usage, slot)));

for (const directory of ['src', 'build']) {
    test(`${directory}: page-wide Exit preserves other quiz pages and feedback Escape priority`, async() => {
        const browser = await chromium.launch({headless: true});
        try {
            const page = await browser.newPage({viewport: {width: 1440, height: 1000}});
            const errors = [];
            page.on('pageerror', error => errors.push(error.message));
            await page.route(/^https?:/, route => {
                const url = new URL(route.request().url());
                return url.hostname === 'coi.test' ? route.fulfill({contentType: 'text/html',
                    body: coordinatedFixture(301, false, [1777, 1777, 1600, 500])}) : route.abort();
            });
            const navigate = async(only = '') => {
                await page.goto(`https://coi.test/mod/quiz/attempt.php?attempt=301&only=${only}`);
                await page.addStyleTag({path: process.env.COI_THEME_CSS});
                await page.addStyleTag({path: path.join(__dirname, '../styles.css')});
                if (only) {
                    await page.locator('.que').evaluateAll((questions, slot) => questions.forEach(question => {
                        if (question.id !== `question-coi-${slot}`) {
                            question.remove();
                        }
                    }), only);
                }
                await loadController(page, directory, 'layout');
                await settle(page);
            };
            const button = slot => page.locator(`#question-coi-${slot}`).locator(toggleSelector);
            await navigate('1');
            await button(1).click();
            assert.deepEqual(await storedFlags(page, 301), ['1', null, null, null]);
            await navigate();
            assert.deepEqual(await modeStates(page), [true, false, false, false], 'Restoration does not coordinate');
            const firstTrack = await page.locator('#question-coi-1 .qtype-clozeonimage-panorama-track').elementHandle();
            await button(2).click();
            await settle(page);
            assert.deepEqual(await modeStates(page), [true, true, true, false]);
            assert.equal(await firstTrack.evaluate(element => element.isConnected), true, 'Already Wide track reused');
            await button(4).click();
            await settle(page);
            assert.deepEqual(await modeStates(page), [true, true, true, true], 'Small explicitly selected question also Wide');
            for (const exitSlot of [1, 3, 4]) {
                await button(exitSlot).click();
                await settle(page);
                assert.deepEqual(await modeStates(page), [false, false, false, false], 'Any Exit returns the whole page to Normal');
                assert.deepEqual(await storedFlags(page, 301), [null, null, null, null]);
                assert.equal(await button(exitSlot).evaluate(element => document.activeElement === element), true);
                await button(4).click();
                await settle(page);
                assert.deepEqual(await modeStates(page), [true, true, true, true]);
            }
            // Navigation renders only C: Exit must not enumerate/delete A, B or D's stored flags.
            await navigate('3');
            assert.deepEqual(await modeStates(page), [true]);
            await button(3).click();
            assert.deepEqual(await storedFlags(page, 301), ['1', '1', null, '1']);
            await navigate();
            assert.deepEqual(await modeStates(page), [true, true, false, true],
                'Absent page states survive, and restore does not scan');
            await button(3).click();
            await settle(page);
            await page.addScriptTag({path: require.resolve('bootstrap/dist/js/bootstrap.bundle.js')});
            const feedback = page.locator('#question-coi-2 input[readonly]');
            await feedback.evaluate(element => {
                element.classList.add('form-control', 'qtype-clozeonimage-feedback-trigger');
                Object.assign(element.dataset, {bsToggle: 'popover', bsContent: 'Feedback',
                    bsTrigger: 'hover focus', bsContainer: 'body'});
            });
            await loadController(page, directory, 'feedback');
            await feedback.focus();
            await settle(page);
            assert.equal(await page.locator('.popover.show').count(), 1);
            await page.keyboard.press('Escape');
            await settle(page);
            assert.equal(await page.locator('.popover.show').count(), 0);
            assert.deepEqual(await modeStates(page), [true, true, true, true], 'First Escape only closes feedback');
            await page.keyboard.press('Escape');
            await settle(page);
            assert.deepEqual(await modeStates(page), [false, false, false, false], 'Second Escape exits the whole page');
            assert.deepEqual(await storedFlags(page, 301), [null, null, null, null]);
            assert.equal(await page.locator('.popover.show').count(), 0, 'Focus restoration cannot reopen feedback');
            assert.equal(await feedback.evaluate(element => document.activeElement === element), true);
            assert.deepEqual(errors, []);
        } finally {
            await browser.close();
        }
    });

    test(`${directory}: per-question memory survives AJAX and page-wide Exit when storage is blocked`, async() => {
        const browser = await chromium.launch({headless: true});
        try {
            const page = await browser.newPage({viewport: {width: 1440, height: 1000}});
            await page.route(/^https?:/, route => route.abort());
            const html = coordinatedFixture(401, false, [500, 1777, 1600, 500]);
            await page.setContent(html);
            await page.addStyleTag({path: process.env.COI_THEME_CSS});
            await page.addStyleTag({path: path.join(__dirname, '../styles.css')});
            await page.evaluate(() => {
                Object.defineProperty(window, 'sessionStorage', {get() {
                    throw new DOMException('Blocked by browser', 'SecurityError');
                }});
            });
            await instrumentLifecycle(page);
            await loadController(page, directory, 'layout');
            await settle(page);
            const resources = await page.evaluate(() => window.resourceCounts);
            const button = slot => page.locator(`#question-coi-${slot}`).locator(toggleSelector);
            for (const exitSlot of [3, 2, 1]) {
                await button(1).click();
                await settle(page);
                assert.deepEqual(await modeStates(page), [true, true, true, false]);
                await page.locator('#question-coi-2').evaluate((element, html) => {
                    element.replaceWith(new DOMParser().parseFromString(html, 'text/html').querySelector('#question-coi-2'));
                }, html);
                await settle(page);
                assert.deepEqual(await modeStates(page), [true, true, true, false], 'Memory restores only the AJAX question');
                await button(exitSlot).click();
                await settle(page);
                assert.deepEqual(await modeStates(page), [false, false, false, false]);
                assert.deepEqual(await page.evaluate(() => window.resourceCounts), resources, 'No resource accumulation');
                await page.evaluate(() => window.layout.init());
                await settle(page);
                assert.deepEqual(await modeStates(page), [false, false, false, false], 'Exit cleared in-memory flags');
            }
        } finally {
            await browser.close();
        }
    });
}

/** Visual canvas metrics independent of the production geometry helpers. */
const canvasMetrics = viewport => viewport.evaluate(element => {
    const track = element.querySelector('.qtype-clozeonimage-panorama-track');
    const image = element.querySelector('.qtype-clozeonimage-image').getBoundingClientRect();
    const box = element.getBoundingClientRect();
    const trackBox = track.getBoundingClientRect();
    const style = getComputedStyle(track);
    const gutter = parseFloat(getComputedStyle(element).getPropertyValue('--qtype-clozeonimage-panorama-gutter')) || 0;
    return {image: {left: image.left, right: image.right, top: image.top, bottom: image.bottom, width: image.width},
        left: box.left + element.clientLeft + gutter, right: box.left + element.clientLeft + element.clientWidth - gutter,
        top: box.top + element.clientTop, bottom: box.top + element.clientTop + element.clientHeight,
        innerWidth: element.clientWidth - 2 * gutter, gutter, range: element.scrollWidth - element.clientWidth,
        verticalRange: element.scrollHeight - element.clientHeight,
        trackTop: trackBox.top, trackHeight: trackBox.height,
        paddingTop: parseFloat(style.paddingTop), paddingBottom: parseFloat(style.paddingBottom)};
});

for (const directory of ['src', 'build']) {
    test(`${directory}: visual-union thumb endpoints preserve controls on every side`, async() => {
        const browser = await chromium.launch({headless: true, ignoreDefaultArgs: ['--hide-scrollbars']});
        try {
            for (const width of [800, 1777]) {
                const cases = {
                    inside: [[100, 100, 80, 40]],
                    equal: [[-60, 100, 80, 40], [width - 20, 100, 80, 40]],
                    left: [[-60, 100, 80, 40]],
                    right: [[width - 20, 100, 80, 40]],
                    above: [[100, -40, 80, 70]],
                    below: [[100, 580, 80, 70]],
                    'left+above': [[-60, -40, 80, 70]],
                    'right+below': [[width - 20, 580, 80, 70]],
                    'all sides': [[-60, -40, 80, 70], [width - 20, 580, 80, 70]],
                    'separate extrema': [[-60, 150, 80, 40], [100, -40, 80, 70],
                        [width - 20, 150, 80, 40], [100, 580, 80, 70]],
                    'union wider than viewport': [[-900, -40, 80, 70], [width + 900, 580, 80, 70]],
                };
                for (const [name, controls] of Object.entries(cases)) {
                    const page = await browser.newPage({viewport: {width: name === 'equal' ? width + 60 + 60 + 22 : 1440,
                        height: 1000}});
                    const errors = [];
                    page.on('pageerror', error => errors.push(error.message));
                    await page.route(/^https?:/, route => route.abort());
                    await page.setContent(fixture(width).replace(encodeURIComponent('height="340"'),
                        encodeURIComponent('height="600"')));
                    await page.addStyleTag({path: process.env.COI_THEME_CSS});
                    await page.addStyleTag({path: path.join(__dirname, '../styles.css')});
                    await page.locator('.qtype-clozeonimage-composition').evaluate((element, controls) => {
                        element.querySelectorAll('.qtype-clozeonimage-subquestion').forEach(control => control.remove());
                        controls.forEach(([left, top, width, height], index) => {
                            const control = document.createElement('div');
                            control.className = 'qtype-clozeonimage-subquestion';
                            Object.assign(control.style, {left: `${left}px`, top: `${top}px`,
                                width: `${width}px`, height: `${height}px`});
                            control.innerHTML = `<input aria-label="Answer ${index}" value="unchanged" ` +
                                'style="width:100%;height:100%;box-sizing:border-box;margin:0;outline:none;box-shadow:none">';
                            element.append(control);
                        });
                    }, controls);
                    await loadController(page, directory, 'layout');
                    await settle(page);
                    if (name === 'equal') {
                        const scrollbar = await page.evaluate(() => window.innerWidth - document.documentElement.clientWidth);
                        await page.setViewportSize({width: page.viewportSize().width + scrollbar, height: 1000});
                        await settle(page);
                    }
                    const normal = await normalMetrics(page);
                    const geometry = await allImageGeometry(page);
                    const coordinates = await page.locator('.qtype-clozeonimage-subquestion').evaluateAll(elements =>
                        elements.map(element => [element.style.left, element.style.top]));
                    await page.locator(toggleSelector).click();
                    await settle(page);
                    const viewport = page.locator(scrollSelector);
                    const metrics = await canvasMetrics(viewport);
                    assert.equal(metrics.gutter, 10, 'Full visual bounds use the 10 px gutter');
                    const minX = Math.min(0, ...controls.map(control => control[0]));
                    const maxX = Math.max(width, ...controls.map(control => control[0] + control[2]));
                    const minY = Math.min(0, ...controls.map(control => control[1]));
                    const maxY = Math.max(600, ...controls.map(control => control[1] + control[3]));
                    const unionWidth = maxX - minX;
                    const direction = unionWidth < metrics.innerWidth ? 1 : -1;
                    close(metrics.range, Math.abs(metrics.innerWidth - unionWidth), `${width}/${name}: union-based range`);
                    if (name === 'equal') {
                        close(metrics.range, 0, 'Equal union and usable width has no travel');
                    }
                    close(metrics.trackHeight, maxY - minY + metrics.paddingTop + metrics.paddingBottom,
                        `${width}/${name}: full vertical union with existing gutters`);
                    close(metrics.image.top - metrics.trackTop, metrics.paddingTop - minY, 'Negative top offsets the composition');
                    assert.equal(metrics.verticalRange, 0, 'Auto height needs no vertical scrollbar');
                    assert.deepEqual(await allImageGeometry(page), geometry);
                    const colours = await viewport.evaluate(element => [getComputedStyle(element).backgroundColor,
                        getComputedStyle(element.closest('.formulation')).backgroundColor]);
                    assert.equal(colours[0], colours[1], 'Background exactly matches Boost formulation');
                    await page.locator('.formulation').evaluate(element => {
                        element.style.backgroundColor = 'rgb(219, 232, 209)';
                    });
                    assert.deepEqual(await viewport.evaluate(element => [getComputedStyle(element).backgroundColor,
                        getComputedStyle(element.parentElement).backgroundColor]), ['rgb(219, 232, 209)', 'rgb(219, 232, 209)'],
                    'Theme changes inherit through the anchor into the breakout viewport');
                    // Drive the native scrollbar itself: zero is fully LEFT, maximum is fully RIGHT.
                    for (const fraction of [0, 1, 0.5]) {
                        await viewport.evaluate((element, fraction) => {
                            element.scrollLeft = fraction * (element.scrollWidth - element.clientWidth);
                        }, fraction);
                        await settle(page);
                        const current = await canvasMetrics(viewport);
                        const expectedLeft = metrics.left + direction * metrics.range * fraction;
                        close(current.image.left + minX, expectedLeft, 'Union follows the requested thumb position');
                        const edge = fraction === 1 ? current.image.left + maxX : current.image.left + minX;
                        close(edge, fraction === 1 ? metrics.right : expectedLeft,
                            'Thumb endpoints expose the matching visual extrema');
                        close(current.range, metrics.range, 'Scrolling never enlarges the native range');
                        close(current.image.width, width, 'Teacher-selected width never changes');
                        assert.deepEqual(await allImageGeometry(page), geometry);
                    }
                    for (const input of await page.locator('.qtype-clozeonimage-subquestion input').all()) {
                        await input.scrollIntoViewIfNeeded();
                        const current = await canvasMetrics(viewport);
                        const rect = await input.boundingBox();
                        assert.ok(rect.x >= current.left - 1 && rect.x + rect.width <= current.right + 1,
                            `${width}/${name}: control ${rect.x}..${rect.x + rect.width}; ` +
                            `gutter edges ${current.left}/${current.right}`);
                        assert.ok(rect.y >= current.top && rect.y + rect.height <= current.bottom,
                            `${name}: no top/bottom clipping`);
                        assert.equal(await input.evaluate(element => {
                            const rect = element.getBoundingClientRect();
                            return document.elementFromPoint(rect.left + rect.width / 2, rect.top + rect.height / 2) === element;
                        }), true, 'Visible control is directly hit-testable');
                        await input.fill('Preserved');
                        assert.equal((await snapshot(page)).documentX, 0);
                    }
                    assert.equal((await snapshot(page)).overflow, 0);
                    assert.deepEqual(await page.locator('.qtype-clozeonimage-subquestion').evaluateAll(elements =>
                        elements.map(element => [element.style.left, element.style.top])), coordinates,
                    'No control coordinates rewritten');
                    await page.locator(toggleSelector).click();
                    await settle(page);
                    assert.deepEqual(await normalMetrics(page), normal,
                        'Normal layout returns exactly, including negative coordinates');
                    assert.deepEqual(errors, []);
                    await page.close();
                }
            }
        } finally {
            await browser.close();
        }
    });

    test(`${directory}: runtime full bounds include Clear, focus outlines and late control changes without polling`, async() => {
        const browser = await chromium.launch({headless: true});
        try {
            const page = await browser.newPage({viewport: {width: 1440, height: 1000}});
            await page.route(/^https?:/, route => route.abort());
            await page.setContent(fixture(1777));
            await page.addStyleTag({path: process.env.COI_THEME_CSS});
            await page.addStyleTag({path: path.join(__dirname, '../styles.css')});
            await instrumentLifecycle(page);
            await page.evaluate(() => {
                window.boundsFrames = 0;
                const request = window.requestAnimationFrame.bind(window);
                window.requestAnimationFrame = callback => {
                    window.boundsFrames++;
                    return request(callback);
                };
            });
            await loadController(page, directory, 'layout');
            await page.locator(toggleSelector).click();
            await settle(page);
            const resources = await page.evaluate(() => window.resourceCounts);
            await page.locator('.qtype-clozeonimage-composition').evaluate(element => {
                element.insertAdjacentHTML('beforeend', '<div id="late-control" class="qtype-clozeonimage-subquestion" ' +
                    'style="left:-90px;top:-70px;width:90px;height:50px">' +
                    '<div class="qtype-clozeonimage-feedback-region qtype-clozeonimage-state-correct" ' +
                    'style="width:90px;height:50px"><input style="width:90px;height:50px">' +
                    '<button type="button" class="qtype-clozeonimage-clear-choice">C</button></div>' +
                    '<label class="accesshide" style="left:-10000px;top:-10000px">Accessible label</label></div>');
            });
            await settle(page);
            const viewport = page.locator(scrollSelector);
            await viewport.evaluate(element => {
                element.scrollLeft = 0;
            });
            const outline = await page.locator('#late-control .qtype-clozeonimage-feedback-region').boundingBox();
            let current = await canvasMetrics(viewport);
            assert.ok(outline.x - 5 >= current.left - 1 && outline.y - 5 >= current.top,
                'Correctness outline included at negative edges');
            assert.ok(current.range < 2000, 'Offscreen accessible label does not inflate the canvas');
            await page.locator('#late-control').evaluate(element => {
                element.style.left = '1800px';
                element.style.top = '450px';
            });
            await settle(page);
            await viewport.evaluate(element => {
                element.scrollLeft = element.scrollWidth;
            });
            const clear = page.locator('#late-control .qtype-clozeonimage-clear-choice');
            await clear.focus();
            await settle(page);
            current = await canvasMetrics(viewport);
            await viewport.evaluate(element => {
                element.scrollLeft = element.scrollWidth;
            });
            const clearRect = await clear.boundingBox();
            current = await canvasMetrics(viewport);
            assert.ok(clearRect.x + clearRect.width + 4 <= current.right + 1,
                'Protruding Clear and theme focus ring are reachable');
            assert.ok(clearRect.y + clearRect.height + 4 <= current.bottom, 'Clear bottom is accommodated');
            assert.equal(current.verticalRange, 0);
            await page.locator('#late-control').evaluate(element => element.remove());
            await settle(page);
            assert.deepEqual(await page.evaluate(() => window.resourceCounts), resources,
                'Removed descendants release observation');
            const frames = await page.evaluate(() => window.boundsFrames);
            await settle(page);
            assert.equal(await page.evaluate(() => window.boundsFrames), frames, 'No polling or self-induced geometry loop');
        } finally {
            await browser.close();
        }
    });

    test(`${directory}: negative extents survive explicit, automatic and restored Wide states`, async() => {
        const browser = await chromium.launch({headless: true});
        try {
            const page = await browser.newPage({viewport: {width: 1440, height: 1000}});
            const html = coordinatedFixture(501, false, [1777, 1600, 500])
                .replaceAll('left:40px;top:30px', 'left:-60px;top:-40px');
            await page.route(/^https?:/, route => {
                const url = new URL(route.request().url());
                return url.hostname === 'coi.test' ? route.fulfill({contentType: 'text/html',
                    body: url.searchParams.has('away') ? '<!doctype html><html><body>Another page</body></html>' : html}) :
                    route.abort();
            });
            const initialise = async() => {
                await page.addStyleTag({path: process.env.COI_THEME_CSS});
                await page.addStyleTag({path: path.join(__dirname, '../styles.css')});
                await loadController(page, directory, 'layout');
                await settle(page);
            };
            const check = async() => {
                assert.deepEqual(await modeStates(page), [true, true, false]);
                for (const question of await page.locator(`.${activeClass}`).all()) {
                    const viewport = question.locator(scrollSelector);
                    await viewport.evaluate(element => {
                        element.scrollLeft = 0;
                    });
                    const metrics = await canvasMetrics(viewport);
                    const control = await question.locator('.qtype-clozeonimage-subquestion').first().boundingBox();
                    assert.ok(control.x >= metrics.left - 1 && control.y >= metrics.top, 'Restored negative control is visible');
                    close(control.x - metrics.image.left, -60, 'Negative X remains teacher-defined');
                    close(control.y - metrics.image.top, -40, 'Negative Y remains teacher-defined');
                    assert.equal(metrics.verticalRange, 0);
                }
            };
            await page.goto('https://coi.test/mod/quiz/attempt.php?attempt=501');
            await initialise();
            const original = await allImageGeometry(page);
            await page.locator(toggleSelector).first().click();
            await settle(page);
            await check();
            const flags = await storedFlags(page, 501, [1, 2, 3]);
            assert.deepEqual(flags, ['1', '1', null]);
            for (const action of ['Check', 'Try again']) {
                await page.locator('#question-coi-1').getByRole('button', {name: action, exact: true}).click();
                await page.locator('#question-coi-1').evaluate((element, html) => {
                    element.replaceWith(new DOMParser().parseFromString(html, 'text/html').querySelector('#question-coi-1'));
                }, html);
                await settle(page);
                await check();
            }
            await page.reload();
            await initialise();
            await check();
            await page.goto('https://coi.test/mod/quiz/attempt.php?attempt=501&away=1');
            await initialise();
            await page.goto('https://coi.test/mod/quiz/attempt.php?attempt=501');
            await initialise();
            await check();
            assert.deepEqual(await allImageGeometry(page), original);
            assert.deepEqual(await storedFlags(page, 501, [1, 2, 3]), flags);
            await page.locator(toggleSelector).nth(1).click();
            await settle(page);
            assert.deepEqual(await modeStates(page), [false, false, false], 'Page-wide Exit clears both questions');
            assert.deepEqual(await allImageGeometry(page), original);
        } finally {
            await browser.close();
        }
    });
}

for (const directory of ['src', 'build']) {
    test(`${directory}: split border paints only breakout wings and follows drawers, resize and zoom geometry`, async() => {
        const browser = await chromium.launch({headless: true, ignoreDefaultArgs: ['--hide-scrollbars']});
        try {
            const page = await browser.newPage({viewport: {width: 1440, height: 1200}});
            await page.route(/^https?:/, route => route.abort());
            await page.setContent(fixture(800));
            await page.addStyleTag({path: process.env.COI_THEME_CSS});
            await page.addStyleTag({path: path.join(__dirname, '../styles.css')});
            await page.locator('.formulation').evaluate(element => {
                // Contrasting theme border makes the actual painted pixels independently testable.
                element.style.borderColor = 'rgb(240, 0, 0)';
                element.style.backgroundColor = 'rgb(220, 240, 230)';
                element.querySelector('.qtype-clozeonimage-subquestion').style.left = '-60px';
                element.querySelector('.qtype-clozeonimage-subquestion').style.top = '-40px';
            });
            await loadController(page, directory, 'layout');
            await settle(page);
            const geometry = await allImageGeometry(page);
            await page.locator(toggleSelector).click();
            for (const [width, left, right] of [[1440, false, false], [1440, true, false], [1440, false, true],
                [1440, true, true], [1800, false, false], [1152, false, false], [700, false, false]]) {
                await page.setViewportSize({width, height: 1200});
                await page.evaluate(({left, right}) => {
                    document.querySelector('.drawer-left').classList.toggle('show', left);
                    document.querySelector('.drawer-right').classList.toggle('show', right);
                    document.getElementById('page').classList.toggle('show-drawer-left', left);
                    document.getElementById('page').classList.toggle('show-drawer-right', right);
                    window.scrollTo(0, 0);
                }, {left, right});
                await settle(page);
                const metrics = await page.locator(scrollSelector).evaluate(viewport => {
                    const anchor = viewport.parentElement;
                    const v = viewport.getBoundingClientRect();
                    const f = viewport.closest('.formulation').getBoundingClientRect();
                    const style = getComputedStyle(viewport);
                    const before = getComputedStyle(anchor, '::before');
                    const after = getComputedStyle(anchor, '::after');
                    const formulation = getComputedStyle(viewport.closest('.formulation'));
                    const rect = box => ({left: box.left, right: box.right, top: box.top, bottom: box.bottom});
                    return {v: rect(v), f: rect(f),
                        leftWing: parseFloat(anchor.style.getPropertyValue('--panorama-left-wing')),
                        rightWing: parseFloat(anchor.style.getPropertyValue('--panorama-right-wing')),
                        transparent: [style.borderTopColor, style.borderBottomColor],
                        background: [style.backgroundColor, formulation.backgroundColor],
                        borders: [before.borderTop, after.borderBottom, formulation.borderTop],
                        radius: [before.borderTopLeftRadius, after.borderTopRightRadius, formulation.borderTopLeftRadius],
                        position: [before.position, after.position, before.pointerEvents, after.pointerEvents]};
                });
                close(metrics.leftWing, Math.max(0, Math.min(metrics.v.right, metrics.f.left) - metrics.v.left), 'Left wing');
                close(metrics.rightWing, Math.max(0, metrics.v.right - Math.max(metrics.v.left, metrics.f.right)), 'Right wing');
                assert.deepEqual(metrics.transparent, ['rgba(0, 0, 0, 0)', 'rgba(0, 0, 0, 0)']);
                assert.equal(metrics.background[0], metrics.background[1]);
                assert.equal(new Set(metrics.borders).size, 1, 'Wing borders follow formulation color and thickness');
                assert.equal(new Set(metrics.radius).size, 1, 'Outer radius matches formulation');
                assert.deepEqual(metrics.position, ['absolute', 'absolute', 'none', 'none']);
                // Inspect rasterized border pixels, not just CSS declarations or geometry variables.
                const screenshot = (await page.screenshot()).toString('base64');
                const painted = await page.evaluate(async({screenshot, metrics}) => {
                    const image = new Image();
                    image.src = `data:image/png;base64,${screenshot}`;
                    await image.decode();
                    const canvas = document.createElement('canvas');
                    canvas.width = image.width;
                    canvas.height = image.height;
                    const context = canvas.getContext('2d');
                    context.drawImage(image, 0, 0);
                    const redAt = (x, y) => {
                        for (let row = Math.floor(y) - 1; row <= Math.ceil(y) + 1; row++) {
                            const [r, g, b] = context.getImageData(Math.floor(x), row, 1, 1).data;
                            if (r > g + 80 && r > b + 80) {
                                return true;
                            }
                        }
                        return false;
                    };
                    const {v, f, leftWing, rightWing} = metrics;
                    const samples = [{name: 'center', x: (Math.max(v.left, f.left) + Math.min(v.right, f.right)) / 2,
                        expected: false}];
                    if (leftWing > 12) {
                        samples.push({name: 'left wing', x: v.left + leftWing / 2, expected: true});
                    }
                    if (rightWing > 12) {
                        samples.push({name: 'right wing', x: v.right - rightWing / 2, expected: true});
                    }
                    return samples.map(sample => ({...sample, top: redAt(sample.x, v.top), bottom: redAt(sample.x, v.bottom)}));
                }, {screenshot, metrics});
                for (const sample of painted) {
                    assert.equal(sample.top, sample.expected, `${width}/${left}/${right}: ${sample.name} top border`);
                    assert.equal(sample.bottom, sample.expected, `${width}/${left}/${right}: ${sample.name} bottom border`);
                }
                assert.deepEqual(await allImageGeometry(page), geometry, 'Decorative split cannot move controls or scale images');
                for (const selector of ['.qtype-clozeonimage-panorama-track', '.qtype-clozeonimage-composition', imageSelector]) {
                    assert.equal(await page.locator(selector).evaluate(element => getComputedStyle(element).borderWidth), '0px');
                }
                assert.equal((await snapshot(page)).overflow, 0);
                assert.equal((await canvasMetrics(page.locator(scrollSelector))).verticalRange, 0);
            }
        } finally {
            await browser.close();
        }
    });
}
