// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Positioning cursor structure, not browser pixels or drag behaviour.
 * Run with: node tests/preview_cursor_test.js
 *
 * @copyright  2026 DynamicCourseware.org (Dominique Bauer)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/* eslint-env node */

const assert = require('node:assert/strict');
const {readFileSync} = require('node:fs');
const path = require('node:path');
const {test} = require('node:test');
const {JSDOM} = require('jsdom');
const postcss = require('postcss');
const css = postcss.parse(readFileSync(path.join(__dirname, '..', 'styles.css'), 'utf8'));
const descendantSelector = '#qtype-clozeonimage-preview-composition .qtype-clozeonimage-preview-subquestion *';
const families = [
    'SHORTANSWER', 'SHORTANSWER_C', 'NUMERICAL', 'MULTICHOICE', 'MULTICHOICE_S',
    'MULTICHOICE_V', 'MULTICHOICE_VS', 'MULTICHOICE_H', 'MULTICHOICE_HS',
    'MULTIRESPONSE', 'MULTIRESPONSE_S', 'MULTIRESPONSE_H', 'MULTIRESPONSE_HS',
];

for (const family of families) {
    test(`${family}: preview descendants inherit distinct movement and resize cursors`, () => {
        let surface = '<input class="form-control" disabled>';
        if (['MULTICHOICE', 'MULTICHOICE_S'].includes(family)) {
            surface = '<select class="form-select" disabled><option>Choice</option></select>';
        } else if (family.startsWith('MULTI')) {
            const type = family.startsWith('MULTIRESPONSE') ? 'checkbox' : 'radio';
            surface = `<fieldset class="answer"><legend>Answer</legend><div class="form-check">
                <input type="${type}" class="form-check-input" disabled>
                <label class="form-check-label"><span>Choice</span></label>
            </div></fieldset>`;
        }
        const dom = new JSDOM(`<div id="qtype-clozeonimage-preview-composition">
            <div class="qtype-clozeonimage-preview-subquestion">
                <div class="qtype-clozeonimage-preview-control">${surface}
                    <span class="qtype-clozeonimage-preview-number">1</span>
                </div>
            </div>
            <img><button>Unrelated preview button</button>
        </div>
        <form><input><select></select><label>Form field</label><button>Validate and update preview</button></form>
        <div class="que clozeonimage"><div class="qtype-clozeonimage-subquestion">${surface}</div></div>`);
        try {
            const rule = css.nodes.find(node => node.selector === descendantSelector);
            assert.ok(rule, 'ID-scoped inheritance overrides Bootstrap disabled-label specificity without !important');
            assert.deepEqual(rule.nodes.filter(node => node.type === 'decl').map(node => [node.prop, node.value, !!node.important]),
                [['cursor', 'inherit', false]], 'No hit-area, geometry or interaction properties');
            const document = dom.window.document;
            const wrapper = document.querySelector('.qtype-clozeonimage-preview-subquestion');
            for (const state of ['', 'dragging', 'resizing']) {
                wrapper.classList.toggle('qtype-clozeonimage-preview-dragging', state === 'dragging');
                wrapper.classList.toggle('qtype-clozeonimage-preview-resizing', state === 'resizing');
                let cursor;
                css.walkRules(candidate => {
                    if (candidate.nodes.some(node => node.prop === 'cursor') && wrapper.matches(candidate.selector)) {
                        candidate.walkDecls('cursor', declaration => {
                            cursor = declaration.value;
                        });
                    }
                });
                assert.equal(cursor, {dragging: 'grabbing', resizing: 'ew-resize', '': 'grab'}[state]);
                for (const element of document.querySelectorAll('*')) {
                    assert.equal(element.matches(descendantSelector), element !== wrapper && wrapper.contains(element));
                }
            }
        } finally {
            dom.window.close();
        }
    });
}

test('gesture cleanup handles release, cancellation and lost capture without moving to invalid coordinates', () => {
    const source = readFileSync(path.join(__dirname, '..', 'amd', 'src', 'form.js'), 'utf8');
    const start = source.indexOf('const initialiseDragging = () =>');
    const end = source.indexOf('\n    /**', start);
    for (const resizing of [false, true]) {
        for (const ending of ['pointerup', 'pointercancel', 'lostpointercapture']) {
            const dom = new JSDOM('<div class="qtype-clozeonimage-preview-subquestion" data-row-index="0">' +
                '<span class="qtype-clozeonimage-resize"></span></div>');
            try {
                const control = dom.window.document.querySelector('div');
                let captured = false;
                control.setPointerCapture = () => { captured = true; };
                control.hasPointerCapture = () => captured;
                control.releasePointerCapture = () => { captured = false; };
                let updates = 0;
                const initialise = new Function('document', '$', 'setBlockWidth', 'normalisePosition', 'setPosition',
                    'formSelector', 'precisePosition', source.slice(start, end) + '\nreturn initialiseDragging;');
                initialise(dom.window.document, () => ({trigger: () => undefined}), () => { updates++; },
                    (x, y) => { updates++; return {x, y}; }, () => undefined, 'form', () => ({x: 0, y: 0}))();
                const target = resizing ? control.firstChild : control;
                target.dispatchEvent(new dom.window.MouseEvent('pointerdown', {bubbles: true, button: 0}));
                assert.equal(captured, true);
                assert.equal(control.classList.contains('qtype-clozeonimage-preview-resizing'), resizing);
                assert.equal(control.classList.contains('qtype-clozeonimage-preview-dragging'), !resizing);
                control.dispatchEvent(new dom.window.MouseEvent('pointermove', {clientX: 100, clientY: 50}));
                assert.equal(updates, 1);
                control.dispatchEvent(new dom.window.MouseEvent(ending));
                assert.equal(updates, ending === 'pointerup' ? 2 : 1);
                assert.equal(captured, false);
                assert.equal(control.classList.contains('qtype-clozeonimage-preview-resizing'), false);
                assert.equal(control.classList.contains('qtype-clozeonimage-preview-dragging'), false);
                const after = updates;
                control.dispatchEvent(new dom.window.MouseEvent('pointermove'));
                assert.equal(updates, after, 'No gesture listener remains after cleanup');
            } finally {
                dom.window.close();
            }
        }
    }
});

