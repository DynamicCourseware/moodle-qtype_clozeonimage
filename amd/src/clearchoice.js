// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Manage local Clear choice controls for positioned Multichoice subquestions.
 *
 * @module     qtype_clozeonimage/clearchoice
 * @copyright  2026 DynamicCourseware.org (Dominique Bauer)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {

    'use strict';

    const regionSelector = '[data-region="clozeonimage-multichoice"]';
    const choiceSelector = '[data-role="clozeonimage-multichoice-choice"]';
    const sentinelSelector = '[data-role="clozeonimage-clear-choice-sentinel"]';
    const clearSelector = '[data-action="clozeonimage-clear-choice"]';
    let initialised = false;

    /**
     * Return one element belonging to a local Multichoice region.
     *
     * @param {HTMLElement} region Multichoice feedback region.
     * @param {String} selector Element selector.
     * @returns {HTMLElement|null}
     */
    const getLocalElement = (region, selector) => region.querySelector(selector);

    /**
     * Show Clear only for a selected editable answer whose complete region contains focus.
     *
     * @param {HTMLElement} region Local radio feedback/control region.
     * @param {EventTarget|null} focused Focus destination, including during focusout.
     */
    const updateClear = (region, focused = document.activeElement) => {
        const button = getLocalElement(region, clearSelector);
        if (!button) {
            return;
        }
        const selected = region.querySelector(`${choiceSelector}:checked:not(:disabled)`);
        button.hidden = button.disabled || !selected || !focused || !region.contains(focused);
    };

    /**
     * Make the local Clear button available after a visible choice is selected.
     *
     * @param {HTMLInputElement} choice Selected visible radio.
     */
    const choiceChanged = choice => {
        if (!choice.checked || choice.matches(':disabled')) {
            return;
        }
        const region = choice.closest(regionSelector);
        const sentinel = getLocalElement(region, sentinelSelector);
        const button = getLocalElement(region, clearSelector);
        if (!sentinel || !button) {
            return;
        }
        sentinel.disabled = true;
        updateClear(region);
    };

    /**
     * Clear the selected response in one Multichoice subquestion.
     *
     * @param {HTMLButtonElement} button Activated local Clear button.
     */
    const clearChoice = button => {
        const region = button.closest(regionSelector);
        const selected = region?.querySelector(`${choiceSelector}:checked`);
        const sentinel = region ? getLocalElement(region, sentinelSelector) : null;
        if (!selected || !sentinel || selected.matches(':disabled') || button.disabled) {
            return;
        }

        selected.checked = false;
        sentinel.disabled = false;
        button.hidden = true;
        sentinel.dispatchEvent(new Event('change', {bubbles: true}));
        region.querySelector(`${choiceSelector}:not(:disabled)`)?.focus();
    };

    /**
     * Initialise delegated Clear choice interactions.
     */
    const init = () => {
        // Account for focus restored before this module loads, and for newly rendered regions.
        document.querySelectorAll(regionSelector).forEach(region => {
            const sentinel = getLocalElement(region, sentinelSelector);
            if (sentinel) {
                // A restored visible selection must be the sole submitted value.
                sentinel.disabled = !!region.querySelector(`${choiceSelector}:checked`);
            }
            updateClear(region);
        });
        if (initialised) {
            return;
        }
        initialised = true;

        document.addEventListener('change', event => {
            if (event.target.matches(choiceSelector)) {
                choiceChanged(event.target);
            }
        });

        document.addEventListener('click', event => {
            const button = event.target.closest(clearSelector);
            if (button) {
                clearChoice(button);
            }
        });

        document.addEventListener('focusin', event => {
            const region = event.target.closest(regionSelector);
            if (!region) {
                return;
            }
            updateClear(region);
        });

        document.addEventListener('focusout', event => {
            const region = event.target.closest(regionSelector);
            if (region) {
                // The next radio or Clear button belongs to the same scope; null means focus left it.
                updateClear(region, event.relatedTarget);
            }
        });
    };

    return {init};
});
