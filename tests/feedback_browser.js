// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Feedback regressions with real Bootstrap and Moodle's Boost popover loader.
 * Run with COI_THEME_CSS and optionally COI_BOOST_LOADER pointing to the target Moodle installation.
 * Unlike a Bootstrap-only fixture, this includes core's focus-only initialization and delegated handlers.
 *
 * @copyright 2026 DynamicCourseware.org (Dominique Bauer)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/* eslint-env node */
const assert = require('node:assert/strict');
const {readFileSync} = require('node:fs');
const path = require('node:path');
const {test} = require('node:test');
const {chromium} = require('playwright');
const root = path.join(__dirname, '..');
const loader = readFileSync(process.env.COI_BOOST_LOADER ||
    path.resolve(__dirname, '../../../../theme/boost/amd/src/loader.js'), 'utf8');
const start = loader.indexOf('const enablePopovers =');
const end = loader.indexOf('/**\n * Enable tooltips', start);
assert.ok(start >= 0 && end > start, 'Use the actual Boost popover initialization and event handlers');
const corePopovers = loader.slice(start, end);
const activeClass = 'qtype-clozeonimage-panorama-active';
const toggle = '[data-action="clozeonimage-toggle-panorama"]';
const settle = page => page.waitForTimeout(250);
const attributes = 'data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-container="body" ' +
    'data-bs-placement="right" data-bs-content="Feedback content"';
const types = ['shortanswer', 'numerical', 'select', 'radio', 'checkbox'];
const fixture = () => {
    const controls = types.map((type, index) => {
        const id = `feedback-${type}`;
        const trigger = `id="${id}" class="qtype-clozeonimage-feedback-trigger`;
        const control = index < 2 ?
            `<input ${trigger} form-control" ${attributes} type="text" readonly value="${index ? '12' : 'answer'}">` :
            `${type === 'select' ? '<select disabled class="form-select"><option>Answer</option></select>' :
                `<label><input disabled checked type="${type}"> Answer</label>`}
            <button type="button" ${trigger} qtype-clozeonimage-review-surface" ${attributes}>
                <span class="visually-hidden">Feedback</span></button>`;
        return `<div class="qtype-clozeonimage-subquestion" style="left:60px;top:${30 + index * 70}px;width:220px">
            <span class="subquestion qtype-clozeonimage-feedback-region">${control}</span></div>`;
    }).join('');
    return `<!doctype html><html><body class="pagelayout-standard limitedwidth uses-drawers">
        <div id="page" class="drawers"><div class="main-inner"><div class="que clozeonimage" id="q-feedback">
        <div class="info">Question 1</div><div class="content"><div class="formulation">
        <button id="before" type="button">Before controls</button>
        <div class="qtype-clozeonimage-scroll" id="viewport"><div class="qtype-clozeonimage-composition">
        <img class="qtype-clozeonimage-image" style="width:800px;max-width:none;height:450px" alt=""
            src="data:image/svg+xml,${encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="800" height="450"/>')}">
        ${controls}</div></div><button type="button" id="outside">Outside controls</button>
        <button type="button" data-action="clozeonimage-toggle-panorama" data-normal-label="Framed view"
            data-panorama-label="Exit framed view" aria-controls="viewport" aria-pressed="false">Framed view</button>
        </div></div></div><button id="unrelated" ${attributes}>Unrelated core popover</button></div></div></body></html>`;
};
const loadModule = async(page, directory, name) => {
    await page.evaluate(module => {
        window.define = (...args) => {
 window[module] = args.at(-1)(window.bootstrap);
};
    }, name);
    const file = directory === 'src' ? `${name}.js` : `${name}.min.js`;
    await page.addScriptTag({path: path.join(root, 'amd', directory, file)});
    await page.evaluate(module => window[module].init(), name);
};
const setup = async(browser, directory, wide) => {
    const page = await browser.newPage({viewport: {width: 1440, height: 1000}});
    await page.route('**/*', route => route.abort());
    await page.setContent(fixture());
    await page.addStyleTag({path: process.env.COI_THEME_CSS});
    await page.addStyleTag({path: path.join(root, 'styles.css')});
    await page.addScriptTag({path: require.resolve('bootstrap/dist/js/bootstrap.bundle.js')});
    await page.evaluate(() => {
        window.Bootstrap = window.bootstrap;
        window.DefaultAllowlist = window.bootstrap.Tooltip.Default.allowList;
        // Core consults SelectorEngine only for help-dialog Tab handling; there are no help icons here.
        window.SelectorEngine = {focusableChildren: () => []};
    });
    await page.addScriptTag({content: `${corePopovers}\nenablePopovers();`});
    await loadModule(page, directory, 'feedback');
    await loadModule(page, directory, 'layout');
    if (wide) {
        await page.locator(toggle).click();
        await settle(page);
        await page.locator('.qtype-clozeonimage-scroll').evaluate(element => {
 element.scrollLeft = 0;
});
    }
    await page.mouse.move(0, 0);
    return page;
};
const visible = async(page, count, message) => assert.equal(await page.locator('.popover.show').count(), count, message);

