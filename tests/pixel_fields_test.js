// This file is part of Moodle - https://moodle.org/
// Moodle is free software under the GNU GPL v3 or later.
/**
 * Lossless geometry presentation and actual input event routing.
 * @copyright 2026 DynamicCourseware.org (Dominique Bauer)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/* eslint-env node */
const {test} = require('node:test');
const assert = require('node:assert/strict');
const {readFileSync} = require('node:fs');
const {JSDOM} = require('jsdom');
const path = require('node:path');
for (const directory of ['src', 'build']) {
    test(`${directory}: internal coordinates never come from rounded CSS serialization`, () => {
        const dom = new JSDOM('<form class="mform" data-qtype="clozeonimage">' +
            '<input name="xleft[0]" value="123.45678901"><input name="ytop[0]" value="-123.45678901">' +
            '<div data-row-index="0" style="left:123.457px;top:-123.457px"></div></form>', {runScripts: 'outside-only'});
        let form;
        dom.window.define = (...args) => { form = args.at(-1)(); };
        dom.window.eval(readFileSync(path.join(__dirname, '../amd', directory,
            directory === 'src' ? 'form.js' : 'form.min.js'), 'utf8'));
        const position = form.precisePosition(dom.window.document.querySelector('div'));
        assert.equal(position.x, 123.45678901);
        assert.equal(position.y, -123.45678901);
        dom.window.close();
    });
    test(`${directory}: precise geometry, integer display, editing, invalid decimal and reordered identity`, () => {
        const dom = new JSDOM('<form data-qtype="clozeonimage">' +
            '<input type="hidden" name="xleft[0]" data-pixel-value="1" value="123.456">' +
            '<input name="xleft_px[0]" value="123" data-pixel-error="Whole pixels required">' +
            '</form>', {runScripts: 'outside-only'});
        const {window} = dom;
        let module;
        window.define = (...args) => { module = args.at(-1)(); };
        window.eval(readFileSync(path.join(__dirname, '../amd', directory,
            directory === 'src' ? 'pixel_fields.js' : 'pixel_fields.min.js'), 'utf8'));
        module.init();
        const [precise, display] = window.document.querySelectorAll('input');
        assert.equal(precise.value, '123.456');
        assert.equal(display.value, '123');
        for (const [value, expected] of [[-123.5, '-124'], [-.49, '0'], [.5, '1'], [12345.6, '12346']]) {
            precise.value = value;
            assert.equal(display.value, expected);
            assert.equal(Number(precise.value), value);
        }
        const events = [];
        precise.addEventListener('change', () => events.push(precise.value));
        display.value = '124';
        display.dispatchEvent(new window.Event('change', {bubbles: true}));
        assert.equal(precise.value, '124');
        display.value = '124.5';
        display.dispatchEvent(new window.Event('input', {bubbles: true}));
        assert.equal(precise.value, '124');
        assert.equal(display.validationMessage, 'Whole pixels required');
        display.value = '124';
        display.dispatchEvent(new window.Event('input', {bubbles: true}));
        precise.name = 'xleft[3]'; display.name = 'xleft_px[3]';
        precise.value = -15.75;
        assert.equal(display.value, '-16');
        display.value = '-17';
        display.dispatchEvent(new window.Event('change', {bubbles: true}));
        assert.equal(precise.value, '-17');
        assert.deepEqual(events, ['124', '-17']);
        dom.window.close();
    });
}
for (const directory of ['src', 'build']) {
    test(`${directory}: empty fields restore on blur, Enter and submit without mutating precise values`, () => {
        const dom = new JSDOM('<form data-qtype="clozeonimage">' +
            '<input type="hidden" name="xleft[0]" data-pixel-value="1" value="-123.456">' +
            '<input name="xleft_px[0]" value="-123" data-pixel-error="Integer required">' +
            '<input type="hidden" name="blocklayer_current[0]" value="-2345">' +
            '<div class="qtype-clozeonimage-layer"><input name="blocklayer[0]" value="-2345"></div>' +
            '</form>', {runScripts: 'outside-only'});
        const w = dom.window;
        let module;
        w.define = (...args) => { module = args.at(-1)(); };
        w.eval(readFileSync(path.join(__dirname, '../amd', directory,
            directory === 'src' ? 'pixel_fields.js' : 'pixel_fields.min.js'), 'utf8'));
        module.init();
        const [precise, display, last, layer] = w.document.querySelectorAll('input');
        let changes = 0;
        precise.addEventListener('change', () => changes++);
        const empty = input => { input.value = ''; input.dispatchEvent(new w.Event('input', {bubbles: true})); };
        for (const type of ['blur', 'keydown', 'submit']) {
            empty(display); empty(layer);
            assert.equal(display.value, ''); assert.equal(layer.value, '');
            assert.equal(precise.value, '-123.456');
            if (type === 'submit') {
                w.document.querySelector('form').dispatchEvent(new w.Event('submit'));
            } else {
                for (const e of [display, layer]) {
                    e.dispatchEvent(type === 'keydown' ? new w.KeyboardEvent(type, {key: 'Enter'}) : new w.Event(type));
                }
            }
            assert.equal(display.value, '-123'); assert.equal(layer.value, '-2345');
            assert.equal(precise.value, '-123.456'); assert.equal(changes, 0);
        }
        empty(display);
        precise.value = -987.654; // A precise geometry update while typing must not refill the editor.
        assert.equal(display.value, '');
        display.dispatchEvent(new w.Event('blur'));
        assert.equal(display.value, '-988'); assert.equal(precise.value, '-987.654');
        layer.value = '-6789'; layer.dispatchEvent(new w.Event('input'));
        assert.equal(last.value, '-6789'); empty(layer); layer.dispatchEvent(new w.Event('blur'));
        assert.equal(layer.value, '-6789');
        empty(display); display.value = '-124'; display.dispatchEvent(new w.Event('change'));
        assert.equal(precise.value, '-124'); assert.equal(changes, 1);
        w.close();
    });
}
