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
 * Rebuild a subset/permutation of authoring rows without saving the question.
 *
 * @package qtype_clozeonimage
 * @copyright 2026 DynamicCourseware.org (Dominique Bauer)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class block_rebuild {
    /**
     * Keep complete rows in the requested order; reject duplicate/out-of-range indices.
     * Empty order deliberately removes every row, while retaining the origin high-water mark.
     *
     * @param array $submission Draft form fields.
     * @return array Updated draft form fields (no persistence).
     */
    public static function apply(array $submission): array {
        $count = max(0, (int)($submission['nosubquestions'] ?? 0));
        $sequence = (string)($submission['blockorder'] ?? '');
        $order = $sequence === '' ? [] : explode(',', $sequence);
        $seen = [];
        foreach ($order as $index) {
            if (!ctype_digit($index) || (int)$index >= $count || isset($seen[(int)$index])) {
                return $submission;
            }
            $seen[(int)$index] = true;
        }
        $rebuilt = [];
        foreach (block_insertion::ROW_FIELDS as $field) {
            $rebuilt[$field] = [];
            foreach ($order as $next => $previous) {
                if (array_key_exists((int)$previous, $submission[$field] ?? [])) {
                    $rebuilt[$field][$next] = $submission[$field][(int)$previous];
                }
            }
        }
        return array_replace($submission, $rebuilt, ['nosubquestions' => count($order), 'blockorder' => '']);
    }
}
