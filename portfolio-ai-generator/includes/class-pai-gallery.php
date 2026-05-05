<?php
if (!defined('ABSPATH')) {
    exit;
}

final class PAI_Gallery {
    public function register() {
        add_shortcode('portfolio_ai_gallery', array($this, 'shortcode'));
        add_action('wp_ajax_pai_submit_gallery', array($this, 'ajax_submit_gallery'));
        add_action('wp_ajax_nopriv_pai_submit_gallery', array($this, 'ajax_submit_gallery'));
        add_action('wp_ajax_pai_load_gallery', array($this, 'ajax_load_gallery'));
        add_action('wp_ajax_nopriv_pai_load_gallery', array($this, 'ajax_load_gallery'));
    }

    public function shortcode($atts) {
        $atts = shortcode_atts(array(
            'project' => '',
            'limit' => '',
            'shape' => '',
            'size' => '',
            'caption' => '',
            'download' => '',
        ), $atts);

        $slug = sanitize_key($atts['project']);
        $project = PAI_Projects::get($slug);

        if (!$project) {
            return current_user_can('manage_options') ? '<p>Portfolio AI gallery project missing.</p>' : '';
        }

        PAI_Plugin::assets();
        $settings = PAI_Projects::gallery_settings($project, $atts);
        $nonce = wp_create_nonce('pai_gallery_' . $slug);

        return $this->render_gallery($slug, $settings, $nonce);
    }

    public function ajax_load_gallery() {
        $slug = sanitize_key(wp_unslash($_POST['project'] ?? ''));
        $nonce = sanitize_text_field(wp_unslash($_POST['nonce'] ?? ''));

        if (!$slug || !wp_verify_nonce($nonce, 'pai_gallery_' . $slug)) {
            wp_send_json_error(array('message' => 'Gallery security check failed. Refresh the page and try again.'), 403);
        }

        $project = PAI_Projects::get($slug);
        if (!$project) {
            wp_send_json_error(array('message' => 'Gallery project unavailable.'), 404);
        }

        $settings = PAI_Projects::gallery_settings($project, array(
            'limit' => sanitize_text_field(wp_unslash($_POST['limit'] ?? '')),
            'shape' => sanitize_key(wp_unslash($_POST['shape'] ?? '')),
            'size' => sanitize_key(wp_unslash($_POST['size'] ?? '')),
            'caption' => sanitize_key(wp_unslash($_POST['caption'] ?? '')),
            'download' => sanitize_text_field(wp_unslash($_POST['download'] ?? '')),
        ));

        wp_send_json_success(array(
            'html' => $this->render_gallery($slug, $settings, $nonce),
        ));
    }

    public function ajax_submit_gallery() {
        $slug = sanitize_key(wp_unslash($_POST['project'] ?? ''));
        check_ajax_referer('pai_generate_' . $slug, 'nonce');

        $project = PAI_Projects::get($slug);
        if (!$project || $project['gallery_mode'] === 'off') {
            wp_send_json_error(array('message' => 'Gallery submissions are disabled.'), 403);
        }

        $id = absint($_POST['id'] ?? 0);
        $status = $project['gallery_mode'] === 'approved' ? 'approved' : 'pending';

        global $wpdb;
        $ok = $wpdb->update(
            PAI_Constants::table(),
            array('status' => $status, 'updated_at' => current_time('mysql')),
            array('id' => $id, 'project_slug' => $slug, 'status' => 'private'),
            array('%s', '%s'),
            array('%d', '%s', '%s')
        );

        if (!$ok) {
            wp_send_json_error(array('message' => 'Could not submit this image.'), 400);
        }

        wp_send_json_success(array(
            'status' => $status,
            'auto_refresh' => !empty($project['gallery_auto_refresh']) ? 1 : 0,
            'message' => $status === 'approved' ? 'Image added to gallery.' : 'Image submitted for approval.',
        ));
    }

