// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * Interactive DOM regressions. Run with: node --test tests/interactive_test.js
 *
 * @copyright  2026 DynamicCourseware.org (Dominique Bauer)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/* eslint-env node */

const assert = require('node:assert/strict');
const {readFileSync} = require('node:fs');
const path = require('node:path');
const {test} = require('node:test');
const {runInContext} = require('node:vm');
const {JSDOM} = require('jsdom');

/**
 * Load the real AMD controllers into an isolated DOM, with only Bootstrap mocked.
 *
 * @param {String} html Question markup.
 * @param {String} directory Source or built AMD directory.
 * @returns {Object} DOM and Bootstrap mock.
 */
const setup = (html, directory) => {
    const dom = new JSDOM(`<form class="que clozeonimage">${html}</form>`, {runScripts: 'outside-only'});
    const popover = {
        showCount: 0,
        show() {
            this.showCount++;
        },
        hide() {
            // Bootstrap's presentation is outside these controller tests.
        },
        dispose() {
            // Bootstrap's presentation is outside these controller tests.
        },
    };
    const bootstrap = {Popover: {getInstance: () => popover, getOrCreateInstance: () => popover}};
    dom.window.define = (...args) => args.at(-1)(bootstrap).init();
    for (const module of ['feedback', 'clearchoice']) {
        const filename = directory === 'build' ? `${module}.min.js` : `${module}.js`;
        runInContext(readFileSync(path.join(__dirname, '..', 'amd', directory, filename), 'utf8'), dom.getInternalVMContext());
    }
    return {dom, window: dom.window, document: dom.window.document, popover};
};

/**
 * Render the radio state whose server-side prerequisites are covered in renderer_test.php.
 *
 * @param {Number} selected Initial answer, including the unanswered -1 sentinel.
 * @param {Boolean} hidden Whether Clear starts hidden.
 * @param {Boolean} locked Whether Moodle has made the group non-editable.
 * @returns {String} Local group markup.
 */
const radios = (selected, hidden, locked = false) => `
    <div class="qtype-clozeonimage-feedback-region" data-region="clozeonimage-multichoice">
        <fieldset class="answer">
            ${[0, 1, 2].map(value => `
                <input type="radio" name="answer" value="${value}" data-role="clozeonimage-multichoice-choice"
                    ${selected === value ? 'checked' : ''} ${locked ? 'disabled' : ''}>
            `).join('')}
            ${locked ? '' : `
                <input type="radio" name="answer" value="-1" data-role="clozeonimage-clear-choice-sentinel"
                    ${selected === -1 ? 'checked' : 'disabled'}>
            `}
        </fieldset>
        ${locked ? '' : `<button type="button" data-action="clozeonimage-clear-choice" ${hidden ? 'hidden' : ''}>C</button>`}
    </div>`;

for (const directory of ['src', 'build']) {
    test(`${directory}: retained correct radio requires a real change before Clear appears`, () => {
        const {dom, window, document} = setup(radios(2, true), directory);
        try {
            const choices = document.querySelectorAll('[data-role="clozeonimage-multichoice-choice"]');
            const button = document.querySelector('button');
            const form = document.querySelector('form');
            assert.equal(new window.FormData(form).get('answer'), '2');
            choices[2].dispatchEvent(new window.MouseEvent('mouseover', {bubbles: true}));
            choices[2].click();
            assert.equal(button.hidden, true);
            assert.equal(new window.FormData(form).get('answer'), '2');
            choices[1].click();
            assert.equal(button.hidden, false);
            assert.equal(new window.FormData(form).get('answer'), '1');
            choices[2].click();
            assert.equal(button.hidden, false);
            button.click();
            assert.equal(button.hidden, true);
            assert.equal(document.querySelectorAll('[data-role="clozeonimage-multichoice-choice"]:checked').length, 0);
            assert.equal(new window.FormData(form).get('answer'), '-1');
        } finally {
            dom.window.close();
        }
    });

    test(`${directory}: cleared wrong radio stays -1 until a replacement is selected`, () => {
        const {dom, window, document} = setup(radios(-1, true), directory);
        try {
            const form = document.querySelector('form');
            const button = document.querySelector('button');
            assert.equal(new window.FormData(form).get('answer'), '-1');
            assert.equal(document.querySelectorAll('[data-role="clozeonimage-multichoice-choice"]:checked').length, 0);
            document.querySelector('fieldset').dispatchEvent(new window.MouseEvent('mouseover', {bubbles: true}));
            assert.equal(button.hidden, true);
            document.querySelector('input[value="2"]').click();
            assert.equal(button.hidden, false);
            assert.equal(new window.FormData(form).get('answer'), '2');
        } finally {
            dom.window.close();
        }
    });

    test(`${directory}: first try selected radio keeps normal Clear behavior`, () => {
        const {dom, document} = setup(radios(0, false), directory);
        try {
            assert.equal(document.querySelector('button').hidden, false);
            document.querySelector('button').click();
            assert.equal(document.querySelector('button').hidden, true);
            document.querySelector('input[value="0"]').click();
            assert.equal(document.querySelector('button').hidden, false);
        } finally {
            dom.window.close();
        }
    });

    test(`${directory}: graded or otherwise locked radios cannot expose Clear`, () => {
        const {dom, window, document} = setup(radios(2, true, true), directory);
        try {
            const input = document.querySelector('input[value="1"]');
            input.click();
            input.dispatchEvent(new window.Event('change', {bubbles: true}));
            assert.equal(document.querySelector('button'), null);
            assert.equal(document.querySelector('input:checked').value, '2');
        } finally {
            dom.window.close();
        }
    });

    test(`${directory}: pending retry text blocks mouse focus but retains keyboard focus and popovers`, () => {
        const {dom, window, document, popover} = setup(`
            <span class="subquestion qtype-clozeonimage-feedback-region">
                <input type="text" class="form-control qtype-clozeonimage-feedback-trigger" value="answer" readonly
                    data-clozeonimage-awaiting-retry="true" data-bs-toggle="popover" data-bs-trigger="hover focus">
            </span>`, directory);
        try {
            const input = document.querySelector('input');
            const original = input.outerHTML;
            const down = new window.MouseEvent('mousedown', {bubbles: true, cancelable: true, button: 0});
            input.dispatchEvent(down);
            assert.equal(down.defaultPrevented, true);
            input.click();
            assert.equal(popover.showCount, 1);
            assert.equal(input.outerHTML, original);
            const tab = new window.KeyboardEvent('keydown', {key: 'Tab', bubbles: true, cancelable: true});
            input.dispatchEvent(tab);
            assert.equal(tab.defaultPrevented, false);
            input.focus();
            assert.equal(document.activeElement, input);
            assert.equal(input.readOnly, true);
            assert.equal(input.disabled, false);
            assert.equal(input.tabIndex, 0);
        } finally {
            dom.window.close();
        }
    });

    test(`${directory}: mouse focus guard excludes editable controls and final review`, () => {
        const {dom, window, document} = setup(`
            <input id="adaptive" class="form-control qtype-clozeonimage-feedback-trigger" data-bs-toggle="popover">
            <input id="retry" class="form-control">
            <input id="review" class="form-control qtype-clozeonimage-feedback-trigger" readonly data-bs-toggle="popover">
            <input id="editable" data-clozeonimage-awaiting-retry="true">`, directory);
        try {
            for (const input of document.querySelectorAll('input')) {
                const down = new window.MouseEvent('mousedown', {bubbles: true, cancelable: true});
                input.dispatchEvent(down);
                assert.equal(down.defaultPrevented, false, input.id);
            }
        } finally {
            dom.window.close();
        }
    });
}
