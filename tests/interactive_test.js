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
 * Result presentation and Interactive/Adaptive DOM regressions. Run with: node tests/interactive_test.js
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
const postcss = require('postcss');

/**
 * Load the real AMD controllers into an isolated DOM, with only Bootstrap mocked.
 *
 * @param {String} html Question markup.
 * @param {String} directory Source or built AMD directory.
 * @param {Boolean} clearFirst Load Clear before feedback to exercise either delegated listener order.
 * @returns {Object} DOM and Bootstrap mock.
 */
const setup = (html, directory, clearFirst = false) => {
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
    for (const module of clearFirst ? ['clearchoice', 'feedback'] : ['feedback', 'clearchoice']) {
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
 * @param {Boolean} graded Whether the region displays a current graded result.
 * @param {String} name Response name to isolate multiple groups.
 * @returns {String} Local group markup.
 */
const radios = (selected, hidden, locked = false, graded = false, name = 'answer') => `
    <div class="qtype-clozeonimage-feedback-region ${graded ?
        'qtype-clozeonimage-state-incorrect qtype-clozeonimage-feedback-trigger' : ''}"
        ${graded ? 'data-bs-toggle="popover"' : ''}
        data-region="clozeonimage-multichoice">
        <fieldset class="answer">
            ${locked ? '' : `
                <input type="hidden" name="${name}" value="-1" data-role="clozeonimage-clear-choice-sentinel"
                    ${selected === -1 ? '' : 'disabled'}>
            `}
            ${[0, 1, 2].map(value => `
                <input type="radio" name="${name}" id="${name}-${value}" value="${value}"
                    data-role="clozeonimage-multichoice-choice"
                    ${selected === value ? 'checked' : ''} ${locked ? 'disabled' : ''}>
                <label for="${name}-${value}">Choice ${value}</label>
            `).join('')}
        </fieldset>
        ${locked ? '' : `<button type="button" data-action="clozeonimage-clear-choice" ${hidden ? 'hidden' : ''}>C</button>`}
    </div>`;

/**
 * Verify the submission and focus structure after clearing, without simulating browser Tab navigation.
 *
 * @param {Window} window Test window.
 * @param {HTMLElement} region Local radio region.
 */
const assertUnansweredRadio = (window, region) => {
    const sentinel = region.querySelector('[data-role="clozeonimage-clear-choice-sentinel"]');
    assert.equal(sentinel.type, 'hidden');
    assert.equal(sentinel.value, '-1');
    assert.equal(sentinel.disabled, false);
    assert.equal(sentinel.hasAttribute('tabindex'), false);
    assert.equal(sentinel.hasAttribute('checked'), false);
    const focused = window.document.activeElement;
    sentinel.focus();
    assert.equal(window.document.activeElement, focused);
    assert.equal(region.querySelectorAll('input[type="radio"]:checked').length, 0);
    assert.equal(region.querySelector('button').hidden, true);
    for (const choice of region.querySelectorAll('[data-role="clozeonimage-multichoice-choice"]')) {
        assert.equal(choice.type, 'radio');
        assert.equal(choice.disabled, false);
        assert.equal(choice.tabIndex, 0);
        assert.equal(choice.hasAttribute('tabindex'), false);
    }
    assert.deepEqual(new window.FormData(region.closest('form')).getAll(sentinel.name), ['-1']);
};

// Structural CSS checks only: jsdom does not reproduce Moodle/Bootstrap's visual rendering.
test('feedback controls have no plugin outer focus outline or pointer-focus CSS override', () => {
    const css = readFileSync(path.join(__dirname, '..', 'styles.css'), 'utf8');
    assert.equal(css.includes('qtype-clozeonimage-pointer-focus'), false);
    postcss.parse(css).walkRules(rule => {
        if (!rule.selector.includes(':focus') || rule.selector.includes('.qtype-clozeonimage-clear-choice')) {
            return;
        }
        rule.walkDecls(/^outline(?:-.*)?$/, declaration => {
            assert.ok(['0', 'none'].includes(declaration.value), `${rule.selector}: ${declaration.toString()}`);
        });
    });
});

test('dark rounded keyboard focus applies only to non-editable review controls without replacing semantic outlines', () => {
    const css = postcss.parse(readFileSync(path.join(__dirname, '..', 'styles.css'), 'utf8'));
    const focusRules = [];
    const semanticRules = [];
    css.walkRules(rule => {
        rule.walkDecls('box-shadow', declaration => {
            if (declaration.value.includes('inset')) {
                focusRules.push(rule);
                assert.equal(declaration.value, 'inset 0 0 0 4px #212529');
            }
        });
        rule.walkDecls('outline', declaration => {
            if (declaration.value.includes('--qtype-clozeonimage-state-outline')) {
                semanticRules.push(rule);
                assert.equal(declaration.value, '4px solid var(--qtype-clozeonimage-state-outline)');
                assert.equal(rule.nodes.find(node => node.prop === 'outline-offset').value, '1px');
                assert.equal(rule.selector.includes(':focus'), false);
            }
        });
    });
    assert.equal(focusRules.length, 1);
    assert.equal(semanticRules.length, 3);
    const focusRule = focusRules[0];
    assert.ok(focusRule.selector.startsWith('.que.clozeonimage .qtype-clozeonimage-subquestion '));
    assert.ok(focusRule.selector.endsWith(':focus-visible'));
    assert.deepEqual(focusRule.nodes.filter(node => node.type === 'decl').map(node => node.prop),
        ['border-radius', 'box-shadow']);
    assert.equal(focusRule.nodes.find(node => node.prop === 'border-radius').value, 'var(--bs-border-radius)');

    const controls = `
        <input class="form-control" data-family="SHORTANSWER">
        <input class="form-control" data-family="NUMERICAL">
        <input class="form-control" readonly data-family="readonly SHORTANSWER" data-review>
        <input class="form-control" readonly data-family="readonly NUMERICAL" data-review>
        <select class="form-select" data-family="dropdown"><option>Choice</option></select>
        <input type="radio" class="form-check-input" data-family="radio">
        <input type="checkbox" class="form-check-input" data-family="checkbox">
        <button class="qtype-clozeonimage-clear-choice" data-family="Clear">C</button>
        <button class="qtype-clozeonimage-review-surface" data-family="dropdown review" data-review>Feedback</button>
        <button class="qtype-clozeonimage-review-surface" data-family="radio review" data-review>Feedback</button>
        <button class="qtype-clozeonimage-review-surface" data-family="checkbox review" data-review>Feedback</button>`;
    const dom = new JSDOM(`
        <div class="que clozeonimage"><div class="qtype-clozeonimage-subquestion">${controls}</div></div>
        <div class="que other"><div class="qtype-clozeonimage-subquestion">${controls}</div></div>`);
    try {
        // Match only the structural part; browser focus-visible heuristics/pixels are not emulated here.
        const selector = focusRule.selector.replace(':focus-visible', '');
        for (const control of dom.window.document.querySelectorAll('[data-family]')) {
            assert.equal(control.matches(selector),
                !!control.closest('.clozeonimage') && control.hasAttribute('data-review'), control.dataset.family);
        }
        css.walkRules(rule => {
            if (!rule.selector.endsWith(':focus-visible')) {
                return;
            }
            rule.walkDecls('outline', declaration => {
                if (declaration.value !== '0') {
                    return;
                }
                // Only the review button's native outline is suppressed; editable controls and semantic outlines stay intact.
                assert.equal(rule.selector, '.que.clozeonimage .qtype-clozeonimage-subquestion ' +
                    '.qtype-clozeonimage-review-surface:focus-visible');
                assert.equal(rule.selector.includes('.form-control'), false);
                assert.equal(rule.selector.includes('.form-select'), false);
            });
        });
    } finally {
        dom.window.close();
    }
});

test('dropdown review geometry excludes mb-1 only with semantic state and keyboard focus', () => {
    const css = postcss.parse(readFileSync(path.join(__dirname, '..', 'styles.css'), 'utf8'));
    const rules = [];
    css.walkRules(rule => {
        if (rule.selector.includes('select.form-select.mb-1:disabled')) {
            rules.push(rule);
        }
    });
    assert.equal(rules.length, 1);
    const rule = rules[0];
    assert.ok(rule.selector.startsWith('.que.clozeonimage .qtype-clozeonimage-subquestion'));
    assert.ok(rule.selector.endsWith(' + .qtype-clozeonimage-review-surface:focus-visible'));
    assert.deepEqual(rule.nodes.map(node => [node.prop, node.value]), [['bottom', '.25rem']]);

    const dom = new JSDOM('<div class="que clozeonimage"><div class="qtype-clozeonimage-subquestion">' +
        '<span class="subquestion qtype-clozeonimage-feedback-region"></span></div></div>');
    try {
        const region = dom.window.document.querySelector('.qtype-clozeonimage-feedback-region');
        // Only structural matching here. Native focus-visible and pixel geometry require a browser.
        const selector = rule.selector.replace(':focus-visible', '');
        for (const state of ['', 'notanswered', 'correct', 'incorrect', 'partiallycorrect']) {
            region.className = 'subquestion qtype-clozeonimage-feedback-region' +
                (state ? ` qtype-clozeonimage-state-${state}` : '');
            for (const disabled of [false, true]) {
                region.innerHTML = `<select class="form-select d-inline-block mb-1" ${disabled ? 'disabled' : ''}>` +
                    '<option>Answer</option></select><button class="qtype-clozeonimage-review-surface">Feedback</button>';
                assert.equal(region.querySelector('button').matches(selector),
                    disabled && ['correct', 'incorrect', 'partiallycorrect'].includes(state), `${state}, ${disabled}`);
            }
            for (const control of [
                '<input class="form-control" readonly>',
                '<fieldset class="answer"><input type="radio" disabled></fieldset>',
                '<fieldset class="answer"><input type="checkbox" disabled></fieldset>',
                '<select class="form-select" disabled><option>Without mb-1</option></select>',
            ]) {
                region.innerHTML = control + '<button class="qtype-clozeonimage-review-surface">Feedback</button>';
                assert.equal(region.querySelector('button').matches(selector), false);
            }
        }
        region.innerHTML = '<select class="form-select mb-1" disabled><option>Answer</option></select>' +
            '<button class="qtype-clozeonimage-review-surface">Feedback</button>';
        dom.window.document.querySelector('.que').className = 'que other';
        assert.equal(region.querySelector('button').matches(selector), false);
    } finally {
        dom.window.close();
    }
});

test('editable choice panels and Clear share the Moodle focus presentation', () => {
    const css = postcss.parse(readFileSync(path.join(__dirname, '..', 'styles.css'), 'utf8'));
    const rules = [];
    css.walkRules(rule => {
        rule.walkDecls('box-shadow', declaration => {
            if (declaration.value.includes('--bs-focus-ring-color')) {
                rules.push(rule);
            }
        });
    });
    assert.equal(rules.length, 1);
    assert.ok(rules[0].selector.startsWith('.que.clozeonimage '));
    assert.ok(rules[0].selector.includes(':focus-within'));
    const clearRule = css.nodes.find(rule =>
        rule.selectors?.includes('.que.clozeonimage .qtype-clozeonimage-clear-choice:focus'));
    assert.ok(clearRule);
    assert.equal(clearRule, rules[0]);
    assert.equal(clearRule.nodes.find(node => node.prop === 'outline').value, '0');
});

for (const directory of ['src', 'build']) {
    test(`${directory}: feedback declares the Moodle 5.2-compatible Bootstrap dependency`, () => {
        const dom = new JSDOM('', {runScripts: 'outside-only'});
        try {
            let dependencies;
            // Source uses anonymous define; the built module also includes its module name.
            dom.window.define = (...args) => {
                dependencies = Array.from(args.at(-2));
            };
            const filename = directory === 'build' ? 'feedback.min.js' : 'feedback.js';
            runInContext(readFileSync(path.join(__dirname, '..', 'amd', directory, filename), 'utf8'),
                dom.getInternalVMContext());
            assert.deepEqual(dependencies, ['theme_boost/index']);
        } finally {
            dom.window.close();
        }
    });

    test(`${directory}: unanswered radio structure and native-style selection events keep sentinel outside navigation`, () => {
        const {dom, document, window} = setup(radios(-1, true) +
            '<button type="button" id="outside">Outside</button>', directory);
        try {
            const region = document.querySelector('[data-region="clozeonimage-multichoice"]');
            const choices = region.querySelectorAll('input[type="radio"]');
            const sentinel = region.querySelector('input[type="hidden"]');
            const clear = region.querySelector('button');
            assert.equal(choices.length, 3);
            choices[0].focus();
            assert.equal(document.activeElement, choices[0]);
            assertUnansweredRadio(window, region);
            // These assertions check only that controllers leave keyboard events uncancelled.
            // jsdom does not implement native Tab/arrow/Space default actions.
            for (const key of ['Tab', 'ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', ' ']) {
                for (const eventType of ['keydown', 'keyup']) {
                    const event = new window.KeyboardEvent(eventType, {key, bubbles: true, cancelable: true});
                    choices[0].dispatchEvent(event);
                    assert.equal(event.defaultPrevented, false);
                }
            }
            document.querySelector('#outside').focus();
            assertUnansweredRadio(window, region);
            // Model the checked/focus/input/change sequence produced by native radio selection.
            choices[1].checked = true;
            choices[1].focus();
            choices[1].dispatchEvent(new window.Event('input', {bubbles: true}));
            choices[1].dispatchEvent(new window.Event('change', {bubbles: true}));
            assert.equal(sentinel.disabled, true);
            assert.deepEqual(new window.FormData(document.querySelector('form')).getAll('answer'), ['1']);
            assert.equal(clear.hidden, false);
            clear.focus();
            assert.equal(clear.hidden, false);
            clear.click();
            assert.equal(document.activeElement, choices[0]);
            assertUnansweredRadio(window, region);
            document.querySelector('#outside').focus();
            choices[0].focus();
            assertUnansweredRadio(window, region);
        } finally {
            dom.window.close();
        }
    });

    test(`${directory}: initialization synchronizes the hidden fallback with a restored visible selection`, () => {
        const original = radios(1, true);
        const markup = original.replace(
            'value="-1" data-role="clozeonimage-clear-choice-sentinel"\n                    disabled',
            'value="-1" data-role="clozeonimage-clear-choice-sentinel"');
        assert.notEqual(markup, original);
        const {dom, document, window} = setup(markup, directory);
        try {
            assert.equal(document.querySelector('input[type="hidden"]').disabled, true);
            assert.deepEqual(new window.FormData(document.querySelector('form')).getAll('answer'), ['1']);
        } finally {
            dom.window.close();
        }
    });

    test(`${directory}: neutral radio feedback keeps focus-based Clear and resets only on an answer change`, () => {
        const markup = radios(2, true, false, true).replace('qtype-clozeonimage-state-incorrect ', '');
        const {dom, document, window} = setup(markup, directory);
        try {
            const region = document.querySelector('[data-region="clozeonimage-multichoice"]');
            const selected = region.querySelector('input[value="2"]');
            const clear = region.querySelector('button');
            selected.focus();
            selected.click();
            assert.equal(clear.hidden, false);
            assert.equal(region.getAttribute('data-bs-toggle'), 'popover');
            assert.equal(region.className.includes('qtype-clozeonimage-state-'), false);
            clear.focus();
            clear.click();
            assert.equal(new window.FormData(document.querySelector('form')).get('answer'), '-1');
            assert.equal(clear.hidden, true);
            assert.equal(region.hasAttribute('data-bs-toggle'), false);
        } finally {
            dom.window.close();
        }
    });

    test(`${directory}: neutral text feedback has no semantic pointer treatment and resets on editing`, () => {
        const {dom, document, window} = setup(`
            <span class="qtype-clozeonimage-feedback-region">
                <input class="form-control qtype-clozeonimage-feedback-trigger" value="old" data-bs-toggle="popover">
            </span>`, directory);
        try {
            const input = document.querySelector('input');
            input.dispatchEvent(new window.MouseEvent('mousedown', {button: 0, bubbles: true}));
            input.focus();
            assert.equal(input.classList.contains('qtype-clozeonimage-pointer-focus'), false);
            input.dispatchEvent(new window.Event('input', {bubbles: true}));
            assert.equal(input.getAttribute('data-bs-toggle'), 'popover');
            input.value = 'edited';
            input.dispatchEvent(new window.Event('input', {bubbles: true}));
            assert.equal(input.hasAttribute('data-bs-toggle'), false);
            assert.equal(input.value, 'edited');
        } finally {
            dom.window.close();
        }
    });

    for (const clearFirst of [false, true]) {
        test(`${directory}: Adaptive graded focus/Clear lifecycle, clearFirst=${clearFirst}`, () => {
            const {dom, window, document} = setup(radios(2, true, false, true) +
                radios(1, true, false, true, 'other') + '<button type="button" id="outside">Outside</button>',
                directory, clearFirst);
            try {
                const groups = document.querySelectorAll('[data-region="clozeonimage-multichoice"]');
                const group = groups[0];
                const other = groups[1];
                const clear = group.querySelector('button');
                const selected = group.querySelector('input[value="2"]');
                const response = () => new window.FormData(document.querySelector('form')).get('answer');
                const graded = () => group.classList.contains('qtype-clozeonimage-state-incorrect');
                let changes = 0;
                group.addEventListener('change', () => changes++);
                assert.equal(response(), '2');
                assert.equal(clear.hidden, true);
                group.dispatchEvent(new window.MouseEvent('pointerover', {bubbles: true}));
                assert.equal(clear.hidden, true);
                // Model the browser focus transition explicitly: jsdom does not focus on click.
                selected.focus();
                selected.click();
                assert.equal(clear.hidden, false);
                assert.equal(response(), '2');
                assert.equal(changes, 0);
                assert.equal(graded(), true);
                assert.equal(group.getAttribute('data-bs-toggle'), 'popover');
                document.querySelector('#outside').focus();
                assert.equal(clear.hidden, true);
                selected.focus();
                group.querySelector('label[for="answer-2"]').click();
                assert.equal(clear.hidden, false);
                assert.equal(changes, 0);
                assert.equal(graded(), true);
                group.querySelector('fieldset').click();
                assert.equal(clear.hidden, false);
                other.querySelector('input[value="1"]').focus();
                assert.equal(clear.hidden, true);
                assert.equal(other.querySelector('button').hidden, false);
                selected.focus();
                assert.equal(clear.hidden, false);
                assert.equal(other.querySelector('button').hidden, true);

                group.querySelector('input[value="1"]').focus();
                group.querySelector('input[value="1"]').click();
                assert.equal(changes, 1);
                assert.equal(response(), '1');
                assert.equal(graded(), false);
                assert.equal(group.hasAttribute('data-bs-toggle'), false);
                assert.equal(clear.hidden, false);
                document.querySelector('#outside').focus();
                assert.equal(clear.hidden, true);
                group.querySelector('input[value="1"]').focus();
                assert.equal(clear.hidden, false);
                clear.focus();
                assert.equal(clear.hidden, false);
                assert.equal(document.activeElement, clear);
                clear.click();
                assert.equal(response(), '-1');
                assertUnansweredRadio(window, document.querySelector('[data-region="clozeonimage-multichoice"]'));
                assert.equal(group.querySelectorAll('[data-role="clozeonimage-multichoice-choice"]:checked').length, 0);
                assert.equal(clear.hidden, true);
                group.click();
                assert.equal(clear.hidden, true);
                group.querySelector('input[value="2"]').focus();
                group.querySelector('input[value="2"]').click();
                assert.equal(clear.hidden, false);
                assert.equal(response(), '2');

                // A fresh server render after Check starts a new result with Clear hidden.
                group.outerHTML = radios(2, true, false, true);
                const fresh = document.querySelector('[data-region="clozeonimage-multichoice"]');
                assert.equal(fresh.querySelector('button').hidden, true);
                assert.equal(fresh.classList.contains('qtype-clozeonimage-state-incorrect'), true);
                fresh.querySelector('input[value="2"]').focus();
                fresh.querySelector('button').focus();
                fresh.querySelector('button').click();
                assert.equal(response(), '-1');
                assertUnansweredRadio(window, document.querySelector('[data-region="clozeonimage-multichoice"]'));
                assert.equal(fresh.classList.contains('qtype-clozeonimage-state-incorrect'), false);
                assert.equal(fresh.hasAttribute('data-bs-toggle'), false);
                assert.equal(fresh.querySelector('button').hidden, true);
            } finally {
                dom.window.close();
            }
        });
    }

    for (const state of ['correct', 'partiallycorrect', 'incorrect']) {
        for (const readonly of [false, true]) {
            test(`${directory}: ${state} text pointer/keyboard focus, readonly=${readonly}`, () => {
                const {dom, window, document} = setup(`
                    <span class="subquestion qtype-clozeonimage-feedback-region qtype-clozeonimage-state-${state}">
                        <span data-role="clozeonimage-result-state">Status</span>
                        <input type="text" class="form-control ${state} qtype-clozeonimage-feedback-trigger" value="old"
                            ${readonly ? 'readonly' : ''} data-bs-toggle="popover">
                    </span>`, directory);
                try {
                    const input = document.querySelector('input');
                    const region = input.parentNode;
                    const pointer = () => input.dispatchEvent(new window.MouseEvent('mousedown',
                        {button: 0, bubbles: true, cancelable: true}));
                    assert.equal(pointer(), !readonly);
                    if (!readonly) {
                        input.focus();
                        input.click();
                    }
                    assert.equal(input.classList.contains('qtype-clozeonimage-pointer-focus'), false);
                    assert.equal(region.classList.contains(`qtype-clozeonimage-state-${state}`), true);
                    assert.equal(input.getAttribute('data-bs-toggle'), 'popover');
                    input.dispatchEvent(new window.Event('input', {bubbles: true}));
                    assert.equal(region.classList.contains(`qtype-clozeonimage-state-${state}`), true);
                    // Keyboard focus remains available even when pointer focus was prevented.
                    input.focus();
                    input.dispatchEvent(new window.KeyboardEvent('keydown', {key: 'ArrowLeft', bubbles: true}));
                    assert.equal(input.classList.contains('qtype-clozeonimage-pointer-focus'), false);
                    assert.equal(document.activeElement, input);
                    pointer();
                    input.blur();
                    assert.equal(input.classList.contains('qtype-clozeonimage-pointer-focus'), false);
                    pointer();
                    if (!readonly) {
                        input.value = 'edited';
                        input.dispatchEvent(new window.Event('input', {bubbles: true}));
                        assert.equal(region.classList.contains(`qtype-clozeonimage-state-${state}`), false);
                        assert.equal(input.classList.contains(state), false);
                        assert.equal(input.hasAttribute('data-bs-toggle'), false);
                        assert.equal(input.classList.contains('qtype-clozeonimage-pointer-focus'), false);
                        assert.equal(region.querySelector('[data-role="clozeonimage-result-state"]'), null);
                        assert.equal(input.value, 'edited');
                        pointer();
                        assert.equal(input.classList.contains('qtype-clozeonimage-pointer-focus'), false);
                    }
                } finally {
                    dom.window.close();
                }
            });
        }
    }

    test(`${directory}: retained Interactive answer exposes Clear on focus without a change`, () => {
        const {dom, window, document} = setup(radios(2, true) + '<button id="outside" type="button">Outside</button>', directory);
        try {
            const choices = document.querySelectorAll('[data-role="clozeonimage-multichoice-choice"]');
            const button = document.querySelector('button');
            const form = document.querySelector('form');
            assert.equal(new window.FormData(form).get('answer'), '2');
            let changes = 0;
            form.addEventListener('change', () => changes++);
            choices[2].dispatchEvent(new window.MouseEvent('mouseover', {bubbles: true}));
            assert.equal(button.hidden, true);
            choices[2].focus();
            choices[2].click();
            document.querySelector('fieldset').click();
            assert.equal(button.hidden, false);
            assert.equal(changes, 0);
            assert.equal(new window.FormData(form).get('answer'), '2');
            document.querySelector('#outside').focus();
            assert.equal(button.hidden, true);
            choices[1].focus();
            choices[1].click();
            assert.equal(button.hidden, false);
            assert.equal(new window.FormData(form).get('answer'), '1');
            choices[2].focus();
            choices[2].click();
            assert.equal(button.hidden, false);
            button.focus();
            assert.equal(button.hidden, false);
            button.click();
            assert.equal(button.hidden, true);
            assert.equal(document.querySelectorAll('[data-role="clozeonimage-multichoice-choice"]:checked').length, 0);
            assert.equal(new window.FormData(form).get('answer'), '-1');
            assertUnansweredRadio(window, document.querySelector('[data-region="clozeonimage-multichoice"]'));
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
            assertUnansweredRadio(window, document.querySelector('[data-region="clozeonimage-multichoice"]'));
            assert.equal(document.querySelectorAll('[data-role="clozeonimage-multichoice-choice"]:checked').length, 0);
            document.querySelector('fieldset').dispatchEvent(new window.MouseEvent('mouseover', {bubbles: true}));
            assert.equal(button.hidden, true);
            document.querySelector('input[value="2"]').focus();
            assert.equal(button.hidden, true);
            document.querySelector('input[value="2"]').click();
            assert.equal(button.hidden, false);
            assert.equal(new window.FormData(form).get('answer'), '2');
        } finally {
            dom.window.close();
        }
    });

    test(`${directory}: editable initial selection follows focus, including internal and null destinations`, () => {
        const {dom, window, document} = setup(radios(-1, true) +
            '<button id="outside" type="button">Outside</button>', directory);
        try {
            const clear = document.querySelector('button');
            const first = document.querySelector('input[value="0"]');
            const second = document.querySelector('input[value="1"]');
            assert.equal(clear.hidden, true);
            first.focus();
            assert.equal(clear.hidden, true);
            first.click();
            assert.equal(clear.hidden, false);
            first.dispatchEvent(new window.FocusEvent('focusout', {bubbles: true, relatedTarget: second}));
            assert.equal(clear.hidden, false);
            second.focus();
            assert.equal(clear.hidden, false);
            clear.focus();
            assert.equal(clear.hidden, false);
            document.querySelector('#outside').focus();
            assert.equal(clear.hidden, true);
            first.focus();
            assert.equal(clear.hidden, false);
            first.blur();
            assert.equal(clear.hidden, true);
            // Selecting without moving focus must not expose Clear.
            second.click();
            assert.equal(clear.hidden, true);
            second.focus();
            assert.equal(clear.hidden, false);
            clear.focus();
            clear.click();
            assert.equal(clear.hidden, true);
            // Even if focus remains on (or is restored to) Clear, no selection means no Clear.
            clear.focus();
            assert.equal(clear.hidden, true);
            assert.equal(new window.FormData(document.querySelector('form')).get('answer'), '-1');
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

    for (const type of ['SHORTANSWER', 'NUMERICAL', 'dropdown']) {
        test(`${directory}: editable ${type} MAX_ONLY popover preserves native focus without extra styling`, () => {
            const attributes = 'class="qtype-clozeonimage-feedback-trigger ' +
                `${type === 'dropdown' ? 'form-select' : 'form-control'}" ` +
                'data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Marked out of 1.00"';
            const control = type === 'dropdown' ? `<select ${attributes}><option value="">Choose</option></select>` :
                `<input type="text" ${attributes} value="">`;
            const {dom, document, window, popover} = setup(
                `<span class="subquestion qtype-clozeonimage-feedback-region">${control}</span>`, directory);
            try {
                const input = document.querySelector('input, select');
                const original = input.outerHTML;
                const down = new window.MouseEvent('mousedown', {bubbles: true, cancelable: true, button: 0});
                input.dispatchEvent(down);
                assert.equal(down.defaultPrevented, false);
                input.focus();
                assert.equal(document.activeElement, input);
                input.click();
                assert.equal(popover.showCount, 1);
                assert.equal(input.outerHTML, original);
                assert.equal(input.parentNode.className.includes('qtype-clozeonimage-state-'), false);
            } finally {
                dom.window.close();
            }
        });
    }

    // Both text qtypes render input[type=text]; the actual behaviour/readonly contract is tested in PHP.
    for (const behaviour of ['Interactive exhausted', 'Immediate feedback after Check']) {
        for (const [type, response] of [['SHORTANSWER', 'wrong'], ['NUMERICAL', '999']]) {
            for (const option of ['Maximum marks', 'Marks', 'Specific feedback', 'Right answer', 'none']) {
                test(`${directory}: ${behaviour} ${type} readonly pointer/keyboard path, ${option}`, () => {
                    const feedback = option === 'none' ? '' :
                        'data-bs-toggle="popover" data-bs-trigger="hover focus"';
                    const triggerClass = option === 'none' ? '' : 'qtype-clozeonimage-feedback-trigger';
                    const {dom, window, document, popover} = setup(`
                        <span class="subquestion qtype-clozeonimage-feedback-region">
                            <input type="text" name="answer" class="form-control ${triggerClass}"
                                ${feedback} value="${response}" readonly>
                        </span>`, directory);
                    try {
                        const input = document.querySelector('input');
                        const original = input.outerHTML;
                        let changes = 0;
                        input.addEventListener('input', () => changes++);
                        input.addEventListener('change', () => changes++);
                        const pointer = () => {
                            const down = new window.MouseEvent('mousedown', {bubbles: true, cancelable: true, button: 0});
                            input.dispatchEvent(down);
                            assert.equal(down.defaultPrevented, true);
                            // No synthetic focus(): the cancelled default action is what prevents browser focus.
                            input.click();
                            assert.notEqual(document.activeElement, input);
                            assert.equal(input.classList.contains('qtype-clozeonimage-pointer-focus'), false);
                        };
                        pointer();
                        assert.equal(popover.showCount, option === 'none' ? 0 : 1);
                        const tab = new window.KeyboardEvent('keydown', {key: 'Tab', bubbles: true, cancelable: true});
                        input.dispatchEvent(tab);
                        assert.equal(tab.defaultPrevented, false);
                        input.focus();
                        assert.equal(document.activeElement, input);
                        assert.equal(input.disabled, false);
                        assert.equal(input.tabIndex, 0);
                        // A pointer click after keyboard focus must also remove the focus contour.
                        pointer();
                        pointer();
                        assert.equal(popover.showCount, option === 'none' ? 0 : 2);
                        assert.equal(input.outerHTML, original);
                        assert.equal(changes, 0);
                        assert.equal(new window.FormData(document.querySelector('form')).get('answer'), response);
                    } finally {
                        dom.window.close();
                    }
                });
            }
        }
    }

    for (const [type, response] of [['SHORTANSWER', 'sample answer'], ['NUMERICAL', '123.45']]) {
        for (const feedback of [false, true]) {
            test(`${directory}: readonly ${type} collapses entry selection but permits manual selection, popup=${feedback}`, () => {
                const trigger = feedback ? 'qtype-clozeonimage-feedback-trigger' : '';
                const {dom, window, document, popover} = setup(`
                    <span class="subquestion qtype-clozeonimage-feedback-region qtype-clozeonimage-state-correct">
                        <input type="text" name="answer" class="form-control ${trigger}" readonly value="${response}"
                            ${feedback ? 'data-bs-toggle="popover" data-bs-trigger="hover focus"' : ''}>
                    </span><button type="button" id="next">Next</button>`, directory);
                try {
                    const input = document.querySelector('input');
                    const original = input.parentNode.outerHTML;
                    let changes = 0;
                    input.addEventListener('input', () => changes++);
                    input.addEventListener('change', () => changes++);
                    // Model native Tab selection explicitly; jsdom does not implement it.
                    input.setSelectionRange(0, response.length);
                    input.focus();
                    assert.equal(document.activeElement, input);
                    assert.equal(input.selectionStart, 0);
                    assert.equal(input.selectionEnd, 0);
                    assert.equal(input.readOnly, true);
                    assert.equal(input.disabled, false);
                    assert.equal(input.tabIndex, 0);
                    // Deliberate selection after entry is not cleared by a select/selectionchange handler or timer.
                    input.select();
                    assert.equal(input.selectionStart, 0);
                    assert.equal(input.selectionEnd, response.length);
                    for (const key of ['a', 'c']) {
                        const event = new window.KeyboardEvent('keydown', {key, ctrlKey: true, bubbles: true, cancelable: true});
                        input.dispatchEvent(event);
                        assert.equal(event.defaultPrevented, false);
                    }
                    input.setSelectionRange(1, 3);
                    assert.equal(input.selectionStart, 1);
                    assert.equal(input.selectionEnd, 3);
                    input.blur();
                    input.focus();
                    assert.equal(input.selectionStart, 1);
                    assert.equal(input.selectionEnd, 3);
                    const tab = new window.KeyboardEvent('keydown', {key: 'Tab', bubbles: true, cancelable: true});
                    input.dispatchEvent(tab);
                    assert.equal(tab.defaultPrevented, false);
                    document.querySelector('#next').focus();
                    assert.equal(document.activeElement.id, 'next');
                    input.click();
                    assert.equal(popover.showCount, feedback ? 1 : 0);
                    assert.equal(input.parentNode.outerHTML, original);
                    assert.equal(changes, 0);
                    assert.equal(new window.FormData(document.querySelector('form')).get('answer'), response);
                } finally {
                    dom.window.close();
                }
            });
        }
    }

    test(`${directory}: entry selection on editable inputs and unrelated readonly fields is untouched`, () => {
        const {dom, document} = setup(`
            <span class="qtype-clozeonimage-feedback-region">
                <input type="text" class="form-control" value="editable">
            </span>
            <input type="text" class="form-control" readonly value="unrelated">`, directory);
        try {
            document.body.insertAdjacentHTML('beforeend', `<div class="que other">
                <span class="qtype-clozeonimage-feedback-region">
                    <input type="text" class="form-control" readonly value="other question">
                </span></div>`);
            for (const input of document.querySelectorAll('input')) {
                input.setSelectionRange(0, input.value.length);
                input.focus();
                assert.equal(document.activeElement, input);
                assert.equal(input.selectionStart, 0);
                assert.equal(input.selectionEnd, input.value.length);
            }
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
            assert.equal(input.classList.contains('qtype-clozeonimage-pointer-focus'), false);
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

    test(`${directory}: mouse focus guard excludes editable controls and unrelated readonly inputs`, () => {
        const {dom, window, document} = setup(`
            <span class="qtype-clozeonimage-feedback-region">
                <input id="adaptive" class="form-control qtype-clozeonimage-feedback-trigger" data-bs-toggle="popover">
                <input id="retry" class="form-control">
            </span>
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
