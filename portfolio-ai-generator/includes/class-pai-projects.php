<?php
if (!defined('ABSPATH')) {
    exit;
}

final class PAI_Projects {
    public static function all() {
        $projects = get_option(PAI_Constants::OPT_PROJECTS, array());
        return is_array($projects) ? $projects : array();
    }

    public static function get($slug) {
        $projects = self::all();
        if (!isset($projects[$slug])) {
            return null;
        }

        return wp_parse_args($projects[$slug], self::defaults($slug));
    }

    public static function defaults($slug = '') {
        return array(
            'name' => '',
            'slug' => $slug,
            'enabled' => 1,
            'hidden_prompt' => '',
            'negative_prompt' => '',
            'user_template' => 'Create a {{generation_format}} showcase image based on: {{user_prompt}}.',
            'style_summary' => '',
            'model_name' => 'image-generation-model',
            'reference_image_id' => 0,
            'generation_format' => 'portrait',
            'aspect_ratios' => array('portrait'),
            'daily_limit' => 20,
            'gallery_mode' => 'pending',
            'gallery_limit' => 12,
            'gallery_thumb_shape' => 'square',
            'gallery_thumb_size' => 'medium',
            'gallery_caption' => 'prompt',
            'gallery_card_style' => 'soft',
            'gallery_download' => 0,
            'gallery_auto_refresh' => 1,
        );
    }

    public static function allowed_generation_formats() {
        return array('portrait', 'square', 'landscape');
    }

    public static function format_size($format) {
        if ($format === 'landscape') {
            return array(1024, 768);
        }

        if ($format === 'square') {
            return array(1024, 1024);
        }

        return array(768, 1024);
    }

    public static function gallery_settings($project, $overrides = array()) {
        $settings = array(
            'limit' => max(1, min(100, absint($project['gallery_limit'] ?? 12))),
            'shape' => self::choice($project['gallery_thumb_shape'] ?? 'square', array('square', 'portrait', 'landscape', 'natural'), 'square'),
            'size' => self::choice($project['gallery_thumb_size'] ?? 'medium', array('small', 'medium', 'large'), 'medium'),
            'caption' => self::choice($project['gallery_caption'] ?? 'prompt', array('hide', 'prompt', 'date', 'prompt_date'), 'prompt'),
            'card_style' => self::choice($project['gallery_card_style'] ?? 'soft', array('minimal', 'soft', 'framed'), 'soft'),
            'download' => !empty($project['gallery_download']) ? 1 : 0,
            'auto_refresh' => !empty($project['gallery_auto_refresh']) ? 1 : 0,
        );

        if (isset($overrides['limit'])) {
            $settings['limit'] = max(1, min(100, absint($overrides['limit'])));
        }

        foreach (array('shape', 'size', 'caption') as $key) {
            if (isset($overrides[$key]) && $overrides[$key] !== '') {
                $settings[$key] = sanitize_key($overrides[$key]);
            }
        }

        if (isset($overrides['download']) && $overrides['download'] !== '') {
            $settings['download'] = in_array(strtolower((string) $overrides['download']), array('1', 'true', 'yes', 'on'), true) ? 1 : 0;
        }

        return $settings;
    }

