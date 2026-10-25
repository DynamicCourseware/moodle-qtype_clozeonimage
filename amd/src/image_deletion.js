// This file is part of Moodle - https://moodle.org/
// Moodle is free software under the GNU GPL v3 or later.
/**
 * Confirm deferred image deletion only when committing the question form.
 * @module qtype_clozeonimage/image_deletion
 * @copyright 2026 DynamicCourseware.org (Dominique Bauer)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/notification', 'core/str', 'core/modal_events'], function(Notification, Str, ModalEvents) {
    'use strict';
    const init = () => {
        const form = document.querySelector('form[data-qtype="clozeonimage"]');
        if (!form || form.dataset.imageDeletionReady) {
            return;
        }
        form.dataset.imageDeletionReady = '1';
        let pending = false;
        let approved = null;
        form.addEventListener('submit', event => {
            const button = event.submitter;
            if (!button || !['submitbutton', 'updatebutton'].includes(button.name) || approved === button) {
                return;
            }
            const selected = [...form.querySelectorAll('input[type=checkbox][data-image-label]:checked')];
            if (!selected.length) {
                return;
            }
            // Stop before Moodle's validation/change-checker/submit listeners mark this as a real submission.
            event.preventDefault();
            event.stopImmediatePropagation();
            if (pending) {
                return;
            }
            pending = true;
            const confirm = async() => {
                const names = selected.map(input => {
                    const text = document.createElement('span');
                    text.textContent = input.dataset.imageLabel;
                    return text.innerHTML;
                });
                let list = names[0];
                if (names.length > 1) {
                    const separator = await Str.get_string('listsep', 'langconfig');
                    list = await Str.get_string('and', 'moodle', {
                        one: names.slice(0, -1).join(separator + ' '), two: names.at(-1),
                    });
                }
                const [title, body, proceed] = await Str.get_strings([
                    {key: 'confirm', component: 'moodle'},
                    {key: 'confirmimagedeletion', component: 'qtype_clozeonimage', param: list},
                    {key: 'continue', component: 'moodle'},
                ]);
                let handled = false;
                const modal = await Notification.saveCancel(title, body, proceed, () => {
                    if (handled) {
                        return;
                    }
                    handled = true;
                    approved = button;
                    try {
                        // Retain the original submitter, normal validation, TinyMCE synchronisation and save action.
                        form.requestSubmit(button);
                    } finally {
                        approved = null;
                    }
                }, undefined, {triggerElement: button});
                // Also cover Escape and the dialogue's close button, not only its Cancel button.
                modal.getRoot().on(ModalEvents.hidden, () => {
                    pending = false;
                });
            };
            confirm().catch(error => {
                pending = false;
                Notification.exception(error);
            });
        }, true);
    };
    return {init};
});
