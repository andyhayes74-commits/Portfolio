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
            'provider' => 'global',
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
            'gallery_desktop_columns' => 3,
            'gallery_tablet_columns' => 2,
            'gallery_mobile_columns' => 1,
            'gallery_gap' => 'medium',
            'gallery_crop_mode' => 'cover',
            'gallery_max_width' => 'full',
            'gallery_alignment' => 'center',
            'gallery_background_color' => 'transparent',
            'gallery_card_background_color' => 'rgba(255,255,255,0.06)',
            'gallery_card_text_color' => 'inherit',
            'gallery_card_border_color' => 'rgba(255,255,255,0.16)',
            'gallery_card_border_enabled' => 0,
            'gallery_card_radius' => 16,
            'gallery_card_padding' => 'none',
            'gallery_card_shadow' => 'none',
            'gallery_caption_position' => 'below',
            'gallery_caption_color' => 'inherit',
            'gallery_caption_background_color' => 'rgba(0,0,0,0.58)',
            'gallery_caption_text_size' => 'small',
            'gallery_caption_words' => 10,
        );
    }

    public static function allowed_providers() {
        return array('global', 'gemini_direct', 'openai_direct', 'custom_route');
    }

    public static function resolve_provider($project) {
        $provider = sanitize_key($project['provider'] ?? 'global');
        if ($provider === 'global' || !in_array($provider, self::allowed_providers(), true)) {
            $provider = get_option(PAI_Constants::OPT_PROVIDER, 'custom_route');
        }

        return in_array($provider, array('gemini_direct', 'openai_direct', 'custom_route'), true) ? $provider : 'custom_route';
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
            'desktop_columns' => max(1, min(6, absint($project['gallery_desktop_columns'] ?? 3))),
            'tablet_columns' => max(1, min(4, absint($project['gallery_tablet_columns'] ?? 2))),
            'mobile_columns' => max(1, min(2, absint($project['gallery_mobile_columns'] ?? 1))),
            'gap' => self::choice($project['gallery_gap'] ?? 'medium', array('none', 'small', 'medium', 'large'), 'medium'),
            'crop_mode' => self::choice($project['gallery_crop_mode'] ?? 'cover', array('cover', 'contain'), 'cover'),
            'max_width' => self::choice($project['gallery_max_width'] ?? 'full', array('full', 'wide', 'contained'), 'full'),
            'alignment' => self::choice($project['gallery_alignment'] ?? 'center', array('left', 'center'), 'center'),
            'background_color' => self::css_color($project['gallery_background_color'] ?? 'transparent', 'transparent'),
            'card_background_color' => self::css_color($project['gallery_card_background_color'] ?? 'rgba(255,255,255,0.06)', 'rgba(255,255,255,0.06)'),
            'card_text_color' => self::css_color($project['gallery_card_text_color'] ?? 'inherit', 'inherit'),
            'card_border_color' => self::css_color($project['gallery_card_border_color'] ?? 'rgba(255,255,255,0.16)', 'rgba(255,255,255,0.16)'),
            'card_border_enabled' => !empty($project['gallery_card_border_enabled']) ? 1 : 0,
            'card_radius' => max(0, min(60, absint($project['gallery_card_radius'] ?? 16))),
            'card_padding' => self::choice($project['gallery_card_padding'] ?? 'none', array('none', 'small', 'medium', 'large'), 'none'),
            'card_shadow' => self::choice($project['gallery_card_shadow'] ?? 'none', array('none', 'soft', 'strong'), 'none'),
            'caption_position' => self::choice($project['gallery_caption_position'] ?? 'below', array('below', 'overlay'), 'below'),
            'caption_color' => self::css_color($project['gallery_caption_color'] ?? 'inherit', 'inherit'),
            'caption_background_color' => self::css_color($project['gallery_caption_background_color'] ?? 'rgba(0,0,0,0.58)', 'rgba(0,0,0,0.58)'),
            'caption_text_size' => self::choice($project['gallery_caption_text_size'] ?? 'small', array('small', 'medium', 'large'), 'small'),
            'caption_words' => max(3, min(40, absint($project['gallery_caption_words'] ?? 10))),
        );

        if (isset($overrides['limit']) && $overrides['limit'] !== '') {
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

        $provider = sanitize_key(wp_unslash($_POST['provider'] ?? 'global'));
        if (!in_array($provider, self::allowed_providers(), true)) {
            $provider = 'global';
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
            'provider' => $provider,
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
            'gallery_desktop_columns' => max(1, min(6, absint($_POST['gallery_desktop_columns'] ?? 3))),
            'gallery_tablet_columns' => max(1, min(4, absint($_POST['gallery_tablet_columns'] ?? 2))),
            'gallery_mobile_columns' => max(1, min(2, absint($_POST['gallery_mobile_columns'] ?? 1))),
            'gallery_gap' => self::choice(sanitize_key(wp_unslash($_POST['gallery_gap'] ?? 'medium')), array('none', 'small', 'medium', 'large'), 'medium'),
            'gallery_crop_mode' => self::choice(sanitize_key(wp_unslash($_POST['gallery_crop_mode'] ?? 'cover')), array('cover', 'contain'), 'cover'),
            'gallery_max_width' => self::choice(sanitize_key(wp_unslash($_POST['gallery_max_width'] ?? 'full')), array('full', 'wide', 'contained'), 'full'),
            'gallery_alignment' => self::choice(sanitize_key(wp_unslash($_POST['gallery_alignment'] ?? 'center')), array('left', 'center'), 'center'),
            'gallery_background_color' => self::css_color(wp_unslash($_POST['gallery_background_color'] ?? 'transparent'), 'transparent'),
            'gallery_card_background_color' => self::css_color(wp_unslash($_POST['gallery_card_background_color'] ?? 'rgba(255,255,255,0.06)'), 'rgba(255,255,255,0.06)'),
            'gallery_card_text_color' => self::css_color(wp_unslash($_POST['gallery_card_text_color'] ?? 'inherit'), 'inherit'),
            'gallery_card_border_color' => self::css_color(wp_unslash($_POST['gallery_card_border_color'] ?? 'rgba(255,255,255,0.16)'), 'rgba(255,255,255,0.16)'),
            'gallery_card_border_enabled' => isset($_POST['gallery_card_border_enabled']) ? 1 : 0,
            'gallery_card_radius' => max(0, min(60, absint($_POST['gallery_card_radius'] ?? 16))),
            'gallery_card_padding' => self::choice(sanitize_key(wp_unslash($_POST['gallery_card_padding'] ?? 'none')), array('none', 'small', 'medium', 'large'), 'none'),
            'gallery_card_shadow' => self::choice(sanitize_key(wp_unslash($_POST['gallery_card_shadow'] ?? 'none')), array('none', 'soft', 'strong'), 'none'),
            'gallery_caption_position' => self::choice(sanitize_key(wp_unslash($_POST['gallery_caption_position'] ?? 'below')), array('below', 'overlay'), 'below'),
            'gallery_caption_color' => self::css_color(wp_unslash($_POST['gallery_caption_color'] ?? 'inherit'), 'inherit'),
            'gallery_caption_background_color' => self::css_color(wp_unslash($_POST['gallery_caption_background_color'] ?? 'rgba(0,0,0,0.58)'), 'rgba(0,0,0,0.58)'),
            'gallery_caption_text_size' => self::choice(sanitize_key(wp_unslash($_POST['gallery_caption_text_size'] ?? 'small')), array('small', 'medium', 'large'), 'small'),
            'gallery_caption_words' => max(3, min(40, absint($_POST['gallery_caption_words'] ?? 10))),
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

    private static function css_color($value, $fallback) {
        $value = trim((string) $value);
        if ($value === '') {
            return $fallback;
        }

        if (in_array(strtolower($value), array('transparent', 'inherit', 'currentcolor'), true)) {
            return strtolower($value);
        }

        if (preg_match('/^#[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/', $value)) {
            return $value;
        }

        if (preg_match('/^rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})(\s*,\s*(0|1|0?\.\d+))?\s*\)$/', $value, $matches)) {
            $r = min(255, max(0, (int) $matches[1]));
            $g = min(255, max(0, (int) $matches[2]));
            $b = min(255, max(0, (int) $matches[3]));
            if (isset($matches[5]) && $matches[5] !== '') {
                $a = min(1, max(0, (float) $matches[5]));
                return 'rgba(' . $r . ',' . $g . ',' . $b . ',' . $a . ')';
            }
            return 'rgb(' . $r . ',' . $g . ',' . $b . ')';
        }

        return $fallback;
    }
}