    private function render_gallery($slug, $settings, $nonce) {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . PAI_Constants::table() . " WHERE project_slug=%s AND status='approved' ORDER BY created_at DESC LIMIT %d",
            $slug,
            (int) $settings['limit']
        ));

        $classes = array(
            'pai-gallery',
            'pai-gallery--shape-' . sanitize_html_class($settings['shape']),
            'pai-gallery--size-' . sanitize_html_class($settings['size']),
            'pai-gallery--card-' . sanitize_html_class($settings['card_style']),
            'pai-gallery--caption-' . sanitize_html_class($settings['caption']),
            'pai-gallery--crop-' . sanitize_html_class($settings['crop_mode']),
            'pai-gallery--max-' . sanitize_html_class($settings['max_width']),
            'pai-gallery--align-' . sanitize_html_class($settings['alignment']),
            'pai-gallery--padding-' . sanitize_html_class($settings['card_padding']),
            'pai-gallery--shadow-' . sanitize_html_class($settings['card_shadow']),
            'pai-gallery--caption-pos-' . sanitize_html_class($settings['caption_position']),
            'pai-gallery--caption-size-' . sanitize_html_class($settings['caption_text_size']),
        );

        if (!empty($settings['card_border_enabled'])) {
            $classes[] = 'pai-gallery--border-on';
        }

        $style = $this->style_vars($settings);

        ob_start();
        echo '<div class="' . esc_attr(implode(' ', $classes)) . '" style="' . esc_attr($style) . '" data-project="' . esc_attr($slug) . '" data-nonce="' . esc_attr($nonce) . '" data-limit="' . esc_attr((string) $settings['limit']) . '" data-shape="' . esc_attr($settings['shape']) . '" data-size="' . esc_attr($settings['size']) . '" data-caption="' . esc_attr($settings['caption']) . '" data-download="' . esc_attr((string) $settings['download']) . '">';

        if (!$rows) {
            echo '<p class="pai-gallery__empty">No approved generated images yet.</p>';
        }

        foreach ($rows as $row) {
            $caption = $this->caption($row, $settings['caption'], $settings['caption_words']);
            echo '<figure class="pai-gallery__item">';
            echo '<a class="pai-gallery__image-link" href="' . esc_url($row->image_url) . '" target="_blank" rel="noopener noreferrer">';
            echo '<img src="' . esc_url($row->image_url) . '" alt="' . esc_attr(wp_trim_words($row->user_prompt, 10)) . '" loading="lazy">';
            echo '</a>';

            if ($caption || !empty($settings['download'])) {
                echo '<figcaption>';
                if ($caption) {
                    echo '<span class="pai-gallery__caption-text">' . esc_html($caption) . '</span>';
                }
                if (!empty($settings['download'])) {
                    echo '<a class="pai-gallery__download" href="' . esc_url($row->image_url) . '" download target="_blank" rel="noopener noreferrer">Download</a>';
                }
                echo '</figcaption>';
            }

            echo '</figure>';
        }

        echo '</div>';
        return ob_get_clean();
    }

    private function style_vars($settings) {
        $gap_map = array('none' => '0px', 'small' => '8px', 'medium' => '16px', 'large' => '28px');
        $padding_map = array('none' => '0px', 'small' => '8px', 'medium' => '14px', 'large' => '22px');

        $vars = array(
            '--pai-gallery-desktop-cols:' . (int) $settings['desktop_columns'],
            '--pai-gallery-tablet-cols:' . (int) $settings['tablet_columns'],
            '--pai-gallery-mobile-cols:' . (int) $settings['mobile_columns'],
            '--pai-gallery-gap:' . ($gap_map[$settings['gap']] ?? '16px'),
            '--pai-gallery-bg:' . $settings['background_color'],
            '--pai-card-bg:' . $settings['card_background_color'],
            '--pai-card-text:' . $settings['card_text_color'],
            '--pai-card-border:' . $settings['card_border_color'],
            '--pai-card-radius:' . (int) $settings['card_radius'] . 'px',
            '--pai-card-padding:' . ($padding_map[$settings['card_padding']] ?? '0px'),
            '--pai-caption-color:' . $settings['caption_color'],
            '--pai-caption-bg:' . $settings['caption_background_color'],
        );

        return implode(';', $vars) . ';';
    }

    private function caption($row, $mode, $words = 10) {
        if ($mode === 'hide') {
            return '';
        }

        $date = !empty($row->created_at) ? mysql2date(get_option('date_format'), $row->created_at) : '';
        $prompt = wp_trim_words($row->user_prompt, max(3, (int) $words));

        if ($mode === 'date') {
            return $date;
        }

        if ($mode === 'prompt_date') {
            return trim($prompt . ($date ? ' · ' . $date : ''));
        }

        return $prompt;
    }
}
