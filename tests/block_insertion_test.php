<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace qtype_clozeonimage;

defined('MOODLE_INTERNAL') || die();

/**
 * Multiple insertion retains draft editors and all existing row identities.
 *
 * @package qtype_clozeonimage
 * @copyright 2026 DynamicCourseware.org (Dominique Bauer)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class block_insertion_test extends \advanced_testcase {
    /** Counts and snapshots include consecutive, separated, first, last and every row. */
    public static function insertion_provider(): array {
        $cases = [];
        foreach ([1, 2, 3] as $amount) {
            foreach (['first' => [0], 'last' => [3], 'consecutive' => [1, 2],
                'separated' => [0, 3], 'all' => [0, 1, 2, 3], 'three' => [0, 2, 3]] as $name => $selected) {
                foreach (['above', 'below'] as $placement) {
                    $cases[$amount . ' ' . $name . ' ' . $placement] = [$amount, $selected, $placement];
                }
            }
        }
        return $cases;
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('insertion_provider')]
    public function test_insert_snapshot_retains_complete_rows(int $amount, array $selected, string $placement): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $post = ['nosubquestions' => 4, 'roworigincount' => 7, 'insertblockcount' => $amount,
            'insertplacement' => $placement, 'insertbefore' => implode(',', array_reverse($selected)), 'name' => 'Unsaved name',
            'roworigin' => [4, 1, 5, 3], 'xleft' => [-40, 900, 170, 42], 'ytop' => [13, -91, 25, 999],
            'stickybottom' => [0, 1, 1, 0], 'stickytop' => [1, 0, 1, 0], 'stickyleft' => [1, 0, 1, 0], 'stickyright' => [0, 1, 1, 0],
            'blockwidth' => [320, 640, 500, 427], 'anchor' => [0, 4, 8, 6],
            'previewwidth' => [320.0, 75.5, 500.0, 427.0], 'previewheight' => [83.5, 144.0, 91.25, 52.0]];
        foreach (range(0, 3) as $index) {
            $post['blockkey'][$index] = str_repeat((string)($index + 1), 32);
            $post['subquestion'][$index] = ['text' => '<p>Draft <img src="draftfile.php/' . (40 + $index) .
                '/image.png"> {1:NUMERICAL:=42:0}</p>', 'format' => FORMAT_HTML, 'itemid' => 40 + $index];
        }
        [$result, $first] = \qtype_clozeonimage\local\block_insertion::apply($post);
        $this->assertSame(min($selected) + (int)($placement === 'below'), $first);
        $this->assertSame(4 + $amount * count($selected), $result['nosubquestions']);
        $this->assertSame(7 + $amount * count($selected), $result['roworigincount']);
        $this->assertSame('Unsaved name', $result['name']);
        $this->assertSame('', $result['insertbefore']);
        $oldindices = [];
        $neworigins = [];
        foreach ($result['roworigin'] as $index => $origin) {
            $old = array_search($origin, $post['roworigin'], true);
            if ($old !== false) {
                $oldindices[] = $old;
                foreach (['subquestion', 'roworigin', 'xleft', 'ytop', 'blockwidth', 'anchor',
                    'previewwidth', 'previewheight', 'stickybottom', 'stickytop', 'stickyleft', 'stickyright'] as $field) {
                    $this->assertSame($post[$field][$old], $result[$field][$index], $field);
                }
                if (in_array($old, $selected, true)) {
                    for ($n = 1; $n <= $amount; $n++) {
                        $this->assertGreaterThan(7, $result['roworigin'][$index + ($placement === 'below' ? $n : -$n)]);
                    }
                }
            } else {
                $neworigins[] = $origin;
                $this->assertSame('', $result['subquestion'][$index]['text']);
                $this->assertSame(0, $result['subquestion'][$index]['itemid']);
                $this->assertNotContains($result['blockkey'][$index], $post['blockkey']);
                $this->assertSame('', $result['relativeimage'][$index]);
                $this->assertSame(100, $result['blocklayer'][$index]);
                $this->assertSame(0, $result['stickybottom'][$index]);
                $this->assertSame(0, $result['stickytop'][$index]);
                $this->assertSame(0, $result['stickyleft'][$index]);
                $this->assertSame(0, $result['stickyright'][$index]);
                $this->assertSame(320, $result['blockwidth'][$index]);
                $this->assertSame(12 + 20 * $index, $result['xleft'][$index]);
                $this->assertSame(12 + 20 * $index, $result['ytop'][$index]);
                $this->assertSame(-1, $result['anchor'][$index]);
                $this->assertSame(0, $result['previewwidth'][$index]);
                $this->assertSame(0, $result['previewheight'][$index]);
            }
        }
        $this->assertCount($result['nosubquestions'], array_unique($result['blockkey']));
        $this->assertSame([0, 1, 2, 3], $oldindices);
        $this->assertSame(range(8, $result['roworigincount']), $neworigins);
    }

    public function test_insert_into_empty_list(): void {
        foreach (['above', 'below'] as $placement) {
            foreach ([1, 2, 3] as $amount) {
                [$data, $first] = \qtype_clozeonimage\local\block_insertion::apply([
                    'nosubquestions' => 0, 'roworigincount' => 7,
                    'insertblockcount' => $amount, 'insertplacement' => $placement]);
                $this->assertSame(0, $first);
                $this->assertSame($amount, $data['nosubquestions']);
                $this->assertSame(range(8, 7 + $amount), $data['roworigin']);
            }
        }
    }

    public function test_empty_selection_and_invalid_amount_do_not_insert(): void {
        foreach (['', '-1,9', 'bad'] as $targets) {
            $post = ['nosubquestions' => 4, 'insertbefore' => $targets];
            $this->assertSame([$post, null], \qtype_clozeonimage\local\block_insertion::apply($post));
        }
        foreach ([0, 4, -1] as $amount) {
            $post = ['nosubquestions' => 4, 'insertbefore' => '1', 'insertblockcount' => $amount];
            $this->assertSame([$post, null], \qtype_clozeonimage\local\block_insertion::apply($post));
        }
    }
}
