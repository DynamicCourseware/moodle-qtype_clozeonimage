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
define(['jquery', 'core/sortable_list'], function($, SortableList) {
    'use strict';

    const listSelector = '#qtype-clozeonimage-rows';
    const rowSelector = '.qtype-clozeonimage-subquestion-row';
    const handleSelector = `${listSelector} [data-drag-type="move"]`;

    /**
     * Match the submitted indices and accessible references to the visible row order.
     * Existing controls are retained, including their values, listeners and selection.
     *
     * @param {HTMLElement} list The list after the core component has removed its proxy.
     */
    const renumber = list => {
        const previews = new Map(Array.from(document.querySelectorAll(
            '.qtype-clozeonimage-preview-subquestion'
        ), control => [control.dataset.rowIndex, control]));
        const rows = Array.from(list.children);
        const checked = Array.from(list.querySelectorAll('input[type="radio"]:checked'));
        // Temporarily separate radio groups so renaming one row cannot uncheck another row.
        list.querySelectorAll('input[type="radio"]').forEach(input => {
            input.dataset.reorderName = input.name;
            input.removeAttribute('name');
        });
        rows.forEach((row, index) => {
            const oldIndex = row.querySelector('textarea').name.match(/\[(\d+)\]/)[1];
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
                if (node.matches('textarea, .qtype-clozeonimage-coordinate, .qtype-clozeonimage-anchor-area, ' +
                        '[data-drag-type="move"]')) {
                    ['aria-label', 'title'].forEach(attribute => {
                        if (node.hasAttribute(attribute)) {
                            node.setAttribute(attribute, node.getAttribute(attribute).replace(/\d+/, newNumber));
                        }
                    });
                }
            });
            row.querySelectorAll('.qtype-clozeonimage-row-number').forEach(number => {
                number.lastChild.textContent = ` ${newNumber}`;
            });
            row.querySelectorAll('.form-control-feedback .visually-hidden').forEach(prefix => {
                prefix.textContent = prefix.textContent.replace(/\d+/, newNumber);
            });
            const preview = previews.get(oldIndex);
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

    /** Initialise once; core's delegated handlers also accept subsequently inserted rows. */
    const init = () => {
        const form = document.querySelector('form.mform[data-qtype="clozeonimage"]');
        if (!form || form.querySelector(listSelector)) {
            return;
        }
        const rows = Array.from(form.querySelectorAll(rowSelector));
        if (!rows.length) {
            return;
        }
        const list = document.createElement('div');
        list.id = listSelector.slice(1);
        rows[0].before(list);
        // Group labels are rendered twice by QuickForm, with the visible copy aria-hidden.
        // Place the single core handle beside that label, outside its aria-hidden container.
        const prepareRow = row => {
            const label = row.querySelector('.col-form-label');
            const handle = row.querySelector('.qtype-clozeonimage-move');
            if (label && handle && handle.parentElement !== label) {
                label.prepend(handle);
            }
        };
        rows.forEach(row => {
            prepareRow(row);
            list.append(row);
        });
        new MutationObserver(records => {
            records.forEach(record => record.addedNodes.forEach(row => {
                if (row.nodeType === Node.ELEMENT_NODE && row.matches(rowSelector) &&
                        !row.classList.contains('sortable-list-is-dragged')) {
                    prepareRow(row);
                }
            }));
        }).observe(list, {childList: true});
        // One CSS rule reveals handles, including those in dynamically appended rows.
        list.classList.add('qtype-clozeonimage-sortable');
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

        let focus;
        let selection;
        list.addEventListener(SortableList.EVENTS.elementDragStart, event => {
            const row = event.detail.element[0];
            focus = row.contains(document.activeElement) ? document.activeElement : row.querySelector('[data-drag-type="move"]');
            selection = typeof focus.selectionStart === 'number' ? [focus.selectionStart, focus.selectionEnd,
                focus.selectionDirection] : null;
        });
        list.addEventListener(SortableList.EVENTS.elementDragEnd, event => {
            if (event.detail.dropped && event.detail.positionChanged) {
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
