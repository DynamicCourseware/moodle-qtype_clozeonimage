<?php
// This file is part of Moodle - https://moodle.org/
// Moodle is free software under the GNU GPL v3 or later.
namespace qtype_clozeonimage\local;
defined('MOODLE_INTERNAL') || die();

/**
 * Integer presentation of precise authoring geometry. Never round the stored geometry.
 * @package qtype_clozeonimage
 * @copyright 2026 DynamicCourseware.org (Dominique Bauer)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class pixel_fields {
    /** Geometry fields with a separate visible integer representation. */
    public const FIELDS = ['compositionwidth', 'compositionheight', 'imageleft', 'imagetop', 'imagewidth', 'imageheight',
        'xleft', 'ytop', 'blockwidth'];

    /** Half values away from zero, matching the browser helper; no thousands separators. */
    public static function rounded($value): string {
        $rounded = round((float)$value, 0, PHP_ROUND_HALF_UP);
        return $rounded == 0 ? '0' : sprintf('%.0f', $rounded);
    }

    /** Strict integer syntax; no decimal truncation or scientific notation. */
    public static function integer($value): bool {
        return is_scalar($value) && preg_match('/^-?\d+$/D', (string)$value) && is_finite((float)$value);
    }

    /** Apply deliberate integer edits, leaving an unchanged rounded presentation lossless. */
    public static function apply(array $data): array {
        foreach (self::FIELDS as $field) {
            if (!array_key_exists($field . '_px', $data)) { continue; }
            $array = is_array($data[$field . '_px']);
            foreach ($array ? $data[$field . '_px'] : [$data[$field . '_px']] as $i => $display) {
                $precise = $array ? ($data[$field][$i] ?? '') : ($data[$field] ?? '');
                if ($display === '' && is_numeric($precise) && is_finite((float)$precise)) {
                    $display = self::rounded($precise);
                    if ($array) { $data[$field . '_px'][$i] = $display; } else { $data[$field . '_px'] = $display; }
                    continue; // Presentation only: never replace precise geometry with its rounded value.
                }
                if (self::integer($display) && (!is_numeric($precise) || (float)$display !== (float)self::rounded($precise))) {
                    if ($array) { $data[$field][$i] = $display; } else { $data[$field] = $display; }
                }
            }
        }
        foreach (['blocklayer', 'imagelayer'] as $field) {
            foreach ($data[$field] ?? [] as $i => $value) {
                if ($value === '' && isset($data[$field . '_current'][$i])) {
                    // The normal layer validator still checks this submitted fallback; it is not trusted data.
                    $data[$field][$i] = $data[$field . '_current'][$i];
                }
            }
        }
        return $data;
    }

    /** Report invalid manual presentation separately from precise internal coordinate validation. */
    public static function errors(array $data, bool $geometry = true): array {
        $data = self::apply($data);
        $errors = [];
        foreach (self::FIELDS as $field) {
            if (!array_key_exists($field . '_px', $data)) { continue; }
            $array = is_array($data[$field . '_px']);
            foreach ($array ? $data[$field . '_px'] : [$data[$field . '_px']] as $i => $display) {
                if (!self::integer($display)) {
                    $group = str_starts_with($field, 'composition') ? 'compositiondimensions' :
                        (str_starts_with($field, 'image') ? "imageproperties[$i]" : "subquestionrow[$i]");
                    $errors[$group] = get_string('integerpixels', 'qtype_clozeonimage');
                }
            }
        }
        if (!$geometry) { return $errors; }
        foreach (self::FIELDS as $field) {
            foreach (isset($data[$field]) ? (is_array($data[$field]) ? $data[$field] : [$data[$field]]) : [] as $i => $value) {
                $minimum = str_contains($field, 'width') || str_contains($field, 'height') ?
                    (str_starts_with($field, 'image') ? 0 : ($field === 'blockwidth' ? 40 : PHP_FLOAT_MIN)) : -4194303;
                if (!is_numeric($value) || !is_finite((float)$value) || $value < $minimum || $value > 4194303) {
                    $group = str_starts_with($field, 'composition') ? 'compositiondimensions' :
                        (str_starts_with($field, 'image') ? "imageproperties[$i]" : "subquestionrow[$i]");
                    $errors[$group] = get_string('geometrylimit', 'qtype_clozeonimage');
                }
            }
        }
        return $errors;
    }
}