    public static function save_from_post() {
        $projects = self::all();
        $original = sanitize_key(wp_unslash($_POST['original_slug'] ?? ''));
        $slug = sanitize_key(wp_unslash($_POST['slug'] ?? ''));

        if (!$slug) {
            wp_die('Project slug is required.');
        }

        if ($original && $original !== $slug) {
            unset($projects[$original]);
        }

        $generation_format = sanitize_key(wp_unslash($_POST['generation_format'] ?? 'portrait'));
        if (!in_array($generation_format, self::allowed_generation_formats(), true)) {
            $generation_format = 'portrait';
        }

        $gallery_mode = sanitize_key(wp_unslash($_POST['gallery_mode'] ?? 'pending'));
        if (!in_array($gallery_mode, array('off', 'private', 'pending', 'approved'), true)) {
            $gallery_mode = 'pending';
        }

        $gallery_shape = self::choice(sanitize_key(wp_unslash($_POST['gallery_thumb_shape'] ?? 'square')), array('square', 'portrait', 'landscape', 'natural'), 'square');
        $gallery_size = self::choice(sanitize_key(wp_unslash($_POST['gallery_thumb_size'] ?? 'medium')), array('small', 'medium', 'large'), 'medium');
        $gallery_caption = self::choice(sanitize_key(wp_unslash($_POST['gallery_caption'] ?? 'prompt')), array('hide', 'prompt', 'date', 'prompt_date'), 'prompt');
        $gallery_card = self::choice(sanitize_key(wp_unslash($_POST['gallery_card_style'] ?? 'soft')), array('minimal', 'soft', 'framed'), 'soft');

        $projects[$slug] = array(
            'name' => sanitize_text_field(wp_unslash($_POST['name'] ?? '')),
            'slug' => $slug,
            'enabled' => isset($_POST['enabled']) ? 1 : 0,
            'hidden_prompt' => sanitize_textarea_field(wp_unslash($_POST['hidden_prompt'] ?? '')),
            'negative_prompt' => sanitize_textarea_field(wp_unslash($_POST['negative_prompt'] ?? '')),
            'user_template' => sanitize_textarea_field(wp_unslash($_POST['user_template'] ?? '')),
            'style_summary' => sanitize_textarea_field(wp_unslash($_POST['style_summary'] ?? '')),
            'model_name' => sanitize_text_field(wp_unslash($_POST['model_name'] ?? '')),
            'reference_image_id' => absint($_POST['reference_image_id'] ?? 0),
            'generation_format' => $generation_format,
            'aspect_ratios' => array($generation_format),
            'daily_limit' => max(1, min(1000, absint($_POST['daily_limit'] ?? 20))),
            'gallery_mode' => $gallery_mode,
            'gallery_limit' => max(1, min(100, absint($_POST['gallery_limit'] ?? 12))),
            'gallery_thumb_shape' => $gallery_shape,
            'gallery_thumb_size' => $gallery_size,
            'gallery_caption' => $gallery_caption,
            'gallery_card_style' => $gallery_card,
            'gallery_download' => isset($_POST['gallery_download']) ? 1 : 0,
            'gallery_auto_refresh' => isset($_POST['gallery_auto_refresh']) ? 1 : 0,
        );

        update_option(PAI_Constants::OPT_PROJECTS, $projects, false);
    }

    public static function compile_prompt($project, $user_prompt, $format) {
        $template = $project['user_template'] ?: 'Create a {{generation_format}} showcase image based on: {{user_prompt}}.';
        $user_section = str_replace(
            array('{{user_prompt}}', '{{aspect_ratio}}', '{{generation_format}}'),
            array($user_prompt, $format, $format),
            $template
        );

        $parts = array();
        if (!empty($project['hidden_prompt'])) {
            $parts[] = "Project master prompt:\n" . $project['hidden_prompt'];
        }

        if (!empty($project['reference_image_id'])) {
            $parts[] = "Reference image instruction:\n"
                . "A reference image is attached. Use it as the primary visual reference for the generated image. "
                . "Preserve its overall visual identity, style, colour palette, structure, composition cues, and defining details where relevant. "
                . "If the reference image contains a key subject, object, landmark, or character, keep the important visual traits consistent unless the user request clearly asks for a change. "
                . "The final result should clearly reflect the attached reference image while still fulfilling the user request. "
                . "Do not ignore the reference image and do not treat it as only loose inspiration.";
        }

        $parts[] = "User request:\n" . $user_section;
        $parts[] = "Output format:\nCreate a " . $format . " image suitable for a fast portfolio showcase.";

        if (!empty($project['negative_prompt'])) {
            $parts[] = "Avoid:\n" . $project['negative_prompt'];
        }

        return implode("\n\n", $parts);
    }

    private static function choice($value, $allowed, $fallback) {
        return in_array($value, $allowed, true) ? $value : $fallback;
    }
}
