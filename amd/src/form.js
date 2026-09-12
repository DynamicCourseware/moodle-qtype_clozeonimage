// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Keep the positioning preview image in sync with the background-image file picker.
 *
 * @module     qtype_clozeonimage/form
 * @copyright  2026 DynamicCourseware.org (Dominique Bauer)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/str', 'core/notification', 'qtype_clozeonimage/layout'], function($, Str, Notification, Layout) {

    'use strict';

    const formSelector = 'form.mform[data-qtype="clozeonimage"]';
    const imageSelector = '#qtype-clozeonimage-preview-composition .qtype-clozeonimage-preview-image';
    const compositionSelector = '#qtype-clozeonimage-preview-composition';
    let scrollToPreviewAfterLayout = false;
    let imageInitialised = false;
    let initialImageSource = null;

    /**
     * Scroll to just above the positioning section after its layout has settled.
     */
    const scrollToPreview = () => {
        if (!scrollToPreviewAfterLayout) {
            return;
        }
        scrollToPreviewAfterLayout = false;
        requestAnimationFrame(() => requestAnimationFrame(() => {
            const previewHeader = document.getElementById('id_previewheader');
            if (previewHeader) {
                const sectionY = previewHeader.getBoundingClientRect().top + window.scrollY;
                window.scrollTo(window.scrollX, Math.max(0, sectionY - 10));
            }
        }));
    };

    /**
     * Get the URL of the file currently shown by the background-image picker.
     *
     * @returns {String|null}
     */
    const getImageUrl = () => {
        const filePicker = $(`${formSelector} input.filepickerhidden[name="bgimage"]`);
        const fileLink = filePicker.parent().find('div.filepicker-filelist a');
        return fileLink.length ? fileLink.get(0).href : null;
    };

    /**
     * Resolve an image URL to the form used by the browser.
     *
     * @param {String|null} imageUrl Image URL.
     * @returns {String|null}
     */
    const normaliseImageUrl = imageUrl => {
        if (!imageUrl) {
            return null;
        }
        try {
            return new URL(imageUrl, document.baseURI).href;
        } catch {
            return imageUrl;
        }
    };

    /**
     * Identify an SVG draft-file URL for client-side width controls.
     *
     * Server-side validation independently uses the stored file's MIME type.
     *
     * @param {String} imageUrl Selected draft-file URL.
     * @returns {Boolean}
     */
    const isSvgImageUrl = imageUrl => {
        try {
            return /\.svgz?$/i.test(decodeURIComponent(new URL(imageUrl, document.baseURI).pathname));
        } catch {
            return false;
        }
    };

    /**
     * Measure the content width allocated to the preview by Moodle's form layout.
     *
     * @returns {Number} Available integer width, or 0 when it cannot be measured.
     */
    const getAvailableWidth = () => {
        const previewArea = document.getElementById('qtype-clozeonimage-preview-area');
        if (!previewArea) {
            return 0;
        }
        const styles = window.getComputedStyle(previewArea);
        const leftPadding = Number.parseFloat(styles.paddingLeft) || 0;
        const rightPadding = Number.parseFloat(styles.paddingRight) || 0;
        return Math.max(0, Math.floor(previewArea.clientWidth - leftPadding - rightPadding));
    };

    /**
     * Apply a fixed image width and optionally scale all control coordinates.
     *
     * @param {DOMRect} oldImageRect Previous displayed image rectangle.
     * @param {Number} newWidth New fixed image width.
     * @param {Boolean} scalePositions Whether existing coordinates should be scaled.
     */
    const applyDisplayWidth = (oldImageRect, newWidth, scalePositions) => {
        const image = document.querySelector(imageSelector);
        image.style.width = `${newWidth}px`;
        document.querySelector(compositionSelector).style.width = `${newWidth}px`;
        document.querySelector(`${formSelector} input[name="displaywidth"]`).value = newWidth;

        if (!scalePositions || oldImageRect.width <= 0) {
            return;
        }
        const newImageRect = image.getBoundingClientRect();
        const xScale = newImageRect.width / oldImageRect.width;
        const yScale = oldImageRect.height > 0 ? newImageRect.height / oldImageRect.height : xScale;
        document.querySelectorAll('.qtype-clozeonimage-preview-subquestion').forEach(control => {
            const anchor = getAnchor(control.dataset.rowIndex);
            const ax = (anchor % 3) / 2;
            const ay = Math.floor(anchor / 3) / 2;
            const controlRect = control.getBoundingClientRect();
            const anchorX = control.offsetLeft + ax * controlRect.width;
            const anchorY = control.offsetTop + ay * controlRect.height;
            setPosition(control, Math.round(anchorX * xScale - ax * controlRect.width),
                Math.round(anchorY * yScale - ay * controlRect.height), false);
        });
    };

    /**
     * Read the selected anchor for one sparse visible row.
     *
     * @param {String} rowIndex Zero-based visible form row index.
     * @returns {Number} Anchor value from 0 to 8.
     */
    const getAnchor = rowIndex => {
        const selected = document.querySelector(
            `${formSelector} input[name="anchor[${rowIndex}]"]:checked`
        );
        const anchor = selected ? Number.parseInt(selected.value, 10) : 4;
        return Number.isInteger(anchor) && anchor >= 0 && anchor <= 8 ? anchor : 4;
    };

    /**
     * Initialise the width field once the current image dimensions are known.
     *
     * @param {DOMRect} oldImageRect Previous displayed image rectangle.
     * @param {Boolean} preserveWidth Whether a valid submitted or stored width should be preserved.
     * @param {Boolean} scalePositions Whether existing coordinates should be scaled.
     */
    const initialiseDisplayWidth = (oldImageRect, preserveWidth, scalePositions) => {
        const image = document.querySelector(imageSelector);
        const widthInput = document.querySelector(`${formSelector} input[name="displaywidth"]`);
        const range = document.getElementById('qtype-clozeonimage-displaywidth-range');
        const storedWidth = Number.parseInt(widthInput.value, 10);
        const naturalWidth = image.naturalWidth;
        const imageSource = image.currentSrc || image.src;
        const isSvg = isSvgImageUrl(imageSource);
        const minimumWidth = isSvg ? 200 : Math.min(200, naturalWidth);
        const maximumWidth = isSvg ? null : naturalWidth;
        const availableWidth = getAvailableWidth();
        const fittedWidth = availableWidth > 0
            ? Math.max(minimumWidth, Math.min(naturalWidth, availableWidth))
            : Math.max(minimumWidth, naturalWidth);
        const storedWidthIsValid = storedWidth >= minimumWidth &&
            (maximumWidth === null || storedWidth <= maximumWidth);
        const newWidth = preserveWidth && storedWidthIsValid
            ? storedWidth
            : fittedWidth;

        widthInput.min = minimumWidth;
        if (maximumWidth === null) {
            widthInput.removeAttribute('max');
        } else {
            widthInput.max = maximumWidth;
        }
        let rangeString;
        if (isSvg) {
            rangeString = Str.get_string('displaywidthminimumcompact', 'qtype_clozeonimage', minimumWidth);
        } else if (minimumWidth === naturalWidth) {
            rangeString = Str.get_string('displaywidthsinglecompact', 'qtype_clozeonimage', naturalWidth);
        } else {
            rangeString = Str.get_string('displaywidthrangecompact', 'qtype_clozeonimage', {
                min: minimumWidth,
                max: naturalWidth,
            });
        }
        rangeString.then(localisedRange => {
            const currentImage = document.querySelector(imageSelector);
            if (currentImage && currentImage.naturalWidth === naturalWidth &&
                    (currentImage.currentSrc || currentImage.src) === imageSource) {
                range.textContent = localisedRange;
            }
            return localisedRange;
        }).catch(Notification.exception);
        applyDisplayWidth(oldImageRect, newWidth, scalePositions);
    };

    /**
     * Copy the file picker's prepared draft URL to the positioning preview.
     */
    const loadPreviewImage = () => {
        const imageUrl = getImageUrl();
        const image = $(imageSelector);
        if (imageUrl === null) {
            image.removeAttr('src');
            scrollToPreview();
        } else {
            const requestedSource = normaliseImageUrl(imageUrl);
            const oldImageRect = image.get(0).getBoundingClientRect();
            const firstInitialisation = !imageInitialised;
            const unchangedInitialImage = firstInitialisation && initialImageSource !== null &&
                requestedSource === initialImageSource;
            const replacement = !firstInitialisation ||
                (initialImageSource !== null && !unchangedInitialImage);
            imageInitialised = true;
            let handled = false;
            const imageLoaded = () => {
                if (handled) {
                    return;
                }
                const loadedSource = normaliseImageUrl(image.get(0).currentSrc || image.get(0).src);
                if (loadedSource !== requestedSource) {
                    return;
                }
                handled = true;
                initialiseDisplayWidth(oldImageRect, unchangedInitialImage, replacement);
                scrollToPreview();
            };
            image.one('load', imageLoaded);
            image.attr('src', imageUrl);
            if (image.get(0).complete) {
                imageLoaded();
            }
        }
        $('.qtype-clozeonimage-preview-message').toggle(
            imageUrl === null || $('.qtype-clozeonimage-preview-subquestion').length === 0
        );
    };

    /**
     * Resize immediately when the teacher changes the fixed image width.
     *
     * @param {Event} event Width input event.
     */
    const displayWidthChanged = event => {
        const image = document.querySelector(imageSelector);
        if (!image.naturalWidth) {
            return;
        }
        const widthInput = event.currentTarget;
        const minimumWidth = Number.parseInt(widthInput.min, 10);
        const maximumWidth = Number.parseInt(widthInput.max, 10);
        let newWidth = Number.parseInt(widthInput.value, 10);
        if (!Number.isInteger(newWidth)) {
            return;
        }
        if (event.type === 'input' && newWidth < minimumWidth) {
            return;
        }
        newWidth = Math.max(minimumWidth, newWidth);
        if (Number.isInteger(maximumWidth)) {
            newWidth = Math.min(maximumWidth, newWidth);
        }
        widthInput.value = newWidth;
        applyDisplayWidth(image.getBoundingClientRect(), newWidth, true);
    };

    /**
     * Write a preview control's coordinates to its sparse form row.
     *
     * @param {HTMLElement} control The positioned preview control.
     * @param {Number} x Horizontal position.
     * @param {Number} y Vertical position.
     * @param {Boolean} notify Whether to trigger form change handling.
     */
    const setPosition = (control, x, y, notify) => {
        const rowIndex = control.dataset.rowIndex;
        const form = document.querySelector(formSelector);
        const xInput = form.querySelector(`input[name="xleft[${rowIndex}]"]`);
        const yInput = form.querySelector(`input[name="ytop[${rowIndex}]"]`);
        control.style.left = `${x}px`;
        control.style.top = `${y}px`;
        Layout.updateCompositionHeight(control.parentElement);
        if (!xInput || !yInput) {
            return;
        }
        xInput.value = x;
        yInput.value = y;
        if (notify) {
            $(xInput).trigger('change');
            $(yInput).trigger('change');
        }
    };

    /**
     * Round a preview position to the integer coordinates used for storage.
     *
     * @param {Number} x Requested horizontal position.
     * @param {Number} y Requested vertical position.
     * @returns {{x: Number, y: Number}} Integer coordinates.
     */
    const normalisePosition = (x, y) => {
        return {
            x: Math.round(x),
            y: Math.round(y),
        };
    };

    /**
     * Move a preview control when one of its visible coordinate fields changes.
     *
     * @param {Event} event Coordinate input event.
     */
    const coordinateChanged = event => {
        const input = event.target;
        const match = typeof input.name === 'string' ? input.name.match(/^(xleft|ytop)\[(\d+)]$/) : null;
        if (!match) {
            return;
        }
        const isnegative = input.value.startsWith('-');
        const digits = input.value.replace(/[^0-9]/g, '');
        const sanitised = (isnegative ? '-' : '') + digits;
        if (input.value !== sanitised) {
            input.value = sanitised;
        }
        if (sanitised === '' || sanitised === '-') {
            return;
        }
        const value = Number(sanitised);
        if (!Number.isFinite(value)) {
            return;
        }
        const control = document.querySelector(
            `.qtype-clozeonimage-preview-subquestion[data-row-index="${match[2]}"]`
        );
        if (!control) {
            return;
        }
        const position = normalisePosition(
            match[1] === 'xleft' ? value : control.offsetLeft,
            match[1] === 'ytop' ? value : control.offsetTop
        );
        setPosition(control, position.x, position.y, false);
    };

    /**
     * Ensure the displayed positions are in the submitted hidden fields before any form rebuild.
     */
    const syncPositionsBeforeSubmit = () => {
        document.querySelectorAll('.qtype-clozeonimage-preview-subquestion').forEach(control => {
            setPosition(control, control.offsetLeft, control.offsetTop, false);
        });
    };

    /**
     * Enable dragging of each control within the background image.
     */
    const initialiseDragging = () => {
        document.querySelectorAll('.qtype-clozeonimage-preview-subquestion').forEach(control => {
            control.addEventListener('pointerdown', event => {
                event.preventDefault();
                const startX = event.clientX;
                const startY = event.clientY;
                const originalX = control.offsetLeft;
                const originalY = control.offsetTop;

                control.setPointerCapture(event.pointerId);
                control.classList.add('qtype-clozeonimage-preview-dragging');

                const move = moveEvent => {
                    const position = normalisePosition(
                        originalX + moveEvent.clientX - startX,
                        originalY + moveEvent.clientY - startY
                    );
                    setPosition(control, position.x, position.y, false);
                };
                const stop = stopEvent => {
                    move(stopEvent);
                    control.classList.remove('qtype-clozeonimage-preview-dragging');
                    control.removeEventListener('pointermove', move);
                    control.removeEventListener('pointerup', stop);
                    control.removeEventListener('pointercancel', stop);
                    setPosition(control, control.offsetLeft, control.offsetTop, true);
                };

                control.addEventListener('pointermove', move);
                control.addEventListener('pointerup', stop);
                control.addEventListener('pointercancel', stop);
            });
        });
    };

    /**
     * Wait until Moodle's file-picker JavaScript has populated its file list.
     */
    const waitForFilePickerToInitialise = () => {
        if (getImageUrl() === null) {
            setTimeout(waitForFilePickerToInitialise, 1000);
            return;
        }

        $(formSelector).on('change', '#id_bgimage', loadPreviewImage);
        loadPreviewImage();
    };

    return {
        /**
         * Initialise the edit-form enhancements.
         *
         * @param {Boolean} shouldScrollToPreview Whether this reload was requested by Update preview.
         */
        init: shouldScrollToPreview => {
            scrollToPreviewAfterLayout = shouldScrollToPreview;
            initialImageSource = normaliseImageUrl(getImageUrl());
            Layout.init();
            const widthInput = document.querySelector(`${formSelector} input[name="displaywidth"]`);
            widthInput.addEventListener('input', displayWidthChanged);
            widthInput.addEventListener('change', displayWidthChanged);
            document.querySelector(formSelector).addEventListener('input', coordinateChanged);
            document.querySelector(formSelector).addEventListener('change', coordinateChanged);
            initialiseDragging();
            document.querySelector(formSelector).addEventListener('submit', syncPositionsBeforeSubmit);
            waitForFilePickerToInitialise();
        },
    };
});
