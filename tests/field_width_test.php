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

namespace qtype_clozeonimage;

use qtype_clozeonimage\local\field_width;

/**
 * Strict header extensions leave core answers, comments and grading untouched.
 * @package qtype_clozeonimage
 * @copyright 2026 DynamicCourseware.org (Dominique Bauer)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(field_width::class)]
final class field_width_test extends \advanced_testcase {
    /** All aliases use the same core answer interpretation. */
    public function test_aliases_and_ordinary_expressions(): void {
        \question_bank::get_qtype('clozeonimage');
        foreach (['SHORTANSWER', 'SA', 'MW', 'SHORTANSWER_C', 'SAC', 'MWC', 'NUMERICAL', 'NM'] as $type) {
            $answer = in_array($type, ['NUMERICAL', 'NM']) ? '42:0' : 'Paris';
            foreach (['' => null, '(150px)' => ['min' => 150, 'max' => 150, 'adaptive' => false],
                '(150px,300px)' => ['min' => 150, 'max' => 300, 'adaptive' => true],
                '(10em)' => ['min' => 10.0, 'max' => 10.0, 'adaptive' => false, 'unit' => 'em'],
                '(9.50rem)' => ['min' => 9.5, 'max' => 9.5, 'adaptive' => false, 'unit' => 'rem'],
                '(8em,16em)' => ['min' => 8.0, 'max' => 16.0, 'adaptive' => true, 'unit' => 'em'],
                '(8.5rem,16.25rem)' => ['min' => 8.5, 'max' => 16.25, 'adaptive' => true, 'unit' => 'rem']] as $parameter => $width) {
                $source = '{2:' . $type . $parameter . ':=' . $answer . '}';
                [$parsed, $errors] = \qtype_clozeonimage::diagnose_block_source($source);
                $this->assertSame([], $errors, $source);
                $this->assertCount(1, $parsed->options->questions);
                $child = $parsed->options->questions[1];
                $this->assertSame($width, $child->clozeonimagewidth ?? null);
                $this->assertEquals(2, $parsed->defaultmark);
                $this->assertSame(in_array($type, ['NUMERICAL', 'NM']) ? ['42'] : ['Paris'], $child->answer);
                $this->assertSame(['1'], $child->fraction);
                if ($width) {
                    $attributes = field_width::attributes($width);
                    $this->assertArrayNotHasKey('size', $attributes);
                    $this->assertArrayNotHasKey('maxlength', $attributes);
                    $this->assertStringContainsString('box-sizing:border-box;', $attributes['style']);
                    $unit = $width['unit'] ?? 'px';
                    $this->assertStringContainsString('width:' . $width['min'] . $unit . ';', $attributes['style']);
                    $this->assertStringContainsString('min-width:' . $width['min'] . $unit . ';', $attributes['style']);
                    $this->assertStringContainsString('max-width:' . $width['max'] . $unit . ';', $attributes['style']);
                    if ($width['adaptive']) {
                        $this->assertSame($width['min'], $attributes['data-clozeonimage-field-min']);
                        $this->assertSame($unit, $attributes['data-clozeonimage-field-unit'] ?? 'px');
                    }
                }
            }
        }
    }

    /** A complete standard expression is consumed before apparent extensions inside its answer/feedback. */
    public function test_literal_answers_feedback_and_comments(): void {
        \question_bank::get_qtype('clozeonimage');
        $ordinary = '{1:SA:=SHORTANSWER(150px)#Literal {1:NUMERICAL(9.5rem):=9:0\\}}';
        $source = '<!-- {1:SA(0px):=ignored} {1:NM(8em,16rem):=ignored} -->' . $ordinary . '{:SAC(160px):=Paris}';
        [$parsed, $errors] = \qtype_clozeonimage::diagnose_block_source($source);
        $this->assertSame([], $errors);
        $this->assertCount(2, $parsed->options->questions);
        $first = $parsed->options->questions[1];
        $this->assertFalse(isset($first->clozeonimagewidth));
        $this->assertSame('SHORTANSWER(150px)', $first->answer[0]);
        $this->assertStringContainsString('NUMERICAL(9.5rem)', $first->feedback[0]['text']);
        $this->assertSame(160, $parsed->options->questions[2]->clozeonimagewidth['min']);
    }

    /** Invalid widths must not silently turn into ungraded text. */
    public function test_invalid_parameters(): void {
        \question_bank::get_qtype('clozeonimage');
        foreach (['0px', '-1px', '150.5px', '150%', '*', '', '150px,*,300px', '150px, *,300px',
            '150px,', '150px, 300px', '150px,300px,400px', '-8em', 'NaNem', 'Infinityrem',
            '1e309em', '8vh', '8EM', '8 em', '8,5em', '8em,', '8em,*,16em'] as $parameter) {
            [$parsed, $errors] = \qtype_clozeonimage::diagnose_block_source('{1:SA(' . $parameter . '):=yes}');
            $this->assertContains(get_string('invalidfieldwidth', 'qtype_clozeonimage'), $errors, $parameter);
        }
        foreach (['px' => ['39px', '2001px', '999999999999999999999px', '150px,2001px'],
            'em' => ['0em', '2.49em', '125.01em', '8em,126em'],
            'rem' => ['0rem', '2rem', '126rem', '8rem,' . str_repeat('9', 400) . 'rem']] as $unit => $parameters) {
            foreach ($parameters as $parameter) {
                [, $errors] = \qtype_clozeonimage::diagnose_block_source('{1:SA(' . $parameter . '):=yes}');
                $this->assertContains(get_string('fieldwidthrange', 'qtype_clozeonimage', (object)[
                    'unit' => $unit, 'min' => $unit === 'px' ? 40 : 2.5, 'max' => $unit === 'px' ? 2000 : 125]),
                    $errors, $parameter);
            }
        }
        foreach (['150px,149px', '16em,8em', '16rem,8rem'] as $parameter) {
            [, $errors] = \qtype_clozeonimage::diagnose_block_source('{1:SA(' . $parameter . '):=yes}');
            $this->assertContains(get_string('fieldwidthorder', 'qtype_clozeonimage'), $errors, $parameter);
        }
        foreach (['8em,16rem', '150px,16em', '8rem,300px'] as $parameter) {
            [, $errors] = \qtype_clozeonimage::diagnose_block_source('{1:SA(' . $parameter . '):=yes}');
            $this->assertContains(get_string('fieldwidthmixedunits', 'qtype_clozeonimage'), $errors, $parameter);
        }
        foreach (['40px', '2000px', '150px,150px', '2.5em', '125rem', '9.5em,9.5em'] as $parameter) {
            [, $errors] = \qtype_clozeonimage::diagnose_block_source('{1:NM(' . $parameter . '):=42:0}');
            $this->assertSame([], $errors, $parameter);
        }
    }
}
