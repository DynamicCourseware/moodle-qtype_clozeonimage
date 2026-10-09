// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Exercise the real core/sortable_list with editable authoring rows.
 * Only modal rendering, localisation and geometry are supplied by the fixture.
 * Run with: node --test tests/reorder_test.js
 *
 * @copyright 2026 DynamicCourseware.org (Dominique Bauer)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/* eslint-env node */
/* eslint-disable promise/no-native, promise/avoid-new */
const assert = require('node:assert/strict');
const {readFileSync} = require('node:fs');
const path = require('node:path');
const {test} = require('node:test');
const {runInContext} = require('node:vm');
const {JSDOM} = require('jsdom');
const core = path.resolve(__dirname, '../../../../lib');
const row = index => `<div id="fgroup_id_subquestionrow_${index}"
    class="qtype-clozeonimage-subquestion-row" data-groupname="subquestionrow[${index}]">
    <div class="col-form-label"><p aria-hidden="true" id="id_subquestionrow_${index}_label">
        <span class="qtype-clozeonimage-row-number"><span>Subquestion</span> ${index + 1}</span></p></div>
    <div class="felement"><fieldset><legend class="visually-hidden">
        <span class="qtype-clozeonimage-row-number"><span>Subquestion</span> ${index + 1}</span></legend>
    <span class="qtype-clozeonimage-move d-none"><button type="button" data-drag-type="move"
        title="Move subquestion ${index + 1}" aria-label="Move subquestion ${index + 1}">Move</button></span>
    <input type="hidden" name="roworigin[${index}]" value="${index + 1}">
    <input class="qtype-clozeonimage-coordinate" id="id_xleft_${index}" name="xleft[${index}]" value="${index * -20}">
    <input class="qtype-clozeonimage-coordinate" name="ytop[${index}]" value="${index * 30}">
    <input type="radio" id="id_anchor_${index}_0" name="anchor[${index}]" value="0" ${index === 0 ? 'checked' : ''}>
    <label for="id_anchor_${index}_0">Top left</label>
    <input type="radio" id="id_anchor_${index}_4" name="anchor[${index}]" value="4" ${index === 0 ? '' : 'checked'}>
    <label for="id_anchor_${index}_4">Centre</label>
    <textarea id="id_subquestion_${index}" name="subquestion[${index}]" aria-label="Source ${index + 1}"
        aria-describedby="id_error_subquestionrow_${index}">Source ${index}</textarea>
    <div class="form-control-feedback" id="id_error_subquestionrow_${index}">
        <span class="visually-hidden">Subquestion ${index + 1}: </span>Error</div></fieldset></div></div>`;
const setup = directory => {
    const dom = new JSDOM(`<form class="mform" data-qtype="clozeonimage">${[0, 1, 2].map(row).join('')}
        <button type="submit">Save</button></form>
        ${[0, 2].map(index => `<div class="qtype-clozeonimage-preview-subquestion" data-row-index="${index}">
            <span class="qtype-clozeonimage-preview-number">${index + 1}</span></div>`).join('')}`,
    {runScripts: 'outside-only', url: 'https://example.test/'});
    const {window} = dom;
    const {document} = window;
    const run = filename => runInContext(readFileSync(filename, 'utf8'), dom.getInternalVMContext());
    run(path.join(core, 'jquery/jquery-3.7.1.js'));
    const $ = window.jQuery;
    const errors = [];
    let modal;
    let sortable;
    window.define = (dependencies, factory) => {
        window.CoreSortable = factory($, {}, {start: () => undefined, stop: () => undefined}, {
            dispatchEvent: (type, detail, target) => target.dispatchEvent(new window.CustomEvent(type, {detail, bubbles: true})),
        }, {get_string: (key, component, name) => Promise.resolve(`${key} ${name || ''}`)}, { // eslint-disable-line camelcase
            create: async config => {
                const body = await config.body;
                const root = $('<div role="dialog">').append(body).appendTo(document.body);
                modal = {getRoot: () => root, setLarge: () => undefined, show: () => root.find('a').first().trigger('focus'),
                    hide: () => root.trigger('hidden'), destroy: () => root.remove()};
                return modal;
            },
        }, {hidden: 'hidden'}, {exception: error => errors.push(error)});
    };
    run(path.join(core, 'amd/src/sortable_list.js'));
    window.define = (...args) => {
        const CaptureSortable = function(...parameters) {
            sortable = new window.CoreSortable(...parameters);
            return sortable;
        };
        CaptureSortable.EVENTS = window.CoreSortable.EVENTS;
        window.Reorder = args.at(-1)($, CaptureSortable);
    };
    run(path.join(__dirname, '../amd', directory, directory === 'src' ? 'reorder.js' : 'reorder.min.js'));
    window.Reorder.init();
    const list = document.getElementById('qtype-clozeonimage-rows');
    const mouseStart = target => $(target).trigger($.Event('mousedown', {which: 1, pageX: 10, pageY: 10}));
    const mouseDrop = destination => {
        document.elementFromPoint = () => destination;
        destination.getBoundingClientRect = () => ({left: 0, top: 0, width: 100, height: 100});
        $(window).trigger($.Event('mouseup', {pageX: 20, pageY: 90, clientX: 20, clientY: 90}));
    };
    return {dom, window, document, $, list, errors, getSortable: () => sortable, mouseStart, mouseDrop,
        getModal: () => modal};
};
const waitFor = async condition => {
    for (let count = 0; count < 100; count++) {
        if (condition()) {
            return;
        }
        await new Promise(resolve => setTimeout(resolve, 10));
    }
    assert.fail('Modal did not become ready');
};

