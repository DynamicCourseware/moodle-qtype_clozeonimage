// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Pure composition_scroll geometry regressions. Run with: node --test tests/composition_scroll_test.js
 *
 * @copyright  2026 DynamicCourseware.org (Dominique Bauer)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/* eslint-env node */

const assert = require('node:assert/strict');
const {readFileSync} = require('node:fs');
const path = require('node:path');
const {test} = require('node:test');
const {runInNewContext} = require('node:vm');

for (const directory of ['src', 'build']) {
    let layout;
    runInNewContext(readFileSync(path.join(__dirname, '../amd', directory,
        directory === 'src' ? 'layout.js' : 'layout.min.js'), 'utf8'), {define: (...args) => {
        layout = args.at(-1)();
    }});
    const {scrollGeometry, contentBounds, drawerBounds, visibilityShift} = layout;
    test(`${directory}: minimal visibility scrolling, with no recentering`, () => {
        for (const low of [0, 8, 320]) {
            const high = low + 400;
            for (const width of [1, 150, 400]) {
                for (let start = low - 500; start <= high + 500; start += 10) {
                    const end = start + width;
                    const shift = visibilityShift(start, end, low, high);
                    assert.ok(start + shift >= low && end + shift <= high);
                    if (start >= low && end <= high) {
                        assert.equal(shift, 0);
                    } else {
                        const smaller = shift - Math.sign(shift);
                        assert.ok(start + smaller < low || end + smaller > high);
                    }
                }
            }
        }
    });
    for (const viewport of [300, 1200, 2200]) {
        for (const gutter of [0, 8, 10]) {
            test(`${directory}: V=${viewport}, gutter=${gutter}: fixed union corner during growth AND shrink`, () => {
                for (const size of [150, 300, 600, 1000, 600, 300, 150]) {
                    const bounds = contentBounds(800, 400, [
                        {left: -180 - size, right: -180, top: -size, bottom: -100},
                        {left: 900, right: 1000, top: 450, bottom: 550},
                    ]);
                    assert.equal(bounds.minX, -180 - size);
                    assert.equal(bounds.minY, -size);
                    assert.equal(bounds.maxX, 1000);
                    assert.equal(bounds.maxY, 550);
                    for (const scroll of [-100, 0, 200, 9000]) {
                        const g = scrollGeometry(viewport, bounds, scroll, gutter);
                        assert.equal(g.space + bounds.minX, gutter, 'Fixed content-relative horizontal origin');
                        assert.equal(g.top + bounds.minY, 0, 'Fixed vertical origin (CSS supplies vertical gutter)');
                        assert.equal(g.height, size + 550, 'No height retained from an earlier larger union');
                        assert.equal(g.track, Math.max(viewport, 1180 + size + 2 * gutter));
                        assert.equal(g.range, g.track - viewport);
                        assert.equal(g.scroll, Math.max(0, Math.min(g.range, scroll)), 'Only clamp native scrolling');
                        assert.ok(g.space + bounds.maxX <= g.track - gutter);
                        if (g.range > 0) {
                            assert.equal(g.space + bounds.maxX - g.range, viewport - gutter,
                                'Rightmost persistent content is reachable');
                        }
                    }
                }
            });
        }
    }
    test(`${directory}: contained compositions need no artificial scrolling or centering`, () => {
        const bounds = contentBounds(600, 400, [{left: 50, right: 350, top: 60, bottom: 150}]);
        const g = scrollGeometry(1200, bounds, 50, 10);
        assert.equal(g.space, 10);
        assert.equal(g.top, 0);
        assert.equal(g.height, 400);
        assert.equal(g.track, 1200);
        assert.equal(g.range, 0);
        assert.equal(g.scroll, 0);
    });
    for (const [name, drawers, expected] of [
        ['none', [], [0, 1400]],
        ['left', [{left: 40, right: 321, side: 'left'}], [321, 1400]],
        ['right', [{left: 1027, right: 1340, side: 'right'}], [0, 1027]],
        ['both', [{left: 40, right: 321, side: 'left'}, {left: 1027, right: 1340, side: 'right'}], [321, 1027]],
        ['offscreen', [{left: -300, right: -1, side: 'left'}, {left: 1401, right: 1800, side: 'right'}], [0, 1400]],
        ['partly visible', [{left: -200, right: 73, side: 'left'}], [73, 1400]],
        ['overlapping', [{left: 0, right: 900, side: 'left'}, {left: 800, right: 1400, side: 'right'}], [900, 900]],
    ]) {
        test(`${directory}: ${name} drawers use actual rectangles`, () => {
            const bounds = drawerBounds(0, 1400, drawers);
            assert.deepEqual([bounds.left, bounds.right], expected);
        });
    }
}
