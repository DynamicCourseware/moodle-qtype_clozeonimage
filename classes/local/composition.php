<?php
// This file is part of Moodle - https://moodle.org/
// Moodle is free software under the GNU GPL v3 or later.

namespace qtype_clozeonimage\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Autonomous composition format and question-scoped image identities.
 *
 * @package qtype_clozeonimage
 * @copyright 2026 DynamicCourseware.org (Dominique Bauer)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class composition {
    /** New contents use this format; old records are not migrated. */
    public const FORMAT = 2;
    /** Editable geometry keys. */
    public const FIELDS = ['x', 'y', 'width', 'height', 'ratio', 'locked', 'background'];
    /** Image form properties mapped to storage columns. */
    public const IMAGE_FIELDS = ['imagekey' => 'elementkey', 'imagename' => 'name', 'imageleft' => 'xleft',
        'imagetop' => 'ytop', 'imagewidth' => 'width', 'imageheight' => 'height', 'imagelayer' => 'layer'];

    /** A single rule for visibility, parsing, grading and file access. */
    public static function active(object|array $question): bool {
        $data = (object)$question;
        return !empty($data->showcomposition ?? 1);
    }

    /** Stable identities are scoped to the owning question version, never a list index. */
    public static function key(?string $key = null): string {
        if ($key === null || $key === '') {
            return bin2hex(random_bytes(16));
        }
        if (!preg_match('/^[a-f0-9]{32}$/D', $key)) {
            throw new \moodle_exception('invalidcomposition', 'qtype_clozeonimage');
        }
        return $key;
    }

    /** Default base rectangle; only a new visible editor chooses the initial actual width. */
    public static function defaults(): array {
        return ['x' => 0, 'y' => 0, 'width' => 800, 'height' => 600,
            'ratio' => 4 / 3, 'locked' => 1, 'background' => '#ffffff'];
    }

    /** Validate explicit geometry independently of browser-only content measurements. */
    public static function geometry(object|array $source): array {
        $source = (object)$source;
        $stored = json_decode($source->composition ?? '', true) ?: self::defaults();
        $result = [];
        foreach (self::FIELDS as $name) {
            $value = $source->{'composition' . $name} ?? $stored[$name] ?? self::defaults()[$name];
            if ($name === 'background') {
                if (!preg_match('/^#[0-9a-f]{6}$/Di', (string)$value)) {
                    throw new \moodle_exception('invalidcomposition', 'qtype_clozeonimage');
                }
                $result[$name] = strtolower($value);
            } else {
                if (!is_numeric($value) || !is_finite((float)$value) || abs((float)$value) > 4194303 ||
                        (in_array($name, ['width', 'height', 'ratio']) && (float)$value <= 0) ||
                        ($name === 'locked' && !in_array((string)$value, ['0', '1'], true))) {
                    throw new \moodle_exception('invalidcomposition', 'qtype_clozeonimage');
                }
                $result[$name] = (float)$value;
            }
        }
        if ($result['locked'] && abs($result['width'] / $result['height'] - $result['ratio']) > 0.01) {
            throw new \moodle_exception('invalidcompositionratio', 'qtype_clozeonimage');
        }
        return $result;
    }

    /** Finite internal coordinates, including subpixels from proportional image associations. */
    public static function coordinate($value): float {
        if (!is_numeric($value) || !is_finite((float)$value) || abs((float)$value) > 4194303) {
            throw new \moodle_exception('invalidcomposition', 'qtype_clozeonimage');
        }
        return (float)$value;
    }

    /** Internal block width may contain subpixels; only manual presentation must be integral. */
    public static function block_width($value): float {
        if (!is_numeric($value) || !is_finite((float)$value) || $value < 40 || $value > 4194303) {
            throw new \moodle_exception('invalidblockwidth', 'qtype_clozeonimage');
        }
        return (float)$value;
    }

    /** Layers are independent of answer order; reject invalid values, never clamp or truncate. */
    public static function layer($value): int {
        if (!pixel_fields::integer($value) || (float)$value < -2147483648 || (float)$value > 2147483647) {
            throw new \moodle_exception('invalidlayer', 'qtype_clozeonimage');
        }
        return (int)$value;
    }

    /** Validate all supplied layers, including retained inactive content. */
    public static function layers(object|array $data): void {
        $data = (object)$data;
        foreach (['blocklayer', 'imagelayer'] as $field) {
            foreach ($data->$field ?? [] as $value) {
                self::layer($value);
            }
        }
    }

    /** Native image dimensions, including SVG viewBox, without executing/parsing external entities. */
    public static function intrinsic(\stored_file $file): array {
        $info = $file->get_imageinfo();
        if (!empty($info['width']) && !empty($info['height'])) {
            if ($info['mimetype'] === 'image/jpeg' && function_exists('exif_read_data')) {
                $handle = $file->get_content_file_handle();
                try {
                    $exif = @exif_read_data($handle, 'IFD0');
                } finally {
                    fclose($handle);
                }
                if (in_array((int)($exif['Orientation'] ?? 0), [5, 6, 7, 8], true)) {
                    return [(float)$info['height'], (float)$info['width']];
                }
            }
            return [(float)$info['width'], (float)$info['height']];
        }
        if (file_is_svg_image_from_mimetype($file->get_mimetype())) {
            $svg = $file->get_content();
            if (str_starts_with($svg, "\x1f\x8b")) {
                $svg = @gzdecode($svg, 16 * 1024 * 1024);
                if ($svg === false) {
                    throw new \moodle_exception('invalidcompositionimage', 'qtype_clozeonimage');
                }
            }
            if (preg_match('/<svg\b([^>]*)>/i', $svg, $tag)) {
                if (preg_match('/\bwidth=["\']([0-9.]+)(?:px)?["\']/i', $tag[1], $w) &&
                        preg_match('/\bheight=["\']([0-9.]+)(?:px)?["\']/i', $tag[1], $h) && $w[1] > 0 && $h[1] > 0) {
                    return [(float)$w[1], (float)$h[1]];
                }
                if (preg_match('/\bviewBox=["\']\s*[-0-9.]+[ ,]+[-0-9.]+[ ,]+([0-9.]+)[ ,]+([0-9.]+)\s*["\']/i',
                        $tag[1], $v) && $v[1] > 0 && $v[2] > 0) {
                    return [(float)$v[1], (float)$v[2]];
                }
            }
        }
        throw new \moodle_exception('invalidcompositionimage', 'qtype_clozeonimage');
    }

    /** Validate only files in the current user's standard draft areas. */
    public static function images(object $form): array {
        global $USER, $CFG;
        self::layers($form);
        $images = [];
        $geometry = self::geometry($form);
        foreach (($form->sceneimage ?? []) as $index => $draft) {
            if (!empty($form->imageremove[$index])) {
                continue;
            }
            $files = get_file_storage()->get_area_files(\context_user::instance($USER->id)->id,
                'user', 'draft', (int)$draft, 'id', false);
            if (!$files) {
                continue;
            }
            if (count($files) !== 1) {
                throw new \moodle_exception('invalidcompositionimage', 'qtype_clozeonimage');
            }
            $file = reset($files);
            if (!file_mimetype_in_typegroup($file->get_mimetype(), 'web_image') ||
                    !file_extension_in_typegroup($file->get_filename(), 'web_image') ||
                    (!empty($CFG->maxbytes) && $file->get_filesize() > $CFG->maxbytes)) {
                throw new \moodle_exception('invalidcompositionimage', 'qtype_clozeonimage');
            }
            [$iw, $ih] = self::intrinsic($file);
            $image = (object)['draft' => (int)$draft];
            foreach (self::IMAGE_FIELDS as $field => $column) {
                $image->$column = $form->{$field}[$index] ?? ($column === 'name' ? $file->get_filename() : 0);
            }
            $image->elementkey = self::key((string)($form->imagekey[$index] ?? ''));
            if (isset($images[$image->elementkey])) {
                throw new \moodle_exception('invalidcomposition', 'qtype_clozeonimage');
            }
            $image->name = clean_param($image->name, PARAM_TEXT) ?: $file->get_filename();
            foreach (['xleft', 'ytop', 'width', 'height'] as $column) {
                if (!is_numeric($image->$column) || !is_finite((float)$image->$column) || abs($image->$column) > 4194303) {
                    throw new \moodle_exception('invalidcompositionimage', 'qtype_clozeonimage');
                }
                $image->$column = (float)$image->$column;
            }
            if ($image->width < 0 || $image->height < 0) {
                throw new \moodle_exception('invalidcompositionimage', 'qtype_clozeonimage');
            }
            if ($image->width == 0) {
                $image->width = min($iw, $geometry['width'], $geometry['height'] * $iw / $ih);
            }
            // Always use the file's true proportions, including after replacement.
            $image->height = $image->width * $ih / $iw;
            self::coordinate($image->height);
            $image->layer = self::layer($image->layer);
            $images[$image->elementkey] = $image;
        }
        return $images;
    }

    /** Persist image records/files for the new question version; local keys survive copies. */
    public static function save_images(object $form, array $images): void {
        global $DB;
        $fs = get_file_storage();
        foreach ($DB->get_records('qtype_clozeonimage_img', ['questionid' => $form->id]) as $old) {
            $fs->delete_area_files($form->context->id, 'qtype_clozeonimage', 'image', $old->id);
        }
        $DB->delete_records('qtype_clozeonimage_img', ['questionid' => $form->id]);
        foreach ($images as $image) {
            $record = clone $image;
            unset($record->draft);
            $record->questionid = $form->id;
            $record->id = $DB->insert_record('qtype_clozeonimage_img', $record);
            file_save_draft_area_files($image->draft, $form->context->id, 'qtype_clozeonimage', 'image', $record->id,
                ['subdirs' => 0, 'maxfiles' => 1]);
        }
    }
}
