// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Reorder editable subquestion rows locally using Moodle's standard move interaction.
 *
 * @module qtype_clozeonimage/reorder
 * @copyright 2026 DynamicCourseware.org (Dominique Bauer)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/sortable_list', 'editor_tiny/editor', 'core/notification'],
    function($, SortableList, Tiny, Notification) {
    'use strict';

    const listSelector = '#qtype-clozeonimage-rows';
    const rowSelector = '.qtype-clozeonimage-subquestion-row';
    const handleSelector = `${listSelector} [data-drag-type="move"]`;
    const previewByRow = new WeakMap();

    /**
     * Match the submitted indices and accessible references to the visible row order.
     * Existing controls are retained, including their values, listeners and selection.
     *
     * @param {HTMLElement} list The list after the core component has removed its proxy.
     */
    const renumber = list => {
        const rows = Array.from(list.children).filter(row => row.matches(rowSelector));
        const checked = Array.from(list.querySelectorAll('input[type="radio"]:checked'));
        // Temporarily separate radio groups so renaming one row cannot uncheck another row.
        list.querySelectorAll('input[type="radio"]').forEach(input => {
            input.dataset.reorderName = input.name;
            input.removeAttribute('name');
        });
        rows.forEach((row, index) => {
            const newNumber = String(index + 1);
            const nodes = [row, ...row.querySelectorAll('*')];
            nodes.forEach(node => {
                if (node.dataset.reorderName) {
                    node.setAttribute('name', node.dataset.reorderName);
                    delete node.dataset.reorderName;
                }
                // Keep IDs and QuickForm group references stable: Moodle's label and
                // validation listeners retain those references. Only submitted names change.
                if (node.hasAttribute('name')) {
                    node.setAttribute('name', node.getAttribute('name').replace(/\[\d+\]/g, `[${index}]`));
                }
                // These labels contain only the row number, never the editable source.
                if (node.matches('textarea, .qtype-clozeonimage-coordinate, .qtype-clozeonimage-blockwidth, ' +
                        '.qtype-clozeonimage-anchor-area, .qtype-clozeonimage-select-block, ' +
                        '[data-drag-type="move"]')) {
                    ['aria-label', 'title'].forEach(attribute => {
                        if (node.hasAttribute(attribute)) {
                            node.setAttribute(attribute, node.getAttribute(attribute).replace(/\d+/, newNumber));
                        }
                    });
                }
            });
            row.querySelectorAll('.qtype-clozeonimage-row-number').forEach(number => {
                if (number.dataset.blockLabel) {
                    number.textContent = number.dataset.blockLabel.replace('{blockno}', newNumber);
                } else {
                    number.lastChild.textContent = ` ${newNumber}`;
                }
            });
            row.querySelectorAll('.qtype-clozeonimage-content-label').forEach(label => {
                label.textContent = label.textContent.replace(/\d+/, newNumber);
            });
            row.querySelectorAll('.form-control-feedback .visually-hidden').forEach(prefix => {
                prefix.textContent = prefix.textContent.replace(/\d+/, newNumber);
            });
            const preview = previewByRow.get(row);
            if (preview) {
                preview.dataset.rowIndex = String(index);
                preview.querySelector('.qtype-clozeonimage-preview-number').textContent = newNumber;
                preview.querySelectorAll('[aria-label], legend').forEach(label => {
                    if (label.hasAttribute('aria-label')) {
                        label.setAttribute('aria-label', label.getAttribute('aria-label').replace(/\d+/, newNumber));
                    } else {
                        label.textContent = label.textContent.replace(/\d+/, newNumber);
                    }
                });
            }
        });
        checked.forEach(input => {
            input.checked = true;
        });
    };

    /**
     * Initialise once; core's delegated handlers also accept subsequently inserted rows.
     * @param {Boolean} useTiny Whether block editors use TinyMCE
     * @param {Object|null} focusTarget Row index and control to focus after a non-saving rebuild
     */
    const init = (useTiny = false, focusTarget = null) => {
        const form = document.querySelector('form.mform[data-qtype="clozeonimage"]');
        if (!form || form.querySelector(`${listSelector}[data-initialised]`)) {
            return;
        }
        const rows = Array.from(form.querySelectorAll(rowSelector));
        const list = form.querySelector(listSelector);
        list.dataset.initialised = 'true';
        // Group labels are rendered twice by QuickForm, with the visible copy aria-hidden.
        // Place the single core handle beside that label, outside its aria-hidden container.
        const prepareRow = row => {
            // Retain the association by DOM identity across repeated atomic moves.
            if (!previewByRow.has(row)) {
                const index = row.querySelector('textarea').name.match(/\[(\d+)\]/)[1];
                previewByRow.set(row, document.querySelector(
                    `.qtype-clozeonimage-preview-subquestion[data-row-index="${index}"]`
                ));
            }
            const checkbox = row.querySelector('.qtype-clozeonimage-select-block');
            if (checkbox && checkbox.parentElement !== row) {
                row.prepend(checkbox);
            }
            const label = row.querySelector('.col-form-label');
            const handle = row.querySelector('.qtype-clozeonimage-move');
            if (label && handle && handle.parentElement !== label) {
                label.prepend(handle);
            }
        };
        rows.forEach(row => {
            prepareRow(row);

        });
        new MutationObserver(records => {
            records.forEach(record => record.addedNodes.forEach(row => {
                if (row.nodeType === Node.ELEMENT_NODE && row.isConnected && row.matches(rowSelector) &&
                        !row.classList.contains('sortable-list-is-dragged')) {
                    prepareRow(row);
                }
            }));
        }).observe(list, {childList: true});
        const selectAll = form.querySelector('[data-action="select-all-blocks"]');
        const deleteButton = form.querySelector('[data-action="delete-selected-blocks"]');
        const insertButton = form.querySelector('[data-action="insert-selected-blocks"]');
        const liveRows = () => Array.from(list.children).filter(row => row.matches(rowSelector) &&
            !row.classList.contains('sortable-list-is-dragged'));
        const saveEditors = () => liveRows().forEach(row => row.querySelectorAll('textarea').forEach(target =>
            Tiny.getInstanceForElement(target)?.save()));
        const rebuild = (orderedRows, focusRow, kind) => {
            const previous = liveRows();
            form.querySelector('[name="blockorder"]').value = orderedRows.map(row => previous.indexOf(row)).join(',');
            form.querySelector('[name="blockfocusorigin"]').value =
                focusRow?.querySelector('[name^="roworigin"]').value || '0';
            form.querySelector('[name="blockfocuskind"]').value = kind;
            // Keep every editor alive until navigation: native autosave has private pending callbacks.
            // The registered Moodle no-submit button rebuilds draft fields without saving the question.
            saveEditors();
            form.querySelector('[name="rebuildblocks"]').click();
        };
        if (selectAll && deleteButton) {
            const updateSelection = () => {
                const checkboxes = liveRows().map(row => row.querySelector('.qtype-clozeonimage-select-block'));
                const count = checkboxes.filter(checkbox => checkbox.checked).length;
                selectAll.checked = count > 0 && count === checkboxes.length;
                selectAll.indeterminate = count > 0 && count < checkboxes.length;
                selectAll.disabled = !checkboxes.length;
                deleteButton.disabled = !count;
                if (insertButton) {
                    insertButton.disabled = !count && checkboxes.length > 0;
                }
            };
            updateSelection();
            list.addEventListener('change', updateSelection);
            selectAll.addEventListener('change', () => {
                liveRows().forEach(row => {
                    row.querySelector('.qtype-clozeonimage-select-block').checked = selectAll.checked;
                });
                updateSelection();
            });
            insertButton?.addEventListener('click', event => {
                // Snapshot current rows before the form rebuild; new rows never become targets.
                const targets = liveRows().flatMap((row, index) =>
                    row.querySelector('.qtype-clozeonimage-select-block').checked ? [index] : []);
                if (!targets.length && liveRows().length) {
                    event.preventDefault();
                    return;
                }
                form.querySelector('[name="insertbefore"]').value = targets.join(',');
                saveEditors();
            });
            deleteButton.addEventListener('click', () => {
                const selected = liveRows().filter(row => row.querySelector('.qtype-clozeonimage-select-block').checked);
                if (!selected.length) {
                    return;
                }
                Notification.deleteCancel(deleteButton.dataset.title,
                    deleteButton.dataset.confirm.replace('{count}', String(selected.length)), deleteButton.dataset.delete, () => {
                        const remaining = liveRows().filter(row => !selected.includes(row));
                        rebuild(remaining, remaining[0], 'checkbox');
                    }, undefined, {triggerElement: deleteButton}).catch(Notification.exception);
            });
        }
        // Move QuickForm action elements before restoring focus: detaching a focused button blurs it.
        Array.from(list.children).filter(node => !node.matches(rowSelector)).reverse().forEach(node => list.after(node));
        // Reveal handles before restoring focus, including when Tiny is already initialised.
        list.classList.add('qtype-clozeonimage-sortable');
        if (focusTarget !== null) {
            const target = focusTarget.index === null ? null :
                form.querySelector(`textarea[name="subquestion[${focusTarget.index}][text]"]`);
            if (target) {
                const restoreFocus = () => {
                    const editor = Tiny.getInstanceForElement(target);
                    if (useTiny && !editor?.initialized) {
                        return false;
                    }
                    const row = target.closest(rowSelector);
                    row.scrollIntoView({block: 'center'});
                    if (focusTarget.kind === 'editor') {
                        if (editor) {
                            editor.focus();
                        } else {
                            target.focus();
                        }
                    } else {
                        row.querySelector(focusTarget.kind === 'checkbox' ? '.qtype-clozeonimage-select-block' :
                            '[data-drag-type="move"]')?.focus();
                    }
                    return true;
                };
                if (!restoreFocus()) {
                    const observer = new MutationObserver(() => {
                        if (restoreFocus()) {
                            observer.disconnect();
                        }
                    });
                    observer.observe(target, {attributes: true, attributeFilter: ['data-fieldtype']});
                }
            } else {
                form.querySelector('[name="addsubquestions"]')?.focus();
            }
        }
        const sortable = new SortableList(listSelector, {moveHandlerSelector: handleSelector});
        sortable.getElementName = row => Promise.resolve(row.find('.qtype-clozeonimage-row-number').first().text().trim());

        // Core's default proxy clones live named inputs. Strip names/IDs BEFORE insertion to
        // protect radio groups, label associations and FormData, even during an active drag.
        sortable.createProxy = function() {
            this.proxy = this.info.element.clone();
            this.proxy.find('[id]').addBack().removeAttr('id');
            this.proxy.find('[name]').removeAttr('name');
            this.proxy.find('input, textarea, select, button').prop('disabled', true);
            this.proxy.attr({'aria-hidden': 'true', inert: ''})
                .removeClass('sortable-list-current-position').addClass('sortable-list-is-dragged')
                .css({position: 'fixed', width: this.info.element.outerWidth()});
            this.info.sourceList.append(this.proxy);
            this.proxy.offset({top: this.proxyDelta.y + this.lastEvent.pageY,
                left: this.proxyDelta.x + this.lastEvent.pageX});
        };

        // Atomic moves preserve the iframe, native autosave listeners and Undo history.
        // Without this API, collect the destination without detaching any live editor;
        // only a confirmed drop submits a no-save form rebuild.
        let pendingMove = null;
        sortable.moveElement = function(parent, before) {
            const row = this.info.element[0];
            if (before[0] === row) {
                return;
            }
            if (typeof parent[0].moveBefore === 'function') {
                const destination = before[0] || (this.proxy?.parent()[0] === parent[0] ? this.proxy[0] : null);
                parent[0].moveBefore(row, destination);
            } else {
                pendingMove = {row, before: before[0] || null};
            }
            this.info.targetList = parent;
            this.info.targetNextElement = before;
            this.executeCallback(SortableList.EVENTS.elementDrag);
        };

        let focus;
        let selection;
        list.addEventListener(SortableList.EVENTS.elementDragStart, event => {
            const row = event.detail.element[0];
            focus = row.contains(document.activeElement) ? document.activeElement : row.querySelector('[data-drag-type="move"]');
            selection = typeof focus.selectionStart === 'number' ? [focus.selectionStart, focus.selectionEnd,
                focus.selectionDirection] : null;
        });
        list.addEventListener(SortableList.EVENTS.elementDragEnd, event => {
            const move = pendingMove;
            pendingMove = null;
            if (event.detail.dropped && event.detail.positionChanged) {
                if (move) {
                    const ordered = liveRows().filter(row => row !== move.row);
                    const index = move.before ? ordered.indexOf(move.before) : ordered.length;
                    ordered.splice(index < 0 ? ordered.length : index, 0, move.row);
                    rebuild(ordered, move.row, focus?.matches('iframe, textarea') ? 'editor' : 'handle');
                    return;
                }
                renumber(list);
                // Notify Moodle's form change checker; persistence still uses the normal submit.
                $(event.detail.element[0].querySelector('textarea')).trigger('change');
            }
            if (focus?.isConnected) {
                focus.focus({preventScroll: true});
                if (selection) {
                    focus.setSelectionRange(...selection);
                }
            }
        });
    };

    return {init};
});
