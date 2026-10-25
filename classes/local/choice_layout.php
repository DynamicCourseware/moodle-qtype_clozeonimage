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
 * Shared paragraph-compatible choice panel for author previews and live responses.
 *
 * @package qtype_clozeonimage
 * @copyright 2026 DynamicCourseware.org (Dominique Bauer)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class choice_layout {
    /**
     * Start an intrinsically sized panel. Accessibility labels belong to the caller.
     *
     * @param bool $horizontal Whether choices share a single row.
     * @param array $attributes HTML attributes, including the caller's accessibility labels.
     * @return string Opening panel markup.
     */
    public static function start(bool $horizontal, array $attributes = []): string {
        $attributes['class'] = 'answer qtype-clozeonimage-choices ' .
            ($horizontal ? 'qtype-clozeonimage-choices-horizontal' : 'qtype-clozeonimage-choices-vertical');
        return \html_writer::start_tag('span', $attributes);
    }
}
