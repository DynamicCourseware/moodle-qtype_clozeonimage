// This file is part of Moodle - https://moodle.org/
// Moodle is free software under the GNU GPL v3 or later.
/**
 * Sticky geometry: run with node --test tests/sticky_test.js.
 * @copyright 2026 DynamicCourseware.org (Dominique Bauer)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
const {test} = require('node:test');
const assert = require('node:assert/strict');
const {readFileSync} = require('node:fs');
const {runInNewContext} = require('node:vm');
for (const directory of ['src', 'build']) {
    let sticky;
    runInNewContext(readFileSync(`${__dirname}/../amd/${directory}/sticky${directory === 'build' ? '.min' : ''}.js`, 'utf8'),
        {define: (...args) => { sticky = args.at(-1)(); }});
    const composition = {left: -500, right: 1600, top: -100, bottom: 1800};
    const viewport = {left: 300, right: 1200, top: 64, bottom: 900};
    const block = {left: -100, top: -20, width: 320, height: 80};
    test(`${directory}: all 16 combinations, Bottom symmetry and natural return`, () => {
        for (let mask = 0; mask < 16; mask++) {
            const top = Boolean(mask & 4), bottom = Boolean(mask & 8), horizontal = mask & 3;
            for (const [left, y] of [[-200, -200], [600, 200], [1400, 1400], [600, 200]]) {
                const n = {left, top: y, width: 180, height: 180};
                const result = sticky.geometry(n, composition, viewport, top, horizontal, bottom);
                if (!horizontal) {assert.equal(result.x, 0);}
                if (!top && !bottom) {assert.equal(result.y, 0);}
                if (left === 600 && y === 200) {
                    assert.equal(result.x, 0); assert.equal(result.y, 0); assert.equal(result.hidden, false);
                }
                const rotated = {left: y, top: left, width: n.height, height: n.width};
                const rotate = r => ({left: r.top, right: r.bottom, top: r.left, bottom: r.right});
                const mirror = sticky.geometry(rotated, rotate(composition), rotate(viewport),
                    Boolean(horizontal & 1), (top ? 1 : 0) + (bottom ? 2 : 0), Boolean(horizontal & 2));
                assert.equal(result.y, mirror.x); assert.equal(result.height, mirror.width);
                assert.equal(result.cropTop, mirror.cropLeft); assert.equal(result.hidden, mirror.hidden);
            }
        }
    });
    test(`${directory}: Bottom and both vertical limits at tiny and zero intersections`, () => {
        for (const top of [false, true]) {
            for (const available of [900, 199, 80, 1, 0, 1, 80, 199, 900]) {
                const area = {...composition, top: 900 - available, bottom: 900};
                const r = sticky.geometry({left: 400, top: 600, width: 180, height: 1200},
                    area, viewport, top, 3, true);
                assert.equal(r.hidden, available === 0);
                assert.equal(r.height, Math.min(available, 836));
            }
        }
    });
    test(`${directory}: axes are independent and returning to reference needs no saved state`, () => {
        let r = sticky.geometry(block, composition, viewport, true, 0);
        assert.equal(r.x, 0); assert.equal(r.y, 84); assert.equal(r.height, 80);
        r = sticky.geometry(block, composition, viewport, false, 1);
        assert.equal(r.x, 400); assert.equal(r.y, 0);
        r = sticky.geometry(block, composition, viewport, true, 1);
        assert.equal(r.x, 400); assert.equal(r.y, 84);
        r = sticky.geometry({...block, left: 400, top: 200}, composition, viewport, true, 1);
        assert.equal(r.x, 0); assert.equal(r.y, 0);
    });
    test(`${directory}: visibility down to zero in both directions`, () => {
        for (const available of [900, 201, 200, 199, 150, 1, 0, 1, 150, 199, 200, 201, 900]) {
            const area = {...composition, bottom: 64 + available};
            const small = sticky.geometry({...block, left: 400}, area, viewport, true, 0);
            assert.equal(small.hidden, available <= 0);
            const large = sticky.geometry({...block, left: 400, height: 1200}, area, viewport, true, 0);
            assert.equal(large.hidden, available <= 0);
            assert.equal(large.height, Math.min(available, 836));
        }
    });
    test(`${directory}: mirror Right and wide scroll viewport and zero width`, () => {
        let r = sticky.geometry({...block, left: 1100}, composition, viewport, false, 2);
        assert.equal(r.x, -220); assert.equal(r.y, 0);
        for (const horizontal of [1, 2]) {
            r = sticky.geometry({...block, width: 1800}, composition, {...viewport, right: 450}, true, horizontal);
            assert.equal(r.width, 150); assert.equal(r.hidden, false);
            assert.equal(block.left + r.x, 300);
        }
    });
    test(`${directory}: both horizontal edges follow geometry and return to normal`, () => {
        for (const left of [2000, 1100, 600, 100, -800, 600, 2000]) {
            const r = sticky.geometry({...block, left}, composition, viewport, false, 3);
            assert.equal(left + r.x, Math.max(300, Math.min(left, 880)));
            assert.equal(r.width, 320);
            assert.equal(r.y, 0);
        }
    });
    test(`${directory}: positive widths, small fitting blocks and two-axis reduction`, () => {
        for (const width of [900, 301, 300, 299, 250, 180, 1, 0, 1, 180, 250, 300, 301, 900]) {
            const area = {...viewport, right: viewport.left + width};
            const large = sticky.geometry({...block, width: 1800, height: 1200}, composition, area, true, 3);
            assert.equal(large.width, width);
            assert.equal(large.hidden, width <= 0);
            assert.equal(large.height, 836);
            const small = sticky.geometry({...block, width: 180}, composition, area, true, 3);
            assert.equal(small.hidden, width <= 0);
        }
        for (const horizontal of [1, 2, 3]) {
            const r = sticky.geometry({...block, left: 300, width: 1800, height: 1200}, composition,
                {...viewport, bottom: 264}, true, horizontal);
            assert.equal(r.hidden, false);
        }
    });
    test(`${directory}: reserve real scrollbar chrome without manufacturing cross-axis overflow`, () => {
        const normal = {left: 400, top: 200, width: 320, height: 400};
        const gutter = {width: 15, height: 15};
        let r = sticky.frameGeometry(normal, composition, {...viewport, bottom: 500}, true, 3, normal, gutter);
        assert.equal(r.width, 335); assert.equal(r.height, 300);
        assert.equal(r.horizontalBar, false); assert.equal(r.verticalBar, true);
        r = sticky.frameGeometry(normal, composition, {...viewport, right: 600}, false, 1, normal, gutter);
        assert.equal(r.width, 200); assert.equal(r.height, 415);
        assert.equal(r.horizontalBar, true); assert.equal(r.verticalBar, false);
        r = sticky.frameGeometry(normal, composition, {...viewport, right: 600, bottom: 500}, true, 1, normal, gutter);
        assert.equal(r.width, 200); assert.equal(r.height, 300);
        assert.equal(r.horizontalBar, true); assert.equal(r.verticalBar, true);
        // When even the added chrome cannot fit, the extra overflow is real and stays accessible.
        r = sticky.frameGeometry(normal, {...composition, right: 720}, {...viewport, bottom: 500}, true, 1, normal, gutter);
        assert.equal(r.width, 320); assert.equal(r.horizontalBar, true);
        r = sticky.frameGeometry(normal, composition, viewport, true, 3, normal, gutter);
        assert.equal(r.width, 320); assert.equal(r.height, 400);
        assert.equal(r.horizontalBar, false); assert.equal(r.verticalBar, false);
    });
    test(`${directory}: composition end, negative origins and offscreen composition`, () => {
        const r = sticky.geometry(block, {...composition, right: 450}, viewport, false, 1);
        assert.equal(r.width, 150); assert.equal(block.left + r.x + r.width, 450);
        assert.equal(sticky.geometry(block, {...composition, bottom: 60}, viewport, true, 1).hidden, true);
        assert.equal(sticky.geometry(block, {...composition, right: 250}, viewport, true, 1).hidden, true);
    });
}
