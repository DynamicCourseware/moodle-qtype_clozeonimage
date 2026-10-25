// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Temporary visual sticky placement, independent of the composition's natural geometry.
 *
 * @module qtype_clozeonimage/sticky
 * @copyright 2026 DynamicCourseware.org (Dominique Bauer)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    'use strict';

    const states = new WeakMap();
    const selector = '.qtype-clozeonimage-block[data-sticky-top="1"], ' +
        '.qtype-clozeonimage-block[data-sticky-bottom="1"], ' +
        '.qtype-clozeonimage-block[data-sticky-horizontal="1"], ' +
        '.qtype-clozeonimage-block[data-sticky-horizontal="2"], ' +
        '.qtype-clozeonimage-block[data-sticky-horizontal="3"]';
    const sides = ['Top', 'Right', 'Bottom', 'Left'];
    const properties = ['translate', 'width', 'height', 'maxHeight', 'maxWidth', 'minWidth', 'overflow', 'visibility',
        ...sides.map(side => `padding${side}`), ...sides.map(side => `border${side}Width`)];

    /**
     * Constrain enabled axes only. Right is the reflection of Left.
     * @param {Object} normal Natural screen rectangle.
     * @param {Object} composition Persistent composition screen rectangle.
     * @param {Object} viewport Usable screen rectangle.
     * @param {Boolean} top Pin the top edge.
     * @param {Number} horizontal Bit mask: 1 left, 2 right, 3 both; 0 off.
     * @param {Boolean} bottom Pin the bottom edge, independently of Top.
     * @returns {Object} Temporary translation, viewport size and visibility.
     */
    const geometry = (normal, composition, viewport, top, horizontal, bottom = false) => {
        const left = Math.max(composition.left, viewport.left);
        const right = Math.min(composition.right, viewport.right);
        const start = Math.max(composition.top, viewport.top);
        const end = Math.min(composition.bottom, viewport.bottom);
        let x = normal.left, y = normal.top, width = normal.width, height = normal.height;
        let hidden = right <= left || end <= start;
        let cropTop = 0;
        if (top || bottom) {
            if (top) {
                y = Math.max(y, start);
            }
            if (bottom) {
                y = Math.min(y, end - normal.height);
            }
            if (top && bottom && normal.height > end - start) {
                y = start;
            }
            const visibleTop = Math.max(start, y);
            height = Math.max(0, Math.min(end, y + normal.height) - visibleTop);
            cropTop = Math.max(0, visibleTop - y);
            y = visibleTop;
            hidden ||= height <= 0;
        }
        let cropLeft = 0;
        if (horizontal) {
            // Clamp the enabled edges using reference geometry, never the last scroll direction.
            // The opposite edge may enter naturally; clip its viewport to the visible intersection.
            if (horizontal === 1 || horizontal === 3) {
                x = Math.max(x, left);
            }
            if (horizontal === 2 || horizontal === 3) {
                x = Math.min(x, right - normal.width);
            }
            if (horizontal === 3 && normal.width > right - left) {
                x = left;
            }
            const visibleLeft = Math.max(left, x);
            width = Math.max(0, Math.min(right, x + normal.width) - visibleLeft);
            cropLeft = Math.max(0, visibleLeft - x);
            x = visibleLeft;
            hidden ||= width <= 0;
        }
        hidden ||= Math.min(x + width, right) <= Math.max(x, left) ||
            Math.min(y + height, end) <= Math.max(y, start);
        return {x: x - normal.left, y: y - normal.top, width, height, hidden, cropLeft, cropTop};
    };

    /**
     * Reserve native scrollbar chrome without consuming the other axis's reference content space.
     * At most two bars can become necessary; the third pass places their final outer rectangle.
     * @param {Object} normal Reference border box.
     * @param {Object} area Composition bounds.
     * @param {Object} viewport Usable viewport.
     * @param {Boolean} top Sticky Top.
     * @param {Number} horizontal Horizontal flags.
     * @param {Object} extent Natural overflow including padding and borders.
     * @param {Object} gutter Native vertical-bar width and horizontal-bar height.
     * @param {Boolean} bottom Sticky Bottom.
     * @returns {Object} Temporary outer geometry and required bars.
     */
    const frameGeometry = (normal, area, viewport, top, horizontal, extent, gutter, bottom = false) => {
        let horizontalBar = false, verticalBar = false, result;
        for (let pass = 0; pass < 3; pass++) {
            const frame = {...normal,
                width: normal.width + (verticalBar ? gutter.width : 0),
                height: normal.height + (horizontalBar ? gutter.height : 0)};
            // An inactive axis keeps normal page scrolling. Added chrome still stays in the composition.
            if (!horizontal) {
                frame.width = Math.min(frame.width, Math.max(0, area.right - normal.left));
            }
            if (!top && !bottom) {
                frame.height = Math.min(frame.height, Math.max(0, area.bottom - normal.top));
            }
            result = geometry(frame, area, viewport, top, horizontal, bottom);
            const needsHorizontal = extent.width > result.width - (verticalBar ? gutter.width : 0) + .5;
            const needsVertical = extent.height > result.height - (horizontalBar ? gutter.height : 0) + .5;
            horizontalBar ||= needsHorizontal;
            verticalBar ||= needsVertical;
        }
        return {...result, horizontalBar, verticalBar};
    };

    /**
     * Restore the natural rendered size synchronously before layout measurement (never painted).
     * Scroll position and the live answer elements survive this measurement.
     * @param {HTMLElement} composition Runtime composition.
     */
    const prepare = composition => {
        composition.querySelectorAll(selector).forEach(block => {
            let state = states.get(block);
            if (!state) {
                const content = document.createElement('div');
                content.className = 'qtype-clozeonimage-sticky-content';
                content.append(...block.childNodes);
                block.append(content);
                state = {content, styles: {}, scrollLeft: 0, scrollTop: 0, tabindex: block.getAttribute('tabindex')};
                states.set(block, state);
            } else {
                if (!state.hidden) {
                    state.scrollLeft = block.scrollLeft;
                    state.scrollTop = block.scrollTop;
                }
                properties.forEach(name => { block.style[name] = state.styles[name] || ''; });
                state.content.style.cssText = '';
            }
        });
    };

    /**
     * Remember natural geometry after the common layout has fitted and positioned the blocks.
     * @param {HTMLElement} composition Runtime composition.
     */
    const measure = composition => {
        const origin = composition.getBoundingClientRect();
        composition.querySelectorAll(selector).forEach(block => {
            const state = states.get(block);
            const rect = block.getBoundingClientRect();
            const style = getComputedStyle(block);
            state.normal = {left: rect.left - origin.left, top: rect.top - origin.top,
                width: rect.width, height: rect.height};
            state.contentWidth = rect.width - ['paddingLeft', 'paddingRight', 'borderLeftWidth', 'borderRightWidth']
                .reduce((sum, name) => sum + (parseFloat(style[name]) || 0), 0);
            properties.forEach(name => { state.styles[name] = block.style[name]; });
            state.padding = sides.map(side => style[`padding${side}`]);
            state.borders = sides.map(side => parseFloat(style[`border${side}Width`]) || 0);
            const borderX = state.borders[1] + state.borders[3];
            const borderY = state.borders[0] + state.borders[2];
            state.extent = {width: Math.max(rect.width, block.scrollWidth + borderX),
                height: Math.max(rect.height, block.scrollHeight + borderY)};
            // Measure this block's actual bars (including non-overlay/theme scrollbars), synchronously.
            state.content.style.width = `${state.contentWidth}px`;
            block.style.width = `${rect.width}px`;
            block.style.height = `${rect.height}px`;
            block.style.overflow = 'scroll';
            state.gutter = {width: Math.max(0, block.offsetWidth - block.clientWidth - borderX),
                height: Math.max(0, block.offsetHeight - block.clientHeight - borderY)};
            ['width', 'height', 'overflow'].forEach(name => { block.style[name] = state.styles[name]; });
            state.content.style.width = '';
        });
    };

    /**
     * Park focus on the composition with an explanation, retaining the original live field.
     * Restore only if the learner has not moved focus elsewhere in the meantime.
     * @param {HTMLElement} block Block being hidden.
     * @param {Object} state Block state.
     * @param {Boolean} hidden Whether the block must be hidden.
     */
    const visibility = (block, state, hidden) => {
        const viewport = block.closest('.qtype-clozeonimage-scroll');
        if (hidden && block.contains(document.activeElement)) {
            state.focus = document.activeElement;
            if (!state.notice) {
                state.notice = document.createElement('div');
                state.notice.className = 'visually-hidden';
                state.notice.setAttribute('role', 'status');
                viewport.before(state.notice);
            }
            viewport.tabIndex = -1;
            viewport.setAttribute('aria-label', viewport.dataset.stickyHiddenLabel);
            viewport.focus({preventScroll: true});
            state.notice.textContent = viewport.dataset.stickyHiddenLabel;
        }
        state.hidden = hidden;
        block.inert = hidden;
        block.style.visibility = hidden ? 'hidden' : state.styles.visibility;
        if (!hidden && state.focus) {
            if (document.activeElement === viewport && state.focus.isConnected) {
                state.focus.focus({preventScroll: true});
            }
            state.focus = null;
            if (state.notice) {
                state.notice.textContent = '';
            }
        }
    };

    /**
     * Update only visual placement on scroll; never remeasure composition bounds here.
     * @param {HTMLElement} composition Runtime composition.
     * @param {Object} bounds Natural composition bounds.
     * @param {Object} viewport Usable area, excluding Moodle chrome and ancestor clipping.
     */
    const apply = (composition, bounds, viewport) => {
        const origin = composition.getBoundingClientRect();
        const area = {left: origin.left + bounds.minX, right: origin.left + bounds.maxX,
            top: origin.top + bounds.minY, bottom: origin.top + bounds.maxY};
        composition.querySelectorAll(selector).forEach(block => {
            const state = states.get(block);
            if (!state?.normal) {
                return;
            }
            const normal = {...state.normal, left: origin.left + state.normal.left, top: origin.top + state.normal.top};
            const result = frameGeometry(normal, area, viewport, block.dataset.stickyTop === '1',
                Number(block.dataset.stickyHorizontal), state.extent, state.gutter, block.dataset.stickyBottom === '1');
            if (result.hidden) {
                // Keep the last scrollport intact at zero visible area, so native clamping loses no offsets.
                visibility(block, state, true);
                return;
            }
            const reducedWidth = result.width < normal.width;
            const reducedHeight = result.height < normal.height;
            const scrolling = result.horizontalBar || result.verticalBar;
            block.style.translate = `${result.x}px ${result.y}px`;
            // Padding belongs to the fixed content inside the viewport. Even a 1px viewport can then
            // shrink correctly without padding imposing a 26px minimum outer box.
            state.content.style.width = scrolling ? `${state.contentWidth}px` : '';
            state.content.style.padding = scrolling ? state.padding.join(' ') : '';
            state.content.style.boxSizing = scrolling ? 'content-box' : '';
            sides.forEach((side, index) => {
                block.style[`padding${side}`] = scrolling ? '0' : state.styles[`padding${side}`];
                const dimension = index % 2 ? result.width : result.height;
                block.style[`border${side}Width`] = scrolling ? `${dimension < (index % 2 ? state.borders[1] + state.borders[3] :
                    state.borders[0] + state.borders[2]) ? 0 : state.borders[index]}px`
                    : state.styles[`border${side}Width`];
            });
            block.style.minWidth = scrolling ? '0' : state.styles.minWidth;
            block.style.width = scrolling ? `${result.width}px` : state.styles.width;
            block.style.height = scrolling ? `${result.height}px` : state.styles.height;
            block.style.maxWidth = scrolling ? `${result.width}px` : state.styles.maxWidth;
            block.style.maxHeight = scrolling ? `${result.height}px` : state.styles.maxHeight;
            block.style.overflow = scrolling ? 'auto' : state.styles.overflow;
            if (scrolling) {
                block.tabIndex = 0;
                block.setAttribute('role', 'region');
                block.setAttribute('aria-label', block.dataset.stickyLabel);
            } else {
                if (state.tabindex === null) {
                    block.removeAttribute('tabindex');
                } else {
                    block.setAttribute('tabindex', state.tabindex);
                }
                block.removeAttribute('role');
                block.removeAttribute('aria-label');
            }
            if (!reducedWidth) {
                state.manualHorizontalScroll = false;
            }
            if (!state.manualHorizontalScroll) {
                state.scrollLeft = result.cropLeft;
            }
            if (!reducedHeight) {
                state.manualVerticalScroll = false;
            }
            if (!state.manualVerticalScroll) {
                state.scrollTop = result.cropTop;
            }
            // Reassigning even the same scroll offset cancels native smooth keyboard scrolling.
            // Restore only offsets actually displaced by natural measurement or a changed crop.
            if (Math.abs(block.scrollLeft - state.scrollLeft) > .5) {
                block.scrollLeft = state.scrollLeft;
            }
            state.appliedScrollLeft = block.scrollLeft;
            if (Math.abs(block.scrollTop - state.scrollTop) > .5) {
                block.scrollTop = state.scrollTop;
            }
            state.appliedScrollTop = block.scrollTop;
            visibility(block, state, result.hidden);
        });
    };

    /**
     * Remember deliberate internal scrolling without resetting the learner's position.
     * @param {HTMLElement} target Scrolled element.
     */
    const rememberScroll = target => {
        const state = states.get(target);
        if (state && !state.hidden) {
            if (Math.abs(target.scrollLeft - state.appliedScrollLeft) > .5) {
                state.manualHorizontalScroll = true;
            }
            if (Math.abs(target.scrollTop - state.appliedScrollTop) > .5) {
                state.manualVerticalScroll = true;
            }
            state.scrollLeft = target.scrollLeft;
            state.scrollTop = target.scrollTop;
        }
    };

    return {geometry, frameGeometry, prepare, measure, apply, rememberScroll};
});
