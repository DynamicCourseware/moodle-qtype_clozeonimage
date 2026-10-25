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
define(['jquery', 'core/str', 'core/notification', 'qtype_clozeonimage/layout', 'editor_tiny/editor',
    'qtype_clozeonimage/pixel_fields'],
        function($, Str, Notification, Layout, Tiny, Pixels) {

    'use strict';

    const formSelector = 'form.mform[data-qtype="clozeonimage"]';
    const imageSelector = '#qtype-clozeonimage-preview-composition .qtype-clozeonimage-preview-image';
    const compositionSelector = '#qtype-clozeonimage-preview-composition';
    let scrollToPreviewAfterLayout = false;
    let imageInitialised = false;
    let initialImageSource = null;
    const blockAnchors = new WeakMap();

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
        if (document.querySelector(`${formSelector} [name="removebgimage"]:checked`)) {
            return null;
        }
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
     * @param {Map|null} anchors Original anchor coordinates for a pointer gesture, avoiding cumulative rounding.
     */
    const applyDisplayWidth = (oldImageRect, newWidth, scalePositions, anchors = null) => {
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
            const anchorX = anchors?.get(control)?.x ?? precisePosition(control).x + ax * controlRect.width;
            const anchorY = anchors?.get(control)?.y ?? precisePosition(control).y + ay * controlRect.height;
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
        const oldImageRect = image.get(0).getBoundingClientRect();
        image.closest('.qtype-clozeonimage-preview-scroll').prop('hidden', imageUrl === null);
        document.querySelector(`${formSelector} input[name="displaywidth"]`).disabled = imageUrl === null;
        if (imageUrl === null) {
            image.removeAttr('src');
            scrollToPreview();
        } else {
            const requestedSource = normaliseImageUrl(imageUrl);
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
            imageUrl === null || !document.querySelector(`${formSelector} input[type="checkbox"][name="showcomposition"]`)?.checked
        );
    };

    /**
     * Resize immediately when the teacher changes the fixed image width.
     *
     * @param {Event} event Width input event.
     * @param {Object|null} reference Starting image rectangle and anchors for a pointer gesture.
     */
    const displayWidthChanged = (event, reference = null) => {
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
        applyDisplayWidth(reference?.rect || image.getBoundingClientRect(), newWidth, true, reference?.anchors);
        Layout.updateCompositionHeight(document.querySelector(compositionSelector));
    };

    /**
     * Resize the background through the width field's validation and anchor-scaling path.
     * The normal width field remains the keyboard alternative.
     */
    const initialiseImageResizing = () => {
        const grip = document.querySelector('.qtype-clozeonimage-image-resize');
        grip?.addEventListener('pointerdown', event => {
            const image = document.querySelector(imageSelector);
            if (event.button !== 0 || !image.naturalWidth) {
                return;
            }
            event.preventDefault();
            const widthInput = document.querySelector(`${formSelector} input[name="displaywidth"]`);
            const reference = {rect: image.getBoundingClientRect(), anchors: new Map()};
            document.querySelectorAll('.qtype-clozeonimage-preview-subquestion').forEach(control => {
                const anchor = getAnchor(control.dataset.rowIndex);
                const rect = control.getBoundingClientRect();
                reference.anchors.set(control, {
                    x: precisePosition(control).x + (anchor % 3) / 2 * rect.width,
                    y: precisePosition(control).y + Math.floor(anchor / 3) / 2 * rect.height,
                });
            });
            const startX = event.clientX;
            const track = grip.closest('.qtype-clozeonimage-preview-track');
            const minimumTrackWidth = track.style.minWidth;
            // At the right scroll limit, shrinking must not clamp scrollLeft and pull the grip away from the pointer.
            track.style.minWidth = track.style.width;
            grip.setPointerCapture(event.pointerId);
            const move = moveEvent => {
                widthInput.value = Math.round(reference.rect.width + moveEvent.clientX - startX);
                displayWidthChanged({currentTarget: widthInput, type: 'change'}, reference);
            };
            const stop = () => {
                track.style.minWidth = minimumTrackWidth;
                grip.removeEventListener('pointermove', move);
                grip.removeEventListener('pointerup', stop);
                grip.removeEventListener('pointercancel', stop);
                $(widthInput).trigger('change');
            };
            grip.addEventListener('pointermove', move);
            grip.addEventListener('pointerup', stop);
            grip.addEventListener('pointercancel', stop);
        });
    };

    /**
     * Read internal coordinates, avoiding the browser's rounded CSS serialisation.
     * @param {HTMLElement} control Preview block
     * @returns {{x: Number, y: Number}} Precise internal position
     */
    const precisePosition = control => {
        const form = document.querySelector(formSelector);
        const read = (name, style) => {
            const input = form?.querySelector(`[name="${name}[${control.dataset.rowIndex}]"]`);
            return input && input.value !== '' ? Number(input.value) : parseFloat(control.style[style]) || 0;
        };
        return {x: read('xleft', 'left'), y: read('ytop', 'top')};
    };

    /**
     * Write a preview control's coordinates to its sparse form row.
     *
     * @param {HTMLElement} control The positioned preview control.
     * @param {Number} x Horizontal position.
     * @param {Number} y Vertical position.
     * @param {Boolean} notify Whether to trigger form change handling.
     * @param {Boolean} rememberAnchor Whether this edit establishes a new anchor.
     */
    const setPosition = (control, x, y, notify, rememberAnchor = true) => {
        const rowIndex = control.dataset.rowIndex;
        const form = document.querySelector(formSelector);
        const xInput = form.querySelector(`input[name="xleft[${rowIndex}]"]`);
        const yInput = form.querySelector(`input[name="ytop[${rowIndex}]"]`);
        if (xInput && yInput) {
            xInput.value = x;
            yInput.value = y;
        }
        control.style.left = `${x}px`;
        control.style.top = `${y}px`;
        if (rememberAnchor) {
            rememberBlockAnchor(control);
        }
        Layout.updateCompositionHeight(control.parentElement);
        if (!xInput || !yInput) {
            return;
        }
        control.dispatchEvent(new CustomEvent('clozeonimage:blockposition', {bubbles: true}));
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
            x: Number(x.toFixed(8)),
            y: Number(y.toFixed(8)),
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
        const value = Number(input.value);
        if (input.value === '' || !Number.isFinite(value)) {
            return;
        }
        const control = document.querySelector(
            `.qtype-clozeonimage-preview-subquestion[data-row-index="${match[2]}"]`
        );
        if (!control) {
            return;
        }
        const position = normalisePosition(
            match[1] === 'xleft' ? value : precisePosition(control).x,
            match[1] === 'ytop' ? value : precisePosition(control).y
        );
        setPosition(control, position.x, position.y, false);
    };

    /**
     * Resize a block without scaling its contents; keep its selected anchor fixed.
     * @param {HTMLElement} control Preview block
     * @param {Number} width Width in CSS pixels
     * @param {Boolean} notify Notify form change handlers
     * @param {Object|null} reference Starting geometry for a pointer resize
     */
    const setBlockWidth = (control, width, notify = false, reference = null) => {
        const minimum = Layout.minimumBlockWidth(control);
        if (!Number.isFinite(width) || width > 4194303) {
            Str.get_string('geometrylimit', 'qtype_clozeonimage').then(message => Notification.alert('', message));
            return;
        }
        width = Math.max(minimum, width);
        if (!control.getClientRects().length) {
            control.style.width = `${width}px`;
            blockAnchors.delete(control);
            return;
        }
        const rect = control.getBoundingClientRect();
        const old = reference || {x: precisePosition(control).x, y: precisePosition(control).y,
            width: rect.width, height: rect.height};
        const anchor = getAnchor(control.dataset.rowIndex);
        const ax = (anchor % 3) / 2;
        const ay = Math.floor(anchor / 3) / 2;
        control.style.minWidth = `${minimum}px`;
        control.style.width = `${width}px`;
        const current = control.getBoundingClientRect();
        setPosition(control, old.x + ax * (old.width - current.width),
            old.y + ay * (old.height - current.height), notify);
        // Keep the unrounded anchor if fonts, images or mathematics finish laying out later.
        blockAnchors.set(control, {anchor, x: old.x + ax * old.width, y: old.y + ay * old.height});
        const input = document.querySelector(`${formSelector} [name="blockwidth[${control.dataset.rowIndex}]"]`);
        input.value = width;
        input.min = minimum || 40;
    };

    /**
     * Remember an anchor only after an explicit geometry edit, never from unrendered page content.
     * @param {HTMLElement} control Preview block
     */
    const rememberBlockAnchor = control => {
        if (!control.getClientRects().length) {
            blockAnchors.delete(control);
            return;
        }
        const anchor = getAnchor(control.dataset.rowIndex);
        const rect = control.getBoundingClientRect();
        blockAnchors.set(control, {anchor,
            x: precisePosition(control).x + (anchor % 3) / 2 * rect.width,
            y: precisePosition(control).y + Math.floor(anchor / 3) / 2 * rect.height});
    };

    /**
     * Refresh the width limit after content, font, image or mathematical layout changes.
     * @param {HTMLElement} control Preview block
     */
    const fitPreviewBlock = control => {
        const input = document.querySelector(`${formSelector} [name="blockwidth[${control.dataset.rowIndex}]"]`);
        const minimum = Layout.minimumBlockWidth(control);
        if (input) {
            input.min = minimum || 40;
        }
        if (!minimum) {
            control.style.removeProperty('min-width');
        } else if (control.getBoundingClientRect().width < minimum) {
            setBlockWidth(control, minimum);
        } else {
            control.style.minWidth = `${minimum}px`;
        }
    };

    /** Keep edited anchors fixed through asynchronous math/image/font layout, without reload drift. */
    const observeBlockSizes = () => {
        if (!window.ResizeObserver) {
            return;
        }
        const pending = new Set();
        let frame = 0;
        const queue = controls => {
            controls.forEach(control => pending.add(control));
            if (frame) {
                return;
            }
            frame = requestAnimationFrame(() => {
                frame = 0;
                pending.forEach(control => {
                    if (!control?.isConnected || !control.getClientRects().length) {
                        return;
                    }
                    const saved = blockAnchors.get(control);
                    fitPreviewBlock(control);
                    if (saved && saved.anchor === getAnchor(control.dataset.rowIndex)) {
                        const rect = control.getBoundingClientRect();
                        setPosition(control, saved.x - (saved.anchor % 3) / 2 * rect.width,
                            saved.y - Math.floor(saved.anchor / 3) / 2 * rect.height, false, false);
                        blockAnchors.set(control, saved);
                    } else {
                        Layout.updateCompositionHeight(control.parentElement);
                    }
                });
                pending.clear();
            });
        };
        const observer = new ResizeObserver(entries => queue(
            entries.map(({target}) => target.closest('.qtype-clozeonimage-preview-subquestion'))));
        const observed = new Set();
        const watch = () => {
            observed.forEach(element => {
                if (!element.isConnected) {
                    observer.unobserve(element);
                    observed.delete(element);
                }
            });
            document.querySelectorAll('.qtype-clozeonimage-preview-subquestion').forEach(control => {
                [control, ...control.querySelectorAll('.qtype-clozeonimage-control')].forEach(element => {
                    if (!observed.has(element)) {
                        observed.add(element);
                        observer.observe(element);
                    }
                });
            });
        };
        watch();
        const composition = document.querySelector(compositionSelector);
        if (composition) {
            new MutationObserver(() => {
                watch();
                queue(composition.querySelectorAll('.qtype-clozeonimage-preview-subquestion'));
            }).observe(composition, {childList: true, subtree: true, characterData: true});
        }
        document.querySelector(formSelector).addEventListener('change', event => {
            const match = event.target.name?.match(/^anchor\[(\d+)]$/);
            if (match) {
                const control = document.querySelector(
                    `.qtype-clozeonimage-preview-subquestion[data-row-index="${match[1]}"]`
                );
                if (control) {
                    rememberBlockAnchor(control);
                }
            }
        });
    };

    /**
     * Width is also directly editable with the keyboard in the normal form field.
     * @param {Event} event Input event
     */
    const blockWidthChanged = event => {
        const input = event.target;
        const match = input.name?.match(/^blockwidth\[(\d+)]$/);
        if (!match || input.readOnly || input.validity.badInput || input.value === '' || !Number.isFinite(Number(input.value))) {
            return;
        }
        const control = document.querySelector(`.qtype-clozeonimage-preview-subquestion[data-row-index="${match[1]}"]`);
        if (control) {
            if (event.type === 'input' && Number(input.value) < Layout.minimumBlockWidth(control)) {
                return; // Let a keyboard user finish typing before clamping on change/blur.
            }
            setBlockWidth(control, Number(input.value));
        }
    };

    /**
     * Live authoring hint only: the core Cloze parser determines the saved/preview mode on the server.
     * @param {String} html Editor HTML
     * @returns {Boolean} One expression with insignificant wrappers
     */
    const singleExpression = html => {
        let count = 0;
        const dimension = String.raw`[0-9]+(?:\.[0-9]+)?(?:px|em|rem)`;
        const expression = new RegExp(String.raw`\{[0-9]*:[A-Z_]+(?:\(${dimension}(?:,${dimension})?\))?:` +
            String.raw`(?:\\[\s\S]|[^\\}])*\}`, 'gi');
        const replaced = html.replace(/<!--[\s\S]*?(?:-->|$)/g, '')
            .replace(expression, () => {
            count++;
            return '{#1}';
        });
        if (count !== 1) {
            return false;
        }
        const doc = new DOMParser().parseFromString(replaced, 'text/html');
        const wrappers = new Set(['P', 'DIV', 'SPAN', 'BR', 'STRONG', 'B', 'EM', 'I', 'U', 'S', 'SMALL',
            'BIG', 'SUB', 'SUP', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6']);
        return [...doc.body.querySelectorAll('*')].every(element => wrappers.has(element.tagName)) &&
            doc.body.textContent.replace(/[\s\u00a0\u200b\ufeff]+/g, '') === '{#1}';
    };

    /** Bind both initial and re-created Tiny editors without retaining obsolete row indices. */
    const initialiseAutomaticWidths = () => {
        const form = document.querySelector(formSelector);
        const bound = new WeakSet();
        const update = (textarea, automatic, reposition = true) => {
            const index = textarea.name.match(/\[(\d+)\]/)?.[1];
            const input = form.querySelector(`[name="blockwidth[${index}]"]`);
            if (!input) {
                return;
            }
            // Readonly keeps the manual value in form submissions, unlike disabled controls.
            input.readOnly = automatic;
            input.setAttribute('aria-disabled', String(automatic));
            input.title = automatic ? input.dataset.autoHelp : '';
            input.setAttribute('aria-description', input.title);
            const control = form.querySelector(`.qtype-clozeonimage-preview-subquestion[data-row-index="${index}"]`);
            if (!control || control.classList.contains('qtype-clozeonimage-block-auto') === automatic) {
                return;
            }
            if (reposition) {
                rememberBlockAnchor(control);
            }
            control.classList.toggle('qtype-clozeonimage-block-auto', automatic);
            control.style.width = automatic ? '' : `${input.value}px`;
        };
        form.querySelectorAll('#qtype-clozeonimage-rows textarea').forEach(textarea => {
            const index = textarea.name.match(/\[(\d+)\]/)?.[1];
            const control = form.querySelector(`.qtype-clozeonimage-preview-subquestion[data-row-index="${index}"]`);
            update(textarea, !!control?.classList.contains('qtype-clozeonimage-block-auto'), false);
            if (control?.getClientRects().length) {
                const oldWidth = Number(control.dataset.previousWidth);
                const oldHeight = Number(control.dataset.previousHeight);
                if (oldWidth > 0 && oldHeight > 0 && Number.isFinite(oldWidth + oldHeight)) {
                    const rect = control.getBoundingClientRect();
                    const anchor = getAnchor(index);
                    setPosition(control, precisePosition(control).x + (anchor % 3) / 2 * (oldWidth - rect.width),
                        precisePosition(control).y + Math.floor(anchor / 3) / 2 * (oldHeight - rect.height), false);
                }
                rememberBlockAnchor(control);
                fitPreviewBlock(control);
            }
        });
        const bind = () => {
            form.querySelectorAll('#qtype-clozeonimage-rows textarea').forEach(textarea => {
                const editor = Tiny?.getInstanceForElement(textarea);
                if (!editor || bound.has(editor)) {
                    return;
                }
                bound.add(editor);
                editor.on('input change Undo Redo SetContent', () => update(textarea, singleExpression(editor.getContent())));
            });
        };
        bind();
        // Moodle marks data-fieldtype after registering each Tiny instance, including after reordering.
        new MutationObserver(bind).observe(form, {subtree: true, attributes: true, attributeFilter: ['data-fieldtype']});
        form.addEventListener('input', event => {
            if (event.target.matches('#qtype-clozeonimage-rows textarea')) {
                update(event.target, singleExpression(event.target.value));
            }
        });
    };

    /**
     * Ensure the displayed positions are in the submitted hidden fields before any form rebuild.
     */
    const syncPositionsBeforeSubmit = () => {
        document.querySelectorAll('.qtype-clozeonimage-preview-subquestion').forEach(control => {
            if (control.getClientRects().length) {
                // Coordinates are already synchronised by geometry writers; do not re-read rounded CSS here.
                const rect = control.getBoundingClientRect();
                // Transient geometry survives Update preview; the database stores only the manual width.
                for (const [name, value] of [['previewwidth', rect.width], ['previewheight', rect.height]]) {
                    const fieldname = `${name}[${control.dataset.rowIndex}]`;
                    let input = document.querySelector(`${formSelector} [name="${fieldname}"]`);
                    if (!input) {
                        input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = fieldname;
                        document.querySelector(formSelector).append(input);
                    }
                    input.value = value;
                }
            }
        });
    };

    /**
     * Move preview blocks singly or as a temporary group; resizing always affects one block.
     */
    const initialiseDragging = () => {
        const selected = new Set();
        const selectionClass = 'qtype-clozeonimage-preview-selected';
        let stopActiveGesture = null;
        const clearSelection = () => {
            selected.forEach(block => block.classList.remove(selectionClass));
            selected.clear();
        };
        document.getElementById('qtype-clozeonimage-preview-area')?.addEventListener('pointerdown', event => {
            if (event.button === 0 && !event.target.closest('.qtype-clozeonimage-preview-subquestion')) {
                clearSelection();
            }
        });
        document.addEventListener('keydown', event => {
            if (event.key === 'Escape' && (selected.size || stopActiveGesture)) {
                stopActiveGesture?.(event);
                clearSelection();
                event.preventDefault();
            }
        });
        document.querySelectorAll('.qtype-clozeonimage-preview-subquestion').forEach(control => {
            const marker = document.createElement('span');
            marker.className = 'qtype-clozeonimage-preview-selection-mark';
            marker.textContent = '\u2713';
            marker.setAttribute('aria-hidden', 'true');
            control.append(marker);
            control.addEventListener('pointerdown', event => {
                if (event.button !== 0 || stopActiveGesture) {
                    return;
                }
                event.preventDefault();
                const resizing = event.target.closest('.qtype-clozeonimage-resize');
                if (!resizing && (event.ctrlKey || event.metaKey)) {
                    if (selected.has(control)) {
                        selected.delete(control);
                    } else {
                        selected.add(control);
                    }
                    control.classList.toggle(selectionClass, selected.has(control));
                    return;
                }
                if (!resizing && !selected.has(control)) {
                    clearSelection();
                }
                const originalRect = control.getBoundingClientRect();
                const originalWidth = originalRect.width;
                const startX = event.clientX;
                const startY = event.clientY;
                const originalX = precisePosition(control).x;
                const originalY = precisePosition(control).y;
                // Snapshot membership and positions once. One rounded delta preserves relative coordinates.
                const moving = [...(!resizing && selected.has(control) ? selected : [control])].map(block => ({
                    block, x: (parseFloat(block.style.left) || 0), y: (parseFloat(block.style.top) || 0),
                }));

                control.setPointerCapture(event.pointerId);
                const gestureClass = resizing ? 'qtype-clozeonimage-preview-resizing' : 'qtype-clozeonimage-preview-dragging';
                moving.forEach(({block}) => block.classList.add(gestureClass));

                const move = moveEvent => {
                    if (resizing) {
                        setBlockWidth(control, Math.max(40, Math.round(originalWidth + moveEvent.clientX - startX)), false,
                            {x: originalX, y: originalY, width: originalWidth, height: originalRect.height});
                        return;
                    }
                    const delta = normalisePosition(moveEvent.clientX - startX, moveEvent.clientY - startY);
                    moving.forEach(({block, x, y}) => setPosition(block, x + delta.x, y + delta.y, false));
                };
                const stop = stopEvent => {
                    // Cancellation/lost capture may not carry a meaningful final pointer position.
                    if (stopEvent.type === 'pointerup') {
                        move(stopEvent);
                    }
                    if (resizing) {
                        $(document.querySelector(`${formSelector} [name="blockwidth[${control.dataset.rowIndex}]"]`))
                            .trigger('change');
                    }
                    stopActiveGesture = null;
                    moving.forEach(({block}) => block.classList.remove(gestureClass));
                    control.removeEventListener('pointermove', move);
                    control.removeEventListener('pointerup', stop);
                    control.removeEventListener('pointercancel', stop);
                    control.removeEventListener('lostpointercapture', stop);
                    if (control.hasPointerCapture(event.pointerId)) {
                        control.releasePointerCapture(event.pointerId);
                    }
                    moving.forEach(({block}) => setPosition(block, (parseFloat(block.style.left) || 0),
                        (parseFloat(block.style.top) || 0), true));
                };

                stopActiveGesture = stop;
                control.addEventListener('pointermove', move);
                control.addEventListener('pointerup', stop);
                control.addEventListener('pointercancel', stop);
                control.addEventListener('lostpointercapture', stop);
            });
        });
    };

    /**
     * Wait until Moodle's file-picker JavaScript has populated its file list.
     */
    const waitForFilePickerToInitialise = () => {
        $(formSelector).on('change', '#id_bgimage', () => {
            const remove = document.querySelector(`${formSelector} input[type="checkbox"][name="removebgimage"]`);
            if (remove) {
                remove.checked = false;
            }
            loadPreviewImage();
        });
        document.querySelector(`${formSelector} input[type="checkbox"][name="removebgimage"]`)
            ?.addEventListener('change', loadPreviewImage);
        document.querySelector(`${formSelector} input[type="checkbox"][name="showcomposition"]`)
            ?.addEventListener('change', event => {
                $('.qtype-clozeonimage-preview-message').toggle(!event.target.checked || getImageUrl() === null);
            });
        for (const [name, prefix] of [['blockappearance', 'qtype-clozeonimage-block-appearance-'],
            ['controlappearance', 'qtype-clozeonimage-appearance-']]) {
            document.querySelector(`${formSelector} [name="${name}"]`)?.addEventListener('change', event => {
                const composition = document.getElementById('qtype-clozeonimage-preview-composition');
                composition.classList.remove(...['transparent', 'translucent', 'opaque'].map(mode => prefix + mode));
                composition.classList.add(prefix + ['translucent', 'opaque', 'transparent'][Number(event.target.value)]);
            });
        }
        const picker = document.querySelector(`${formSelector} [name="bgimage"]`)?.parentElement;
        if (picker) {
            const observer = new MutationObserver(() => {
                if (normaliseImageUrl(getImageUrl()) !== normaliseImageUrl(document.querySelector(imageSelector)?.src)) {
                    loadPreviewImage();
                }
            });
            observer.observe(picker, {childList: true, subtree: true});
        }
        loadPreviewImage();
    };

    return {
        setPosition, setBlockWidth, getAnchor, precisePosition,
        /**
         * Initialise the edit-form enhancements.
         *
         * @param {Boolean} shouldScrollToPreview Whether this reload was requested by Update preview.
         */
        init: shouldScrollToPreview => {
            Pixels.init();
            scrollToPreviewAfterLayout = shouldScrollToPreview;
            initialImageSource = normaliseImageUrl(getImageUrl());
            Layout.init();
            const widthInput = document.querySelector(`${formSelector} input[name="displaywidth"]`);
            widthInput?.addEventListener('input', displayWidthChanged);
            widthInput?.addEventListener('change', displayWidthChanged);
            document.querySelector(formSelector).addEventListener('input', coordinateChanged);
            document.querySelector(formSelector).addEventListener('change', coordinateChanged);
            document.querySelector(formSelector).addEventListener('input', blockWidthChanged);
            document.querySelector(formSelector).addEventListener('change', blockWidthChanged);
            observeBlockSizes();
            initialiseAutomaticWidths();
            if (!document.querySelector(`${compositionSelector}[data-composition]`)) {
                initialiseDragging();
                initialiseImageResizing();
            }
            document.querySelector(formSelector).addEventListener('submit', syncPositionsBeforeSubmit);
            if (widthInput) {
                waitForFilePickerToInitialise();
            } else {
                scrollToPreview();
            }
        },
    };
});
