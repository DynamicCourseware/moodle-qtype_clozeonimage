// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Scope of the desktop answer-focus drawer guard. Real mouse ordering is tested in Moodle.
 *
 * @copyright 2026 DynamicCourseware.org (Dominique Bauer)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/* eslint-env node */
const assert = require('node:assert/strict');
const {readFileSync} = require('node:fs');
const {test} = require('node:test');
const path = require('node:path');
const {JSDOM} = require('jsdom');

for (const directory of ['src', 'build']) {
    for (const [name, overrides, expected] of [
        ['desktop false-small answer focus', {}, true],
        ['actual narrow viewport', {desktop: false}, false],
        ['no horizontal document scroll', {scrollX: 0}, false],
        ['core correctly identifies desktop', {small: false}, false],
        ['teacher positioning preview', {preview: true}, false],
        ['unrelated input', {outside: true}, false],
        ['explicit close after focus dispatch', {later: true}, false],
        ['already canceled by another listener', {canceled: true}, false],
    ]) {
        test(`${directory}: drawer guard: ${name}`, () => {
            const settings = {desktop: true, small: true, scrollX: 2000, ...overrides};
            const dom = new JSDOM('<body></body>', {runScripts: 'outside-only'});
            const {window} = dom;
            window.scrollX = settings.scrollX;
            window.matchMedia = () => ({matches: settings.desktop});
            const completed = [];
            let layout;
            window.define = (...args) => {
                layout = args.at(-1)({isSmall: () => settings.small}, {complete: key => completed.push(key)});
            };
            window.eval(readFileSync(path.join(__dirname, '../amd', directory,
                directory === 'src' ? 'layout.js' : 'layout.min.js'), 'utf8'));
            // Initialise delegation before adding the fixture: geometry is deliberately not simulated.
            layout.init();
            window.document.body.innerHTML = `<div >
                <div class="${settings.outside ? '' : settings.preview ? 'qtype-clozeonimage-preview-composition' :
                    'qtype-clozeonimage-composition'}"><input id="answer"></div></div>`;
            let hide;
            const dispatchHide = () => {
                hide = new window.CustomEvent('theme_boost/drawers:hide', {
                    bubbles: true, cancelable: true, detail: {drawerInstance: {}},
                });
                if (settings.canceled) {
                    hide.preventDefault();
                }
                window.document.dispatchEvent(hide);
            };
            window.document.addEventListener('focusin', () => {
                if (!settings.later) {
                    dispatchHide();
                }
            });
            const input = window.document.getElementById('answer');
            input.focus();
            if (settings.later) {
                dispatchHide();
            }
            assert.equal(hide.defaultPrevented, expected || !!settings.canceled);
            assert.deepEqual(completed, expected ? ['theme_boost/drawers:close'] : [],
                'Complete only the operation canceled by this guard');
            assert.equal(window.document.activeElement, input, 'The guard never blurs or refocuses');
            dom.window.close();
        });
    }
}
