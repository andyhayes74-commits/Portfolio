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
            'user_template' => 'Create an image based on: {{user_prompt}}. Aspect ratio: {{aspect_ratio}}.',
            'style_summary' => '',
            'model_name' => 'image-generation-model',
            'reference_image_id' => 0,
            'aspect_ratios' => array('square', 'landscape', 'portrait'),
            'daily_limit' => 20,
            'gallery_mode' => 'pending',
        );
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

        $raw_ratios = explode(',', wp_unslash($_POST['aspect_ratios'] ?? 'square'));
        $ratios = array_values(array_intersect(
            array('square', 'landscape', 'portrait'),
            array_map('sanitize_key', array_map('trim', $raw_ratios))
        ));

        if (!$ratios) {
            $ratios = array('square');
        }

        $gallery_mode = sanitize_key(wp_unslash($_POST['gallery_mode'] ?? 'pending'));
        if (!in_array($gallery_mode, array('off', 'private', 'pending', 'approved'), true)) {
            $gallery_mode = 'pending';
        }

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
            'aspect_ratios' => $ratios,
            'daily_limit' => max(1, min(1000, absint($_POST['daily_limit'] ?? 20))),
            'gallery_mode' => $gallery_mode,
        );

        update_option(PAI_Constants::OPT_PROJECTS, $projects, false);
    }

    public static function compile_prompt($project, $user_prompt, $ratio) {
        $template = $project['user_template'] ?: 'Create an image based on: {{user_prompt}}.';
        $user_section = str_replace(
            array('{{user_prompt}}', '{{aspect_ratio}}'),
            array($user_prompt, $ratio),
            $template
        );

        $parts = array();
        if (!empty($project['hidden_prompt'])) {
            $parts[] = "Project master prompt:\n" . $project['hidden_prompt'];
        }

        $parts[] = "User request:\n" . $user_section;

        if (!empty($project['negative_prompt'])) {
            $parts[] = "Avoid:\n" . $project['negative_prompt'];
        }

        return implode("\n\n", $parts);
    }
}
