// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Structural appearance regressions. Run with: node tests/appearance_test.js
 *
 * These verify plugin selectors and inherited colour tokens, not browser pixels or Bootstrap's cascade.
 * Actual rendered family markup and composition classes are covered by the PHP renderer/form tests.
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
const families = {
    SHORTANSWER: 'input', SHORTANSWER_C: 'input', NUMERICAL: 'input',
    MULTICHOICE: 'select', MULTICHOICE_S: 'select',
    MULTICHOICE_V: 'fieldset', MULTICHOICE_VS: 'fieldset',
    MULTICHOICE_H: 'fieldset', MULTICHOICE_HS: 'fieldset',
    MULTIRESPONSE: 'div', MULTIRESPONSE_S: 'div', MULTIRESPONSE_H: 'table', MULTIRESPONSE_HS: 'table',
};
const states = {correct: '168, 240, 168', partiallycorrect: '240, 240, 168', incorrect: '255, 184, 184'};

/**
 * Collect matching plugin declarations in source order for the structural fixtures below.
 *
 * @param {Element} element Fixture element.
 * @returns {Object} Property values (no full browser cascade emulation).
 */
const declarations = element => {
    const values = {};
    css.walkRules(rule => {
        // Skip pseudo-elements and media rules, which are checked separately or concern form geometry.
        if (rule.parent.type !== 'root' ||
                !rule.nodes.some(node => node.prop === 'background-color' || node.prop?.startsWith('--'))) {
            return;
        }
        const selectors = rule.selectors.filter(selector => !selector.includes('::'));
        if (selectors.length && element.matches(selectors.join(', '))) {
            rule.walkDecls(decl => {
                values[decl.prop] = decl.value;
            });
        }
    });
    return values;
};

/**
 * Expected palette colour for the selected appearance.
 *
 * @param {String} rgb Colour channels.
 * @param {String} appearance Selected appearance.
 * @returns {String} Normalised CSS colour.
 */
const expectedColour = (rgb, appearance) => appearance === 'opaque' ? `rgb(${rgb})` : `rgba(${rgb}, 0.78)`;

for (const [family, tag] of Object.entries(families)) {
    for (const appearance of ['opaque', 'translucent']) {
        test(`${family}: ${appearance} preview, editable and readonly backgrounds in every result state`, () => {
            for (const phase of ['preview', 'editable', 'readonly']) {
                for (const state of ['', ...Object.keys(states)]) {
                    const preview = phase === 'preview';
                    const locked = phase !== 'editable';
                    const scalar = ['input', 'select'].includes(tag);
                    const renderedTag = preview && !scalar ? 'fieldset' : tag;
                    const controlClass = {input: 'form-control', select: 'form-select'}[tag] || 'answer';
                    let attributes = '';
                    if (locked) {
                        attributes = tag === 'input' && !preview ? 'readonly' : 'disabled';
                    }
                    const choice = `<input type="${family.startsWith('MULTIRESPONSE') ? 'checkbox' : 'radio'}"
                        class="form-check-input" ${locked ? 'disabled' : ''}>`;
                    const contents = renderedTag === 'table' ? `<tbody><tr><td>${choice}</td></tr></tbody>` : choice;
                    const prefix = preview ? 'preview-' : '';
                    const dom = new JSDOM(`<div class="que clozeonimage">
                        <div class="qtype-clozeonimage-${prefix}composition qtype-clozeonimage-appearance-${appearance}">
                            <div class="qtype-clozeonimage-${prefix}subquestion">
                                <span class="qtype-clozeonimage-feedback-region subquestion">
                                    <${renderedTag} id="surface" class="${controlClass} ${preview ? '' : state}" ${attributes}>
                                        ${scalar ? '' : contents}
                                    ${renderedTag === 'input' ? '' : `</${renderedTag}>`}
                                </span>
                            </div>
                        </div>
                    </div>`);
                    try {
                        const composition = dom.window.document.querySelector(`.qtype-clozeonimage-${prefix}composition`);
                        const tokens = declarations(composition);
                        const resolve = value => value.replace(/var\((--[\w-]+)\)/g, (_, key) => {
                            assert.ok(tokens[key], `Missing inherited token ${key}`);
                            return resolve(tokens[key]);
                        });
                        const surface = dom.window.document.getElementById('surface');
                        const background = declarations(surface)['background-color'];
                        assert.ok(background, `${phase} ${state}: surface has an appearance rule`);
                        const colour = resolve(background);
                        // Text/select result fills use semantic colours. Choice panels remain neutral;
                        // their selected-row pseudo-elements carry the semantic colour separately.
                        const rgb = !preview && scalar && state ? states[state] : '245, 245, 245';
                        const expected = expectedColour(rgb, appearance);
                        const probe = dom.window.document.createElement('div');
                        probe.style.backgroundColor = colour;
                        assert.equal(probe.style.backgroundColor, expected, `${phase} ${state}`);
                        for (const [result, rgbValue] of Object.entries(states)) {
                            const token = result.replace('partiallycorrect', 'partial');
                            probe.style.backgroundColor = resolve(tokens[`--qtype-clozeonimage-${token}-bg`]);
                            assert.equal(probe.style.backgroundColor,
                                expectedColour(rgbValue, appearance));
                        }
                    } finally {
                        dom.window.close();
                    }
                }
            }
        });
    }
}

