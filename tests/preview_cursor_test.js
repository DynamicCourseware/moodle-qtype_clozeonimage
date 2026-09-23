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
    test(`${family}: every preview descendant inherits grab and the existing grabbing state`, () => {
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
        <form><input><select></select><label>Form field</label><button>Update preview</button></form>
        <div class="que clozeonimage"><div class="qtype-clozeonimage-subquestion">${surface}</div></div>`);
        try {
            const rule = css.nodes.find(node => node.selector === descendantSelector);
            assert.ok(rule, 'ID-scoped inheritance overrides Bootstrap disabled-label specificity without !important');
            assert.deepEqual(rule.nodes.filter(node => node.type === 'decl').map(node => [node.prop, node.value, !!node.important]),
                [['cursor', 'inherit', false]], 'No hit-area, geometry or interaction properties');
            const document = dom.window.document;
            const wrapper = document.querySelector('.qtype-clozeonimage-preview-subquestion');
            for (const active of [false, true]) {
                wrapper.classList.toggle('qtype-clozeonimage-preview-dragging', active);
                let cursor;
                css.walkRules(candidate => {
                    if (candidate.nodes.some(node => node.prop === 'cursor') && wrapper.matches(candidate.selector)) {
                        candidate.walkDecls('cursor', declaration => {
                            cursor = declaration.value;
                        });
                    }
                });
                assert.equal(cursor, active ? 'grabbing' : 'grab');
                for (const element of document.querySelectorAll('*')) {
                    assert.equal(element.matches(descendantSelector), element !== wrapper && wrapper.contains(element));
                }
            }
        } finally {
            dom.window.close();
        }
    });
}

test('cursor inheritance uses the existing drag class and leaves pointer hit testing intact', () => {
    const source = readFileSync(path.join(__dirname, '..', 'amd', 'src', 'form.js'), 'utf8');
    assert.ok(source.includes("control.classList.add('qtype-clozeonimage-preview-dragging')"));
    assert.ok(source.includes("control.classList.remove('qtype-clozeonimage-preview-dragging')"));
    const inputs = css.nodes.find(node => node.selector ===
        '.qtype-clozeonimage-preview-control input,\n.qtype-clozeonimage-preview-control select');
    assert.equal(inputs.nodes.find(node => node.prop === 'pointer-events').value, 'none');
});
