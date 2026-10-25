// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Align numerical diagnostics with their fields without changing flow, validation or focus.
 *
 * @module qtype_clozeonimage/numerical_validation
 * @copyright 2026 DynamicCourseware.org (Dominique Bauer)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    'use strict';

    const selector = '.que.clozeonimage [data-clozeonimage-validation-for]';
    const offsets = new WeakMap();
    const observed = new Set();
    let initialised = false;
    let frame = 0;
    let resizeObserver;

    /** Keep each message in its existing vertical flow; only correct its horizontal offset. */
    const align = () => {
        const watch = new Set();
        document.querySelectorAll(selector).forEach(message => {
            const field = document.getElementById(message.dataset.clozeonimageValidationFor);
            if (!field || !field.getClientRects().length || !message.getClientRects().length) {
                return;
            }
            [field, message, field.closest('.qtype-clozeonimage-region'), field.closest('.que')]
                .filter(Boolean).forEach(element => watch.add(element));
            const previous = offsets.get(message) || 0;
            const difference = field.getBoundingClientRect().left - message.getBoundingClientRect().left;
            if (Math.abs(difference) > .1) {
                const offset = previous + difference;
                message.style.setProperty('--clozeonimage-validation-offset', `${offset}px`);
                offsets.set(message, offset);
            }
            if (!message.classList.contains('qtype-clozeonimage-validation-aligned')) {
                message.classList.add('qtype-clozeonimage-validation-aligned');
            }
        });
        observed.forEach(element => {
            if (!watch.has(element)) {
                resizeObserver?.unobserve(element);
                observed.delete(element);
            }
        });
        watch.forEach(element => {
            if (!observed.has(element)) {
                resizeObserver?.observe(element);
                observed.add(element);
            }
        });
    };

    /** Coalesce wrapping, font loading and composition changes. */
    const schedule = () => {
        if (!frame) {
            frame = requestAnimationFrame(() => {
                frame = 0;
                align();
            });
        }
    };

    /** Register once per page; AJAX replacements retain normal message/field IDs and associations. */
    const init = () => {
        if (!initialised) {
            initialised = true;
            if (window.ResizeObserver) {
                resizeObserver = new ResizeObserver(schedule);
            }
            new MutationObserver(schedule).observe(document.body, {
                childList: true, subtree: true, attributes: true, attributeFilter: ['class'],
            });
            window.addEventListener('resize', schedule);
            window.addEventListener('pageshow', schedule);
            document.addEventListener('load', schedule, true);
            document.fonts?.ready.then(schedule);
            document.fonts?.addEventListener('loadingdone', schedule);
        }
        schedule();
    };

    return {init};
});
