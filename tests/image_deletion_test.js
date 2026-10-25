// This file is part of Moodle - https://moodle.org/
// Moodle is free software under the GNU GPL v3 or later.
/**
 * Submitter-preserving confirmation; actual Moodle modal/validation is covered in the browser.
 * @copyright 2026 DynamicCourseware.org (Dominique Bauer)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/* eslint-env node */
const {test} = require('node:test');
const assert = require('node:assert/strict');
const {readFileSync} = require('node:fs');
const {JSDOM} = require('jsdom');
const path = require('node:path');
const tick = () => new Promise(resolve => setImmediate(resolve));
for (const dir of ['src', 'build']) {
    const setup = () => {
        const dom = new JSDOM('<form data-qtype="clozeonimage">' +
            '<input type="checkbox" data-image-label="Image 1" checked>' +
            '<input type="checkbox" data-image-label="Image 2">' +
            '<input type="checkbox" data-image-label="Image 3">' +
            '<textarea>Unsubmitted text</textarea>' +
            ['submitbutton', 'updatebutton', 'updatepreview', 'addimage', 'insertblocks', 'cancel']
                .map(n => `<button name="${n}" type="submit">${n}</button>`).join('') + '</form>',
        {runScripts: 'outside-only'});
        const w = dom.window, form = w.document.querySelector('form'), dialogs = [], errors = [], submitted = [];
        const str = (key, component, a) => Promise.resolve(key === 'and' ? `${a.one} and ${a.two}` :
            key === 'listsep' ? ',' : key === 'confirmimagedeletion' ? `You are about to delete ${a}. Do you want to continue?` : key);
        let module;
        w.define = (...args) => { module = args.at(-1)({
            saveCancel: async(title, body, proceed, save, cancel, options) => {
                const handlers = {};
                dialogs.push({title, body, proceed, save, cancel, options, close: () => handlers.hidden()});
                return {getRoot: () => ({on: (name, callback) => { handlers[name] = callback; }})};
            }, exception: e => errors.push(e),
        }, {get_string: str, get_strings: entries => Promise.all(entries.map(e => str(e.key, e.component, e.param)))},
        {hidden: 'hidden'}); };
        w.eval(readFileSync(path.join(__dirname, '../amd', dir,
            dir === 'src' ? 'image_deletion.js' : 'image_deletion.min.js'), 'utf8'));
        module.init(); module.init(); // Initialisation is idempotent.
        form.addEventListener('submit', event => { event.preventDefault(); submitted.push(event.submitter.name); });
        form.requestSubmit = button => form.dispatchEvent(new w.SubmitEvent('submit', {bubbles: true,
            cancelable: true, submitter: button}));
        const submit = name => form.requestSubmit(form.querySelector(`[name=${name}]`));
        return {w, form, submit, dialogs, submitted, errors};
    };
    test(`${dir}: cancellation retains inputs and permits another confirmation; duplicate clicks open one modal`, async() => {
        const s = setup();
        s.submit('submitbutton'); s.submit('submitbutton'); await tick();
        assert.equal(s.dialogs.length, 1); assert.deepEqual(s.submitted, []);
        assert.equal(s.dialogs[0].body, 'You are about to delete Image 1. Do you want to continue?');
        s.dialogs[0].close(); // Cancel, Escape or the modal close button.
        assert.equal(s.form.querySelector('textarea').value, 'Unsubmitted text');
        assert.ok(s.form.querySelector('input').checked);
        s.submit('updatebutton'); await tick(); assert.equal(s.dialogs.length, 2);
        assert.equal(s.dialogs[1].options.triggerElement.name, 'updatebutton');
        assert.deepEqual(s.errors, []); s.w.close();
    });
    for (const button of ['updatebutton', 'submitbutton']) {
        test(`${dir}: confirmation resumes ${button} exactly once, preserving native validation`, async() => {
            const s = setup(); s.form.querySelectorAll('input').forEach(e => { e.checked = true; });
            s.submit(button); await tick();
            assert.equal(s.dialogs[0].body, 'You are about to delete Image 1, Image 2 and Image 3. Do you want to continue?');
            s.dialogs[0].save(); s.dialogs[0].save(); s.dialogs[0].close();
            assert.deepEqual(s.submitted, [button]);
            // A native/client validator may cancel the resumed submission: the next attempt must ask again.
            s.submit(button); await tick(); assert.equal(s.dialogs.length, 2);
            assert.deepEqual(s.errors, []); s.w.close();
        });
    }
    test(`${dir}: non-saving commands and saves without deletions do not ask`, async() => {
        const s = setup();
        for (const name of ['updatepreview', 'addimage', 'insertblocks', 'cancel']) { s.submit(name); }
        s.form.querySelectorAll('input').forEach(e => { e.checked = false; });
        s.submit('submitbutton'); s.submit('updatebutton'); await tick();
        assert.equal(s.dialogs.length, 0); assert.equal(s.submitted.length, 6);
        assert.deepEqual(s.errors, []); s.w.close();
    });
}
