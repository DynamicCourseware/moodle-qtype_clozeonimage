<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace qtype_clozeonimage;

defined('MOODLE_INTERNAL') || die();

/** Non-saving subset/permutation with complete draft rows.
 * @package qtype_clozeonimage
 * @copyright 2026 DynamicCourseware.org (Dominique Bauer)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class block_rebuild_test extends \advanced_testcase {
    public function test_complete_rows_and_stable_identity_metadata(): void {
        foreach ([[2, 0, 1], [0, 2], []] as $order) {
            $post = ['nosubquestions' => 3, 'roworigincount' => 9, 'blockinitialcount' => 3,
                'blockdraftscope' => str_repeat('a', 32), 'name' => 'Unsaved question',
                'blockorder' => implode(',', $order), 'roworigin' => [1, 8, 9],
                'xleft_px' => ['-40', '900', '170'], 'ytop_px' => ['13', '-91', '25'],
                'blockwidth_px' => ['320', '640', '500'], 'xleft' => [-40, 900, 170], 'ytop' => [13, -91, 25], 'blockwidth' => [320, 640, 500],
                'blockkey' => [str_repeat('a', 32), str_repeat('b', 32), str_repeat('c', 32)],
                'relativeimage' => ['', str_repeat('d', 32), str_repeat('d', 32)],
                'relativex' => [0, 1.25, -0.5], 'relativey' => [0, -0.75, 1.75], 'blocklayer' => [100, 3, 90], 'blocklayer_current' => [100, 3, 90],
                'stickybottom' => [0, 1, 1], 'stickytop' => [1, 0, 1], 'stickyleft' => [1, 0, 1], 'stickyright' => [0, 1, 1],
                'stickyhorizontal' => [1, 2, 3], 'anchor' => [0, 4, 8], 'previewwidth' => [320.0, 75.5, 500.0], 'previewheight' => [83.5, 144.0, 91.25]];
            foreach (range(0, 2) as $index) {
                $post['subquestion'][$index] = ['text' => '<p>Draft <img src="draftfile.php/' . $index .
                    '/image.png"> {1:NUMERICAL:=42:0}</p>', 'format' => FORMAT_HTML, 'itemid' => 40 + $index];
            }
            $result = \qtype_clozeonimage\local\block_rebuild::apply($post);
            foreach (\qtype_clozeonimage\local\block_insertion::ROW_FIELDS as $field) {
                $this->assertSame(array_map(fn($index) => $post[$field][$index], $order), $result[$field]);
            }
            foreach (['roworigincount', 'blockinitialcount', 'blockdraftscope', 'name'] as $field) {
                $this->assertSame($post[$field], $result[$field]);
            }
            $this->assertSame(count($order), $result['nosubquestions']);
            $this->assertSame('', $result['blockorder']);
        }
    }

    public function test_invalid_order_does_not_drop_or_duplicate_rows(): void {
        foreach (['0,0', '1,01', '-1', '3', 'text', '0,,1'] as $sequence) {
            $post = ['nosubquestions' => 3, 'blockorder' => $sequence, 'roworigin' => [1, 2, 3]];
            $this->assertSame($post, \qtype_clozeonimage\local\block_rebuild::apply($post));
        }
    }
}
