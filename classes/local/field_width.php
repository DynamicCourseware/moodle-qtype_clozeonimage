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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <https://www.gnu.org/licenses/>.

namespace qtype_clozeonimage\local;

/**
 * Optional field widths in active Cloze headers. The original source remains the storage format.
 *
 * @package qtype_clozeonimage
 * @copyright 2026 DynamicCourseware.org (Dominique Bauer)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class field_width {
    /** @var string Core aliases for the three supported text-field families. */
    private const TYPES = 'SHORTANSWER_C|SHORTANSWER|NUMERICAL|SAC|MWC|SA|MW|NM';

    /**
     * Normalise a working copy, consuming complete existing expressions before looking for extensions.
     * This keeps apparent headers inside answers/feedback untouched. Comments are excluded by the caller.
     *
     * @param string $text Active source.
     * @return array Normalised source, widths indexed by child ordinal, diagnostics.
     */
    public static function extract(string $text): array {
        $widths = [];
        $errors = [];
        $ordinal = 0;
        // The extension uses the core alternative grammar, including escaped delimiters and feedback.
        $extended = '\{[0-9]*:(?:' . self::TYPES . ')\([^:{}]*:' .
            '(?:' . ANSWER_ALTERNATIVE_REGEX . ')(?:~' . ANSWER_ALTERNATIVE_REGEX . ')*\}';
        $pattern = '/' . ANSWER_REGEX . '|' . $extended . '/s';
        $text = preg_replace_callback($pattern, static function($match) use (&$widths, &$errors, &$ordinal) {
            $ordinal++;
            if (!preg_match('/^(\{[0-9]*:(?:' . self::TYPES . '))\(([^:{}]*):/s', $match[0], $header)) {
                return $match[0];
            }
            if (!preg_match('/^([0-9]+(?:\.[0-9]+)?)(px|em|rem)(?:,([0-9]+(?:\.[0-9]+)?)(px|em|rem))?\)$/D',
                    $header[2], $values)) {
                $errors[] = get_string('invalidfieldwidth', 'qtype_clozeonimage');
                return $match[0];
            }
            $unit = $values[2];
            $adaptive = isset($values[3]);
            if ($adaptive && $values[4] !== $unit) {
                $errors[] = get_string('fieldwidthmixedunits', 'qtype_clozeonimage');
                return $match[0];
            }
            if ($unit === 'px' && (!preg_match('/^[1-9][0-9]*$/D', $values[1]) ||
                    ($adaptive && !preg_match('/^[1-9][0-9]*$/D', $values[3])))) {
                $errors[] = get_string('invalidfieldwidth', 'qtype_clozeonimage');
                return $match[0];
            }
            $min = (float)$values[1];
            $max = $adaptive ? (float)$values[3] : $min;
            [$lower, $upper] = $unit === 'px' ? [40, 2000] : [2.5, 125];
            if (!is_finite($min) || !is_finite($max) || $min < $lower || $max > $upper ||
                    $max < $lower || $min > $upper) {
                $errors[] = get_string('fieldwidthrange', 'qtype_clozeonimage',
                    (object)['unit' => $unit, 'min' => $lower, 'max' => $upper]);
                return $match[0];
            }
            if ($max < $min) {
                $errors[] = get_string('fieldwidthorder', 'qtype_clozeonimage');
                return $match[0];
            }
            $widths[$ordinal] = ['min' => $unit === 'px' ? (int)$min : $min,
                'max' => $unit === 'px' ? (int)$max : $max, 'adaptive' => $adaptive];
            if ($unit !== 'px') {
                $widths[$ordinal]['unit'] = $unit;
            }
            return $header[1] . ':' . substr($match[0], strlen($header[0]));
        }, $text);
        return [$text, $widths, array_values(array_unique($errors))];
    }

    /**
     * Attributes derived only from explicit author dimensions, never expected answers.
     * @param array|null $width Validated width specification.
     * @return array HTML input attributes.
     */
    public static function attributes(?array $width): array {
        if (!$width) {
            return [];
        }
        $min = $width['min'];
        $max = $width['max'];
        $unit = $width['unit'] ?? 'px';
        $attributes = ['style' => "width:{$min}{$unit};min-width:{$min}{$unit};max-width:{$max}{$unit};box-sizing:border-box;"];
        if ($width['adaptive']) {
            $attributes['data-clozeonimage-field-min'] = $min;
            $attributes['data-clozeonimage-field-max'] = $max;
            if ($unit !== 'px') {
                $attributes['data-clozeonimage-field-unit'] = $unit;
            }
        }
        return $attributes;
    }
}
