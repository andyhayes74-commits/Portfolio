<?php
if (!defined('ABSPATH')) {
    exit;
}

final class PAI_Gallery {
    public function register() {
        add_shortcode('portfolio_ai_gallery', array($this, 'shortcode'));
        add_action('wp_ajax_pai_submit_gallery', array($this, 'ajax_submit_gallery'));
        add_action('wp_ajax_nopriv_pai_submit_gallery', array($this, 'ajax_submit_gallery'));
    }

    public function shortcode($atts) {
        $atts = shortcode_atts(array('project' => '', 'limit' => 24), $atts);
        $slug = sanitize_key($atts['project']);
        $limit = max(1, min(100, absint($atts['limit'])));
        $project = PAI_Projects::get($slug);

        if (!$project) {
            return current_user_can('manage_options') ? '<p>Portfolio AI gallery project missing.</p>' : '';
        }

        PAI_Plugin::assets();

        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . PAI_Constants::table() . " WHERE project_slug=%s AND status='approved' ORDER BY created_at DESC LIMIT %d",
            $slug,
            $limit
        ));

        ob_start();
        echo '<div class="pai-gallery">';
        if (!$rows) {
            echo '<p>No approved generated images yet.</p>';
        }
        foreach ($rows as $row) {
            echo '<figure class="pai-gallery__item">';
            echo '<a href="' . esc_url($row->image_url) . '" target="_blank" rel="noopener noreferrer">';
            echo '<img src="' . esc_url($row->image_url) . '" alt="' . esc_attr(wp_trim_words($row->user_prompt, 10)) . '" loading="lazy">';
            echo '</a>';
            echo '<figcaption>' . esc_html(wp_trim_words($row->user_prompt, 10)) . '</figcaption>';
            echo '</figure>';
        }
        echo '</div>';
        return ob_get_clean();
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
            'message' => $status === 'approved' ? 'Image added to gallery.' : 'Image submitted for approval.',
        ));
    }
}
