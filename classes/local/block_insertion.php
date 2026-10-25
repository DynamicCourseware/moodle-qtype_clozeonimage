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

defined('MOODLE_INTERNAL') || die();

/**
 * Insert empty authoring rows during a standard non-saving form rebuild.
 *
 * @package qtype_clozeonimage
 * @copyright 2026 DynamicCourseware.org (Dominique Bauer)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class block_insertion {
    /** Submitted row arrays, kept together by all non-saving authoring actions. */
    public const ROW_FIELDS = ['subquestion', 'roworigin', 'xleft', 'ytop', 'blockwidth', 'anchor',
        'previewwidth', 'previewheight', 'blockkey', 'relativeimage', 'relativex', 'relativey', 'blocklayer',
        'xleft_px', 'ytop_px', 'blockwidth_px', 'blocklayer_current', 'stickybottom', 'stickytop', 'stickyleft', 'stickyright', 'stickyhorizontal'];

    /**
     * Snapshot source indices once, retaining complete editor arrays and stable row identities.
     *
     * @param array $submission Posted form data (not question data).
     * @return array{0: array, 1: ?int} Rebuilt form data and the first inserted row index.
     */
    public static function apply(array $submission): array {
        $amount = (int)($submission['insertblockcount'] ?? 1);
        $count = max(0, (int)($submission['nosubquestions'] ?? 0));
        $targets = [];
        foreach (explode(',', (string)($submission['insertbefore'] ?? '')) as $index) {
            if (ctype_digit($index) && (int)$index < $count) {
                $targets[(int)$index] = true;
            }
        }
        if ((!$targets && $count > 0) || !in_array($amount, [1, 2, 3], true)) {
            return [$submission, null];
        }
        $below = ($submission['insertplacement'] ?? 'above') === 'below';
        $fields = self::ROW_FIELDS;
        $rebuilt = array_fill_keys($fields, []);
        $origin = max([0, (int)($submission['roworigincount'] ?? 0),
            ...array_map('intval', array_values($submission['roworigin'] ?? []))]);
        $first = null;
        $next = 0;
        for ($index = 0; $index <= $count; $index++) {
            // Insert at each selected boundary. Empty lists use boundary zero for either placement.
            if ($count === 0 || isset($targets[$below ? $index - 1 : $index])) {
                $first ??= $next;
                for ($new = 0; $new < $amount; $new++) {
                    // Match appended blank rows. Moodle assigns each editor its own unused draft area.
                    $defaults = ['subquestion' => ['text' => '', 'format' => editors_get_preferred_format(), 'itemid' => 0],
                        'blockkey' => composition::key(), 'relativeimage' => '', 'relativex' => 0, 'relativey' => 0,
                        'blocklayer' => 100, 'blocklayer_current' => 100,
                        'xleft_px' => 12 + 20 * $next, 'ytop_px' => 12 + 20 * $next, 'blockwidth_px' => 320,
                        'roworigin' => ++$origin, 'xleft' => 12 + 20 * $next, 'ytop' => 12 + 20 * $next,
                        'stickybottom' => 0, 'stickytop' => 0, 'stickyleft' => 0, 'stickyright' => 0, 'stickyhorizontal' => 0, 'blockwidth' => 320, 'anchor' => -1, 'previewwidth' => 0, 'previewheight' => 0];
                    foreach ($defaults as $field => $value) {
                        $rebuilt[$field][$next] = $value;
                    }
                    $next++;
                }
            }
            if ($index === $count) {
                break;
            }
            foreach ($fields as $field) {
                if (array_key_exists($index, $submission[$field] ?? [])) {
                    $rebuilt[$field][$next] = $submission[$field][$index];
                }
            }
            $next++;
        }
        return [array_replace($submission, $rebuilt, ['nosubquestions' => $next, 'roworigincount' => $origin,
            'insertbefore' => '']), $first];
    }
}
