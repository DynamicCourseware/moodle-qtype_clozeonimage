// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Reserve composition space for controls positioned below the image.
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
    const observers = [];
    let drawerOverflowCleanupInitialised = false;

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

        document.querySelectorAll(compositionSelector).forEach(composition => {
            if (composition.dataset.clozeonimageLayoutInitialised) {
                updateCompositionHeight(composition);
                return;
            }
            composition.dataset.clozeonimageLayoutInitialised = 'true';

            const update = () => updateCompositionHeight(composition);
            update();

            const image = composition.querySelector(imageSelector);
            if (image && !image.complete) {
                image.addEventListener('load', update, {once: true});
            }

            if (window.ResizeObserver) {
                const observer = new window.ResizeObserver(update);
                if (image) {
                    observer.observe(image);
                }
                composition.querySelectorAll(controlSelector).forEach(control => observer.observe(control));
                observers.push(observer);
            }
        });
    };

    return {
        init,
        updateCompositionHeight,
    };
});
