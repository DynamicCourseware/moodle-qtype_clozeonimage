<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace qtype_clozeonimage;

use qtype_clozeonimage\local\textfield_typography;

/**
 * Author typography survives automatic sizing without bypassing Moodle's HTML cleaner.
 * @package qtype_clozeonimage
 * @copyright 2026 DynamicCourseware.org (Dominique Bauer)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(textfield_typography::class)]
final class textfield_typography_test extends \basic_testcase {
    public function test_automatic_wrappers_keep_styles_but_not_blank_lines(): void {
        $html = '<div><p> </p><p><span style="font-size:40px;color:orange"><strong>{#1}</strong></span></p>' .
            '<p>&nbsp;<br></p></div>';
        $result = textfield_typography::prepare($html, true);
        $this->assertStringContainsString('font-size:40px;color:orange', $result);
        $this->assertStringContainsString('qtype-clozeonimage-authored-color', $result);
        $this->assertStringContainsString('qtype-clozeonimage-auto-wrapper', $result);
        $this->assertStringContainsString('<strong', $result);
        $this->assertStringNotContainsString('<br', $result);
        $this->assertSame('{#1}', strip_tags($result));
        $this->assertSame($result, textfield_typography::prepare($result));
    }

    public function test_only_explicit_foreground_colour_gets_an_inheritance_hook(): void {
        $plain = '<p>Before {#1} after</p>';
        $this->assertSame($plain, textfield_typography::prepare($plain));
        $background = '<span style="background-color:orange">{#1}</span>';
        $this->assertStringNotContainsString('qtype-clozeonimage-authored-color',
            textfield_typography::prepare($background));
        $foreground = '<span style="background-color:orange; COLOR : purple; font-size:32px">{#1}</span>';
        $this->assertStringContainsString('qtype-clozeonimage-authored-color', textfield_typography::prepare($foreground));
    }

    public function test_existing_cleaner_still_removes_unsafe_content(): void {
        $html = '<span style="font-size:40px;color:orange" onclick="alert(1)">{#1}</span><script>alert(1)</script>';
        $clean = clean_text(textfield_typography::prepare($html), FORMAT_HTML);
        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringNotContainsString('<script', $clean);
        $this->assertStringContainsString('40px', $clean);
        $this->assertStringContainsString('color:#FFA500', $clean);
    }
}