test('cursor changes leave pointer hit testing intact', () => {
    const inputs = css.nodes.find(node => node.selector ===
        '.qtype-clozeonimage-preview-control input,\n.qtype-clozeonimage-preview-control select');
    assert.equal(inputs.nodes.find(node => node.prop === 'pointer-events').value, 'none');
});

test('preview selection moves a fixed group by one rounded delta and never resizes its neighbours', () => {
    const source = readFileSync(path.join(__dirname, '..', 'amd', 'src', 'form.js'), 'utf8');
    const start = source.indexOf('const initialiseDragging = () =>');
    const end = source.indexOf('\n    /**', start);
    const dom = new JSDOM('<div id="qtype-clozeonimage-preview-area"><img>' + [0, 1, 2].map(i =>
        `<div class="qtype-clozeonimage-preview-subquestion" data-row-index="${i}" ` +
        `style="left:${i * 100 - 50}px;top:${i * 30 - 20}px"><span class="qtype-clozeonimage-resize"></span></div>`
    ).join('') + '</div>');
    try {
        const document = dom.window.document;
        const blocks = [...document.querySelectorAll('.qtype-clozeonimage-preview-subquestion')];
        let resized = null;
        blocks.forEach(block => {
            let captured = false;
            block.setPointerCapture = () => { captured = true; };
            block.hasPointerCapture = () => captured;
            block.releasePointerCapture = () => { captured = false; };
            Object.defineProperty(block, 'offsetLeft', {get: () => parseFloat(block.style.left)});
            Object.defineProperty(block, 'offsetTop', {get: () => parseFloat(block.style.top)});
        });
        const initialise = new Function('document', '$', 'setBlockWidth', 'normalisePosition', 'setPosition',
            'formSelector', 'precisePosition', source.slice(start, end) + '\nreturn initialiseDragging;');
        initialise(document, () => ({trigger: () => undefined}), block => { resized = block; },
            (x, y) => ({x: Math.round(x), y: Math.round(y)}), (block, x, y) => {
                block.style.left = `${x}px`;
                block.style.top = `${y}px`;
            }, 'form', block => ({x: parseFloat(block.style.left) || 0, y: parseFloat(block.style.top) || 0}))();
        const send = (target, type, init = {}) => target.dispatchEvent(
            new dom.window.MouseEvent(type, {bubbles: true, button: 0, ...init}));
        const selected = () => blocks.map(block => block.classList.contains('qtype-clozeonimage-preview-selected'));
        const positions = () => blocks.map(block => [block.offsetLeft, block.offsetTop]);
        send(blocks[0], 'pointerdown', {ctrlKey: true});
        send(blocks[1], 'pointerdown', {metaKey: true});
        assert.deepEqual(selected(), [true, true, false]);
        assert.equal(blocks[0].hasPointerCapture(), false, 'Selection alone does not start a gesture');
        send(blocks[0], 'pointerdown', {clientX: 20, clientY: 30});
        send(blocks[0], 'pointermove', {clientX: 9.6, clientY: 15.7});
        send(blocks[0], 'pointerup', {clientX: 9.6, clientY: 15.7});
        assert.deepEqual(positions(), [[-60, -34], [40, -4], [150, 40]]);
        assert.deepEqual(selected(), [true, true, false]);
        const beforeResize = positions();
        send(blocks[1].firstChild, 'pointerdown', {ctrlKey: true});
        send(blocks[1], 'pointermove', {clientX: 80});
        send(blocks[1], 'pointerup', {clientX: 80});
        assert.equal(resized, blocks[1]);
        assert.deepEqual(positions(), beforeResize);
        assert.deepEqual(selected(), [true, true, false]);
        send(blocks[0], 'pointerdown', {ctrlKey: true});
        assert.deepEqual(selected(), [false, true, false]);
        send(blocks[2], 'pointerdown');
        send(blocks[2], 'pointercancel');
        assert.deepEqual(selected(), [false, false, false]);
        send(blocks[0], 'pointerdown', {metaKey: true});
        send(document.querySelector('img'), 'pointerdown');
        assert.deepEqual(selected(), [false, false, false]);
        send(blocks[0], 'pointerdown', {ctrlKey: true});
        send(blocks[1], 'pointerdown', {ctrlKey: true});
        send(blocks[0], 'pointerdown');
        send(blocks[0], 'pointermove', {clientX: 15, clientY: 10});
        document.dispatchEvent(new dom.window.KeyboardEvent('keydown', {key: 'Escape', bubbles: true}));
        const stopped = positions();
        send(blocks[0], 'pointermove', {clientX: 900, clientY: 900});
        assert.deepEqual(positions(), stopped, 'Escape ends the gesture without a synthetic final position');
        assert.deepEqual(selected(), [false, false, false]);
        assert.equal(blocks[0].hasPointerCapture(), false);
        for (const block of blocks) {
            assert.equal(block.classList.contains('qtype-clozeonimage-preview-dragging'), false);
            assert.equal(block.lastChild.getAttribute('aria-hidden'), 'true');
        }
    } finally {
        dom.window.close();
    }
});
