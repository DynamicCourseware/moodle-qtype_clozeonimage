// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Size explicitly adaptive fields from their current value, never from expected answers.
 *
 * @module qtype_clozeonimage/field_width
 * @copyright 2026 DynamicCourseware.org (Dominique Bauer)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['qtype_clozeonimage/layout'], function(Layout) {
    'use strict';

    const selector = 'input[data-clozeonimage-field-min][data-clozeonimage-field-max]';
    let initialised = false;
    let probe;
    let frame = 0;
    let resizeObserver;
    const observed = new Set();
    const measuredFonts = new WeakMap();
    const fontProperties = ['fontFamily', 'fontSize', 'fontWeight', 'fontStyle', 'fontStretch', 'fontVariant',
        'fontFeatureSettings', 'fontVariationSettings', 'letterSpacing', 'textTransform'];
    const chromeProperties = ['paddingLeft', 'paddingRight', 'borderLeftWidth', 'borderRightWidth'];

    /**
     * Identify changes that affect text measurement, excluding temporary sticky viewport geometry.
     * @param {CSSStyleDeclaration} style Computed input style.
     * @returns {String} Font, chrome and root-size signature.
     */
    const sizingKey = style => [...fontProperties, ...chromeProperties].map(property => style[property])
        .concat(getComputedStyle(document.documentElement).fontSize).join('|');

    /**
     * Measure actual text with the field font, including spacing and border-box chrome.
     * @param {HTMLInputElement} input Adaptive response field.
     * @param {Boolean} edited Whether this is an actual input/change event.
     */
    const fit = (input, edited = false) => {
        if (!input.isConnected || !input.getClientRects().length) {
            return;
        }
        const style = getComputedStyle(input);
        measuredFonts.set(input, sizingKey(style));
        fontProperties.forEach(property => {
            probe.style[property] = style[property];
        });
        probe.textContent = input.value;
        const chrome = chromeProperties
            .reduce((total, property) => total + (parseFloat(style[property]) || 0), 0);
        const unit = input.dataset.clozeonimageFieldUnit || 'px';
        const scale = unit === 'em' ? parseFloat(style.fontSize) :
            unit === 'rem' ? parseFloat(getComputedStyle(document.documentElement).fontSize) : 1;
        const natural = Math.ceil(probe.getBoundingClientRect().width + chrome + 2);
        const width = Math.max(Number(input.dataset.clozeonimageFieldMin) * scale,
            Math.min(Number(input.dataset.clozeonimageFieldMax) * scale, natural));
        if (input.style.width !== `${width}px`) {
            input.style.width = `${width}px`;
        }
        // Also keep the caret accessible once an oversized field reaches its maximum width.
        // Layout never changes the input's focus, selection or internal text scrolling.
        const composition = input.closest('.qtype-clozeonimage-composition');
        if (composition) {
            Layout.updateCompositionHeight(composition, edited ? input : null);
        }
    };

    /** Coalesce deferred font, resize and AJAX updates. */
    const schedule = () => {
        if (!frame) {
            frame = requestAnimationFrame(() => {
                frame = 0;
                Layout.init();
                observed.forEach(input => {
                    if (!input.isConnected) {
                        resizeObserver?.unobserve(input);
                        observed.delete(input);
                    }
                });
                document.querySelectorAll(selector).forEach(input => {
                    if (!observed.has(input)) {
                        observed.add(input);
                        resizeObserver?.observe(input);
                    }
                    fit(input);
                });
            });
        }
    };

    /** Enable native input/paste/delete handling, including readonly restored responses. */
    const init = () => {
        if (!initialised) {
            initialised = true;
            probe = document.createElement('span');
            probe.className = 'qtype-clozeonimage-field-measure';
            probe.setAttribute('aria-hidden', 'true');
            document.body.append(probe);
            // A rem-sized sentinel also detects root-size changes made through stylesheet rules.
            // It is outside the composition and cannot contribute to its persistent bounds.
            const rootScale = document.createElement('span');
            rootScale.className = 'qtype-clozeonimage-field-measure';
            rootScale.setAttribute('aria-hidden', 'true');
            rootScale.style.cssText = 'width:1rem;height:1rem;';
            document.body.append(rootScale);
            if (window.ResizeObserver) {
                resizeObserver = new ResizeObserver(schedule);
                resizeObserver.observe(rootScale);
            }
            ['input', 'change'].forEach(type => document.addEventListener(type, event => {
                if (event.target.matches?.(selector)) {
                    fit(event.target, true);
                }
            }));
            window.addEventListener('resize', schedule);
            window.addEventListener('pageshow', schedule);
            document.fonts?.ready.then(schedule);
            document.fonts?.addEventListener('loadingdone', schedule);
            new MutationObserver(records => {
                const inputs = [...document.querySelectorAll(selector)];
                const changed = records.some(record => {
                    if (record.type === 'attributes') {
                        // Authored/theme font changes can affect a field without changing its box yet.
                        // A visual sticky resize must not trigger another natural measurement.
                        return inputs.some(input => record.target.contains(input) &&
                            measuredFonts.get(input) !== sizingKey(getComputedStyle(input)));
                    }
                    return record.target.nodeName === 'STYLE' || [...record.addedNodes].some(node => node.nodeType === 1 &&
                        (node.matches(`${selector}, style, link[rel="stylesheet"]`) || node.querySelector(selector)));
                });
                if (changed) {
                    schedule();
                }
            }).observe(document.documentElement, {
                childList: true, subtree: true, attributes: true, attributeFilter: ['class', 'style'],
            });
        }
        schedule();
    };

    return {init};
});
