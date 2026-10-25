<?php
// This file is part of Moodle - https://moodle.org/
// Moodle is free software under the GNU GPL v3 or later.
namespace qtype_clozeonimage;
use qtype_clozeonimage\local\pixel_fields;
use qtype_clozeonimage\local\composition;

/**
 * Lossless integer authoring presentation and layer validation.
 * @package qtype_clozeonimage
 * @copyright 2026 DynamicCourseware.org (Dominique Bauer)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(pixel_fields::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(composition::class)]
final class pixel_fields_test extends \advanced_testcase {
    public function test_rounding_and_lossless_unchanged_fields(): void {
        foreach ([[123.456, '123'], [-123.5, '-124'], [-.49, '0'], [.5, '1'], [-.5, '-1'], [12345.6, '12346']] as [$v, $text]) {
            $this->assertSame($text, pixel_fields::rounded($v));
            $input = ['xleft' => [$v], 'xleft_px' => [$text], 'subquestion' => ['{1:SA(9.50rem):=X}']];
            $this->assertSame($input, pixel_fields::apply($input));
            $this->assertSame([], pixel_fields::errors($input));
        }
    }
    public function test_integer_edit_replaces_only_edited_value(): void {
        $data = pixel_fields::apply(['xleft' => [123.456, -123.5], 'xleft_px' => ['124', '-124'],
            'imagewidth' => [600.25], 'imagewidth_px' => ['600']]);
        $this->assertSame(['124', -123.5], $data['xleft']);
        $this->assertSame([600.25], $data['imagewidth']);
    }
    public function test_invalid_manual_values_are_not_truncated(): void {
        foreach (['124.5', '-0.5', '1e2', 'NaN', 'INF', '1,000'] as $bad) {
            $data = ['xleft' => [123.456], 'xleft_px' => [$bad]];
            $this->assertSame($data, pixel_fields::apply($data));
            $this->assertArrayHasKey('subquestionrow[0]', pixel_fields::errors($data));
        }
    }
    public function test_blank_submission_restores_precise_geometry_and_last_layer(): void {
        $data = ['xleft' => [-123.456], 'xleft_px' => [''], 'compositionwidth' => 1200.25,
            'compositionwidth_px' => '', 'blocklayer' => [''], 'blocklayer_current' => ['-12345']];
        $result = pixel_fields::apply($data);
        $this->assertSame([-123.456], $result['xleft']);
        $this->assertSame(['-123'], $result['xleft_px']);
        $this->assertSame(1200.25, $result['compositionwidth']);
        $this->assertSame('1200', $result['compositionwidth_px']);
        $this->assertSame(['-12345'], $result['blocklayer']);
        $this->assertSame([], pixel_fields::errors($data));
        $this->assertSame(-12345, composition::layer($result['blocklayer'][0]));
        $result = pixel_fields::apply(['imagelayer' => [''], 'imagelayer_current' => ['2147483648']]);
        $this->expectException(\moodle_exception::class);
        composition::layer($result['imagelayer'][0]);
    }
    public function test_rebuild_preserves_fractional_values_and_invalid_display(): void {
        $data = ['nosubquestions' => 2, 'blockorder' => '1,0', 'xleft' => [123.456, -12.5],
            'xleft_px' => ['123', '-12.5'], 'relativeimage' => ['a', 'b'], 'relativex' => [.12345, -.45678]];
        $result = \qtype_clozeonimage\local\block_rebuild::apply($data);
        $this->assertSame([-12.5, 123.456], $result['xleft']);
        $this->assertSame(['-12.5', '123'], $result['xleft_px']);
        $this->assertSame(['b', 'a'], $result['relativeimage']);
        $this->assertSame([-.45678, .12345], $result['relativex']);
    }
    public function test_layer_limits_and_fractional_internal_width(): void {
        foreach ([-2147483648, -9999, -1, 0, 9999, 2147483647] as $value) { $this->assertSame($value, composition::layer($value)); }
        $this->assertSame(320.25, composition::block_width(320.25));
        foreach ([-2147483649, 2147483648, 1.5, '1.0', '1e2', 'NaN', ''] as $bad) {
            try { composition::layer($bad); $this->fail('Invalid layer accepted: ' . $bad); }
            catch (\moodle_exception $e) { $this->assertSame('invalidlayer', $e->errorcode); }
        }
    }
}
