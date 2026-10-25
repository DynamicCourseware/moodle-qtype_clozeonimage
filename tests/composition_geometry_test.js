// This file is part of Moodle - https://moodle.org/
// Moodle is free software under the GNU GPL v3 or later.
/**
 * Independent geometry contracts for the autonomous format.
 * @copyright 2026 DynamicCourseware.org (Dominique Bauer)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/* eslint-env node */
const assert = require('node:assert/strict');
const {readFileSync} = require('node:fs');
const {test} = require('node:test');
const {runInNewContext} = require('node:vm');
for (const directory of ['src', 'build']) {
    let geometry;
    runInNewContext(readFileSync(`${__dirname}/../amd/${directory}/composition_geometry.${directory === 'src' ? 'js' : 'min.js'}`,
        'utf8'), {define: (...args) => { geometry = args.at(-1)(); }});
    for (const locked of [false, true]) {
        test(`${directory}: extension/reduction in four directions, locked=${locked}`, () => {
            const base = {x: -100, y: -80, width: 800, height: 600, ratio: 4 / 3, locked};
            for (const rect of [{left: -300, top: 0, right: -200, bottom: 100},
                {left: 0, top: -300, right: 100, bottom: -200}, {left: 900, top: 0, right: 1000, bottom: 100},
                {left: 0, top: 700, right: 100, bottom: 800}]) {
                const e = geometry.extent(base, [rect]);
                assert.ok(e.minX <= rect.left && e.minY <= rect.top && e.maxX >= rect.right && e.maxY >= rect.bottom);
                assert.ok(e.minX <= base.x && e.maxX >= base.x + base.width);
                if (locked) {
                    assert.ok(Math.abs((e.maxX - e.minX) / (e.maxY - e.minY) - 4 / 3) < 1e-10);
                }
                // A runtime redraw always starts from the stored base, not the previous extension.
                const shrunk = geometry.extent(base, []);
                assert.equal(shrunk.minX, -100);
                assert.equal(shrunk.maxX, 700);
                assert.equal(shrunk.minY, -80);
                assert.equal(shrunk.maxY, 520);
                // Editing deliberately adopts the enlarged rectangle until an explicit reduction.
                const adopted = {...base, x: e.minX, y: e.minY, width: e.maxX - e.minX, height: e.maxY - e.minY};
                assert.deepEqual(geometry.extent(adopted, []), e);
            }
        });
    }
    for (const locked of [false, true]) {
        test(`${directory}: top-left resize holds opposite corner, content, precise origin, locked=${locked}`, () => {
            const b = {x: -123.456, y: 22.125, width: 800, height: 600, ratio: 4 / 3, locked};
            const c = {minX: -90.5, minY: 40.25, maxX: 450, maxY: 350};
            for (const [dx, dy] of [[-150.25, -200.5], [100, 100], [9000, 9000]]) {
                const r = geometry.resizeCorner(b, dx, dy, true, c);
                assert.ok(Math.abs(r.x + r.width - b.x - b.width) < 1e-10);
                assert.ok(Math.abs(r.y + r.height - b.y - b.height) < 1e-10);
                assert.ok(r.x <= c.minX && r.y <= c.minY && r.width > 0 && r.height > 0);
                if (locked) { assert.ok(Math.abs(r.width / r.height - b.ratio) < 1e-10); }
            }
        });
    }
    test(`${directory}: technical geometry rejects nonfinite and overflow without saturation`, () => {
        assert.ok(geometry.validRectangle({x: -99999, y: 123456.123, width: 100001, height: 1}));
        for (const value of [4194304, -4194304, Infinity, NaN]) {
            assert.equal(geometry.validRectangle({x: value, y: 0, width: 10, height: 10}), false);
        }
        assert.equal(geometry.validRectangle({x: 0, y: 0, width: 0, height: 10}), false);
    });
    for (let anchor = 0; anchor < 9; anchor++) {
        test(`${directory}: anchor ${anchor}, association round trip and proportional movement`, () => {
            const image = {x: -100, y: 50, width: 600, height: 400};
            const block = {x: -250.375, y: 720.25}, size = {width: 241.5, height: 83.25};
            const relative = geometry.relative(image, block, size, anchor);
            const same = geometry.attached(image, relative, size, anchor);
            assert.ok(Math.abs(same.x - block.x) < 1e-10 && Math.abs(same.y - block.y) < 1e-10);
            const moved = geometry.attached({...image, x: image.x + 60, y: image.y - 30}, relative, size, anchor);
            assert.ok(Math.abs(moved.x - block.x - 60) < 1e-10 && Math.abs(moved.y - block.y + 30) < 1e-10);
            const grown = geometry.attached({...image, width: 1200, height: 800}, relative, size, anchor);
            assert.ok(Math.abs(grown.x + anchor % 3 / 2 * size.width - (image.x + relative.x * 1200)) < 1e-10);
            assert.ok(Math.abs(grown.y + Math.floor(anchor / 3) / 2 * size.height - (image.y + relative.y * 800)) < 1e-10);
        });
    }
}
