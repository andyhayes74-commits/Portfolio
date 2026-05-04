<?php
/**
 * Plugin Name: Portfolio AI Generator
 * Description: V1 plugin for project-based AI image generation via LiteLLM with moderation gallery.
 * Version: 1.0.1
 */

if (!defined('ABSPATH')) {
    exit;
}

class Portfolio_AI_Generator {
    const OPTION_KEY = 'pai_projects';
    const OPTION_LITELLM_URL = 'pai_litellm_url';
    const NONCE_ACTION = 'pai_generate_nonce';

    public function __construct() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_init', array($this, 'maybe_handle_admin_actions'));
        add_action('wp_enqueue_scripts', array($this, 'register_assets'));

        add_shortcode('portfolio_ai_generator', array($this, 'render_generator_shortcode'));
        add_shortcode('portfolio_ai_gallery', array($this, 'render_gallery_shortcode'));

        add_action('wp_ajax_portfolio_ai_generate', array($this, 'ajax_generate'));
        add_action('wp_ajax_nopriv_portfolio_ai_generate', array($this, 'ajax_generate'));
        add_action('wp_ajax_portfolio_ai_submit_gallery', array($this, 'ajax_submit_gallery'));
        add_action('wp_ajax_nopriv_portfolio_ai_submit_gallery', array($this, 'ajax_submit_gallery'));
    }

    public static function table_name() {
        global $wpdb;
        return $wpdb->prefix . 'portfolio_ai_images';
    }

    public function activate() {
        global $wpdb;
        $sql = 'CREATE TABLE ' . self::table_name() . " (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            project_slug VARCHAR(120) NOT NULL,
            user_prompt TEXT NOT NULL,
            full_prompt LONGTEXT NOT NULL,
            negative_prompt TEXT NULL,
            image_path TEXT NOT NULL,
            attachment_id BIGINT(20) UNSIGNED NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'private',
            model_name VARCHAR(255) NULL,
            metadata LONGTEXT NULL,
            ip_hash VARCHAR(64) NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY project_slug (project_slug),
            KEY status (status)
        ) " . $wpdb->get_charset_collate() . ';';
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public function admin_menu() {
        add_options_page('Portfolio AI', 'Portfolio AI', 'manage_options', 'portfolio-ai', array($this, 'render_settings_page'));
        add_submenu_page('options-general.php', 'Portfolio AI Moderation', 'Portfolio AI Moderation', 'manage_options', 'portfolio-ai-moderation', array($this, 'render_moderation_page'));
    }

    public function register_assets() {
        wp_register_style('pai-frontend', plugins_url('assets/css/pai-frontend.css', __FILE__), array(), '1.0.1');
        wp_register_script('pai-frontend', plugins_url('assets/js/pai-frontend.js', __FILE__), array('jquery'), '1.0.1', true);
    }

    private function get_projects() {
        $projects = get_option(self::OPTION_KEY, array());
        return is_array($projects) ? $projects : array();
    }

    private function get_project($slug) {
        $projects = $this->get_projects();
        return isset($projects[$slug]) ? $projects[$slug] : null;
    }

    public function maybe_handle_admin_actions() {
        if (!is_admin() || !current_user_can('manage_options')) {
            return;
        }

        if (!empty($_POST['pai_save_settings'])) {
            check_admin_referer('pai_save_settings');
            update_option(self::OPTION_LITELLM_URL, esc_url_raw(wp_unslash($_POST['pai_litellm_url'])));
        }

        if (!empty($_POST['pai_save_project'])) {
            check_admin_referer('pai_save_project');
            $slug = sanitize_title(wp_unslash($_POST['project_slug']));
            if (!$slug) {
                return;
            }
            $template = sanitize_textarea_field(wp_unslash($_POST['user_prompt_template']));
            if ($template === '') {
                $template = '{{user_prompt}}';
            }
            $projects = $this->get_projects();
            $projects[$slug] = array(
                'name' => sanitize_text_field(wp_unslash($_POST['project_name'])),
                'slug' => $slug,
                'hidden_prompt' => sanitize_textarea_field(wp_unslash($_POST['hidden_prompt'])),
                'negative_prompt' => sanitize_textarea_field(wp_unslash($_POST['negative_prompt'])),
                'user_prompt_template' => $template,
                'reference_image_id' => absint($_POST['reference_image_id']),
                'model_name' => sanitize_text_field(wp_unslash($_POST['model_name'])),
                'aspect_ratios' => isset($_POST['aspect_ratios']) ? array_values(array_intersect(array_map('sanitize_text_field', (array) wp_unslash($_POST['aspect_ratios'])), array('square', 'landscape', 'portrait'))) : array('square'),
                'rate_limit' => max(1, absint($_POST['rate_limit'])),
                'require_approval' => !empty($_POST['require_approval']) ? 1 : 0,
            );
            if (empty($projects[$slug]['aspect_ratios'])) {
                $projects[$slug]['aspect_ratios'] = array('square');
            }
            update_option(self::OPTION_KEY, $projects);
        }

        if (!empty($_GET['pai_delete_project'])) {
            check_admin_referer('pai_delete_project');
            $projects = $this->get_projects();
            unset($projects[sanitize_title(wp_unslash($_GET['pai_delete_project']))]);
            update_option(self::OPTION_KEY, $projects);
        }

        if (!empty($_GET['pai_moderate']) && !empty($_GET['id'])) {
            check_admin_referer('pai_moderate');
            $this->moderate_image(absint($_GET['id']), sanitize_text_field(wp_unslash($_GET['pai_moderate'])));
        }
    }

    private function moderate_image($id, $status) {
        global $wpdb;
        if (!in_array($status, array('approved', 'rejected'), true)) {
            return;
        }
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table_name() . ' WHERE id=%d', $id));
        if (!$row) {
            return;
        }
        $attachment_id = (int) $row->attachment_id;
        if ($status === 'approved' && !$attachment_id) {
            $attachment_id = $this->create_attachment_from_url($row->image_path, $row->project_slug, $id);
        }
        $wpdb->update(self::table_name(), array('status' => $status, 'attachment_id' => $attachment_id, 'updated_at' => current_time('mysql')), array('id' => $id), array('%s', '%d', '%s'), array('%d'));
    }

    private function create_attachment_from_url($url, $project_slug, $id) {
        $upload_dir = wp_upload_dir();
        if (strpos($url, $upload_dir['baseurl']) !== 0) {
            return 0;
        }
        $file = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $url);
        if (!file_exists($file)) {
            return 0;
        }
        $filetype = wp_check_filetype(basename($file), null);
        $attachment = array('post_mime_type' => $filetype['type'], 'post_title' => sanitize_text_field('PAI ' . $project_slug . ' #' . $id), 'post_content' => '', 'post_status' => 'inherit');
        $attach_id = wp_insert_attachment($attachment, $file);
        if (!$attach_id || is_wp_error($attach_id)) {
            return 0;
        }
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $meta = wp_generate_attachment_metadata($attach_id, $file);
        wp_update_attachment_metadata($attach_id, $meta);
        return (int) $attach_id;
    }

    public function render_settings_page() { /* unchanged UI minimal */
        if (!current_user_can('manage_options')) { return; }
        $projects = $this->get_projects();
        $litellm_url = get_option(self::OPTION_LITELLM_URL, '');
        include __DIR__ . '/views-settings-inline.php';
    }

    public function render_moderation_page() {
        if (!current_user_can('manage_options')) { return; }
        global $wpdb;
        $rows = $wpdb->get_results('SELECT * FROM ' . self::table_name() . " WHERE status='pending' ORDER BY created_at DESC LIMIT 100");
        echo '<div class="wrap"><h1>Portfolio AI Moderation</h1><table class="widefat"><thead><tr><th>ID</th><th>Project</th><th>Prompt</th><th>Image</th><th>Actions</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            $approve = wp_nonce_url(add_query_arg(array('page' => 'portfolio-ai-moderation', 'pai_moderate' => 'approved', 'id' => $row->id), admin_url('options-general.php')), 'pai_moderate');
            $reject = wp_nonce_url(add_query_arg(array('page' => 'portfolio-ai-moderation', 'pai_moderate' => 'rejected', 'id' => $row->id), admin_url('options-general.php')), 'pai_moderate');
            echo '<tr><td>' . esc_html($row->id) . '</td><td>' . esc_html($row->project_slug) . '</td><td>' . esc_html($row->user_prompt) . '</td><td><img src="' . esc_url($row->image_path) . '" width="120" /></td><td><a class="button" href="' . esc_url($approve) . '">Approve</a> <a class="button" href="' . esc_url($reject) . '">Reject</a></td></tr>';
        }
        echo '</tbody></table></div>';
    }

    public function render_generator_shortcode($atts) {
        $atts = shortcode_atts(array('project' => ''), $atts);
        $project = sanitize_title($atts['project']);
        $config = $this->get_project($project);
        if (!$config) { return '<p>Invalid project.</p>'; }
        wp_enqueue_style('pai-frontend');
        wp_enqueue_script('pai-frontend');
        wp_localize_script('pai-frontend', 'paiData', array('ajaxUrl' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce(self::NONCE_ACTION)));
        ob_start();
        echo '<div class="pai-generator" data-project="' . esc_attr($project) . '"><textarea class="pai-prompt" placeholder="Describe what you want to generate"></textarea><select class="pai-aspect">';
        foreach ($config['aspect_ratios'] as $ratio) {
            echo '<option value="' . esc_attr($ratio) . '">' . esc_html(ucfirst($ratio)) . '</option>';
        }
        echo '</select><button class="pai-generate">Generate</button><div class="pai-status"></div><div class="pai-result"></div></div>';
        return ob_get_clean();
    }

    public function render_gallery_shortcode($atts) {
        global $wpdb;
        $project = sanitize_title(shortcode_atts(array('project' => ''), $atts)['project']);
        $rows = $wpdb->get_results($wpdb->prepare('SELECT image_path FROM ' . self::table_name() . " WHERE project_slug=%s AND status='approved' ORDER BY created_at DESC LIMIT 50", $project));
        $html = '<div class="pai-gallery">';
        foreach ($rows as $row) { $html .= '<a href="' . esc_url($row->image_path) . '" target="_blank" rel="noopener"><img src="' . esc_url($row->image_path) . '" alt="" /></a>'; }
        return $html . '</div>';
    }

    public function ajax_generate() {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');
        $project_slug = sanitize_title(wp_unslash($_POST['project'] ?? ''));
        $user_prompt = sanitize_text_field(wp_unslash($_POST['prompt'] ?? ''));
        $aspect = sanitize_text_field(wp_unslash($_POST['aspect'] ?? 'square'));
        $project = $this->get_project($project_slug);
        if (!$project || $user_prompt === '' || !in_array($aspect, $project['aspect_ratios'], true)) {
            wp_send_json_error(array('message' => 'Invalid request'), 400);
        }
        $ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        $key = 'pai_rate_' . md5($project_slug . ':' . $ip);
        $count = (int) get_transient($key);
        if ($count >= (int) $project['rate_limit']) { wp_send_json_error(array('message' => 'Daily limit reached'), 429); }
        set_transient($key, $count + 1, DAY_IN_SECONDS);

        $compiled_user = str_replace('{{user_prompt}}', $user_prompt, $project['user_prompt_template']);
        $full_prompt = trim($project['hidden_prompt'] . "\n" . $compiled_user . "\nAspect ratio: " . $aspect);
        $image_url = $this->call_litellm($project, $full_prompt, $project['negative_prompt']);
        if (is_wp_error($image_url)) { wp_send_json_error(array('message' => $image_url->get_error_message()), 500); }
        $saved = $this->download_and_store_image($image_url, $project_slug);
        if (is_wp_error($saved)) { wp_send_json_error(array('message' => $saved->get_error_message()), 500); }

        global $wpdb;
        $status = !empty($project['require_approval']) ? 'private' : 'approved';
        $wpdb->insert(self::table_name(), array('project_slug' => $project_slug, 'user_prompt' => $user_prompt, 'full_prompt' => $full_prompt, 'negative_prompt' => $project['negative_prompt'], 'image_path' => $saved, 'status' => $status, 'model_name' => $project['model_name'], 'metadata' => wp_json_encode(array('source_url' => $image_url)), 'ip_hash' => hash('sha256', $ip), 'created_at' => current_time('mysql'), 'updated_at' => current_time('mysql')));
        wp_send_json_success(array('id' => (int) $wpdb->insert_id, 'image_url' => $saved, 'status' => $status));
    }

    private function call_litellm($project, $full_prompt, $negative_prompt) {
        $base = trailingslashit(get_option(self::OPTION_LITELLM_URL, ''));
        if ($base === '') { return new WP_Error('missing_litellm', 'LiteLLM URL not configured'); }
        $payload = array('model' => $project['model_name'], 'prompt' => $full_prompt, 'negative_prompt' => $negative_prompt, 'size' => '1024x1024');
        if (!empty($project['reference_image_id'])) {
            $path = get_attached_file((int) $project['reference_image_id']);
            if ($path && file_exists($path)) { $payload['reference_image_base64'] = base64_encode((string) file_get_contents($path)); }
        }
        $resp = wp_remote_post($base . 'v1/images/generations', array('timeout' => 60, 'headers' => array('Content-Type' => 'application/json'), 'body' => wp_json_encode($payload), 'sslverify' => true));
        if (is_wp_error($resp)) { return $resp; }
        $body = json_decode(wp_remote_retrieve_body($resp), true);
        if ((int) wp_remote_retrieve_response_code($resp) < 200 || (int) wp_remote_retrieve_response_code($resp) >= 300 || empty($body['data'][0]['url'])) {
            return new WP_Error('litellm_error', 'LiteLLM returned an invalid response');
        }
        return esc_url_raw($body['data'][0]['url']);
    }

    private function download_and_store_image($url, $project_slug) {
        $response = wp_remote_get($url, array('timeout' => 60, 'sslverify' => true));
        if (is_wp_error($response)) { return $response; }
        $body = wp_remote_retrieve_body($response);
        if ($body === '') { return new WP_Error('empty_image', 'Generated image was empty'); }
        $upload_dir = wp_upload_dir();
        $dir = trailingslashit($upload_dir['basedir']) . 'portfolio-ai/' . $project_slug;
        wp_mkdir_p($dir);
        $filename = 'pai-' . $project_slug . '-' . time() . '.png';
        $path = trailingslashit($dir) . $filename;
        if (file_put_contents($path, $body) === false) { return new WP_Error('upload_error', 'Failed to write generated image'); }
        return esc_url_raw(trailingslashit($upload_dir['baseurl']) . 'portfolio-ai/' . $project_slug . '/' . $filename);
    }

    public function ajax_submit_gallery() {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');
        $id = absint($_POST['id'] ?? 0);
        if (!$id) { wp_send_json_error(array('message' => 'Invalid ID'), 400); }
        global $wpdb;
        $wpdb->update(self::table_name(), array('status' => 'pending', 'updated_at' => current_time('mysql')), array('id' => $id), array('%s', '%s'), array('%d'));
        wp_send_json_success(array('status' => 'pending'));
    }
}

new Portfolio_AI_Generator();
