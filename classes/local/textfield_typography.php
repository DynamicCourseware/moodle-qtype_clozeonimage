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
 * Preserve authored text-field typography without restoring automatic-block spacing.
 *
 * @package qtype_clozeonimage
 * @copyright 2026 DynamicCourseware.org (Dominique Bauer)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class textfield_typography {
    /**
     * Mark author wrappers before the existing Moodle formatting/cleaning pipeline.
     * No CSS value is copied, allowed or restored by this operation.
     *
     * @param string $html Working source with Cloze placeholders, never the saved source.
     * @param bool $automatic A single text-field expression with insignificant wrappers.
     * @return string Working HTML with typography hooks.
     */
    public static function prepare(string $html, bool $automatic = false): string {
        if (!$automatic && !preg_match('/\bcolor\s*:/i', $html)) {
            return $html;
        }
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8"><body>' . $html . '</body>', LIBXML_NONET);
        $xpath = new \DOMXPath($dom);
        foreach ($xpath->query('//body//*') as $element) {
            $classes = [];
            if (preg_match('/(?:^|;)\s*color\s*:/i', $element->getAttribute('style'))) {
                $classes[] = 'qtype-clozeonimage-authored-color';
            }
            if ($automatic) {
                $classes[] = 'qtype-clozeonimage-auto-wrapper';
            }
            if ($classes) {
                $existing = preg_split('/\s+/', trim($element->getAttribute('class')), -1, PREG_SPLIT_NO_EMPTY);
                $element->setAttribute('class', implode(' ', array_unique(array_merge($existing, $classes))));
            }
        }
        if ($automatic) {
            // Blank Tiny paragraphs, BRs and whitespace must not enlarge a single-control block.
            foreach (iterator_to_array($xpath->query('//body//br | //body//text()')) as $node) {
                if ($node instanceof \DOMElement ||
                        preg_replace('/[\s\x{00a0}\x{200b}\x{feff}]+/u', '', $node->textContent) === '') {
                    $node->parentNode->removeChild($node);
                }
            }
        }
        $result = '';
        foreach ($dom->getElementsByTagName('body')->item(0)->childNodes as $node) {
            $result .= $dom->saveHTML($node);
        }
        return $result;
    }
}