test('choice result layers consume appearance tokens; review overlays and popovers retain their presentation', () => {
    for (const [state, token] of [['correct', 'correct'], ['incorrect', 'incorrect'], ['partiallycorrect', 'partial']]) {
        const rule = css.nodes.find(node => node.selector?.includes(`.qtype-clozeonimage-choice.${state}::before`));
        assert.equal(rule.nodes.find(node => node.prop === 'background-color').value,
            `var(--qtype-clozeonimage-${token}-bg)`);
    }
    const overlay = css.nodes.find(node => node.selector?.endsWith(' .qtype-clozeonimage-review-surface'));
    assert.equal(overlay.nodes.find(node => node.prop === 'background-color').value, 'transparent');
    const popover = css.nodes.find(node => node.selector?.includes('.popover.qtype-clozeonimage-appearance-opaque'));
    assert.equal(popover.nodes.find(node => node.prop === '--qtype-clozeonimage-popover-bg').value,
        'rgba(247, 247, 247, 0.94)');
});

for (const appearance of ['opaque', 'translucent']) {
    test(`Clear C: ${appearance} neutral background is independent of the radio result`, () => {
        const dom = new JSDOM(`<div class="que clozeonimage">
            <div class="qtype-clozeonimage-composition qtype-clozeonimage-appearance-${appearance}">
                <div class="qtype-clozeonimage-subquestion">
                    <div class="qtype-clozeonimage-feedback-region" data-region="clozeonimage-multichoice">
                        <fieldset class="answer"><input type="radio" class="form-check-input"></fieldset>
                        <button type="button" class="qtype-clozeonimage-clear-choice"
                            data-action="clozeonimage-clear-choice">C</button>
                    </div>
                </div>
            </div>
        </div>`);
        try {
            const document = dom.window.document;
            const composition = document.querySelector('.qtype-clozeonimage-composition');
            const region = document.querySelector('.qtype-clozeonimage-feedback-region');
            const button = document.querySelector('button');
            for (const state of ['', 'notanswered', ...Object.keys(states)]) {
                region.className = `qtype-clozeonimage-feedback-region qtype-clozeonimage-state-${state}`;
                assert.equal(declarations(button)['background-color'], 'var(--qtype-clozeonimage-neutral-bg)');
                const probe = document.createElement('div');
                probe.style.backgroundColor = declarations(composition)['--qtype-clozeonimage-neutral-bg'];
                assert.equal(probe.style.backgroundColor, expectedColour('245, 245, 245', appearance));
            }
        } finally {
            dom.window.close();
        }
    });
}

test('Clear C uses the standard neutral control border and Moodle focus ring without changing its background', () => {
    const selector = '.que.clozeonimage .qtype-clozeonimage-clear-choice';
    const base = css.nodes.find(node => node.selector === selector);
    const properties = rule => Object.fromEntries(
        rule.nodes.filter(node => node.type === 'decl').map(node => [node.prop, node.value])
    );
    assert.equal(properties(base).border, 'var(--bs-border-width) solid var(--bs-gray-500)');
    assert.equal(properties(base)['border-radius'], 'var(--bs-border-radius)');
    assert.equal(properties(base)['block-size'], '1.5rem');
    assert.equal(properties(base)['inline-size'], '1.5rem');
    assert.equal(properties(base)['inset-block-end'], '-.75rem');
    assert.equal(properties(base)['inset-inline-end'], '-.75rem');
    const hidden = css.nodes.find(node => node.selector === selector + '[hidden]');
    assert.deepEqual(properties(hidden), {display: 'none'});
    const focus = css.nodes.find(node => node.selectors?.includes(selector + ':focus'));
    assert.ok(focus, 'Focus rule must cover click as well as keyboard focus');
    assert.ok(focus.selector.includes(':focus-within > .answer'), 'Share the existing editable panel focus rule');
    assert.deepEqual(properties(focus), {
        'border-color': '#87b6df',
        'box-shadow': '0 0 0 var(--bs-focus-ring-width) var(--bs-focus-ring-color)',
        outline: '0',
    });
    css.walkRules(rule => {
        if (rule.selector.includes('.qtype-clozeonimage-clear-choice') && rule !== base) {
            rule.walkDecls(/^(background|background-color|background-image)$/, declaration => {
                assert.fail(`Clear state must not override its neutral background: ${declaration}`);
            });
        }
    });
});