for (const directory of ['src', 'build']) {
    test(`${directory}: editable fields do not drag; mouse movement preserves values, focus and radio groups`, () => {
        const fixture = setup(directory);
        const {dom, window, document, list, mouseStart, mouseDrop, getSortable} = fixture;
        try {
            const moved = list.firstElementChild;
            const source = moved.querySelector('textarea');
            source.value = '{1:SHORTANSWER:=Edited answer}';
            source.focus();
            source.setSelectionRange(4, 10);
            mouseStart(source);
            assert.equal(getSortable().info, null);
            let changed = 0;
            fixture.$(source).on('change', () => changed++);
            mouseStart(moved.querySelector('button'));
            getSortable().createProxy();
            const proxy = list.querySelector('.sortable-list-is-dragged');
            assert.equal(proxy.querySelectorAll('[name], [id]').length, 0);
            assert.equal(list.querySelectorAll('input[type="radio"]:checked:not(:disabled)').length, 3);
            const keys = [...new window.FormData(document.querySelector('form')).keys()];
            assert.equal(keys.filter(k => k === 'subquestion[0]').length, 1);
            mouseDrop(list.children[2]);
            assert.equal(list.lastElementChild, moved);
            assert.equal(source.value, '{1:SHORTANSWER:=Edited answer}');
            assert.equal(source.name, 'subquestion[2]');
            assert.equal(document.activeElement, source);
            assert.equal(source.selectionStart, 4);
            assert.equal(source.selectionEnd, 10);
            assert.equal(source.getAttribute('aria-describedby'), 'id_error_subquestionrow_0');
            assert.equal(moved.dataset.groupname, 'subquestionrow[0]');
            assert.equal(moved.querySelector('button').closest('[aria-hidden="true"]'), null);
            assert.equal(moved.querySelectorAll('button').length, 1);
            assert.equal(moved.querySelector('legend .qtype-clozeonimage-row-number').textContent, 'Subquestion 3');
            assert.equal(moved.querySelector('label').htmlFor, moved.querySelector('[type="radio"]').id);
            assert.deepEqual([...list.querySelectorAll('[type="radio"]:checked')].map(input => input.value), ['4', '4', '0']);
            assert.deepEqual([...list.querySelectorAll('[name^="roworigin"]')].map(input => input.value), ['2', '3', '1']);
            assert.equal(moved.querySelector('[name="xleft[2]"]').value, '0');
            assert.equal(document.querySelector('[data-row-index="2"] .qtype-clozeonimage-preview-number').textContent, '3');
            assert.equal(changed, 1);
            assert.deepEqual(fixture.errors, []);
        } finally {
            dom.window.close();
        }
    });

    test(`${directory}: keyboard move and cancel restore focus, including a dynamically added row`, async() => {
        const fixture = setup(directory);
        const {dom, window, document, list, getSortable, getModal} = fixture;
        try {
            list.insertAdjacentHTML('beforeend', row(3));
            const added = list.lastElementChild;
            const handle = added.querySelector('button');
            handle.focus();
            handle.dispatchEvent(new window.KeyboardEvent('keypress', {key: 'Enter', keyCode: 13, bubbles: true}));
            await waitFor(() => getModal() && document.querySelector('[data-core_sortable_list-quickmove]'));
            assert.equal(getSortable().info.element[0], added);
            document.querySelector('[data-core_sortable_list-quickmove]').click();
            await window.Promise.resolve();
            assert.equal(list.firstElementChild, added);
            assert.equal(added.querySelector('textarea').name, 'subquestion[0]');
            assert.equal(document.activeElement, handle);
            handle.click();
            await waitFor(() => document.querySelector('[role="dialog"]'));
            getModal().hide();
            assert.equal(document.activeElement, handle);
            assert.equal(list.firstElementChild, added);
            assert.equal(getSortable().info, null);
            assert.deepEqual(fixture.errors, []);
        } finally {
            dom.window.close();
        }
    });

    test(`${directory}: cancelling a mouse move leaves submission order unchanged`, () => {
        const fixture = setup(directory);
        const {dom, window, list, getSortable, mouseStart} = fixture;
        try {
            const first = list.firstElementChild;
            const handle = first.querySelector('button');
            handle.focus();
            mouseStart(handle);
            getSortable().moveElement(fixture.$(list), fixture.$());
            window.dispatchEvent(new window.KeyboardEvent('keypress', {key: 'Escape', keyCode: 27, bubbles: true}));
            assert.equal(list.firstElementChild, first);
            assert.equal(first.querySelector('textarea').name, 'subquestion[0]');
            assert.equal(window.document.activeElement, handle);
        } finally {
            dom.window.close();
        }
    });
}
