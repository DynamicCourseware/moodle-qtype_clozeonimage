<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace qtype_clozeonimage;

use qtype_clozeonimage\local\preview_positioning;

/**
 * Preview positioning must coexist with Moodle's HTML and CSS sanitation.
 *
 * @package qtype_clozeonimage
 * @copyright 2026 DynamicCourseware.org (Dominique Bauer)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(preview_positioning::class)]
final class preview_positioning_test extends \advanced_testcase {
    /** Only supported positioning survives; executable attributes, URLs and CSS do not. */
    public function test_cleaning_and_positioning(): void {
        $this->resetAfterTest();
        $html = '<div style="position:relative;left:-2.5em;top:10%;width:200px" onclick="alert(1)">' .
            '<span style="position:absolute;bottom:0;right:12px">Équation \(x^2\)</span>' .
            '<img alt="Image" src="javascript:alert(2)" onerror="alert(3)" style="left:expression(alert(4));top:2px">' .
            '<span style="position:fixed;left:calc(100%);z-index:999999;behavior:url(evil)">Unsafe CSS</span>' .
            '<script style="left:20px">alert(5)</script></div>';
        [$protected, $styles] = preview_positioning::protect($html);
        $result = preview_positioning::restore(clean_text($protected), $styles);
        $this->assertStringContainsString('position:relative;', $result);
        $this->assertStringContainsString('position:absolute;', $result);
        foreach (['left:-2.5em;', 'top:10%;', 'bottom:0;', 'right:12px;', 'width:200px;', 'Équation'] as $safe) {
            $this->assertStringContainsString($safe, $result);
        }
        foreach (['onclick', 'onerror', 'javascript:', 'expression(', 'position:fixed', 'calc(', 'z-index',
                'behavior', '<script', 'coiposition'] as $unsafe) {
            $this->assertStringNotContainsString($unsafe, $result);
        }
        $plain = '<p>Unchanged <strong>text</strong> &amp; formula \(x\)</p>';
        $this->assertSame([$plain, []], preview_positioning::protect($plain));
        $this->assertSame($plain, preview_positioning::restore($plain, []));
    }
}