for (const directory of ['src', 'build']) {
    for (const wide of [false, true]) {
        test(`${directory}: ${wide ? 'Wide' : 'Normal'}: review hover, Tab focus and clicks with Boost loader`, async() => {
            const browser = await chromium.launch({headless: true});
            try {
                const page = await setup(browser, directory, wide);
                assert.equal(await page.evaluate(() => {
                    const control = document.getElementById('feedback-radio');
                    const instance = window.bootstrap.Popover.getInstance(control);
                    window.feedback.init();
                    window.feedback.init();
                    return window.bootstrap.Popover.getInstance(control) === instance;
                }), true, 'Repeated initialization retains the owned instance');
                for (const type of types) {
                    const control = page.locator(`#feedback-${type}`);
                    await control.hover();
                    await settle(page);
                    await visible(page, 1, `${type}: hover opens`);
                    await settle(page);
                    await visible(page, 1, `${type}: hover stays open`);
                    await page.mouse.move(0, 0);
                    await settle(page);
                    await visible(page, 0, `${type}: pointer leave closes`);
                }
                await page.locator('#before').focus();
                for (const type of types) {
                    await page.keyboard.press('Tab');
                    await settle(page);
                    assert.equal(await page.evaluate(() => document.activeElement.id), `feedback-${type}`);
                    await visible(page, 1, `${type}: keyboard focus opens`);
                    await settle(page);
                    await visible(page, 1, `${type}: focus remains open`);
                }
                await page.keyboard.press('Tab');
                await settle(page);
                await visible(page, 0, 'Tab away closes');
                for (const type of types) {
                    await page.locator(`#feedback-${type}`).click();
                    await settle(page);
                    await visible(page, 1, `${type}: click does not flash and disappear`);
                    await page.mouse.move(0, 0);
                    await settle(page);
                    await visible(page, 1, `${type}: click pins feedback`);
                    await page.locator('#outside').click();
                    await settle(page);
                    await visible(page, 0, 'Outside click dismisses');
                }
                assert.equal(await page.locator('#unrelated').evaluate(element =>
                    window.bootstrap.Popover.getInstance(element)._config.trigger), 'focus', 'Core questions remain untouched');
                await page.close();
            } finally {
                await browser.close();
            }
        });
    }

    test(`${directory}: Boost Escape priority, focus restoration and deliberate reopening`, async() => {
        const browser = await chromium.launch({headless: true});
        try {
            const page = await setup(browser, directory, true);
            const control = page.locator('#feedback-radio');
            // Transient focus, not a pinned click: core's earlier bubble handler would hide this first.
            await control.focus();
            await settle(page);
            await visible(page, 1, 'Focus opens');
            await page.keyboard.press('Escape');
            await settle(page);
            await visible(page, 0, 'First Escape dismisses');
            assert.equal(await page.locator(`.${activeClass}`).count(), 1, 'First Escape leaves Wide active');
            await page.keyboard.press('Escape');
            await settle(page);
            assert.equal(await page.locator(`.${activeClass}`).count(), 0, 'Second Escape exits Wide');
            await visible(page, 0, 'Restored focus does not reopen feedback');
            assert.equal(await page.evaluate(() => document.activeElement.id), 'feedback-radio', 'Focus retained');
            await page.locator('#before').focus();
            await control.focus();
            await settle(page);
            await visible(page, 1, 'Deliberate later focus reopens');
            await page.keyboard.press('Escape');
            await settle(page);
            await page.mouse.move(0, 0);
            await control.hover();
            await settle(page);
            await visible(page, 1, 'Deliberate later hover reopens');
            await page.keyboard.press('Escape');
            await settle(page);
            await control.click();
            await settle(page);
            await visible(page, 1, 'Deliberate later click reopens');
            await page.close();
        } finally {
            await browser.close();
        }
    });

    test(`${directory}: radio label forwards one activation; changed checkbox still clears stale feedback`, async() => {
        const browser = await chromium.launch({headless: true});
        try {
            const page = await setup(browser, directory, false);
            await page.evaluate(attributes => {
                document.querySelector('.qtype-clozeonimage-composition').insertAdjacentHTML('beforeend',
                    `<div id="editable" class="qtype-clozeonimage-subquestion" style="left:400px;top:30px">
                    <div class="qtype-clozeonimage-feedback-region qtype-clozeonimage-feedback-trigger" ${attributes}>
                    <label id="radio-label"><input type="radio" name="answer" checked> Selected radio</label></div>
                    <div class="qtype-clozeonimage-feedback-region qtype-clozeonimage-feedback-trigger" ${attributes}>
                    <label id="checkbox-label"><input type="checkbox" checked> Selected checkbox</label></div></div>`);
                window.feedback.init();
            }, attributes);
            for (const wide of [false, true]) {
                if (wide) {
                    await page.locator(toggle).click();
                    await settle(page);
                    await page.locator('.qtype-clozeonimage-scroll').evaluate(element => {
 element.scrollLeft = 0;
});
                }
                await page.locator('#radio-label').click({position: {x: 70, y: 10}});
                await settle(page);
                await visible(page, 1, 'Label plus forwarded input click must pin once');
                await page.mouse.move(0, 0);
                await settle(page);
                await visible(page, 1, 'Selected radio feedback stays pinned');
                assert.equal(await page.locator('#radio-label input').isChecked(), true);
                await page.locator('#outside').click();
                await settle(page);
            }
            await page.locator('#checkbox-label').click({position: {x: 70, y: 10}});
            await settle(page);
            assert.equal(await page.locator('#checkbox-label input').isChecked(), false, 'Native answer change remains intact');
            assert.equal(await page.locator('#checkbox-label').evaluate(element =>
                element.parentElement.hasAttribute('data-bs-toggle')), false,
            'Changing an answer intentionally clears stale feedback');
            await visible(page, 0, 'No stale popup after answer change');
            await page.close();
        } finally {
            await browser.close();
        }
    });
}
