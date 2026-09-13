<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Shared review-state and feedback-popover rendering for positioned controls.
 *
 * @package    qtype_clozeonimage
 * @copyright  2026 DynamicCourseware.org (Dominique Bauer)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait qtype_clozeonimage_feedback_renderer_trait {
    /** @var string Appearance class transferred to generated feedback popovers. */
    private string $feedbackpopoverclass = 'qtype-clozeonimage-appearance-translucent';

    /**
     * Initialise the appearance used by this subquestion's feedback popovers.
     *
     * @param question_attempt $qa Question attempt being rendered.
     */
    protected function initialise_feedback_appearance(question_attempt $qa): void {
        $this->feedbackpopoverclass = qtype_clozeonimage::control_appearance_class(
            $qa->get_question()->controlappearance
        );
    }

    /**
     * Return the semantic state class and localized status for review styling.
     *
     * @param float|null $fraction Fraction earned for the response.
     * @param bool $answered Whether the subquestion has a response.
     * @param question_display_options $options Display options.
     * @return array{string,string} State class and localized status, or empty strings.
     */
    protected function review_state(
        ?float $fraction,
        bool $answered,
        question_display_options $options
    ): array {
        if (!$options->correctness) {
            return ['', ''];
        }
        if (!$answered) {
            return ['notanswered', question_state::$gaveup->default_string(true)];
        }

        $state = question_state::graded_state_for_fraction($fraction);
        return [$state->get_feedback_class(), $state->default_string(true)];
    }

    /**
     * Render consistently structured feedback-popover contents.
     *
     * This preserves the core Cloze display-option logic while providing
     * semantic sections for uniform emphasis and spacing.
     *
     * @param question_graded_automatically $subq Subquestion being rendered.
     * @param float|null $fraction Mark earned, or null when unanswered.
     * @param string $feedbacktext Formatted specific feedback.
     * @param string $rightanswer Formatted right answer.
     * @param question_display_options $options Display options.
     * @return string Feedback popup contents, or an empty string.
     */
    protected function feedback_popup(
        question_graded_automatically $subq,
        $fraction,
        $feedbacktext,
        $rightanswer,
        question_display_options $options
    ) {
        $feedback = [];
        if ($options->correctness) {
            $state = is_null($fraction)
                ? question_state::$gaveup
                : question_state::graded_state_for_fraction($fraction);
            $feedback[] = html_writer::div(
                $state->default_string(true),
                'qtype-clozeonimage-feedback-section qtype-clozeonimage-feedback-state'
            );
        }

        if ($options->feedback && $feedbacktext) {
            $feedback[] = html_writer::div(
                $feedbacktext,
                'qtype-clozeonimage-feedback-section'
            );
        }

        if ($options->rightanswer) {
            $feedback[] = html_writer::div(
                get_string('correctansweris', 'qtype_shortanswer', $rightanswer),
                'qtype-clozeonimage-feedback-section'
            );
        }

        if (
            $options->marks >= question_display_options::MARK_AND_MAX && $subq->defaultmark > 0 &&
                (!is_null($fraction) || $feedback)
        ) {
            $mark = (object) [
                'mark' => format_float($fraction * $subq->defaultmark, $options->markdp),
                'max' => format_float($subq->defaultmark, $options->markdp),
            ];
            $feedback[] = html_writer::div(
                get_string('markoutofmax', 'question', $mark),
                'qtype-clozeonimage-feedback-section'
            );
        }

        if (!$feedback) {
            return '';
        }

        return html_writer::div(
            implode('', $feedback),
            'feedbackspan qtype-clozeonimage-feedback-content'
        );
    }

    /**
     * Add the standard Cloze feedback-popover attributes to a focusable element.
     *
     * @param array<string, mixed> $attributes Existing element attributes.
     * @param string $feedbackcontents Formatted feedback popup content.
     * @return array<string, mixed> Updated attributes.
     */
    protected function feedback_trigger_attributes(array $attributes, string $feedbackcontents): array {
        if ($feedbackcontents === '') {
            return $attributes;
        }

        $this->page->requires->js_call_amd('qtype_multianswer/feedback', 'initPopovers');
        $this->page->requires->js_call_amd('qtype_clozeonimage/feedback', 'init');
        $attributes['class'] = trim(($attributes['class'] ?? '') .
            ' feedbacktrigger qtype-clozeonimage-feedback-trigger');
        $attributes['data-bs-toggle'] = 'popover';
        $attributes['data-bs-container'] = 'body';
        $attributes['data-bs-content'] = $feedbackcontents;
        $attributes['data-bs-placement'] = 'right';
        $attributes['data-bs-trigger'] = 'hover focus';
        $attributes['data-bs-html'] = 'true';
        $attributes['data-bs-custom-class'] = $this->feedbackpopoverclass;
        return $attributes;
    }

    /**
     * Render a geometry-neutral feedback surface over disabled review controls.
     *
     * @param string $feedbackcontents Formatted feedback popup content.
     * @param int $index Subquestion number.
     * @param string $statustext Localized result status, when shown.
     * @return string Rendered overlay button, or an empty string.
     */
    protected function feedback_surface_button(
        string $feedbackcontents,
        int $index,
        string $statustext
    ): string {
        if ($feedbackcontents === '') {
            return '';
        }

        $label = get_string('feedbackforsubquestion', 'qtype_clozeonimage', $index);
        if ($statustext !== '') {
            $label .= '. ' . $statustext;
        }
        $attributes = $this->feedback_trigger_attributes([
            'type' => 'button',
            'class' => 'qtype-clozeonimage-review-surface',
        ], $feedbackcontents);
        return html_writer::tag('button', html_writer::span($label, 'visually-hidden'), $attributes);
    }
}
