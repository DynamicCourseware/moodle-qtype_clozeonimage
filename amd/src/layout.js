// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Reserve composition space and initialise the optional student Wide view.
 *
 * @module     qtype_clozeonimage/layout
 * @copyright  2026 DynamicCourseware.org (Dominique Bauer)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {

    'use strict';

    const compositionSelector =
        '.qtype-clozeonimage-composition, .qtype-clozeonimage-preview-composition';
    const imageSelector = '.qtype-clozeonimage-image, .qtype-clozeonimage-preview-image';
    const controlSelector = '.qtype-clozeonimage-subquestion, .qtype-clozeonimage-preview-subquestion';
    const runtimeCompositionSelector = '.qtype-clozeonimage-composition';
    const openFixedDrawerSelector = '[data-region="fixed-drawer"].show';
    const drawerHiddenEvent = 'theme_boost/drawers:hidden';
    const layouts = new Map();
    let drawerOverflowCleanupInitialised = false;

    const panoramas = new Map();
    const drawerSelector = '.drawer.drawer-left, .drawer.drawer-right, #nav-drawer, [data-region="right-hand-drawer"]';
    const interactiveSelector = [
        '.qtype-clozeonimage-subquestion', 'input', 'select', 'textarea', 'button', 'a', 'label',
        '[tabindex]', '[contenteditable]:not([contenteditable="false"])', '[role]', '[data-action]',
        '[data-bs-toggle]', '.popover', '.qtype-clozeonimage-feedback-trigger',
    ].join(',');
    const activeClass = 'qtype-clozeonimage-panorama-active';
    const toggleSelector = '[data-action="clozeonimage-toggle-panorama"]';
    // Remember Wide questions through AJAX replacement, including when session storage is blocked.
    const activeQuestions = new Set();
    let modeControlsInitialised = false;
    let geometryEvents;
    let geometryMutations;
    let frame = 0;
    const transitions = new Map();
    let geometryObserver;
    let modePositionFrame = 0;

    /**
     * Permit session persistence only in Moodle's active quiz response form.
     * Review, reviewquestion and question preview deliberately never read attempt state.
     *
     * @param {HTMLElement} question Rendered question.
     * @returns {String|null} Server-provided site/usage/slot/question key, or null outside an attempt.
     */
    const attemptStorageKey = question => {
        const form = question.closest('form#responseform');
        if (document.body.id !== 'page-mod-quiz-attempt' ||
                !/^[1-9]\d*$/.test(form?.querySelector('input[type="hidden"][name="attempt"]')?.value ?? '')) {
            return null;
        }
        return question.querySelector('.qtype-clozeonimage-scroll')?.dataset.wideKey || null;
    };

    /**
     * Keep page-local AJAX memory separate between attempt, review and preview contexts.
     *
     * @param {HTMLElement} question Rendered question.
     * @returns {String} Context-scoped question identity.
     */
    const questionStateKey = question => attemptStorageKey(question) ?? `${document.body.id}:${question.id}`;

    /**
     * Read or change this question's attempt-scoped state; blocked storage leaves page-local mode working.
     *
     * @param {String|null} key Attempt-scoped key, or null on non-attempt pages.
     * @param {Boolean|undefined} wide Undefined to read, true to store, false to remove.
     * @returns {Boolean} Whether this question is stored as Wide.
     */
    const storedWideView = (key, wide = undefined) => {
        if (!key) {
            return false;
        }
        try {
            if (wide === true) {
                window.sessionStorage.setItem(key, '1');
            } else if (wide === false) {
                window.sessionStorage.removeItem(key);
            }
            return window.sessionStorage.getItem(key) === '1';
        } catch (error) {
            // Private-browser policies or a full storage quota must not break question interaction.
            return false;
        }
    };

    /**
     * Scrollbar endpoints align the complete visual union with the two usable gutter edges.
     * A fitting union travels right as scrollLeft increases; an overflowing union uses native leftward travel.
     *
     * @param {Number} width Image width (never scaled).
     * @param {Number} viewport Viewport width.
     * @param {Number} left Viewport's screen left edge.
     * @param {Number|null} previous Desired image screen left edge, or null at first load.
     * @param {Object} bounds Image/control visual union relative to the image origin.
     * @param {Number} gutter Horizontal inset inside each viewport border.
     * @returns {Object} Track padding, width and clamped scroll position.
     */
    const panGeometry = (width, viewport, left, previous = null,
            bounds = {minX: 0, maxX: width, minY: 0, maxY: 0}, gutter = 0) => {
        const usable = Math.max(0, viewport - 2 * gutter);
        const unionWidth = bounds.maxX - bounds.minX;
        const direction = unionWidth < usable ? 1 : -1;
        const space = gutter - bounds.minX;
        const range = Math.abs(usable - unionWidth);
        const desired = Number.isFinite(previous) ? previous : left + space + Math.max(0, usable - unionWidth) / 2;
        return {space, direction, range, track: viewport + range,
            scroll: Math.max(0, Math.min(range, (desired - left - space) * direction)),
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
     * Measure painted controls as well as their wrappers (Clear, feedback surfaces and focus outlines).
     * Screen-reader-only content must not turn its offscreen positioning into canvas space.
     *
     * @param {HTMLElement} composition Unchanged image/control coordinate system.
     * @param {Object} state Active panorama and its currently observed bounds elements.
     * @returns {Object|null} Image rectangle, image offset inside composition, and visual union, if rendered.
     */
    const measureContent = (composition, state) => {
        const image = composition.querySelector(imageSelector);
        if (!image?.getClientRects().length) {
            return null;
        }
        const imageRect = image.getBoundingClientRect();
        const origin = composition.getBoundingClientRect();
        const observed = new Set([image]);
        const rectangles = [];
        composition.querySelectorAll(controlSelector).forEach(control => {
            [control, ...control.querySelectorAll('*')].forEach(element => {
                if (element.closest('.accesshide, .visually-hidden, .sr-only, [hidden]')) {
                    return;
                }
                const rect = visibleRect(element);
                if (!rect) {
                    return;
                }
                observed.add(element);
                const style = window.getComputedStyle(element);
                const outline = style.outlineStyle === 'none' ? 0 :
                    Math.max(0, parseFloat(style.outlineWidth) + parseFloat(style.outlineOffset));
                const outset = {left: outline, right: outline, top: outline, bottom: outline};
                // Include theme focus-ring spread; inset shadows never extend visual bounds.
                style.boxShadow.split(/,(?![^(]*\))/).filter(shadow => !shadow.includes('inset')).forEach(shadow => {
                    const values = shadow.match(/-?[\d.]+px/g)?.map(parseFloat);
                    if (values?.length >= 3) {
                        const [x, y, blur, spread = 0] = values;
                        const radius = Math.max(0, spread + blur * 1.5);
                        outset.left = Math.max(outset.left, radius - x);
                        outset.right = Math.max(outset.right, radius + x);
                        outset.top = Math.max(outset.top, radius - y);
                        outset.bottom = Math.max(outset.bottom, radius + y);
                    }
                });
                rectangles.push({left: rect.left - imageRect.left - outset.left,
                    right: rect.right - imageRect.left + outset.right,
                    top: rect.top - imageRect.top - outset.top, bottom: rect.bottom - imageRect.top + outset.bottom});
            });
        });
        state.boundsElements.forEach(element => {
            if (!observed.has(element)) {
                geometryObserver?.unobserve(element);
            }
        });
        observed.forEach(element => geometryObserver?.observe(element));
        state.boundsElements = observed;
        return {image: imageRect, offsetX: imageRect.left - origin.left, offsetY: imageRect.top - origin.top,
            bounds: contentBounds(imageRect.width, imageRect.height, rectangles)};
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

    /** Schedule one geometry pass; animation frames run only during relevant CSS transitions. */
    const schedulePanoramas = () => {
        if (panoramas.size && !frame) {
            frame = window.requestAnimationFrame(updatePanoramas);
        }
    };

    /** Measure available screen space and preserve each composition's last screen position. */
    const updatePanoramas = () => {
        frame = 0;
        const visual = window.visualViewport;
        const screenLeft = visual ? visual.offsetLeft : 0;
        const screenRight = Math.min(document.documentElement.clientWidth,
            visual ? visual.offsetLeft + visual.width : document.documentElement.clientWidth);
        const screenTop = visual ? visual.offsetTop : 0;
        const screenBottom = screenTop + (visual ? visual.height : window.innerHeight);
        const drawers = [];
        document.querySelectorAll(drawerSelector).forEach(drawer => {
            geometryObserver?.observe(drawer);
            const rect = visibleRect(drawer);
            if (rect && rect.bottom > screenTop && rect.top < screenBottom) {
                drawers.push({...rect, side: drawer.matches('.drawer-left, #nav-drawer') ? 'left' : 'right'});
            }
        });
        const available = drawerBounds(screenLeft, screenRight, drawers);
        panoramas.forEach((state, composition) => {
            if (!composition.isConnected) {
                deactivatePanorama(composition, true);
                return;
            }
            const {viewport, anchor, track} = state;
            const content = measureContent(composition, state);
            const width = content?.image.width;
            if (!width || !composition.getClientRects().length) {
                return;
            }
            const previous = state.left === null ? state.initialLeft :
                state.left + state.space + state.direction * viewport.scrollLeft;
            const {left, right} = available;
            const anchorRect = anchor.getBoundingClientRect();
            const setStyle = (element, property, value) => {
                if (element.style[property] !== value) {
                    element.style[property] = value;
                }
            };
            setStyle(viewport, 'width', `${right - left}px`);
            setStyle(viewport, 'marginLeft', `${left - anchorRect.left}px`);
            const formulation = viewport.closest('.formulation').getBoundingClientRect();
            const borderGeometry = {
                '--panorama-left': left - anchorRect.left,
                '--panorama-width': right - left,
                '--panorama-left-wing': Math.max(0, Math.min(right, formulation.left) - left),
                '--panorama-right-wing': Math.max(0, right - Math.max(left, formulation.right)),
            };
            Object.entries(borderGeometry).forEach(([property, value]) => {
                const pixels = `${value}px`;
                if (anchor.style.getPropertyValue(property) !== pixels) {
                    anchor.style.setProperty(property, pixels);
                }
            });
            // Keep gutter space in the track geometry, without padding or changing the breakout border.
            const innerLeft = left + viewport.clientLeft;
            const gutter = parseFloat(window.getComputedStyle(viewport).getPropertyValue('--qtype-clozeonimage-panorama-gutter'));
            const geometry = panGeometry(width, viewport.clientWidth, innerLeft, previous, content.bounds, gutter || 0);
            setStyle(track, 'width', `${geometry.track}px`);
            setStyle(track, 'height', `${geometry.height}px`);
            state.marginLeft = geometry.space - content.offsetX;
            state.direction = geometry.direction;
            setStyle(composition, 'marginTop', `${geometry.top - content.offsetY}px`);
            viewport.scrollLeft = geometry.scroll;
            positionComposition(composition, state);
            viewport.classList.toggle('qtype-clozeonimage-panorama-can-drag', geometry.range > 0);
            state.left = innerLeft;
            state.space = geometry.space;
        });
        // A removed element cannot emit transitionend.
        transitions.forEach((properties, element) => {
            if (!element.isConnected) {
                transitions.delete(element);
            }
        });
        if (transitions.size) {
            schedulePanoramas();
        }
    };

    /**
     * Match narrow-union placement to scrollbar endpoints without changing the composition's coordinates.
     * Native scrolling subtracts scrollLeft; adding twice that offset gives the required positive travel.
     * The track already contains the entire range, so this cannot enlarge the native scroll range.
     *
     * @param {HTMLElement} composition Unchanged image/control coordinate system.
     * @param {Object} state Current pan geometry.
     */
    const positionComposition = (composition, state) => {
        const offset = state.direction === 1 ? 2 * state.viewport.scrollLeft : 0;
        const margin = `${state.marginLeft + offset}px`;
        if (composition.style.marginLeft !== margin) {
            composition.style.marginLeft = margin;
        }
    };

    /**
     * Add image-only pointer panning without intercepting form controls or vertical touch scrolling.
     *
     * @param {HTMLElement} viewport Native horizontal scroll container.
     * @param {HTMLElement} image Background image surface.
     * @param {HTMLElement} composition Image/control composition.
     * @param {Object} state Current pan geometry.
     * @returns {Function} Cleanup restoring normal browser image interaction.
     */
    const enableDrag = (viewport, image, composition, state) => {
        let drag = null;
        let suppressClick = false;
        const events = new AbortController();
        const signal = events.signal;
        const draggable = image.getAttribute('draggable');
        image.draggable = false;
        image.addEventListener('dragstart', event => event.preventDefault(), {signal});
        viewport.addEventListener('scroll', () => positionComposition(composition, state), {signal, passive: true});
        viewport.addEventListener('pointerdown', event => {
            const interactive = event.target.closest(interactiveSelector);
            if (event.button !== 0 || !event.isPrimary || event.target !== image ||
                    (interactive && interactive !== viewport && viewport.contains(interactive)) ||
                    viewport.scrollWidth <= viewport.clientWidth) {
                return;
            }
            suppressClick = false;
            drag = {id: event.pointerId, x: event.clientX, moved: false};
            viewport.setPointerCapture(event.pointerId);
            viewport.classList.add('qtype-clozeonimage-panorama-dragging');
        }, {signal});
        viewport.addEventListener('pointermove', event => {
            if (!drag || drag.id !== event.pointerId) {
                return;
            }
            const delta = event.clientX - drag.x;
            if (!drag.moved && Math.abs(delta) < 3) {
                return;
            }
            drag.moved = true;
            viewport.scrollLeft += delta * state.direction;
            positionComposition(composition, state);
            drag.x = event.clientX;
        }, {signal});
        const release = event => {
            if (!drag || drag.id !== event.pointerId) {
                return;
            }
            suppressClick = drag.moved && event.type === 'pointerup';
            drag = null;
            viewport.classList.remove('qtype-clozeonimage-panorama-dragging');
            if (viewport.hasPointerCapture(event.pointerId)) {
                viewport.releasePointerCapture(event.pointerId);
            }
        };
        ['pointerup', 'pointercancel', 'lostpointercapture'].forEach(type => viewport.addEventListener(type, release, {signal}));
        viewport.addEventListener('click', event => {
            if (suppressClick && event.target === viewport) {
                event.preventDefault();
                event.stopPropagation();
            }
            suppressClick = false;
        }, {capture: true, signal});
        return () => {
            if (drag) {
                release({pointerId: drag.id});
            }
            events.abort();
            if (draggable === null) {
                image.removeAttribute('draggable');
            } else {
                image.setAttribute('draggable', draggable);
            }
        };
    };

    /** Install geometry listeners only while at least one panorama is active. */
    const observePanoramas = () => {
        if (geometryEvents) {
            return;
        }
        geometryEvents = new AbortController();
        const signal = geometryEvents.signal;
        if (window.ResizeObserver) {
            geometryObserver = new window.ResizeObserver(schedulePanoramas);
        }
        geometryMutations = new window.MutationObserver(records => {
            if (records.some(record => {
                const target = record.target.nodeType === 1 ? record.target : record.target.parentElement;
                if (!target?.closest('.qtype-clozeonimage-scroll')) {
                    return true;
                }
                const composition = target.closest(runtimeCompositionSelector);
                // Ignore our own viewport/track/composition styles, but observe actual content changes.
                return composition && (record.type !== 'attributes' || target !== composition);
            })) {
                schedulePanoramas();
            }
        });
        geometryMutations.observe(document.body, {
            subtree: true, childList: true, characterData: true,
            attributes: true, attributeFilter: ['class', 'style', 'hidden', 'open'],
        });
        ['focusin', 'focusout'].forEach(type => document.addEventListener(type, schedulePanoramas, {signal}));
        window.addEventListener('resize', schedulePanoramas, {signal});
        document.addEventListener('scroll', event => {
            if (!event.target.matches?.('.qtype-clozeonimage-scroll')) {
                schedulePanoramas();
            }
        }, {capture: true, signal});
        window.visualViewport?.addEventListener('resize', schedulePanoramas, {signal});
        window.visualViewport?.addEventListener('scroll', schedulePanoramas, {signal});
        ['theme_boost/drawers:shown', drawerHiddenEvent].forEach(type =>
            document.addEventListener(type, schedulePanoramas, {signal}));
        ['transitionrun', 'transitionend', 'transitioncancel'].forEach(type => {
            document.addEventListener(type, event => {
                const element = event.target;
                if (!element.matches(drawerSelector) && !element.querySelector(`.${activeClass}`) &&
                        !element.closest(runtimeCompositionSelector)?.closest(`.${activeClass}`)) {
                    return;
                }
                if (type === 'transitionrun') {
                    if (!transitions.has(element)) {
                        transitions.set(element, new Set());
                    }
                    transitions.get(element).add(event.propertyName);
                } else {
                    transitions.get(element)?.delete(event.propertyName);
                    if (!transitions.get(element)?.size) {
                        transitions.delete(element);
                    }
                }
                schedulePanoramas();
            }, {signal});
        });
    };

    /** Release all panorama geometry observation when the last active question exits. */
    const stopObservingPanoramas = () => {
        geometryEvents?.abort();
        geometryEvents = null;
        geometryObserver?.disconnect();
        geometryObserver = null;
        geometryMutations?.disconnect();
        geometryMutations = null;
        transitions.clear();
        window.cancelAnimationFrame(frame);
        frame = 0;
    };

    /**
     * Save only the inline properties panorama owns, leaving height reservation independent.
     *
     * @param {HTMLElement} element Element being modified.
     * @param {Array} properties CSS properties to restore on exit.
     * @returns {Function} Restoration callback.
     */
    const saveStyles = (element, properties) => {
        const saved = properties.map(property => [property,
            element.style.getPropertyValue(property), element.style.getPropertyPriority(property)]);
        return () => saved.forEach(([property, value, priority]) => {
            if (value) {
                element.style.setProperty(property, value, priority);
            } else {
                element.style.removeProperty(property);
            }
        });
    };

    /**
     * Update the localized action and accessible state without replacing the focused button.
     *
     * @param {HTMLElement} question Question whose view changed.
     * @param {Boolean} active Whether panorama is active.
     */
    const updateToggle = (question, active) => {
        const button = question.querySelector(toggleSelector);
        if (button) {
            button.hidden = false;
            button.textContent = active ? button.dataset.panoramaLabel : button.dataset.normalLabel;
            button.setAttribute('aria-pressed', String(active));
        }
    };

    /**
     * Enter panorama using the existing composition as the image/control coordinate system.
     *
     * @param {HTMLElement} composition Runtime composition.
     */
    const activatePanorama = composition => {
        if (panoramas.has(composition)) {
            return;
        }
        const question = composition.closest('.que.clozeonimage');
        const viewport = composition.closest('.qtype-clozeonimage-scroll');
        const image = composition.querySelector('.qtype-clozeonimage-image');
        if (!question || !viewport || !image) {
            return;
        }
        const focused = document.activeElement;
        const initialLeft = image.getClientRects().length ? image.getBoundingClientRect().left : null;
        const direction = window.getComputedStyle(composition).direction;
        const restoreViewport = saveStyles(viewport, ['width', 'margin-left']);
        const viewportAttributes = ['tabindex', 'role', 'aria-label'].map(name => [name, viewport.getAttribute(name)]);
        viewport.setAttribute('tabindex', '0');
        viewport.setAttribute('role', 'region');
        // Reuse the server-localized mode name without changing the toggle's current action.
        viewport.setAttribute('aria-label', question.querySelector(toggleSelector).dataset.normalLabel);
        const restoreComposition = saveStyles(composition, ['direction', 'margin-left', 'margin-top']);
        const anchor = document.createElement('div');
        anchor.className = 'qtype-clozeonimage-panorama-anchor';
        viewport.before(anchor);
        anchor.append(viewport);
        const track = document.createElement('div');
        track.className = 'qtype-clozeonimage-panorama-track';
        viewport.append(track);
        track.append(composition);
        composition.style.direction = direction;
        question.classList.add(activeClass);
        viewport.classList.add('qtype-clozeonimage-panorama');
        const state = {question, viewport, anchor, track, initialLeft, left: null, space: 0, direction: -1, marginLeft: 0,
            boundsElements: new Set(),
            storageKey: attemptStorageKey(question),
            questionKey: questionStateKey(question),
            restoreViewport, restoreComposition, viewportAttributes};
        state.stopDrag = enableDrag(viewport, image, composition, state);
        panoramas.set(composition, state);
        storedWideView(state.storageKey, true);
        if (question.id) {
            activeQuestions.add(state.questionKey);
        }
        updateToggle(question, true);
        observePanoramas();
        for (let element = anchor; element; element = element.parentElement) {
            geometryObserver?.observe(element);
        }
        geometryObserver?.observe(composition);
        // Eliminate an old normal-view document scroll offset before measuring screen bounds.
        // Boost enables smooth root scrolling; this reset must finish before geometry and toggle anchoring.
        window.scrollTo({left: 0, top: window.scrollY, behavior: 'instant'});
        if (focused?.isConnected && document.activeElement !== focused) {
            focused.focus({preventScroll: true});
        }
        schedulePanoramas();
    };

    /**
     * Test this Normal question's own rendered boxes against the document's scrollable inline end.
     * Use document coordinates (not the currently scrolled screen), respecting local clipping so
     * an already internally contained image is not mistaken for a document overflow owner.
     *
     * @param {HTMLElement} composition Normal runtime composition.
     * @returns {Boolean} Whether this question contributes horizontal document overflow.
     */
    const ownsDocumentOverflow = composition => {
        const root = document.scrollingElement;
        if (!root || root.scrollWidth <= root.clientWidth + 1) {
            return false;
        }
        const question = composition.closest('.que.clozeonimage');
        const rtl = window.getComputedStyle(root).direction === 'rtl';
        const elements = [question, question.querySelector(':scope > .content'),
            composition.closest('.formulation'), composition,
            ...composition.querySelectorAll(`${imageSelector}, ${controlSelector}`)];
        return elements.some(element => {
            if (!element) {
                return false;
            }
            const rect = visibleRect(element);
            if (!rect) {
                return false;
            }
            let {left, right} = rect;
            for (let ancestor = element.parentElement;
                ancestor && ancestor !== document.body && ancestor !== document.documentElement;
                ancestor = ancestor.parentElement) {
                const style = window.getComputedStyle(ancestor);
                if (style.overflowX !== 'visible' || /\b(paint|content|strict)\b/.test(style.contain)) {
                    const edge = ancestor.getBoundingClientRect().left + ancestor.clientLeft;
                    left = Math.max(left, edge);
                    right = Math.min(right, edge + ancestor.clientWidth);
                }
            }
            // Negative inline-start overflow is not scrollable in LTR (the converse applies in RTL).
            return right > left && (rtl ? left + window.scrollX < -1 : right + window.scrollX > root.clientWidth + 1);
        });
    };

    /**
     * An explicit Wide request also contains this page's other overflowing Cloze on Image questions.
     * Re-measure after each conversion. Each snapshot member is attempted at most once, so even
     * unrelated overflow or a question that cannot activate cannot keep this loop running.
     * Restoration, AJAX, resize and drawer observers deliberately do not call this coordinator.
     *
     * @param {HTMLElement} composition Explicitly requested runtime composition.
     */
    const requestPanorama = composition => {
        activatePanorama(composition);
        if (!panoramas.has(composition)) {
            return;
        }
        const remaining = new Set([...document.querySelectorAll('.que.clozeonimage')]
            .map(question => question.querySelector(runtimeCompositionSelector))
            .filter(item => item && item !== composition && !panoramas.has(item)));
        let next;
        do {
            // Complete the new viewport geometry before testing the next Normal question.
            window.cancelAnimationFrame(frame);
            updatePanoramas();
            next = [...remaining].find(ownsDocumentOverflow);
            if (next) {
                remaining.delete(next);
                activatePanorama(next);
            }
        } while (next);
    };

    /**
     * Remove the temporary track and restore native Moodle layout and pointer behaviour.
     *
     * @param {HTMLElement} composition Composition leaving panoramic view.
     * @param {Boolean} remember Keep the mode for an AJAX replacement of this question.
     */
    const deactivatePanorama = (composition, remember = false) => {
        const state = panoramas.get(composition);
        if (!state) {
            return;
        }
        const {question, viewport, anchor, track} = state;
        const focused = document.activeElement;
        state.stopDrag();
        viewport.scrollLeft = 0;
        track.before(composition);
        track.remove();
        anchor.before(viewport);
        anchor.remove();
        state.restoreViewport();
        state.restoreComposition();
        state.viewportAttributes.forEach(([name, value]) => {
            if (value === null) {
                viewport.removeAttribute(name);
            } else {
                viewport.setAttribute(name, value);
            }
        });
        viewport.classList.remove('qtype-clozeonimage-panorama', 'qtype-clozeonimage-panorama-can-drag');
        question.classList.remove(activeClass);
        if (!remember) {
            activeQuestions.delete(state.questionKey);
            storedWideView(state.storageKey, false);
        }
        updateToggle(question, false);
        panoramas.delete(composition);
        // Reconnect observation only to surviving active questions; no stale detached targets.
        stopObservingPanoramas();
        if (panoramas.size) {
            observePanoramas();
            panoramas.forEach((remaining, item) => {
                for (let element = remaining.anchor; element; element = element.parentElement) {
                    geometryObserver?.observe(element);
                }
                geometryObserver?.observe(item);
            });
            schedulePanoramas();
        }
        if (!remember) {
            window.scrollTo({left: 0, top: window.scrollY, behavior: 'instant'});
        }
        if (focused !== viewport && focused?.isConnected && document.activeElement !== focused) {
            focused.focus({preventScroll: true});
        }
    };

    /**
     * Position only explicit transitions, after queued geometry and resize-observer work has rendered.
     * A newer mode action supersedes pending positioning; restoration/AJAX never schedules it.
     *
     * @param {Function} callback Position/focus work for the latest user action.
     */
    const afterModeLayout = callback => {
        window.cancelAnimationFrame(modePositionFrame);
        modePositionFrame = window.requestAnimationFrame(() => {
            modePositionFrame = window.requestAnimationFrame(() => {
                modePositionFrame = 0;
                callback();
            });
        });
    };

    /**
     * Find a native answer in DOM/tab order, excluding hidden sentinels and auxiliary controls.
     *
     * @param {HTMLElement} question Question returning to Normal view.
     * @returns {HTMLElement|undefined} First available native answer (readonly text remains eligible).
     */
    const firstAnswer = question => {
        const answers = [...question.querySelectorAll('.qtype-clozeonimage-subquestion input, ' +
            '.qtype-clozeonimage-subquestion select, .qtype-clozeonimage-subquestion textarea')]
            .filter(element => !element.matches(':disabled, [type="hidden"], [type="button"], [type="submit"], ' +
                '[type="reset"], [type="image"]') && element.tabIndex >= 0 && visibleRect(element) &&
                !element.closest('[inert], .qtype-clozeonimage-clear-choice, .qtype-clozeonimage-review-surface'));
        return answers.find(element => element.type !== 'radio' || !element.name || element.checked ||
            !answers.some(other => other.type === 'radio' && other.name === element.name &&
                other.form === element.form && other.checked));
    };

    /**
     * Compensate only the visual Y displacement caused by mode reflow, including nested page scrollers.
     * Native scroll limits still apply; no animation or horizontal repositioning is introduced here.
     *
     * @param {HTMLElement} element Persistent focused control.
     * @param {Number} top Its viewport Y before exit.
     */
    const preserveVerticalContext = (element, top) => {
        for (let parent = element.parentElement; parent && parent !== document.scrollingElement;
                parent = parent.parentElement) {
            if (parent.scrollHeight > parent.clientHeight && /auto|scroll/.test(getComputedStyle(parent).overflowY)) {
                parent.scrollBy({top: element.getBoundingClientRect().top - top, behavior: 'instant'});
            }
        }
        window.scrollBy({top: element.getBoundingClientRect().top - top, behavior: 'instant'});
    };

    /** Exit all rendered Wide questions; absent quiz pages retain their own attempt state. */
    const exitPanoramas = () => {
        [...panoramas.keys()].forEach(composition => {
            if (composition.isConnected) {
                deactivatePanorama(composition);
            }
        });
    };

    /** Register lightweight delegated controls and AJAX lifecycle detection once per page. */
    const initialiseModeControls = () => {
        if (modeControlsInitialised) {
            return;
        }
        modeControlsInitialised = true;
        document.addEventListener('click', event => {
            const button = event.target.closest(toggleSelector);
            if (!button) {
                return;
            }
            const question = button.closest('.que.clozeonimage');
            const composition = question?.querySelector(runtimeCompositionSelector);
            if (composition) {
                const entering = !panoramas.has(composition);
                if (!entering) {
                    exitPanoramas();
                } else {
                    requestPanorama(composition);
                }
                afterModeLayout(() => {
                    if (!button.isConnected || panoramas.has(composition) !== entering) {
                        return;
                    }
                    button.scrollIntoView({block: 'end', inline: 'nearest', behavior: 'instant'});
                    // Native keyboard/assistive activation has no pointer click count.
                    if (entering && event.detail === 0 && document.activeElement === button) {
                        panoramas.get(composition)?.viewport.focus({preventScroll: true});
                    }
                });
            }
        });
        // Window bubbling runs after document-level feedback handlers, regardless of AMD load order.
        window.addEventListener('keydown', event => {
            if (event.key !== 'Escape' || event.defaultPrevented) {
                return;
            }
            if ([...panoramas.keys()].some(composition => composition.isConnected)) {
                event.preventDefault();
                const focused = document.activeElement;
                const focusedFrame = [...panoramas.values()].find(state => state.viewport === focused);
                const top = focused.getBoundingClientRect().top;
                exitPanoramas();
                const restoreContext = () => {
                    if (focusedFrame?.question.isConnected &&
                            [focused, document.body].includes(document.activeElement)) {
                        // All-disabled review questions fall back to the local toggle, without end-aligning it.
                        const target = firstAnswer(focusedFrame.question) || focusedFrame.question.querySelector(toggleSelector);
                        target.focus({preventScroll: true});
                        target.scrollIntoView({block: 'nearest', inline: 'nearest', behavior: 'instant'});
                    } else if (focused.isConnected && focused !== document.body && document.activeElement === focused) {
                        preserveVerticalContext(focused, top);
                    }
                };
                // Compensate the synchronous Info reflow before paint, then check once after layout work settles.
                if (!focusedFrame) {
                    restoreContext();
                }
                afterModeLayout(restoreContext);
            }
        });
        const lifecycle = new window.MutationObserver(records => {
            // Recheck layouts only when composition elements are added or removed; init is idempotent.
            const changed = records.some(record => [...record.addedNodes, ...record.removedNodes].some(node =>
                node.nodeType === 1 && (node.matches(compositionSelector) || node.querySelector(compositionSelector))));
            if (changed) {
                init();
            }
        });
        lifecycle.observe(document.body, {subtree: true, childList: true});
    };

    /**
     * Reveal the server-rendered localized button and restore a remembered AJAX view.
     *
     * @param {HTMLElement} composition Runtime or teacher preview composition.
     */
    const initPanorama = composition => {
        if (!composition.matches(runtimeCompositionSelector)) {
            return;
        }
        const question = composition.closest('.que.clozeonimage');
        if (!question) {
            return;
        }
        initialiseModeControls();
        updateToggle(question, panoramas.has(composition));
        const key = questionStateKey(question);
        if (activeQuestions.has(key) || storedWideView(attemptStorageKey(question))) {
            activatePanorama(composition);
        }
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
     * Set a composition's height to include its image and every control below it.
     *
     * @param {HTMLElement} composition The composition containing the image and controls.
     */
    const updateCompositionHeight = composition => {
        if (!composition) {
            return;
        }

        const image = composition.querySelector(imageSelector);
        let requiredHeight = image ? image.getBoundingClientRect().height : 0;
        composition.querySelectorAll(controlSelector).forEach(control => {
            requiredHeight = Math.max(
                requiredHeight,
                control.offsetTop + control.getBoundingClientRect().height
            );
        });

        const height = `${requiredHeight}px`;
        if (composition.style.height !== height) {
            composition.style.height = height;
        }
    };

    /**
     * Initialise downward-space reservation for all compositions on the page.
     */
    const init = () => {
        if (!drawerOverflowCleanupInitialised) {
            document.addEventListener(drawerHiddenEvent, removeStaleDrawerOverflow);
            drawerOverflowCleanupInitialised = true;
        }

        layouts.forEach((state, composition) => {
            if (!composition.isConnected) {
                deactivatePanorama(composition, true);
                state.observer?.disconnect();
                state.image?.removeEventListener('load', state.update);
                layouts.delete(composition);
            }
        });
        document.querySelectorAll(compositionSelector).forEach(composition => {
            if (layouts.has(composition)) {
                updateCompositionHeight(composition);
                initPanorama(composition);
                return;
            }

            const update = () => updateCompositionHeight(composition);
            update();
            initPanorama(composition);

            const image = composition.querySelector(imageSelector);
            const state = {image, update};
            layouts.set(composition, state);
            if (image && !image.complete) {
                image.addEventListener('load', update, {once: true});
            }

            if (window.ResizeObserver) {
                const observer = new window.ResizeObserver(update);
                if (image) {
                    observer.observe(image);
                }
                composition.querySelectorAll(controlSelector).forEach(control => observer.observe(control));
                state.observer = observer;
            }
        });
    };

    return {
        init,
        updateCompositionHeight,
        panGeometry,
        contentBounds,
        drawerBounds,
    };
});
