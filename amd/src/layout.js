// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Measure, place and scroll the shared composition coordinate system.
 *
 * @module     qtype_clozeonimage/layout
 * @copyright  2026 DynamicCourseware.org (Dominique Bauer)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/pagehelpers', 'core/pending', 'qtype_clozeonimage/composition_geometry',
    'qtype_clozeonimage/sticky'], function(PageHelpers, Pending, Geometry, Sticky) {

    'use strict';

    const compositionSelector =
        '.qtype-clozeonimage-composition, .qtype-clozeonimage-preview-composition';
    const imageSelector = '.qtype-clozeonimage-image, .qtype-clozeonimage-preview-image';
    const controlSelector = '.qtype-clozeonimage-subquestion, .qtype-clozeonimage-preview-subquestion, ' +
        '.qtype-clozeonimage-scene-image, .qtype-clozeonimage-preview-scene-image';
    const runtimeCompositionSelector = '.qtype-clozeonimage-composition';
    const openFixedDrawerSelector = '[data-region="fixed-drawer"].show';
    const drawerHiddenEvent = 'theme_boost/drawers:hidden';
    const layouts = new Map();
    let drawerOverflowCleanupInitialised = false;
    let answerFocusEvent = null;

    const blockGeometry = new WeakMap();
    const normalPlacements = new WeakMap();
    const activeGeometry = new WeakMap();
    const activeEdits = new WeakSet();
    const adaptiveSelector = 'input[data-clozeonimage-field-min][data-clozeonimage-field-max]';
    const transientSelector = '.popover, .qtype-clozeonimage-numerical-validation, .qtype-clozeonimage-review-surface, ' +
        '.qtype-clozeonimage-preview-number, .qtype-clozeonimage-preview-selection-mark, ' +
        '.qtype-clozeonimage-resize, .qtype-clozeonimage-image-resize, .qtype-clozeonimage-composition-resize';
    let caretProbe;

    const drawerSelector = '.drawer.drawer-left, .drawer.drawer-right, #nav-drawer, [data-region="right-hand-drawer"]';

    /**
     * Keep the union origin at a fixed gutter in the scroll track, using only current bounds.
     *
     * @param {Number} viewport Available viewport width.
     * @param {Object} bounds Persistent image/content union relative to the image.
     * @param {Number} scroll Current native scroll position, independent of the origin.
     * @param {Number} gutter Horizontal inset at each end.
     * @returns {Object} Current track dimensions, origin and clamped native scroll position.
     */
    const scrollGeometry = (viewport, bounds, scroll = 0, gutter = 0) => {
        const track = Math.max(viewport, bounds.maxX - bounds.minX + 2 * gutter);
        const range = track - viewport;
        return {space: gutter - bounds.minX, range, track,
            scroll: Math.max(0, Math.min(range, scroll)),
            top: Math.max(0, -bounds.minY), height: bounds.maxY - bounds.minY};
    };

    /**
     * Union the image with visual control rectangles in image-relative coordinates.
     *
     * @param {Number} width Image width.
     * @param {Number} height Image height.
     * @param {Array} rectangles Visible control rectangles, including protruding descendants.
     * @returns {Object} Four visual extrema without changing any control coordinate.
     */
    const contentBounds = (width, height, rectangles) => ({
        minX: Math.min(0, ...rectangles.map(rect => rect.left)),
        maxX: Math.max(width, ...rectangles.map(rect => rect.right)),
        minY: Math.min(0, ...rectangles.map(rect => rect.top)),
        maxY: Math.max(height, ...rectangles.map(rect => rect.bottom)),
    });

    /**
     * Measure persistent block content, including protruding descendants and text.
     * Transient feedback/focus decorations must never move the composition origin.
     * Screen-reader-only content must not turn its offscreen positioning into canvas space.
     *
     * @param {HTMLElement} composition Unchanged image/control coordinate system.
     * @param {Boolean} omitBase Measure content only for explicit editor reduction.
     * @returns {Object|null} Image rectangle, image offset inside composition, and visual union, if rendered.
     */
    const measureContent = (composition, omitBase = false) => {
        const image = composition.querySelector(imageSelector);
        const base = composition.dataset.composition ? JSON.parse(composition.dataset.composition) : null;
        if (!base && !image?.getClientRects().length) {
            return null;
        }
        const origin = composition.getBoundingClientRect();
        const imageRect = base ? {left: origin.left, top: origin.top, width: base.width, height: base.height}
            : image.getBoundingClientRect();
        const observed = new Set(image ? [image] : []);
        const rectangles = [];
        composition.querySelectorAll(controlSelector).forEach(control => {
            [control, ...control.querySelectorAll('*')].forEach(element => {
                if (element.closest('.accesshide, .visually-hidden, .sr-only, [hidden]') ||
                        element.closest(transientSelector)) {
                    return;
                }
                const rect = visibleRect(element);
                if (!rect) {
                    return;
                }
                observed.add(element);
                rectangles.push({left: rect.left - imageRect.left, right: rect.right - imageRect.left,
                    top: rect.top - imageRect.top, bottom: rect.bottom - imageRect.top});
                // Element boxes alone miss unwrapped text protruding beyond a fixed-width parent.
                element.childNodes.forEach(node => {
                    if (node.nodeType !== Node.TEXT_NODE || !node.textContent.trim()) {
                        return;
                    }
                    const range = document.createRange();
                    range.selectNodeContents(node);
                    Array.from(range.getClientRects()).forEach(text => rectangles.push({
                        left: text.left - imageRect.left, right: text.right - imageRect.left,
                        top: text.top - imageRect.top, bottom: text.bottom - imageRect.top,
                    }));
                });
            });
        });
        return {image: imageRect, offsetX: imageRect.left - origin.left, offsetY: imageRect.top - origin.top,
            bounds: base ? Geometry.extent(omitBase ? {...base, x: rectangles.length ? rectangles[0].left : base.x,
                y: rectangles.length ? rectangles[0].top : base.y, width: 0, height: 0, locked: false} : base, rectangles)
                : contentBounds(imageRect.width, imageRect.height, rectangles)};
    };

    /**
     * Constrain a visible interval by independently measured left and right drawers.
     *
     * @param {Number} left Visible browser left edge.
     * @param {Number} right Visible browser right edge.
     * @param {Array} drawers Visible rectangles with a structural side.
     * @returns {Object} Available interval.
     */
    const drawerBounds = (left, right, drawers) => {
        const visible = drawers.filter(rect => rect.right > left && rect.left < right && rect.right > rect.left);
        const start = Math.max(left, ...visible.filter(rect => rect.side === 'left').map(rect => rect.right));
        const end = Math.min(right, ...visible.filter(rect => rect.side === 'right').map(rect => rect.left));
        return {left: Math.min(right, start), right: Math.max(Math.min(right, start), end)};
    };

    /**
     * Read a visible rectangle, including Boost 5.3's inset-clipped anchored drawer.
     *
     * @param {HTMLElement} element Element to measure.
     * @returns {Object|null} Visible rectangle, or null when hidden.
     */
    const visibleRect = element => {
        const style = window.getComputedStyle(element);
        if (!element.getClientRects().length || style.visibility !== 'visible' || Number(style.opacity) === 0) {
            return null;
        }
        const rect = element.getBoundingClientRect();
        let {left, right, top, bottom} = rect;
        const inset = style.clipPath.match(/^inset\(([^)]+)\)/);
        if (inset) {
            const values = inset[1].split(' round ')[0].trim().split(/\s+/);
            const [t, r = t, b = t, l = r] = values;
            const pixels = (value, size) => parseFloat(value) * (value.endsWith('%') ? size / 100 : 1);
            left += pixels(l, rect.width);
            right -= pixels(r, rect.width);
            top += pixels(t, rect.height);
            bottom -= pixels(b, rect.height);
        }
        return right > left && bottom > top ? {left, right, top, bottom} : null;
    };

    /**
     * Veto only Boost's false mobile drawer close during a horizontally scrolled answer focus.
     * core/pagehelpers measures the body's screen right edge, which includes document scrollX.
     * On a desktop this can falsely report a small screen and reflow the question mid-click.
     * The current focus event phase distinguishes this synchronous automatic close from a
     * later explicit drawer action. Normal mobile closing is unaffected.
     *
     * @param {CustomEvent} event Moodle's cancelable drawer-hide notification.
     */
    const preserveAnswerClick = event => {
        const target = answerFocusEvent?.target;
        if (!event.cancelable || event.defaultPrevented || !event.detail?.drawerInstance ||
                !answerFocusEvent?.eventPhase || target !== document.activeElement ||
                !target.matches('input, select, textarea, button') ||
                !target.closest(runtimeCompositionSelector) ||
                window.scrollX <= 0 || !window.matchMedia('(min-width: 992px)').matches || !PageHelpers.isSmall()) {
            return;
        }
        event.preventDefault();
        // Boost creates this pending operation before dispatching hide, but its canceled path
        // returns without resolving it. Complete that canceled operation via Moodle's API.
        Pending.complete('theme_boost/drawers:close');
    };

    /**
     * Remove a stale Boost page scroll lock after the last fixed drawer closes.
     */
    const removeStaleDrawerOverflow = () => {
        const page = document.getElementById('page');
        if (!page ||
                !document.querySelector(runtimeCompositionSelector) ||
                document.querySelector(openFixedDrawerSelector) ||
                page.style.overflow !== 'hidden') {
            return;
        }
        page.style.removeProperty('overflow');
    };

    /**
     * Minimum outer width for in-flow responses, independent of their authored CSS offsets.
     * The normal padding already contains the 4px result outline and its 1px offset.
     * Text/images intentionally positioned outside the block do not impose a width limit.
     *
     * @param {HTMLElement} block A rendered block.
     * @returns {Number} Minimum border-box width, or zero for an automatic block.
     */
    const minimumBlockWidth = block => {
        if (block.classList.contains('qtype-clozeonimage-block-auto')) {
            return 0;
        }
        const controls = [...block.querySelectorAll('.qtype-clozeonimage-control')];
        const widths = controls.filter(control => control.getClientRects().length)
            .map(control => control.getBoundingClientRect().width);
        const style = getComputedStyle(block);
        const number = property => parseFloat(style[property]) || 0;
        const clearance = widths.length ? 5 : 0;
        const sides = number('borderLeftWidth') + number('borderRightWidth') +
            Math.max(clearance, number('paddingLeft')) + Math.max(clearance, number('paddingRight'));
        return Math.max(40, Math.ceil(Math.max(0, ...widths) + sides));
    };

    /**
     * Apply the same minimum in attempts/reviews, retaining the selected outer anchor.
     * Keep the saved manual width as the requested width; only the effective minimum changes.
     *
     * @param {HTMLElement} block Student block.
     */
    const fitRuntimeBlock = block => {
        if (!block.getClientRects().length) {
            return;
        }
        const rect = block.getBoundingClientRect();
        const previous = blockGeometry.get(block) || {width: rect.width, height: rect.height};
        const anchor = Number(block.dataset.anchor ?? 4);
        const left = parseFloat(block.style.left) || 0;
        const top = parseFloat(block.style.top) || 0;
        block.style.minWidth = `${minimumBlockWidth(block)}px`;
        const current = block.getBoundingClientRect();
        block.style.left = `${left + (anchor % 3) / 2 * (previous.width - current.width)}px`;
        block.style.top = `${top + Math.floor(anchor / 3) / 2 * (previous.height - current.height)}px`;
        blockGeometry.set(block, {width: current.width, height: current.height});
    };

    /**
     * Place the unchanged image coordinate system inside a normal-view track.
     * @param {HTMLElement} composition Student composition.
     * @param {Object} bounds Persistent content bounds.
     */
    const placeNormal = (composition, bounds) => {
        const viewport = composition.closest('.qtype-clozeonimage-scroll');
        if (!viewport) {
            return;
        }
        let state = normalPlacements.get(composition);
        if (!state) {
            const track = document.createElement('div');
            track.className = 'qtype-clozeonimage-normal-track';
            composition.before(track);
            track.append(composition);
            state = {track, gutterX: bounds.minX < 0 ? 8 : 0, gutterY: bounds.minY < 0 ? 8 : 0};
            normalPlacements.set(composition, state);
        }
        // Capture the initial margin once; later size changes move the image, never this corner.
        const geometry = scrollGeometry(viewport.clientWidth, bounds, viewport.scrollLeft, state.gutterX);
        state.x = geometry.space;
        state.y = state.gutterY - bounds.minY;
        state.bounds = bounds;
        composition.style.marginLeft = `${state.x}px`;
        composition.style.marginTop = `${state.y}px`;
        state.track.style.width = `${state.x + bounds.maxX}px`;
        state.track.style.height = `${state.y + bounds.maxY}px`;
    };

    /**
     * Visible screen area, excluding fixed drawers, navigation and clipping ancestors.
     * @param {HTMLElement} input Active answer.
     */
    const answerViewport = input => {
        const visual = window.visualViewport;
        const area = {left: visual?.offsetLeft || 0, top: visual?.offsetTop || 0,
            right: (visual?.offsetLeft || 0) + (visual?.width || document.documentElement.clientWidth),
            bottom: (visual?.offsetTop || 0) + (visual?.height || window.innerHeight)};
        const drawers = [...document.querySelectorAll(drawerSelector)].map(element => {
            const rect = visibleRect(element);
            return rect && {...rect, side: element.matches('.drawer-left, #nav-drawer') ? 'left' : 'right'};
        }).filter(rect => rect && rect.bottom > area.top && rect.top < area.bottom);
        Object.assign(area, drawerBounds(area.left, area.right, drawers));
        document.querySelectorAll('.navbar.fixed-top').forEach(element => {
            const rect = visibleRect(element);
            if (rect && rect.top <= area.top) {
                area.top = Math.max(area.top, rect.bottom);
            }
        });
        for (let parent = input.parentElement; parent && parent !== document.body; parent = parent.parentElement) {
            const style = getComputedStyle(parent);
            const rect = parent.getBoundingClientRect();
            if (style.overflowX !== 'visible' && parent.clientWidth) {
                area.left = Math.max(area.left, rect.left + parent.clientLeft);
                area.right = Math.min(area.right, rect.left + parent.clientLeft + parent.clientWidth);
            }
            if (style.overflowY !== 'visible' && parent.clientHeight) {
                area.top = Math.max(area.top, rect.top + parent.clientTop);
                area.bottom = Math.min(area.bottom, rect.top + parent.clientTop + parent.clientHeight);
            }
        }
        return {left: area.left + 8, right: area.right - 8, top: area.top + 8, bottom: area.bottom - 8};
    };

    /**
     * Minimum translation of a fitting interval; an oversized target uses its input/caret instead.
     * @param {Number} start Target start.
     * @param {Number} end Target end.
     * @param {Number} low Visible start.
     * @param {Number} high Visible end.
     */
    const visibilityShift = (start, end, low, high) => start < low ? low - start : end > high ? high - end : 0;

    /**
     * Current insertion/selection endpoint, measured with the actual input font and internal scroll.
     * @param {HTMLInputElement} input Active answer.
     */
    const caretX = input => {
        if (!caretProbe) {
            caretProbe = document.createElement('span');
            caretProbe.className = 'qtype-clozeonimage-field-measure';
            caretProbe.setAttribute('aria-hidden', 'true');
            document.body.append(caretProbe);
        }
        const style = getComputedStyle(input);
        ['fontFamily', 'fontSize', 'fontWeight', 'fontStyle', 'fontStretch', 'fontVariant',
            'fontFeatureSettings', 'fontVariationSettings', 'letterSpacing', 'textTransform'].forEach(property => {
            caretProbe.style[property] = style[property];
        });
        const endpoint = input.selectionDirection === 'backward' ? input.selectionStart : input.selectionEnd;
        caretProbe.textContent = input.value.slice(0, endpoint ?? input.value.length);
        const rect = input.getBoundingClientRect();
        const content = rect.left + input.clientLeft + (parseFloat(style.paddingLeft) || 0);
        const x = content + caretProbe.getBoundingClientRect().width - input.scrollLeft;
        return Math.min(rect.right - 4, Math.max(rect.left + 4, x));
    };

    /**
     * Remember dimensions without changing focus, selection or the browser's input scroll.
     * @param {HTMLElement} composition Student composition.
     */
    const rememberActive = composition => {
        const input = document.activeElement;
        const block = input?.closest('.qtype-clozeonimage-block');
        if (!input?.matches(adaptiveSelector) || !block || !composition.contains(input)) {
            return null;
        }
        const state = {input, block, field: input.getBoundingClientRect(), box: block.getBoundingClientRect(),
            area: answerViewport(input), screen: [document.documentElement.clientWidth, window.innerHeight,
                window.visualViewport?.width, window.visualViewport?.height]};
        activeGeometry.set(composition, state);
        return state;
    };

    /**
     * Move only as far as necessary after growth, prioritising the active field in oversized blocks.
     * @param {HTMLElement} composition Student composition.
     */
    const keepActiveVisible = composition => {
        const previous = activeGeometry.get(composition);
        const edited = activeEdits.has(composition);
        activeEdits.delete(composition);
        const current = rememberActive(composition);
        if (!current || !previous || previous.input !== current.input) {
            return;
        }
        const {input, block, field, box, area} = current;
        const grew = field.width > previous.field.width + .1 || box.width > previous.box.width + .1 ||
            box.height > previous.box.height + .1;
        const resized = current.screen.some((value, index) => value !== previous.screen[index]);
        const oversized = field.width > area.right - area.left;
        const shrank = field.width < previous.field.width - .1 || box.width < previous.box.width - .1 ||
            box.height < previous.box.height - .1;
        if ((!grew && !shrank && !resized && !(oversized && edited)) ||
                area.right <= area.left || area.bottom <= area.top) {
            return;
        }
        // Prefer a fitting block; otherwise expose the field or insertion point without recentring.
        const xTarget = box.width <= area.right - area.left ? box : field;
        const yTarget = box.height <= area.bottom - area.top ? box : field;
        const x = field.width > area.right - area.left ? caretX(input) : null;
        let dx = visibilityShift(x === null ? xTarget.left : x - 4, x === null ? xTarget.right : x + 4,
            area.left, area.right);
        let dy = visibilityShift(yTarget.top, yTarget.bottom, area.top, area.bottom);
        if (Math.abs(dx) < .5 && Math.abs(dy) < .5) {
            return;
        }
        // Visibility uses native scrolling only, independently of the measured composition origin.
        const scroll = element => {
            const before = input.getBoundingClientRect();
            element.scrollBy({left: -dx, top: -dy, behavior: 'instant'});
            const after = input.getBoundingClientRect();
            dx -= after.left - before.left;
            dy -= after.top - before.top;
        };
        for (let parent = block; parent && parent !== document.body; parent = parent.parentElement) {
            if (parent === document.scrollingElement) {
                break;
            }
            if (/auto|scroll/.test(`${getComputedStyle(parent).overflowX} ${getComputedStyle(parent).overflowY}`)) {
                scroll(parent);
            }
        }
        scroll(window);
        rememberActive(composition);
    };

    /**
     * Map layers to positive ranks without changing DOM, answer or keyboard order.
     * @param {HTMLElement} composition Coordinate container.
     */
    const rankLayers = composition => {
        const elements = [...composition.querySelectorAll(':scope > [data-layer]')];
        elements.sort((a, b) => Number(a.dataset.layer) - Number(b.dataset.layer));
        elements.forEach((element, index) => { element.style.zIndex = String(index + 1); });
        composition.querySelectorAll('.qtype-clozeonimage-composition-resize').forEach(handle => {
            handle.style.zIndex = String(elements.length + 2);
        });
    };

    /**
     * Reserve downward space and decorate the image/block union without moving their coordinates.
     *
     * @param {HTMLElement} composition The composition containing the image and controls.
     * @param {HTMLInputElement|null} editedInput Answer just measured after an input/font event.
     */
    const updateCompositionHeight = (composition, editedInput = null) => {
        if (!composition || composition.dataset.gestureBatch) {
            return;
        }

        if (composition.matches(runtimeCompositionSelector)) {
            Sticky.prepare(composition);
            if (editedInput && editedInput === document.activeElement) {
                activeEdits.add(composition);
            }
            composition.querySelectorAll('.qtype-clozeonimage-block').forEach(block => {
                if (block.dataset.relativeImage && !block.dataset.attachmentInitialised) {
                    const image = [...composition.querySelectorAll('[data-image-key]')]
                        .find(item => item.dataset.imageKey === block.dataset.relativeImage);
                    if (image) {
                        const point = Geometry.attached({x: parseFloat(image.style.left), y: parseFloat(image.style.top),
                            width: image.getBoundingClientRect().width, height: image.getBoundingClientRect().height},
                            {x: Number(block.dataset.relativeX), y: Number(block.dataset.relativeY)},
                            block.getBoundingClientRect(), Number(block.dataset.anchor));
                        block.style.left = `${point.x}px`;
                        block.style.top = `${point.y}px`;
                        block.dataset.attachmentInitialised = '1';
                    }
                }
                fitRuntimeBlock(block);
            });
        }
        rankLayers(composition);
        const image = composition.querySelector(imageSelector);
        const imageRect = image?.getBoundingClientRect();
        const rectangles = [];
        composition.querySelectorAll(controlSelector).forEach(control => {
            const rect = control.getBoundingClientRect();
            rectangles.push({left: control.offsetLeft, top: control.offsetTop,
                right: control.offsetLeft + rect.width, bottom: control.offsetTop + rect.height});
        });
        const bounds = ((composition.matches(runtimeCompositionSelector) || composition.dataset.composition) &&
            measureContent(composition)?.bounds) ||
            contentBounds(imageRect?.width || 0, imageRect?.height || 0, rectangles);
        const surface = composition.querySelector('.qtype-clozeonimage-surface');
        if (surface) {
            const base = JSON.parse(composition.dataset.composition);
            const style = `position:absolute;left:${bounds.minX}px;top:${bounds.minY}px;` +
                `width:${bounds.maxX - bounds.minX}px;height:${bounds.maxY - bounds.minY}px;background:${base.background};`;
            if (surface.style.cssText !== style) {
                surface.style.cssText = style;
            }
            composition.dispatchEvent(new CustomEvent('clozeonimage:bounds', {detail: bounds}));
        }
        const height = `${Math.max(0, bounds.maxY)}px`;
        if (composition.style.height !== height) {
            composition.style.height = height;
        }
        const properties = {
            left: bounds.minX, top: bounds.minY,
            width: bounds.maxX - bounds.minX, height: bounds.maxY - bounds.minY,
        };
        Object.entries(properties).forEach(([name, value]) => {
            const property = `--clozeonimage-bound-${name}`;
            const pixels = `${value}px`;
            if (composition.style.getPropertyValue(property) !== pixels) {
                composition.style.setProperty(property, pixels);
            }
        });
        if (composition.matches(runtimeCompositionSelector)) {
            placeNormal(composition, bounds);
            Sticky.measure(composition);
            Sticky.apply(composition, bounds, answerViewport(composition));
            keepActiveVisible(composition);

        }
        const track = composition.closest('.qtype-clozeonimage-preview-track');
        if (track && !composition.dataset.frozenPlacement) {
            const gutter = 20;
            track.style.width = `${properties.width + 2 * gutter}px`;
            track.style.height = `${properties.height + 2 * gutter}px`;
            composition.style.marginLeft = `${gutter - bounds.minX}px`;
            composition.style.marginTop = `${gutter - bounds.minY}px`;
        }
        const grip = composition.dataset.composition ? null : composition.querySelector('.qtype-clozeonimage-image-resize');
        if (grip) {
            grip.style.left = `${imageRect?.width || 0}px`;
            grip.style.height = `${imageRect?.height || 0}px`;
        }
    };

    /**
     * Initialise downward-space reservation for all compositions on the page.
     */
    const init = () => {
        if (!drawerOverflowCleanupInitialised) {
            const updateSticky = () => {
                layouts.forEach((state, composition) => {
                    const placement = normalPlacements.get(composition);
                    if (placement && composition.isConnected) {
                        Sticky.apply(composition, placement.bounds, answerViewport(composition));
                    }
                });
            };
            let stickyFrame = 0;
            const scheduleSticky = event => {
                Sticky.rememberScroll(event.target);
                if (!stickyFrame) {
                    stickyFrame = requestAnimationFrame(() => {
                        stickyFrame = 0;
                        updateSticky();
                    });
                }
            };
            document.addEventListener('scroll', scheduleSticky, true);
            window.visualViewport?.addEventListener('resize', scheduleSticky);
            window.visualViewport?.addEventListener('scroll', scheduleSticky);
            document.addEventListener('transitionend', scheduleSticky);
            document.addEventListener(drawerHiddenEvent, removeStaleDrawerOverflow);
            document.addEventListener('focusin', event => {
                answerFocusEvent = event;
            }, true);
            document.addEventListener('theme_boost/drawers:hide', preserveAnswerClick);
            document.addEventListener('focusin', event => {
                if (event.target.matches?.(adaptiveSelector)) {
                    const composition = event.target.closest(runtimeCompositionSelector);
                    if (composition) {
                        rememberActive(composition);
                    }
                }
            });
            drawerOverflowCleanupInitialised = true;
        }

        layouts.forEach((state, composition) => {
            if (!composition.isConnected) {
                state.observer?.disconnect();
                composition.removeEventListener('load', state.update, true);
                state.image?.removeEventListener('load', state.update);
                state.mutations?.disconnect();
                document.fonts?.removeEventListener('loadingdone', state.update);
                window.removeEventListener('resize', state.update);
                layouts.delete(composition);
            }
        });
        document.querySelectorAll(compositionSelector).forEach(composition => {
            if (layouts.has(composition)) {
                updateCompositionHeight(composition);
                return;
            }

            const update = () => updateCompositionHeight(composition);
            update();

            const image = composition.querySelector(imageSelector);
            const state = {image, update};
            layouts.set(composition, state);
            if (composition.matches(runtimeCompositionSelector) || composition.dataset.composition) {
                composition.addEventListener('load', update, true);
                document.fonts?.ready.then(update);
                document.fonts?.addEventListener('loadingdone', update);
                window.addEventListener('resize', update);
            } else if (image && !image.complete) {
                image.addEventListener('load', update, {once: true});
            }

            if (window.ResizeObserver) {
                let frame = 0;
                const schedule = () => {
                    if (!frame) {
                        frame = requestAnimationFrame(() => {
                            frame = 0;
                            update();
                        });
                    }
                };
                const observer = new window.ResizeObserver(schedule);
                if (image) {
                    observer.observe(image);
                }
                const observed = new Set();
                const watch = () => {
                    observed.forEach(element => {
                        if (!element.isConnected) {
                            observer.unobserve(element);
                            observed.delete(element);
                        }
                    });
                    composition.querySelectorAll(controlSelector).forEach(control => {
                        const descendants = composition.matches(runtimeCompositionSelector) || composition.dataset.composition
                            ? '*' : '.qtype-clozeonimage-control';
                        // Sticky outer boxes are temporary viewports. Observe their natural content instead.
                        [control, ...control.querySelectorAll(descendants)].filter(element => !element.closest(transientSelector) &&
                            !element.querySelector(':scope > .qtype-clozeonimage-sticky-content'))
                            .forEach(element => {
                            if (!observed.has(element)) {
                                observed.add(element);
                                observer.observe(element);
                            }
                        });
                    });
                };
                watch();
                state.mutations = new MutationObserver(() => {
                    watch();
                    schedule();
                });
                state.mutations.observe(composition, {subtree: true, childList: true, characterData: true});
                state.observer = observer;
            }
        });
    };

    return {
        init,
        updateCompositionHeight,
        minimumBlockWidth,
        measureContent,
        rankLayers,
        visibilityShift,
        contentBounds,
        scrollGeometry,
        drawerBounds,
    };
});
