// This file is part of Moodle - https://moodle.org/
// Moodle is free software under the GNU GPL v3 or later.
/**
 * Pure rectangle operations in the permanent composition coordinate system.
 * @module qtype_clozeonimage/composition_geometry
 * @copyright 2026 DynamicCourseware.org (Dominique Bauer)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    'use strict';
    /**
     * Union the base and persistent content; ratio surplus goes right/bottom.
     * @param {Object} base Saved rectangle.
     * @param {Array} rectangles Persistent element bounds.
     * @returns {Object} Effective bounds.
     */
    const extent = (base, rectangles = []) => {
        const minX = Math.min(base.x, ...rectangles.map(r => r.left));
        const minY = Math.min(base.y, ...rectangles.map(r => r.top));
        let width = Math.max(base.x + base.width, ...rectangles.map(r => r.right)) - minX;
        let height = Math.max(base.y + base.height, ...rectangles.map(r => r.bottom)) - minY;
        if (base.locked && base.ratio > 0) {
            width = Math.max(width, height * base.ratio);
            height = width / base.ratio;
        }
        return {minX, minY, maxX: minX + width, maxY: minY + height};
    };
    /**
     * Resolve an associated anchor without scaling the block.
     * @param {Object} image Image rectangle.
     * @param {Object} relative Normalised anchor coordinates.
     * @param {Object} size Actual block size.
     * @param {Number} anchor One of the nine anchors.
     * @returns {Object} Block top-left.
     */
    const attached = (image, relative, size, anchor) => ({
        x: image.x + relative.x * image.width - anchor % 3 / 2 * size.width,
        y: image.y + relative.y * image.height - Math.floor(anchor / 3) / 2 * size.height,
    });
    /**
     * Reassociate at the exact visible anchor (values outside 0..1 are intentional).
     * @param {Object} image Image rectangle.
     * @param {Object} position Block top-left.
     * @param {Object} size Actual block size.
     * @param {Number} anchor One of the nine anchors.
     * @returns {Object} Normalised anchor coordinates.
     */
    const relative = (image, position, size, anchor) => ({
        x: (position.x + anchor % 3 / 2 * size.width - image.x) / image.width,
        y: (position.y + Math.floor(anchor / 3) / 2 * size.height - image.y) / image.height,
    });
    /**
     * Resize a corner without cropping permanent content; the opposite corner stays fixed.
     * @param {Object} base Original rectangle.
     * @param {Number} dx Horizontal movement.
     * @param {Number} dy Vertical movement.
     * @param {Boolean} topLeft Which corner moves.
     * @param {Object|null} content Permanent content bounds.
     */
    const resizeCorner = (base, dx, dy, topLeft, content = null) => {
        const right = base.x + base.width, bottom = base.y + base.height;
        let width = Math.max(1, base.width + (topLeft ? -dx : dx));
        let height = Math.max(1, base.height + (topLeft ? -dy : dy));
        if (content) {
            width = Math.max(width, topLeft ? right - content.minX : content.maxX - base.x);
            height = Math.max(height, topLeft ? bottom - content.minY : content.maxY - base.y);
        }
        if (base.locked) {
            width = Math.max(width, height * base.ratio);
            height = width / base.ratio;
        }
        return {...base, x: topLeft ? right - width : base.x, y: topLeft ? bottom - height : base.y, width, height};
    };
    /**
     * Technical rendering envelope; no silent truncation or saturation.
     * @param {Object} r Rectangle.
     */
    const validRectangle = r => [r.x, r.y, r.width, r.height].every(v => Number.isFinite(v) && Math.abs(v) <= 4194303)
        && r.width > 0 && r.height > 0;
    return {extent, attached, relative, resizeCorner, validRectangle};
});
