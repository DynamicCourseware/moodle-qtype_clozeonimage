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
 * Coordinate feedback popovers for positioned Cloze on Image controls.
 *
 * @module     qtype_clozeonimage/feedback
 * @copyright  2026 DynamicCourseware.org (Dominique Bauer)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['bootstrap'], function(Bootstrap) {

    'use strict';

    const localTriggerSelector = '.qtype-clozeonimage-feedback-trigger[data-bs-toggle="popover"]';
    const triggerSelector = `.que.clozeonimage ${localTriggerSelector}`;
    const feedbackRegionSelector = '.qtype-clozeonimage-feedback-region';
    const clearSentinelSelector = '[data-role="clozeonimage-clear-choice-sentinel"]';
    const resultStateSelector = '[data-role="clozeonimage-result-state"]';
    const localStateSelector = [
        '.form-control.correct',
        '.form-control.incorrect',
        '.form-control.partiallycorrect',
        '.form-select.correct',
        '.form-select.incorrect',
        '.form-select.partiallycorrect',
        '.qtype-clozeonimage-choice.correct',
        '.qtype-clozeonimage-choice.incorrect',
        '.qtype-clozeonimage-choice.partiallycorrect',
    ].join(',');
    const stateClasses = [
        'qtype-clozeonimage-state-correct',
        'qtype-clozeonimage-state-incorrect',
        'qtype-clozeonimage-state-partiallycorrect',
        'qtype-clozeonimage-state-notanswered',
    ];
    const popoverAttributes = [
        'data-bs-toggle',
        'data-bs-container',
        'data-bs-content',
        'data-bs-placement',
        'data-bs-trigger',
        'data-bs-html',
        'data-bs-custom-class',
        'aria-describedby',
    ];
    let initialised = false;
    let transientTrigger = null;
    let pinnedTrigger = null;

    /**
     * Hide one feedback popover and optionally remove focus from its trigger.
     *
     * @param {HTMLElement|null} trigger Feedback trigger.
     * @param {Boolean} blur Whether the trigger should lose focus.
     */
    const close = (trigger, blur = false) => {
        if (!trigger) {
            return;
        }
        if (pinnedTrigger === trigger) {
            pinnedTrigger = null;
        }
        Bootstrap.Popover.getInstance(trigger)?.hide();
        if (blur && (document.activeElement === trigger || trigger.contains(document.activeElement))) {
            document.activeElement.blur();
        }
        if (transientTrigger === trigger) {
            transientTrigger = null;
        }
    };

    /**
     * Make one trigger transiently active without disturbing a pinned trigger.
     *
     * @param {HTMLElement} trigger Feedback trigger.
     */
    const activateTransient = trigger => {
        if (trigger === pinnedTrigger) {
            close(transientTrigger);
            return;
        }
        if (transientTrigger && transientTrigger !== trigger) {
            close(transientTrigger, document.activeElement === transientTrigger);
        }
        transientTrigger = trigger;
    };

    /**
     * Prevent Bootstrap's hover/focus handlers from hiding a click-pinned popover.
     *
     * @param {Event} event Popover hide event.
     */
    const preventPinnedHide = event => {
        if (event.target === pinnedTrigger) {
            event.preventDefault();
        }
    };

    /**
     * Forget a transient trigger after Bootstrap has hidden its popover.
     *
     * @param {Event} event Popover hidden event.
     */
    const popoverHidden = event => {
        if (event.target === transientTrigger) {
            transientTrigger = null;
        }
    };

    /**
     * Remove one stale popover trigger without moving focus or changing its answer.
     *
     * @param {HTMLElement} trigger Feedback trigger.
     */
    const discardTrigger = trigger => {
        if (pinnedTrigger === trigger) {
            pinnedTrigger = null;
        }
        if (transientTrigger === trigger) {
            transientTrigger = null;
        }
        Bootstrap.Popover.getInstance(trigger)?.dispose();
        trigger.classList.remove('qtype-clozeonimage-feedback-trigger');
        popoverAttributes.forEach(attribute => trigger.removeAttribute(attribute));
    };

    /**
     * Return one editable feedback region to its neutral state after its answer changes.
     *
     * @param {HTMLElement} region Feedback region containing the changed answer.
     */
    const resetRegion = region => {
        if (region.matches(localTriggerSelector)) {
            discardTrigger(region);
        }
        region.querySelectorAll(localTriggerSelector).forEach(discardTrigger);
        region.classList.remove(...stateClasses);
        region.querySelectorAll(localStateSelector).forEach(element => {
            element.classList.remove('correct', 'incorrect', 'partiallycorrect');
        });
        region.querySelectorAll(resultStateSelector).forEach(element => element.remove());
    };

    /**
     * Initialise Bootstrap popovers owned by this plugin.
     */
    const initialisePopovers = () => {
        document.querySelectorAll(triggerSelector).forEach(trigger => {
            Bootstrap.Popover.getOrCreateInstance(trigger);
        });
    };

    /**
     * Initialise event-delegated popover coordination.
     */
    const init = () => {
        initialisePopovers();
        if (initialised) {
            return;
        }
        initialised = true;

        document.addEventListener('hide.bs.popover', preventPinnedHide, true);
        document.addEventListener('hidden.bs.popover', popoverHidden, true);

        document.addEventListener('mousedown', event => {
            // Readonly text inputs can match :focus-visible even when clicked. Keep keyboard focus
            // available, but do not focus an Interactive result with the mouse before Try again.
            if (event.button === 0 && event.target.matches(
                '.que.clozeonimage input[readonly][data-clozeonimage-awaiting-retry="true"]'
            )) {
                event.preventDefault();
            }
        });

        document.addEventListener('focusin', event => {
            const trigger = event.target.closest(triggerSelector);
            if (trigger) {
                activateTransient(trigger);
            }
        });

        document.addEventListener('pointerover', event => {
            const trigger = event.target.closest(triggerSelector);
            if (trigger) {
                activateTransient(trigger);
            }
        });

        document.addEventListener('click', event => {
            const trigger = event.target.closest(triggerSelector);

            if (pinnedTrigger) {
                const previouslyPinned = pinnedTrigger;
                close(previouslyPinned, document.activeElement === previouslyPinned);
                if (!trigger || trigger === previouslyPinned) {
                    close(transientTrigger, document.activeElement === transientTrigger);
                    return;
                }
            }

            if (trigger) {
                if (transientTrigger && transientTrigger !== trigger) {
                    close(transientTrigger, document.activeElement === transientTrigger);
                }
                if (transientTrigger === trigger) {
                    transientTrigger = null;
                }
                pinnedTrigger = trigger;
                Bootstrap.Popover.getOrCreateInstance(trigger).show();
                return;
            }
            close(transientTrigger, true);
        });

        document.addEventListener('input', event => {
            if (!event.target.matches(`${triggerSelector}.form-control`)) {
                return;
            }
            const region = event.target.closest(feedbackRegionSelector);
            if (region) {
                resetRegion(region);
            }
        });

        document.addEventListener('change', event => {
            const changedsurface = event.target.matches('select, input[type="radio"], input[type="checkbox"]');
            if (!changedsurface && !event.target.matches(clearSentinelSelector)) {
                return;
            }
            const region = event.target.closest(feedbackRegionSelector);
            if (region && (region.matches(localTriggerSelector) || region.querySelector(localTriggerSelector))) {
                resetRegion(region);
            }
        });

        document.addEventListener('keydown', event => {
            if (event.key !== 'Escape') {
                return;
            }

            const eventTrigger = event.target.closest(triggerSelector);
            const trigger = eventTrigger ?? transientTrigger ?? pinnedTrigger;
            if (!trigger) {
                return;
            }
            event.preventDefault();
            close(trigger, true);
        });
    };

    return {init};
});
