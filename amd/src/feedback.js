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
 * Manage persistent inline-feedback disclosure for positioned choice controls.
 *
 * @module     qtype_clozeonimage/feedback
 * @copyright  2026 DynamicCourseware.org (Dominique Bauer)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {

    'use strict';

    const regionSelector = '[data-region="clozeonimage-choice-feedback"]';
    const triggerSelector = '[data-region="clozeonimage-feedback-trigger"]';
    const popoverTriggerSelector = '.que.clozeonimage .feedbacktrigger[data-bs-toggle="popover"]';
    const openClass = 'qtype-clozeonimage-feedback-open';
    const dismissedClass = 'qtype-clozeonimage-feedback-dismissed';
    let initialised = false;
    let activeTrigger = null;

    /**
     * Synchronise the persistent disclosure state for one feedback region.
     *
     * @param {HTMLElement} region Feedback region.
     * @param {Boolean} open Whether the region is persistently open.
     */
    const setOpen = (region, open) => {
        region.classList.toggle(openClass, open);
        setExpanded(region, open);
    };

    /**
     * Synchronise aria-expanded across equivalent triggers in one region.
     *
     * @param {HTMLElement} region Feedback region.
     * @param {Boolean} expanded Whether the feedback is currently exposed.
     */
    const setExpanded = (region, expanded) => {
        region.querySelectorAll(triggerSelector).forEach(trigger => {
            trigger.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        });
    };

    /**
     * Close all persistently open feedback except an optional region.
     *
     * @param {HTMLElement|null} except Region to leave unchanged.
     * @param {Boolean} dismiss Whether to suppress transient hover/focus display.
     */
    const closeAll = (except = null, dismiss = false) => {
        document.querySelectorAll(`${regionSelector}.${openClass}`).forEach(region => {
            if (region !== except) {
                setOpen(region, false);
                region.classList.toggle(dismissedClass, dismiss);
            }
        });
        if (!except) {
            activeTrigger = null;
        }
    };

    /**
     * Initialise event-delegated feedback disclosure.
     */
    const init = () => {
        if (initialised) {
            return;
        }
        initialised = true;

        document.addEventListener('click', event => {
            const trigger = event.target.closest(triggerSelector);
            if (!trigger) {
                if (event.target.closest(`${regionSelector}.${openClass}`)) {
                    return;
                }
                closeAll(null, true);
                return;
            }

            const region = trigger.closest(regionSelector);
            if (!region) {
                return;
            }
            event.preventDefault();
            closeAll(region, true);
            region.classList.remove(dismissedClass);
            setOpen(region, true);
            activeTrigger = trigger;
        });

        document.addEventListener('focusin', event => {
            const trigger = event.target.closest(triggerSelector);
            if (!trigger) {
                return;
            }
            const region = trigger.closest(regionSelector);
            if (region) {
                closeAll(region, true);
                region.classList.remove(dismissedClass);
                setExpanded(region, true);
            }
        });

        document.addEventListener('focusout', event => {
            const trigger = event.target.closest(triggerSelector);
            if (!trigger) {
                return;
            }
            const region = trigger.closest(regionSelector);
            if (region && !region.classList.contains(openClass)) {
                setExpanded(region, false);
            }
        });

        document.addEventListener('pointerover', event => {
            const trigger = event.target.closest(triggerSelector);
            if (!trigger) {
                return;
            }
            const region = trigger.closest(regionSelector);
            if (region) {
                closeAll(region, true);
                region.classList.remove(dismissedClass);
            }
        });

        document.addEventListener('pointerout', event => {
            const trigger = event.target.closest(triggerSelector);
            if (trigger && !trigger.contains(event.relatedTarget)) {
                trigger.closest(regionSelector)?.classList.remove(dismissedClass);
            }
        });

        document.addEventListener('keydown', event => {
            if (event.key !== 'Escape') {
                return;
            }

            const popoverTrigger = event.target.closest(popoverTriggerSelector);
            if (popoverTrigger) {
                popoverTrigger.blur();
                return;
            }

            const trigger = event.target.closest(triggerSelector);
            const region = trigger?.closest(regionSelector) ??
                document.querySelector(`${regionSelector}.${openClass}`);
            if (!region) {
                return;
            }
            setOpen(region, false);
            region.classList.add(dismissedClass);
            const focusedElement = document.activeElement;
            if (focusedElement instanceof HTMLElement && region.contains(focusedElement)) {
                focusedElement.blur();
            } else if (activeTrigger?.isConnected) {
                activeTrigger.blur();
            }
            activeTrigger = null;
        });
    };

    return {init};
});
