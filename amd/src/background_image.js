// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Enhance the background filepicker without changing its draft or save lifecycle.
 *
 * @module     qtype_clozeonimage/background_image
 * @copyright  2026 DynamicCourseware.org (Dominique Bauer)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import Dropzone from 'core/dropzone';
import {getStrings} from 'core/str';
import {notifyUploadStarted, notifyUploadCompleted} from 'core_form/events';
import Log from 'core/log';

/**
 * Wait for the standard filepicker's independent YUI initialisation.
 *
 * @param {HTMLInputElement} field Background draft field.
 * @returns {Promise<Object|null>} The standard picker, or null if unavailable.
 */
const getPicker = async field => {
    for (let attempt = 0; attempt < 100; attempt++) {
        const picker = Object.values(M.core_filepicker?.instances ?? {})
            .find(instance => instance.options.elementid === field.id);
        if (picker && M.form_filepicker?.instances[field.id]) {
            return picker;
        }
        await new Promise(resolve => setTimeout(resolve, 100));
    }
    return null;
};

/**
 * Add the optional dropzone. The standard picker remains usable if enhancement fails.
 * @param {HTMLInputElement} field Independent Moodle filepicker draft field.
 */
const enhance = async field => {
    if (!field || field.dataset.dropzoneInitialised) {
        return;
    }
    field.dataset.dropzoneInitialised = '1';
    let host;
    try {
        const picker = await getPicker(field);
        if (!picker) {
            return;
        }
        const options = picker.options;
        const wrapper = document.getElementById(`filepicker-wrapper-${options.client_id}`);
        const browse = document.getElementById(`filepicker-button-${options.client_id}`);
        const fileInfo = document.getElementById(`file_info_${options.client_id}`);
        const repository = Object.values(options.repositories).find(repo => repo.type === 'upload');
        if (!repository || !wrapper || !browse || !fileInfo) {
            return;
        }
        const [browseLabel, uploading, uploadError, currentImageLabel] = await getStrings([
            {key: 'browserepositoriesimage', component: 'tiny_media'},
            {key: 'uploading', component: 'repository'},
            {key: 'uploaderror', component: 'repository'},
            {key: 'currentcompositionimage', component: 'qtype_clozeonimage'},
        ]);
        host = document.createElement('div');
        host.className = 'qtype-clozeonimage-background-dropzone';
        const status = document.createElement('div');
        status.className = 'qtype-clozeonimage-background-status';
        status.setAttribute('role', 'status');
        status.setAttribute('aria-live', 'polite');
        let busy = false;
        const labelCurrentImage = () => {
            fileInfo.querySelectorAll('.qtype-clozeonimage-current-image-label').forEach(label => label.remove());
            const link = fileInfo.querySelector('.filepicker-filename a');
            if (!link) {
                return;
            }
            // Core's initial filepicker-container ends the current file with " - ".
            // Remove only these separator/whitespace text nodes, preserving the actual
            // anchor, its URL/behaviour and the standard progress container.
            Array.from(link.parentNode.childNodes).forEach(node => {
                if (node.nodeType === Node.TEXT_NODE && /^[\s-]*$/.test(node.textContent)) {
                    node.remove();
                }
            });
            const label = document.createElement('span');
            label.className = 'qtype-clozeonimage-current-image-label';
            label.textContent = `${currentImageLabel} `;
            link.before(label);
        };
        const standardCallback = options.formcallback;
        const selectFile = params => {
            // Also refresh same-name replacements made through Browse repositories.
            const url = new URL(params.url, window.location.href);
            url.searchParams.set('v', Date.now());
            standardCallback({...params, url: url.href});
            labelCurrentImage();
            field.dispatchEvent(new CustomEvent('clozeonimage:imagefile', {bubbles: true}));
            status.classList.remove('text-danger');
            status.textContent = '';
        };

        const upload = async files => {
            // Cancelled selectors, concurrent drops, and disabled form fields do not upload.
            if (!files?.length || busy || field.closest('.fitem.disabled')) {
                return;
            }
            busy = true;
            browse.disabled = true;
            host.setAttribute('aria-busy', 'true');
            const input = host.querySelector('input[type="file"]');
            input.disabled = true;
            status.classList.remove('text-danger');
            status.textContent = uploading;
            try {
                await notifyUploadStarted(field.id);
                const body = new FormData();
                const params = {
                    repo_id: repository.id,
                    itemid: field.value,
                    ctx_id: options.context.id,
                    env: options.env,
                    sesskey: M.cfg.sesskey,
                    client_id: options.client_id,
                    maxbytes: options.maxbytes ?? 0,
                    areamaxbytes: options.areamaxbytes ?? -1,
                    savepath: '/',
                    author: options.author ?? '',
                    // Core validates the incoming file before replacing an existing draft file.
                    overwrite: 1,
                };
                Object.entries(params).forEach(([key, value]) => body.append(key, value));
                options.accepted_types.forEach(type => body.append('accepted_types[]', type));
                // Like the single-file picker, only the first dropped file is selected.
                body.append('repo_upload_file', files[0], files[0].name);
                const response = await fetch(`${M.cfg.wwwroot}/repository/repository_ajax.php?action=upload`, {
                    method: 'POST', body, credentials: 'same-origin',
                });
                if (!response.ok) {
                    throw new Error(uploadError);
                }
                const result = await response.json();
                if (result.error || !result.url || !result.file) {
                    // Repository errors may contain formatted accepted-type descriptions.
                    const message = new DOMParser().parseFromString(result.error || uploadError, 'text/html');
                    throw new Error(message.body.textContent);
                }
                selectFile({...result, client_id: options.client_id});
            } catch (error) {
                // Do not clear the previous link, preview or draft on failure.
                status.classList.add('text-danger');
                status.textContent = error.message || uploadError;
            } finally {
                notifyUploadCompleted(field.id);
                input.value = '';
                input.disabled = false;
                browse.disabled = false;
                host.removeAttribute('aria-busy');
                busy = false;
            }
        };

        const types = options.accepted_types.join(',');
        const dropzone = new Dropzone(host, types, upload);
        // core/dropzone.init() does not return its rendering promise. Observe it so we only
        // replace the standard presentation after the template and translations succeeded.
        const render = dropzone.renderDropZone.bind(dropzone);
        let rendered;
        dropzone.renderDropZone = (...args) => {
            rendered = render(...args);
            return rendered;
        };
        dropzone.init();
        await rendered;

        // The new dropzone is outside the legacy drop target. Stop its events bubbling to
        // legacy page handlers, and disable drops on the old target once enhancement succeeds.
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(type => {
            host.addEventListener(type, event => event.stopPropagation());
            fileInfo.addEventListener(type, event => {
                event.preventDefault();
                event.stopImmediatePropagation();
            }, true);
        });
        const accessLink = host.querySelector('.dropzone-visually-hidden-focusable');
        accessLink.addEventListener('click', event => event.preventDefault());
        accessLink.addEventListener('keydown', event => {
            if (event.key === ' ') {
                event.preventDefault();
                accessLink.click();
            }
        });
        // Keep the original button and its Moodle repository handler, including keyboard access.
        browse.value = browseLabel;
        browse.classList.replace('btn-secondary', 'btn-primary');
        const actions = browse.parentElement;
        actions.classList.add('qtype-clozeonimage-background-actions');
        wrapper.prepend(host);
        actions.after(status, fileInfo);
        wrapper.classList.add('qtype-clozeonimage-background-picker');
        // Legacy drag handlers only need fileInfo and its progress container, not these
        // presentation nodes. Keep the filename/container and remove both old drop hints.
        fileInfo.querySelectorAll('.dndupload-message, .dndupload-target, .dndupload-arrow')
            .forEach(element => element.remove());
        // The repository picker tolerates absent type descriptions. Restrictions continue
        // to come from options.accepted_types and Moodle's server-side validation.
        field.closest('.fitem').querySelectorAll('.filetypes-descriptions-intro, .form-filetypes-descriptions')
            .forEach(element => element.remove());
        options.formcallback = selectFile;
        labelCurrentImage();
        field.form.addEventListener('submit', event => {
            if (busy && event.submitter?.name !== 'cancel') {
                event.preventDefault();
                event.stopImmediatePropagation();
            }
        }, true);
    } catch (error) {
        host?.remove();
        Log.warn('Background image dropzone unavailable; using the standard filepicker.', error);
    }
};

/** Enhance each independent standard picker; failure remains local to that image. */
export const init = async() => Promise.all([...document.querySelectorAll(
    'form[data-qtype="clozeonimage"] input.filepickerhidden[name^="sceneimage["], ' +
    'form[data-qtype="clozeonimage"] input[name="bgimage"]')].map(enhance));
