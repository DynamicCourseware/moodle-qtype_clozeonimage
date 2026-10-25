<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace qtype_clozeonimage\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Keep a narrow, validated subset of author positioning through preview HTML cleaning.
 *
 * Question rendering trusts author HTML. The editing preview deliberately still uses
 * Moodle's cleaner. Only local positioning keywords and numeric offsets are restored;
 * arbitrary CSS, URLs, scripts and attributes always remain subject to that cleaner.
 *
 * @package qtype_clozeonimage
 * @copyright 2026 DynamicCourseware.org (Dominique Bauer)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class preview_positioning {
    /** Return marked HTML and independently validated declarations, without changing saved content. */
    public static function protect(string $html): array {
        if (!preg_match('/\b(?:position|top|right|bottom|left)\s*:/i', $html)) {
            return [$html, []];
        }
        $dom = self::document($html);
        $styles = [];
        $prefix = 'coiposition' . bin2hex(random_bytes(16));
        foreach ((new \DOMXPath($dom))->query('//*[@style]') as $element) {
            $allowed = [];
            foreach (explode(';', $element->getAttribute('style')) as $declaration) {
                $pair = explode(':', $declaration, 2);
                if (count($pair) !== 2) {
                    continue;
                }
                [$property, $value] = array_map(static fn($part) => strtolower(trim($part)), $pair);
                if ($property === 'position' && in_array($value, ['static', 'relative', 'absolute'], true)) {
                    $allowed[$property] = $value;
                } else if (in_array($property, ['top', 'right', 'bottom', 'left'], true) &&
                        preg_match('/^(?:auto|0|[+-]?(?:\d+(?:\.\d+)?|\.\d+)(?:px|em|rem|%|vh|vw|vmin|vmax|pt|pc|cm|mm|in))$/D',
                            $value)) {
                    $allowed[$property] = $value;
                }
            }
            if ($allowed) {
                $token = $prefix . count($styles);
                $styles[$token] = $allowed;
                $element->setAttribute('class', trim($element->getAttribute('class') . ' ' . $token));
            }
        }
        return $styles ? [self::fragment($dom), $styles] : [$html, []];
    }

    /** Restore only numeric offsets and local positioning on elements that survived Moodle cleaning. */
    public static function restore(string $html, array $styles): string {
        if (!$styles) {
            return $html;
        }
        $dom = self::document($html);
        foreach ((new \DOMXPath($dom))->query('//*[@class]') as $element) {
            $classes = preg_split('/\s+/', $element->getAttribute('class'), -1, PREG_SPLIT_NO_EMPTY);
            foreach ($classes as $index => $token) {
                if (!isset($styles[$token])) {
                    continue;
                }
                $style = rtrim($element->getAttribute('style'), ';');
                foreach ($styles[$token] as $property => $value) {
                    $style .= ';' . $property . ':' . $value;
                }
                $element->setAttribute('style', ltrim($style, ';') . ';');
                unset($classes[$index]);
            }
            $element->setAttribute('class', implode(' ', $classes));
        }
        return self::fragment($dom);
    }

    /** Parse fragments without fetching external resources or expanding external entities. */
    private static function document(string $html): \DOMDocument {
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8"><body>' . $html . '</body>', LIBXML_NONET);
        return $dom;
    }

    /** Serialise only fragment children; never emit a document wrapper or XML declaration. */
    private static function fragment(\DOMDocument $dom): string {
        $html = '';
        foreach ($dom->getElementsByTagName('body')->item(0)->childNodes as $node) {
            $html .= $dom->saveHTML($node);
        }
        return $html;
    }
}
