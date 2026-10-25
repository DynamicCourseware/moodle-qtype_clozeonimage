// This file is part of Moodle - https://moodle.org/
// Moodle is free software under the GNU GPL v3 or later.
/**
 * Integer display fields backed by precise, submitted geometry values.
 * @module qtype_clozeonimage/pixel_fields
 * @copyright 2026 DynamicCourseware.org (Dominique Bauer)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    'use strict';
    const rounded = value => String(Math.sign(Number(value)) * Math.floor(Math.abs(Number(value)) + .5) || 0);
    const init = () => {
        const form = document.querySelector('form[data-qtype="clozeonimage"]');
        const restorers = [];
        const bindRestoration = (input, restore) => {
            restorers.push(restore);
            input.addEventListener('blur', restore);
            input.addEventListener('keydown', event => {
                if (event.key === 'Enter' && input.value === '' && !input.validity.badInput) {
                    event.preventDefault();
                    restore();
                }
            });
        };
        form?.querySelectorAll('.qtype-clozeonimage-layer input').forEach(input => {
            if (input.dataset.layerReady) {
                return;
            }
            input.dataset.layerReady = '1';
            input.type = 'number';
            const current = form.querySelector(`[name="${input.name.replace(/^(\w+)/, '$1_current')}"]`);
            let last = current?.value ?? input.value;
            const valid = () => /^-?\d+$/.test(input.value) &&
                Number(input.value) >= -2147483648 && Number(input.value) <= 2147483647;
            const restore = () => {
                if (input.value === '' && !input.validity.badInput) {
                    input.value = last;
                    input.setCustomValidity(valid() ? '' : input.dataset.layerError);
                }
            };
            bindRestoration(input, restore);
            ['input', 'change'].forEach(type => input.addEventListener(type, () => {
                if (input.value === '' && !input.validity.badInput) {
                    input.setCustomValidity('');
                    if (type === 'change') {
                        restore();
                    }
                    return;
                }
                input.setCustomValidity(valid() ? '' : input.dataset.layerError);
                if (valid()) {
                    last = input.value;
                    if (current) {
                        current.value = last;
                    }
                }
            }));
        });
        form?.querySelectorAll('[data-pixel-value]').forEach(input => {
            if (input.dataset.pixelReady) {
                return;
            }
            const display = form.querySelector(`[name="${input.name.replace(/^(\w+)/, '$1_px')}"]`);
            if (!display) {
                return;
            }
            input.dataset.pixelReady = '1';
            display.type = 'number';
            const descriptor = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value');
            let editing = false;
            let empty = false;
            let invalid = input.value !== '' && !/^-?\d+$/.test(display.value);
            display.setCustomValidity(invalid ? display.dataset.pixelError : '');
            const refresh = () => {
                if (!editing && !invalid && !empty) {
                    display.value = input.value === '' ? '' : rounded(input.value);
                    display.setCustomValidity('');
                }
            };
            // Geometry writers keep their existing precise field API. Update only the presentation.
            Object.defineProperty(input, 'value', {
                configurable: true,
                get() { return descriptor.get.call(this); },
                set(value) { descriptor.set.call(this, value); refresh(); },
            });
            const restore = () => {
                if (display.value === '' && !display.validity.badInput) {
                    empty = false;
                    invalid = false;
                    refresh();
                }
            };
            bindRestoration(display, restore);
            const attributes = () => {
                display.readOnly = input.readOnly;
                display.disabled = input.disabled;
                for (const name of ['min', 'max', 'title', 'placeholder']) {
                    const value = input.getAttribute(name);
                    if (value !== null) {
                        display.setAttribute(name, ['min', 'max'].includes(name) ? rounded(value) : value);
                    }
                }
            };
            attributes();
            new MutationObserver(attributes).observe(input, {attributes: true,
                attributeFilter: ['readonly', 'disabled', 'min', 'max', 'title', 'placeholder']});
            ['input', 'change'].forEach(type => {
                display.addEventListener(type, () => {
                    empty = display.value === '' && !display.validity.badInput;
                    if (empty) {
                        invalid = false;
                        display.setCustomValidity('');
                        if (type === 'change') {
                            restore();
                        }
                        return;
                    }
                    const valid = /^-?\d+$/.test(display.value) && !display.validity.badInput;
                    invalid = !valid;
                    display.setCustomValidity(valid ? '' : display.dataset.pixelError);
                    if (!valid || !display.checkValidity()) {
                        return;
                    }
                    editing = true;
                    input.value = display.value;
                    input.dispatchEvent(new Event(type, {bubbles: true}));
                    editing = false;
                    if (type === 'change') {
                        refresh();
                    }
                });
            });
            // Preserve a rejected submitted value until the teacher corrects it.
            if (display.value === '' && input.value !== '') {
                refresh();
            }
        });
        // Native validation precedes submit. Empty is temporarily valid; normalise before data collection.
        form?.addEventListener('submit', () => restorers.forEach(restore => restore()), true);
        form?.addEventListener('click', event => {
            if (event.target.closest('button[type=submit], input[type=submit]')) {
                restorers.forEach(restore => restore());
            }
        }, true);
    };
    return {init, rounded};
});
