// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Image-width range and field bounds from the real form controller.
 * Run with: node tests/image_width_test.js
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

for (const directory of ['src', 'build']) {
    for (const [intrinsic, submitted, expected] of [[1777, 0, 1073], [1777, 1073, 1073],
        [1777, 1777, 1777], [400, 0, 400], [100, 0, 100]]) {
        test(`${directory}: intrinsic ${intrinsic}, submitted ${submitted}, matching range and field limits`, async() => {
            const dom = new JSDOM(`<form class="mform" data-qtype="clozeonimage">
                <div><input class="filepickerhidden" name="bgimage">
                    <div class="filepicker-filelist"><a href="/draftfile.php/image.jpg">Image</a></div></div>
                <input name="displaywidth" value="${submitted}">
                <span id="qtype-clozeonimage-displaywidth-range"></span>
                <div id="qtype-clozeonimage-preview-area">
                    <div id="qtype-clozeonimage-preview-composition">
                        <img class="qtype-clozeonimage-preview-image" src="/draftfile.php/image.jpg">
                    </div>
                </div>
            </form>`, {runScripts: 'outside-only', url: 'http://example.test/'});
            try {
                const {window} = dom;
                const {document} = window;
                const image = document.querySelector('img');
                // JSDOM cannot decode/auto-orient JPEGs. Supply the browser's oriented naturalWidth;
                // PHP regressions use real JPEG bytes with EXIF to test the server half of this contract.
                Object.defineProperties(image, {
                    naturalWidth: {value: intrinsic},
                    complete: {value: true},
                });
                Object.defineProperty(document.getElementById('qtype-clozeonimage-preview-area'), 'clientWidth', {value: 1073});
                runInContext(readFileSync(path.join(__dirname, '../../../../lib/jquery/jquery-3.7.1.js'), 'utf8'),
                    dom.getInternalVMContext());
                const ranges = [];
                const errors = [];
                window.define = (...args) => args.at(-1)(window.jQuery, {
                    get_string: (key, component, bounds) => { // eslint-disable-line camelcase
                        ranges.push({key, bounds: JSON.parse(JSON.stringify(bounds))});
                        return window.Promise.resolve(JSON.stringify(bounds));
                    },
                }, {exception: error => errors.push(error)}, {init: () => undefined}).init(false);
                const file = directory === 'src' ? 'form.js' : 'form.min.js';
                runInContext(readFileSync(path.join(__dirname, '../amd', directory, file), 'utf8'), dom.getInternalVMContext());
                await window.Promise.resolve();
                assert.deepEqual(errors, []);
                const width = document.querySelector('[name="displaywidth"]');
                const minimum = Math.min(200, intrinsic);
                assert.equal(width.min, String(minimum));
                assert.equal(width.max, String(intrinsic));
                assert.equal(width.value, String(expected));
                assert.deepEqual(ranges, [{
                    key: minimum === intrinsic ? 'displaywidthsinglecompact' : 'displaywidthrangecompact',
                    bounds: minimum === intrinsic ? intrinsic : {min: minimum, max: intrinsic},
                }]);
                assert.equal(document.getElementById('qtype-clozeonimage-displaywidth-range').textContent,
                    JSON.stringify(ranges[0].bounds));
                width.value = intrinsic + 1;
                width.dispatchEvent(new window.Event('change'));
                assert.equal(width.value, String(intrinsic));
                width.value = minimum - 1;
                width.dispatchEvent(new window.Event('change'));
                assert.equal(width.value, String(minimum));
            } finally {
                dom.window.close();
            }
        });
    }
}
