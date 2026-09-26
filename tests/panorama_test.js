// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Pure panorama geometry regressions. Run with: node --test tests/panorama_test.js
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
    const file = directory === 'src' ? 'layout.js' : 'layout.min.js';
    runInNewContext(readFileSync(path.join(__dirname, '../amd', directory, file), 'utf8'), {
        define: (...args) => {
            layout = args.at(-1)();
        },
    });
    const {panGeometry, drawerBounds} = layout;
    for (const width of [800, 1777]) {
        test(`${directory}: W=${width}: thin viewport borders retain both inner edge alignments`, () => {
            const viewport = 1440 - 2;
            const left = 1;
            const geometry = panGeometry(width, viewport, left);
            assert.equal(geometry.range, Math.abs(viewport - width));
            assert.equal(geometry.track - viewport, geometry.range);
            const first = left + geometry.space;
            const last = first + geometry.direction * geometry.range;
            assert.equal(first, 1, 'Thumb fully left aligns the left edge');
            assert.equal(last + width, 1439, 'Thumb fully right aligns the right edge');
        });
    }
    for (const width of [800, 1200, 1777]) {
        test(`${directory}: W=${width}, V=1200: both edges and all intermediate positions`, () => {
            const viewport = 1200;
            const left = 150;
            const minimum = left + Math.min(0, viewport - width);
            const maximum = left + Math.max(0, viewport - width);
            for (let step = 0; step <= 10; step++) {
                const desired = minimum + (maximum - minimum) * step / 10;
                const geometry = panGeometry(width, viewport, left, desired);
                assert.ok(Math.abs(left + geometry.space + geometry.direction * geometry.scroll - desired) < 0.00001);
                assert.equal(geometry.track - viewport, Math.abs(viewport - width));
                assert.ok(geometry.scroll >= 0 && geometry.scroll <= geometry.range);
            }
            assert.equal(panGeometry(width, viewport, left, minimum - 500).scroll,
                width < viewport ? 0 : Math.abs(viewport - width));
            assert.equal(panGeometry(width, viewport, left, maximum + 500).scroll,
                width < viewport ? Math.abs(viewport - width) : 0);
            const initial = panGeometry(width, viewport, left);
            assert.equal(left + initial.space + initial.direction * initial.scroll, left + Math.max(0, viewport - width) / 2);
        });
    }
    test(`${directory}: resize preserves screen position until impossible, crossing W=V in both directions`, () => {
        let previous = 300;
        for (const [viewport, expected] of [[1200, 300], [1500, 300], [900, 100], [600, 0], [1400, 0]]) {
            const geometry = panGeometry(800, viewport, 0, previous);
            previous = geometry.space + geometry.direction * geometry.scroll;
            assert.equal(previous, expected);
        }
        for (const viewport of [900, 1500, 1200]) {
            const geometry = panGeometry(1777, viewport, 100, -100);
            assert.equal(100 + geometry.space + geometry.direction * geometry.scroll, -100);
        }
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
    test(`${directory}: visual viewport offset and drawer resize retain physical screen position`, () => {
        const bounds = drawerBounds(200, 1200, [{left: 100, right: 350, side: 'left'}]);
        const geometry = panGeometry(800, bounds.right - bounds.left, bounds.left, 375);
        assert.equal(bounds.left + geometry.space + geometry.direction * geometry.scroll, 375);
    });
}

for (const directory of ['src', 'build']) {
    let layout;
    runInNewContext(readFileSync(path.join(__dirname, '../amd', directory,
        directory === 'src' ? 'layout.js' : 'layout.min.js'), 'utf8'), {define: (...args) => {
            layout = args.at(-1)();
        }});
    const {contentBounds, panGeometry} = layout;
    const cases = {
        left: [{left: -60, right: 40, top: 20, bottom: 60}],
        right: [{left: 10, right: 90, top: 20, bottom: 60, fromRight: true}],
        above: [{left: 30, right: 110, top: -40, bottom: 10}],
        below: [{left: 30, right: 110, top: 580, bottom: 630}],
        'left+above': [{left: -60, right: 40, top: -40, bottom: 10}],
        'right+below': [{left: 10, right: 90, top: 580, bottom: 630, fromRight: true}],
        'all sides': [{left: -60, right: 90, top: -40, bottom: 630, spansImage: true}],
        'separate extrema': [{left: -60, right: 40, top: 30, bottom: 90},
            {left: 30, right: 110, top: -40, bottom: 20},
            {left: 10, right: 90, top: 40, bottom: 80, fromRight: true},
            {left: 30, right: 110, top: 580, bottom: 630}],
    };
    for (const width of [800, 1777]) {
        for (const [name, definitions] of Object.entries(cases)) {
            test(`${directory}: W=${width}: full visual union ${name} defines endpoints and intermediate positions`, () => {
                const rects = definitions.map(rect => ({
                    left: rect.left + (rect.fromRight ? width : 0),
                    right: rect.right + (rect.fromRight || rect.spansImage ? width : 0),
                    top: rect.top, bottom: rect.bottom,
                }));
                const bounds = contentBounds(width, 600, rects);
                const union = [{left: 0, right: width, top: 0, bottom: 600}, ...rects];
                assert.equal(bounds.minX, Math.min(...union.map(rect => rect.left)));
                assert.equal(bounds.maxX, Math.max(...union.map(rect => rect.right)));
                assert.equal(bounds.minY, Math.min(...union.map(rect => rect.top)));
                assert.equal(bounds.maxY, Math.max(...union.map(rect => rect.bottom)));
                for (const viewport of [600, 1200, 2200]) {
                    const geometry = panGeometry(width, viewport, 17, null, bounds);
                    const start = -bounds.minX;
                    const end = viewport - bounds.maxX;
                    assert.equal(geometry.space + bounds.minX, 0, 'Fully-left thumb exposes minX');
                    assert.equal(geometry.space + geometry.direction * geometry.range + bounds.maxX, viewport,
                        'Fully-right thumb exposes maxX');
                    for (let step = 0; step <= 10; step++) {
                        const position = start + (end - start) * step / 10;
                        const requested = panGeometry(width, viewport, 17, 17 + position, bounds);
                        assert.ok(Math.abs(17 + requested.space + requested.direction * requested.scroll - 17 - position) < 1e-8);
                    }
                    assert.equal(geometry.track - viewport, geometry.range);
                    assert.ok(geometry.space + bounds.minX >= 0);
                    assert.ok(geometry.space + bounds.maxX <= geometry.track);
                    assert.equal(geometry.top + bounds.minY, 0);
                    assert.equal(geometry.top + bounds.maxY, geometry.height);
                    if (viewport >= bounds.maxX - bounds.minX) {
                        assert.equal(geometry.range, viewport - (bounds.maxX - bounds.minX),
                            'Fitting union determines small-object travel');
                    }
                }
            });
        }
    }
    test(`${directory}: changing extents preserves image position until no longer possible`, () => {
        let previous = 250;
        for (const [viewport, minX, maxX] of [[1500, -900, 1900], [1200, -60, 900], [600, -60, 1900], [1600, 0, 800]]) {
            const bounds = {minX, maxX, minY: -40, maxY: 630};
            const geometry = panGeometry(800, viewport, 0, previous, bounds);
            const minimum = Math.min(-minX, viewport - maxX);
            const maximum = Math.max(-minX, viewport - maxX);
            assert.equal(geometry.space + geometry.direction * geometry.scroll, Math.max(minimum, Math.min(maximum, previous)));
            previous = geometry.space + geometry.direction * geometry.scroll;
        }
    });
}

for (const directory of ['src', 'build']) {
    let layout;
    runInNewContext(readFileSync(path.join(__dirname, '../amd', directory,
        directory === 'src' ? 'layout.js' : 'layout.min.js'), 'utf8'), {define: (...args) => {
            layout = args.at(-1)();
        }});
    const {panGeometry} = layout;
    for (const width of [800, 1777]) {
        for (const [minX, extraRight] of [[0, 0], [-50, 0], [0, 75], [-50, 75]]) {
            test(`${directory}: W=${width}: 10 px gutters with extents ${minX}/${extraRight}`, () => {
                const bounds = {minX, maxX: width + extraRight, minY: -40, maxY: 630};
                const gutter = 10;
                for (const viewport of [600, 1200, 2200, bounds.maxX - minX + 2 * gutter]) {
                    const usable = viewport - 2 * gutter;
                    const geometry = panGeometry(width, viewport, 100, null, bounds, gutter);
                    const unionWidth = bounds.maxX - minX;
                    assert.equal(geometry.space + minX, gutter, 'Fully-left thumb: minX is at the left gutter');
                    assert.equal(geometry.space + geometry.direction * geometry.range + bounds.maxX, viewport - gutter,
                        'Fully-right thumb: maxX is at the right gutter');
                    assert.equal(geometry.range, Math.abs(usable - unionWidth));
                    assert.equal(geometry.track - viewport, geometry.range, 'Native scroll range is the union-based range');
                    if (usable === unionWidth) {
                        assert.equal(geometry.range, 0, 'Equal union and usable width need no travel');
                    }
                    for (const origin of [gutter - minX, gutter + usable - bounds.maxX]) {
                        const position = panGeometry(width, viewport, 100, 100 + origin, bounds, gutter);
                        assert.equal(position.space + position.direction * position.scroll, origin,
                            'Both visual union edges are reachable');
                    }
                    assert.equal(geometry.top, 40, 'No change to vertical offset');
                    assert.equal(geometry.height, 670, 'No change to vertical canvas size');
                }
            });
        }
    }
}
