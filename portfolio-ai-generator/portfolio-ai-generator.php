<?php
/**
 * Plugin Name: Portfolio AI Generator
 * Description: Controlled AI image generator for portfolio project pages with custom-route and Gemini Direct providers, hidden project prompts, moderation, galleries, and safe debug logging.
 * Version: 1.2.0
 * Author: Andy Hayes
 * Text Domain: portfolio-ai-generator
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Portfolio_AI_Generator {
    const VERSION = '1.2.0';

    const OPT_PROJECTS = 'pai_projects';
    const OPT_PROVIDER = 'pai_provider';

    const OPT_BASE_URL = 'pai_litellm_base_url';
    const OPT_API_KEY = 'pai_litellm_api_key';
    const OPT_ENDPOINT_PATH = 'pai_endpoint_path';
    const OPT_ENDPOINT_MODE = 'pai_endpoint_mode';
    const OPT_AUTH_MODE = 'pai_auth_mode';

    const OPT_GEMINI_API_KEY = 'pai_gemini_api_key';
    const OPT_GEMINI_MODEL = 'pai_gemini_model';
    const OPT_GEMINI_PROMPT_LIMIT = 'pai_gemini_prompt_limit';

    const OPT_DISABLED = 'pai_emergency_disabled';
    const OPT_DEBUG = 'pai_debug_enabled';
    const OPT_LOGS = 'pai_debug_logs';

    public static function table() {
        global $wpdb;
        return $wpdb->prefix . 'portfolio_ai_images';
    }

    public static function activate() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table = self::table();
        $charset = $wpdb->get_charset_collate();

        dbDelta("CREATE TABLE $table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            project_slug VARCHAR(120) NOT NULL,
            user_prompt TEXT NOT NULL,
            full_prompt LONGTEXT NOT NULL,
            image_path TEXT NULL,
            image_url TEXT NULL,
            image_attachment_id BIGINT UNSIGNED NULL,
            status VARCHAR(24) NOT NULL DEFAULT 'private',
            aspect_ratio VARCHAR(24) NOT NULL DEFAULT 'square',
            model_name VARCHAR(190) NULL,
            ip_hash VARCHAR(128) NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            error_message TEXT NULL,
            PRIMARY KEY (id),
            KEY project_status (project_slug,status),
            KEY created_at (created_at)
        ) $charset;");
    }

    public function __construct() {
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_post_pai_save_settings', array($this, 'save_settings'));
        add_action('admin_post_pai_save_project', array($this, 'save_project'));
        add_action('admin_post_pai_moderate_image', array($this, 'moderate_image'));
        add_action('admin_post_pai_clear_logs', array($this, 'clear_logs'));

        add_shortcode('portfolio_ai_generator', array($this, 'generator_shortcode'));
        add_shortcode('portfolio_ai_gallery', array($this, 'gallery_shortcode'));

        add_action('wp_ajax_pai_generate', array($this, 'ajax_generate'));
        add_action('wp_ajax_nopriv_pai_generate', array($this, 'ajax_generate'));
        add_action('wp_ajax_pai_submit_gallery', array($this, 'ajax_submit_gallery'));
        add_action('wp_ajax_nopriv_pai_submit_gallery', array($this, 'ajax_submit_gallery'));
    }

    public function admin_menu() {
        add_options_page(
            'Portfolio AI',
            'Portfolio AI',
            'manage_options',
            'portfolio-ai-generator',
            array($this, 'admin_page')
        );
    }

    private function projects() {
        $projects = get_option(self::OPT_PROJECTS, array());
        return is_array($projects) ? $projects : array();
    }

    private function project($slug) {
        $projects = $this->projects();
        if (!isset($projects[$slug])) {
            return null;
        }
        return wp_parse_args($projects[$slug], $this->default_project($slug));
    }

    private function default_project($slug = '') {
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

    public function admin_page() {
        if (!current_user_can('manage_options')) {
            wp_die('Permission denied.');
        }

        $tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'projects';

        echo '<div class="wrap">';
        echo '<h1>Portfolio AI Generator <small>v' . esc_html(self::VERSION) . '</small></h1>';
        echo '<h2 class="nav-tab-wrapper">';

        $tabs = array(
            'projects' => 'Projects',
            'settings' => 'API Settings',
            'moderation' => 'Moderation',
            'history' => 'History',
            'logs' => 'Debug Logs',
        );

        foreach ($tabs as $key => $label) {
            $active = $tab === $key ? ' nav-tab-active' : '';
            echo '<a class="nav-tab' . esc_attr($active) . '" href="' . esc_url(admin_url('options-general.php?page=portfolio-ai-generator&tab=' . $key)) . '">' . esc_html($label) . '</a>';
        }

        echo '</h2>';

        if ($tab === 'settings') {
            $this->settings_tab();
        } elseif ($tab === 'moderation') {
            $this->images_table("WHERE status = 'pending'");
        } elseif ($tab === 'history') {
            $this->images_table('');
        } elseif ($tab === 'logs') {
            $this->logs_tab();
        } else {
            $this->projects_tab();
        }

        echo '</div>';
    }

    private function settings_tab() {
        $provider = get_option(self::OPT_PROVIDER, 'custom_route');
        $endpoint = get_option(self::OPT_ENDPOINT_PATH, '/v1/images/generations');
        $endpoint_mode = get_option(self::OPT_ENDPOINT_MODE, 'auto');
        $auth_mode = get_option(self::OPT_AUTH_MODE, 'auto');
        $gemini_model = get_option(self::OPT_GEMINI_MODEL, 'gemini-2.5-flash-image');
        $gemini_limit = (int) get_option(self::OPT_GEMINI_PROMPT_LIMIT, 4000);
        if ($gemini_limit <= 0) {
            $gemini_limit = 4000;
        }
        ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('pai_save_settings'); ?>
            <input type="hidden" name="action" value="pai_save_settings">

            <table class="form-table"><tbody>
                <tr>
                    <th>Emergency disable</th>
                    <td><label><input type="checkbox" name="disabled" value="1" <?php checked(get_option(self::OPT_DISABLED)); ?>> Disable all public generations</label></td>
                </tr>
                <tr>
                    <th>Debug logging</th>
                    <td><label><input type="checkbox" name="debug" value="1" <?php checked(get_option(self::OPT_DEBUG)); ?>> Store safe request/response logs in the Debug Logs tab</label></td>
                </tr>
                <tr>
                    <th>Provider</th>
                    <td>
                        <select name="provider">
                            <?php
                            foreach (array('custom_route' => 'Custom Route', 'gemini_direct' => 'Gemini Direct') as $value => $label) {
                                echo '<option value="' . esc_attr($value) . '" ' . selected($provider, $value, false) . '>' . esc_html($label) . '</option>';
                            }
                            ?>
                        </select>
                        <p class="description">Gemini Direct calls Google Gemini from WordPress server-side. Custom Route keeps the existing LiteLLM/NVIDIA-style route.</p>
                    </td>
                </tr>
            </tbody></table>

            <h2>Gemini Direct Settings</h2>
            <table class="form-table"><tbody>
                <tr>
                    <th>Gemini API key</th>
                    <td>
                        <input class="regular-text" type="password" name="gemini_api_key" value="<?php echo esc_attr(get_option(self::OPT_GEMINI_API_KEY, '')); ?>" autocomplete="off">
                        <p class="description">Stored server-side only. Never exposed to browser JavaScript.</p>
                    </td>
                </tr>
                <tr>
                    <th>Gemini model</th>
                    <td>
                        <input class="regular-text" type="text" name="gemini_model" value="<?php echo esc_attr($gemini_model); ?>">
                        <p class="description">Default: gemini-2.5-flash-image</p>
                    </td>
                </tr>
                <tr>
                    <th>Gemini prompt character limit</th>
                    <td>
                        <input type="number" min="200" max="12000" name="gemini_prompt_limit" value="<?php echo esc_attr((string) $gemini_limit); ?>">
                        <p class="description">Long hidden prompts can increase cost and failure rate. Default: 4000 characters.</p>
                    </td>
                </tr>
            </tbody></table>

            <h2>Custom Route Settings</h2>
            <table class="form-table"><tbody>
                <tr>
                    <th>Base URL</th>
                    <td>
                        <input class="regular-text" type="url" name="base_url" value="<?php echo esc_attr(get_option(self::OPT_BASE_URL, '')); ?>" <?php disabled(defined('PORTFOLIO_AI_LITELLM_BASE_URL')); ?>>
                        <p class="description">Example: https://litellm.hayfam.co.uk</p>
                    </td>
                </tr>
                <tr>
                    <th>Endpoint path or full endpoint</th>
                    <td>
                        <input class="regular-text" type="text" name="endpoint_path" value="<?php echo esc_attr($endpoint); ?>">
                        <p class="description">Examples: /v1/images/generations, /nvidia-flux, or a full endpoint URL.</p>
                    </td>
                </tr>
                <tr>
                    <th>Endpoint mode</th>
                    <td>
                        <select name="endpoint_mode">
                            <?php
                            foreach (array('auto' => 'Auto detect', 'openai' => 'OpenAI-compatible images', 'nvidia_flux' => 'Custom image route') as $value => $label) {
                                echo '<option value="' . esc_attr($value) . '" ' . selected($endpoint_mode, $value, false) . '>' . esc_html($label) . '</option>';
                            }
                            ?>
                        </select>
                        <p class="description">Custom image route sends prompt, width, height, samples, steps, and seed.</p>
                    </td>
                </tr>
                <tr>
                    <th>Auth mode</th>
                    <td>
                        <select name="auth_mode">
                            <?php
                            foreach (array('auto' => 'Auto detect', 'bearer' => 'Bearer token', 'raw' => 'Raw Authorization value', 'none' => 'No Authorization header') as $value => $label) {
                                echo '<option value="' . esc_attr($value) . '" ' . selected($auth_mode, $value, false) . '>' . esc_html($label) . '</option>';
                            }
                            ?>
                        </select>
                        <p class="description">Use Raw if your working curl uses Authorization: sk-... instead of Authorization: Bearer sk-...</p>
                    </td>
                </tr>
                <tr>
                    <th>Custom route API key</th>
                    <td>
                        <input class="regular-text" type="password" name="api_key" value="<?php echo esc_attr(get_option(self::OPT_API_KEY, '')); ?>" <?php disabled(defined('PORTFOLIO_AI_LITELLM_API_KEY')); ?> autocomplete="off">
                        <p class="description">Stored server-side only. Never shown to visitors.</p>
                    </td>
                </tr>
            </tbody></table>

            <?php submit_button('Save settings'); ?>
        </form>
        <?php
    }

    private function logs_tab() {
        $logs = get_option(self::OPT_LOGS, array());
        if (!is_array($logs)) {
            $logs = array();
        }
        ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin:1em 0;">
            <?php wp_nonce_field('pai_clear_logs'); ?>
            <input type="hidden" name="action" value="pai_clear_logs">
            <?php submit_button('Clear logs', 'secondary', 'submit', false); ?>
        </form>
        <p>Logs intentionally exclude API keys, full hidden prompts, and image base64 data.</p>
        <table class="widefat striped">
            <thead><tr><th>Time</th><th>Level</th><th>Message</th><th>Data</th></tr></thead>
            <tbody>
                <?php
                if (!$logs) {
                    echo '<tr><td colspan="4">No logs yet.</td></tr>';
                }
                foreach (array_reverse($logs) as $log) {
                    echo '<tr>';
                    echo '<td>' . esc_html($log['time'] ?? '') . '</td>';
                    echo '<td>' . esc_html($log['level'] ?? '') . '</td>';
                    echo '<td>' . esc_html($log['message'] ?? '') . '</td>';
                    echo '<td><pre style="white-space:pre-wrap;max-width:760px;">' . esc_html(wp_json_encode($log['data'] ?? array(), JSON_PRETTY_PRINT)) . '</pre></td>';
                    echo '</tr>';
                }
                ?>
            </tbody>
        </table>
        <?php
    }

    private function projects_tab() {
        $projects = $this->projects();
        $edit = isset($_GET['edit']) ? sanitize_key(wp_unslash($_GET['edit'])) : '';
        $project = ($edit && isset($projects[$edit])) ? wp_parse_args($projects[$edit], $this->default_project($edit)) : $this->default_project();
        ?>
        <h2><?php echo $edit ? 'Edit project' : 'Add project'; ?></h2>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('pai_save_project'); ?>
            <input type="hidden" name="action" value="pai_save_project">
            <input type="hidden" name="original_slug" value="<?php echo esc_attr($edit); ?>">
            <table class="form-table"><tbody>
                <tr><th>Project name</th><td><input class="regular-text" name="name" value="<?php echo esc_attr($project['name']); ?>" required></td></tr>
                <tr><th>Project slug</th><td><input class="regular-text" name="slug" value="<?php echo esc_attr($project['slug']); ?>" required pattern="[a-z0-9_\-]+"><p class="description">Example: uk_grand_tour</p></td></tr>
                <tr><th>Enabled</th><td><label><input type="checkbox" name="enabled" value="1" <?php checked($project['enabled']); ?>> Allow public generation</label></td></tr>
                <tr><th>Hidden master prompt</th><td><textarea class="large-text code" rows="7" name="hidden_prompt"><?php echo esc_textarea($project['hidden_prompt']); ?></textarea></td></tr>
                <tr><th>Negative prompt</th><td><textarea class="large-text code" rows="3" name="negative_prompt"><?php echo esc_textarea($project['negative_prompt']); ?></textarea></td></tr>
                <tr><th>User prompt template</th><td><textarea class="large-text code" rows="4" name="user_template"><?php echo esc_textarea($project['user_template']); ?></textarea><p class="description">Use {{user_prompt}} and {{aspect_ratio}}.</p></td></tr>
                <tr><th>Public style summary</th><td><textarea class="large-text" rows="3" name="style_summary"><?php echo esc_textarea($project['style_summary']); ?></textarea></td></tr>
                <tr><th>Model name</th><td><input class="regular-text" name="model_name" value="<?php echo esc_attr($project['model_name']); ?>" required><p class="description">Used by Custom Route providers. Gemini Direct uses the global Gemini model setting.</p></td></tr>
                <tr><th>Reference image attachment ID</th><td><input type="number" min="0" name="reference_image_id" value="<?php echo esc_attr((string) $project['reference_image_id']); ?>"></td></tr>
                <tr><th>Aspect ratios</th><td><input class="regular-text" name="aspect_ratios" value="<?php echo esc_attr(implode(',', (array) $project['aspect_ratios'])); ?>"><p class="description">Allowed: square, landscape, portrait</p></td></tr>
                <tr><th>Daily limit per IP</th><td><input type="number" min="1" max="1000" name="daily_limit" value="<?php echo esc_attr((string) $project['daily_limit']); ?>"></td></tr>
                <tr><th>Gallery mode</th><td><select name="gallery_mode">
                    <?php
                    foreach (array('off' => 'Off', 'private' => 'Private only', 'pending' => 'Submit to pending', 'approved' => 'Auto approve on submit') as $value => $label) {
                        echo '<option value="' . esc_attr($value) . '" ' . selected($project['gallery_mode'], $value, false) . '>' . esc_html($label) . '</option>';
                    }
                    ?>
                </select></td></tr>
            </tbody></table>
            <?php submit_button($edit ? 'Update project' : 'Add project'); ?>
        </form>

        <h2>Configured projects</h2>
        <table class="widefat striped">
            <thead><tr><th>Name</th><th>Slug</th><th>Shortcodes</th><th>Actions</th></tr></thead>
            <tbody>
                <?php
                if (!$projects) {
                    echo '<tr><td colspan="4">No projects configured yet.</td></tr>';
                }
                foreach ($projects as $slug => $row) {
                    echo '<tr>';
                    echo '<td>' . esc_html($row['name']) . '</td>';
                    echo '<td><code>' . esc_html($slug) . '</code></td>';
                    echo '<td><code>[portfolio_ai_generator project="' . esc_attr($slug) . '"]</code><br><code>[portfolio_ai_gallery project="' . esc_attr($slug) . '"]</code></td>';
                    echo '<td><a class="button" href="' . esc_url(admin_url('options-general.php?page=portfolio-ai-generator&tab=projects&edit=' . rawurlencode($slug))) . '">Edit</a></td>';
                    echo '</tr>';
                }
                ?>
            </tbody>
        </table>
        <?php
    }

    private function images_table($where) {
        global $wpdb;
        $rows = $wpdb->get_results("SELECT * FROM " . self::table() . " $where ORDER BY created_at DESC LIMIT 100");
        ?>
        <table class="widefat striped">
            <thead><tr><th>Image</th><th>Project</th><th>Prompt</th><th>Status</th><th>Created</th><th>Error</th><th>Actions</th></tr></thead>
            <tbody>
                <?php
                if (!$rows) {
                    echo '<tr><td colspan="7">No images found.</td></tr>';
                }
                foreach ($rows as $row) {
                    echo '<tr>';
                    echo '<td>' . ($row->image_url ? '<img src="' . esc_url($row->image_url) . '" style="width:90px;height:auto" alt="">' : '') . '</td>';
                    echo '<td><code>' . esc_html($row->project_slug) . '</code></td>';
                    echo '<td>' . esc_html(wp_trim_words($row->user_prompt, 14)) . '</td>';
                    echo '<td>' . esc_html($row->status) . '</td>';
                    echo '<td>' . esc_html($row->created_at) . '</td>';
                    echo '<td>' . esc_html(wp_trim_words($row->error_message, 12)) . '</td>';
                    echo '<td>';
                    foreach (array('approved' => 'Approve', 'rejected' => 'Reject', 'deleted' => 'Delete') as $status => $label) {
                        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline-block">';
                        wp_nonce_field('pai_moderate_' . (int) $row->id);
                        echo '<input type="hidden" name="action" value="pai_moderate_image">';
                        echo '<input type="hidden" name="id" value="' . esc_attr((string) $row->id) . '">';
                        echo '<input type="hidden" name="status" value="' . esc_attr($status) . '">';
                        echo '<button class="button" type="submit">' . esc_html($label) . '</button>';
                        echo '</form> ';
                    }
                    echo '</td>';
                    echo '</tr>';
                }
                ?>
            </tbody>
        </table>
        <?php
    }

    public function save_settings() {
        if (!current_user_can('manage_options')) {
            wp_die('Permission denied.');
        }
        check_admin_referer('pai_save_settings');

        update_option(self::OPT_DISABLED, isset($_POST['disabled']) ? 1 : 0, false);
        update_option(self::OPT_DEBUG, isset($_POST['debug']) ? 1 : 0, false);

        $provider = sanitize_key(wp_unslash($_POST['provider'] ?? 'custom_route'));
        if (!in_array($provider, array('custom_route', 'gemini_direct'), true)) {
            $provider = 'custom_route';
        }
        update_option(self::OPT_PROVIDER, $provider, false);

        if (!defined('PORTFOLIO_AI_LITELLM_BASE_URL')) {
            update_option(self::OPT_BASE_URL, esc_url_raw(wp_unslash($_POST['base_url'] ?? '')), false);
        }
        if (!defined('PORTFOLIO_AI_LITELLM_API_KEY')) {
            update_option(self::OPT_API_KEY, sanitize_text_field(wp_unslash($_POST['api_key'] ?? '')), false);
        }

        update_option(self::OPT_ENDPOINT_PATH, sanitize_text_field(wp_unslash($_POST['endpoint_path'] ?? '/v1/images/generations')), false);

        $endpoint_mode = sanitize_key(wp_unslash($_POST['endpoint_mode'] ?? 'auto'));
        if (!in_array($endpoint_mode, array('auto', 'openai', 'nvidia_flux'), true)) {
            $endpoint_mode = 'auto';
        }
        update_option(self::OPT_ENDPOINT_MODE, $endpoint_mode, false);

        $auth_mode = sanitize_key(wp_unslash($_POST['auth_mode'] ?? 'auto'));
        if (!in_array($auth_mode, array('auto', 'bearer', 'raw', 'none'), true)) {
            $auth_mode = 'auto';
        }
        update_option(self::OPT_AUTH_MODE, $auth_mode, false);

        update_option(self::OPT_GEMINI_API_KEY, sanitize_text_field(wp_unslash($_POST['gemini_api_key'] ?? '')), false);
        update_option(self::OPT_GEMINI_MODEL, sanitize_text_field(wp_unslash($_POST['gemini_model'] ?? 'gemini-2.5-flash-image')), false);

        $limit = absint($_POST['gemini_prompt_limit'] ?? 4000);
        $limit = max(200, min(12000, $limit));
        update_option(self::OPT_GEMINI_PROMPT_LIMIT, $limit, false);

        wp_safe_redirect(admin_url('options-general.php?page=portfolio-ai-generator&tab=settings&updated=1'));
        exit;
    }

    public function save_project() {
        if (!current_user_can('manage_options')) {
            wp_die('Permission denied.');
        }
        check_admin_referer('pai_save_project');

        $projects = $this->projects();
        $original = sanitize_key(wp_unslash($_POST['original_slug'] ?? ''));
        $slug = sanitize_key(wp_unslash($_POST['slug'] ?? ''));

        if (!$slug) {
            wp_die('Project slug is required.');
        }

        if ($original && $original !== $slug) {
            unset($projects[$original]);
        }

        $raw_ratios = explode(',', wp_unslash($_POST['aspect_ratios'] ?? 'square'));
        $ratios = array_values(array_intersect(array('square', 'landscape', 'portrait'), array_map('sanitize_key', array_map('trim', $raw_ratios))));
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

        update_option(self::OPT_PROJECTS, $projects, false);
        wp_safe_redirect(admin_url('options-general.php?page=portfolio-ai-generator&tab=projects&updated=1'));
        exit;
    }

    public function moderate_image() {
        if (!current_user_can('manage_options')) {
            wp_die('Permission denied.');
        }

        global $wpdb;
        $id = absint($_POST['id'] ?? 0);
        check_admin_referer('pai_moderate_' . $id);

        $status = sanitize_key(wp_unslash($_POST['status'] ?? 'rejected'));
        if (!in_array($status, array('approved', 'rejected', 'deleted'), true)) {
            $status = 'rejected';
        }

        $wpdb->update(
            self::table(),
            array('status' => $status, 'updated_at' => current_time('mysql')),
            array('id' => $id),
            array('%s', '%s'),
            array('%d')
        );

        wp_safe_redirect(admin_url('options-general.php?page=portfolio-ai-generator&tab=moderation&updated=1'));
        exit;
    }

    public function clear_logs() {
        if (!current_user_can('manage_options')) {
            wp_die('Permission denied.');
        }
        check_admin_referer('pai_clear_logs');
        delete_option(self::OPT_LOGS);
        wp_safe_redirect(admin_url('options-general.php?page=portfolio-ai-generator&tab=logs&updated=1'));
        exit;
    }

    public function generator_shortcode($atts) {
        $atts = shortcode_atts(array('project' => ''), $atts);
        $slug = sanitize_key($atts['project']);
        $project = $this->project($slug);

        if (!$project || empty($project['enabled'])) {
            return current_user_can('manage_options') ? '<p>Portfolio AI project missing or disabled.</p>' : '';
        }

        $this->assets();
        $nonce = wp_create_nonce('pai_generate_' . $slug);

        ob_start();
        ?>
        <div class="pai-generator" data-project="<?php echo esc_attr($slug); ?>">
            <h3><?php echo esc_html('Create an image in the ' . $project['name'] . ' style'); ?></h3>
            <?php if (!empty($project['style_summary'])) : ?>
                <p><?php echo esc_html($project['style_summary']); ?></p>
            <?php endif; ?>
            <form class="pai-generator__form">
                <input type="hidden" name="nonce" value="<?php echo esc_attr($nonce); ?>">
                <input type="hidden" name="project" value="<?php echo esc_attr($slug); ?>">
                <label class="pai-label">Describe the image</label>
                <textarea name="prompt" maxlength="500" required></textarea>
                <label class="pai-label">Aspect ratio</label>
                <select name="aspect_ratio">
                    <?php
                    foreach ((array) $project['aspect_ratios'] as $ratio) {
                        echo '<option value="' . esc_attr($ratio) . '">' . esc_html(ucfirst($ratio)) . '</option>';
                    }
                    ?>
                </select>
                <button class="pai-button" type="submit">Generate Image</button>
            </form>
            <div class="pai-status" aria-live="polite"></div>
            <div class="pai-result" hidden></div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function gallery_shortcode($atts) {
        $atts = shortcode_atts(array('project' => '', 'limit' => 24), $atts);
        $slug = sanitize_key($atts['project']);
        $limit = max(1, min(100, absint($atts['limit'])));
        $project = $this->project($slug);

        if (!$project) {
            return current_user_can('manage_options') ? '<p>Portfolio AI gallery project missing.</p>' : '';
        }

        $this->assets();

        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . self::table() . " WHERE project_slug=%s AND status='approved' ORDER BY created_at DESC LIMIT %d",
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

    public function ajax_generate() {
        try {
            $this->ajax_generate_inner();
        } catch (Throwable $e) {
            $this->log('fatal', 'Generate handler crashed', array(
                'error' => $e->getMessage(),
                'file' => basename($e->getFile()),
                'line' => $e->getLine(),
            ));
            wp_send_json_error(array('message' => 'Plugin error: ' . $e->getMessage()), 500);
        }
    }

    private function ajax_generate_inner() {
        $slug = sanitize_key(wp_unslash($_POST['project'] ?? ''));
        $this->log('info', 'Generate request received', array(
            'project' => $slug,
            'ajax_action' => sanitize_key(wp_unslash($_POST['action'] ?? '')),
        ));

        $nonce = sanitize_text_field(wp_unslash($_POST['nonce'] ?? ''));
        if (!$slug || !wp_verify_nonce($nonce, 'pai_generate_' . $slug)) {
            $this->log('error', 'Nonce check failed', array('project' => $slug));
            wp_send_json_error(array('message' => 'Security check failed. Refresh the page and try again.'), 403);
        }

        if (get_option(self::OPT_DISABLED)) {
            wp_send_json_error(array('message' => 'Generation is temporarily disabled.'), 403);
        }

        $project = $this->project($slug);
        if (!$project || empty($project['enabled'])) {
            wp_send_json_error(array('message' => 'Project unavailable.'), 404);
        }

        $user_prompt = trim(sanitize_textarea_field(wp_unslash($_POST['prompt'] ?? '')));
        $prompt_len = function_exists('mb_strlen') ? mb_strlen($user_prompt) : strlen($user_prompt);
        if ($prompt_len < 3 || $prompt_len > 500) {
            wp_send_json_error(array('message' => 'Prompt must be 3 to 500 characters.'), 400);
        }

        $ratio = sanitize_key(wp_unslash($_POST['aspect_ratio'] ?? 'square'));
        if (!in_array($ratio, (array) $project['aspect_ratios'], true)) {
            wp_send_json_error(array('message' => 'Invalid aspect ratio.'), 400);
        }

        if (!$this->rate_ok($slug, (int) $project['daily_limit'])) {
            wp_send_json_error(array('message' => 'Daily generation limit reached.'), 429);
        }

        $full_prompt = $this->compile_prompt($project, $user_prompt, $ratio);
        $provider = $this->provider();
        $model_name = $provider === 'gemini_direct' ? $this->gemini_model() : $project['model_name'];

        global $wpdb;
        $now = current_time('mysql');
        $wpdb->insert(
            self::table(),
            array(
                'project_slug' => $slug,
                'user_prompt' => $user_prompt,
                'full_prompt' => $full_prompt,
                'status' => 'private',
                'aspect_ratio' => $ratio,
                'model_name' => $model_name,
                'ip_hash' => $this->ip_hash($slug),
                'created_at' => $now,
                'updated_at' => $now,
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );

        $id = (int) $wpdb->insert_id;
        $this->log('info', 'Generation started', array(
            'id' => $id,
            'project' => $slug,
            'provider' => $provider,
            'model' => $model_name,
            'aspect_ratio' => $ratio,
        ));

        $api_result = $this->call_image_api($project, $full_prompt, $ratio);
        if (is_wp_error($api_result)) {
            $wpdb->update(
                self::table(),
                array('status' => 'failed', 'error_message' => $api_result->get_error_message(), 'updated_at' => current_time('mysql')),
                array('id' => $id)
            );
            $this->log('error', 'Generation failed', array('id' => $id, 'error' => $api_result->get_error_message()));
            wp_send_json_error(array('message' => $api_result->get_error_message()), 500);
        }

        $saved = $this->save_image($api_result, $slug, $id);
        if (is_wp_error($saved)) {
            $wpdb->update(
                self::table(),
                array('status' => 'failed', 'error_message' => $saved->get_error_message(), 'updated_at' => current_time('mysql')),
                array('id' => $id)
            );
            $this->log('error', 'Image save failed', array('id' => $id, 'error' => $saved->get_error_message()));
            wp_send_json_error(array('message' => $saved->get_error_message()), 500);
        }

        $wpdb->update(
            self::table(),
            array(
                'image_url' => $saved['url'],
                'image_path' => $saved['path'],
                'image_attachment_id' => $saved['attachment_id'],
                'updated_at' => current_time('mysql'),
            ),
            array('id' => $id)
        );

        $this->log('info', 'Generation completed', array('id' => $id, 'image_url' => $saved['url']));

        wp_send_json_success(array(
            'id' => $id,
            'image_url' => esc_url_raw($saved['url']),
            'can_submit_gallery' => $project['gallery_mode'] !== 'off',
            'message' => 'Image generated successfully.',
        ));
    }

    public function ajax_submit_gallery() {
        $slug = sanitize_key(wp_unslash($_POST['project'] ?? ''));
        check_ajax_referer('pai_generate_' . $slug, 'nonce');

        $project = $this->project($slug);
        if (!$project || $project['gallery_mode'] === 'off') {
            wp_send_json_error(array('message' => 'Gallery submissions are disabled.'), 403);
        }

        $id = absint($_POST['id'] ?? 0);
        $status = $project['gallery_mode'] === 'approved' ? 'approved' : 'pending';

        global $wpdb;
        $ok = $wpdb->update(
            self::table(),
            array('status' => $status, 'updated_at' => current_time('mysql')),
            array('id' => $id, 'project_slug' => $slug, 'status' => 'private')
        );

        if (!$ok) {
            wp_send_json_error(array('message' => 'Could not submit this image.'), 400);
        }

        wp_send_json_success(array(
            'status' => $status,
            'message' => $status === 'approved' ? 'Image added to gallery.' : 'Image submitted for approval.',
        ));
    }

    private function call_image_api($project, $prompt, $ratio) {
        if ($this->provider() === 'gemini_direct') {
            return $this->call_gemini_direct($prompt, $ratio);
        }

        return $this->call_custom_route($project, $prompt, $ratio);
    }

    private function call_custom_route($project, $prompt, $ratio) {
        $base = defined('PORTFOLIO_AI_LITELLM_BASE_URL') ? PORTFOLIO_AI_LITELLM_BASE_URL : get_option(self::OPT_BASE_URL, '');
        $key = defined('PORTFOLIO_AI_LITELLM_API_KEY') ? PORTFOLIO_AI_LITELLM_API_KEY : get_option(self::OPT_API_KEY, '');
        $endpoint = trim((string) get_option(self::OPT_ENDPOINT_PATH, '/v1/images/generations'));
        $url = preg_match('#^https?://#i', $endpoint) ? $endpoint : untrailingslashit(trim($base)) . '/' . ltrim($endpoint ?: '/v1/images/generations', '/');

        if (!$url) {
            return new WP_Error('pai_config', 'Image generation endpoint is not configured.');
        }

        $mode = $this->endpoint_mode($endpoint);
        $body = $mode === 'nvidia_flux' ? $this->custom_image_body($prompt, $ratio) : $this->openai_image_body($project, $prompt, $ratio);

        $headers = array('Content-Type' => 'application/json');
        $auth = $this->auth_header($key, $endpoint);
        if ($auth) {
            $headers['Authorization'] = $auth;
        }

        $this->log('info', 'Calling image endpoint', array(
            'provider' => 'custom_route',
            'url' => $url,
            'endpoint_mode' => $mode,
            'auth_mode' => $this->auth_mode($endpoint),
            'body_fields' => array_keys($body),
        ));

        $response = wp_remote_post($url, array(
            'timeout' => 180,
            'headers' => $headers,
            'body' => wp_json_encode($body),
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $raw = wp_remote_retrieve_body($response);
        $json = json_decode($raw, true);

        $this->log($code >= 200 && $code < 300 ? 'info' : 'error', 'Image endpoint response', array(
            'provider' => 'custom_route',
            'status' => $code,
            'body_preview' => $this->safe_response_preview($raw),
        ));

        if ($code < 200 || $code >= 300) {
            return new WP_Error('pai_api', $this->api_error_message($json, 'Image API request failed with HTTP ' . $code . '. Check Debug Logs.'));
        }

        return $this->extract_image_from_response($json, $raw);
    }

    private function call_gemini_direct($prompt, $ratio) {
        $api_key = get_option(self::OPT_GEMINI_API_KEY, '');
        $model = $this->gemini_model();
        $limit = (int) get_option(self::OPT_GEMINI_PROMPT_LIMIT, 4000);
        if ($limit <= 0) {
            $limit = 4000;
        }

        if (!$api_key) {
            return new WP_Error('pai_gemini_config', 'Gemini API key is not configured.');
        }

        $prompt_len = function_exists('mb_strlen') ? mb_strlen($prompt) : strlen($prompt);
        if ($prompt_len > $limit) {
            return new WP_Error('pai_gemini_prompt_long', 'Prompt is too long for Gemini. Shorten the project prompt or user request.');
        }

        $dims = $this->dimensions($ratio);
        $gemini_prompt = $prompt . "\n\nCreate one finished image. No explanation. Target size: " . $dims[0] . 'x' . $dims[1] . '.';

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent';
        $body = array(
            'contents' => array(
                array(
                    'parts' => array(
                        array('text' => $gemini_prompt),
                    ),
                ),
            ),
        );

        $this->log('info', 'Calling image endpoint', array(
            'provider' => 'gemini_direct',
            'model' => $model,
            'prompt_length' => $prompt_len,
            'target_size' => $dims[0] . 'x' . $dims[1],
        ));

        $response = wp_remote_post($url, array(
            'timeout' => 180,
            'headers' => array(
                'x-goog-api-key' => $api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode($body),
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $raw = wp_remote_retrieve_body($response);
        $json = json_decode($raw, true);

        $this->log($code >= 200 && $code < 300 ? 'info' : 'error', 'Image endpoint response', array(
            'provider' => 'gemini_direct',
            'status' => $code,
            'body_preview' => $this->safe_response_preview($raw),
        ));

        if ($code < 200 || $code >= 300) {
            return new WP_Error('pai_gemini_api', $this->api_error_message($json, 'Gemini API request failed with HTTP ' . $code . '. Check Debug Logs.'));
        }

        $image = $this->extract_gemini_image($json);
        if (is_wp_error($image)) {
            $text_preview = $this->extract_gemini_text_preview($json);
            if ($text_preview) {
                $this->log('error', 'Gemini returned text but no image', array('text_preview' => $text_preview));
            }
            return $image;
        }

        return $image;
    }

    private function openai_image_body($project, $prompt, $ratio) {
        $body = array(
            'model' => $project['model_name'],
            'prompt' => $prompt,
            'n' => 1,
            'size' => $this->size($ratio),
            'response_format' => 'url',
        );

        if (!empty($project['negative_prompt'])) {
            $body['negative_prompt'] = $project['negative_prompt'];
        }

        if (!empty($project['reference_image_id'])) {
            $ref = wp_get_attachment_url((int) $project['reference_image_id']);
            if ($ref) {
                $body['reference_image_url'] = esc_url_raw($ref);
            }
        }

        return $body;
    }

    private function custom_image_body($prompt, $ratio) {
        $dims = $this->dimensions($ratio);
        return array(
            'prompt' => $prompt,
            'width' => $dims[0],
            'height' => $dims[1],
            'samples' => 1,
            'steps' => 4,
            'seed' => 0,
        );
    }

    private function extract_image_from_response($json, $raw) {
        if (is_array($json)) {
            if (!empty($json['data'][0]['url'])) {
                return array('url' => esc_url_raw($json['data'][0]['url']));
            }
            if (!empty($json['data'][0]['b64_json'])) {
                return array('b64_json' => $json['data'][0]['b64_json']);
            }
            if (!empty($json['url'])) {
                return array('url' => esc_url_raw($json['url']));
            }
            if (!empty($json['image_url'])) {
                return array('url' => esc_url_raw($json['image_url']));
            }
            if (!empty($json['b64_json'])) {
                return array('b64_json' => $json['b64_json']);
            }
            if (!empty($json['image'])) {
                return array('b64_json' => $json['image']);
            }
            if (!empty($json['images'][0]['url'])) {
                return array('url' => esc_url_raw($json['images'][0]['url']));
            }
            if (!empty($json['images'][0]['b64_json'])) {
                return array('b64_json' => $json['images'][0]['b64_json']);
            }
            if (!empty($json['images'][0]['base64'])) {
                return array('b64_json' => $json['images'][0]['base64']);
            }
            if (!empty($json['artifacts'][0]['base64'])) {
                return array('b64_json' => $json['artifacts'][0]['base64']);
            }
            if (!empty($json['output'][0]) && is_string($json['output'][0])) {
                return preg_match('#^https?://#', $json['output'][0])
                    ? array('url' => esc_url_raw($json['output'][0]))
                    : array('b64_json' => $json['output'][0]);
            }
        }

        if (substr((string) $raw, 0, 8) === "\x89PNG\r\n\x1a\n") {
            return array('binary' => $raw, 'mime' => 'image/png');
        }

        return new WP_Error('pai_no_image', 'Image API response did not contain a recognised image URL or base64 image. Check Debug Logs.');
    }

    private function extract_gemini_image($json) {
        if (!is_array($json)) {
            return new WP_Error('pai_gemini_no_json', 'Gemini returned an invalid response. Check Debug Logs.');
        }

        if (empty($json['candidates']) || !is_array($json['candidates'])) {
            return new WP_Error('pai_gemini_no_image', 'Gemini did not return an image. Check Debug Logs.');
        }

        foreach ($json['candidates'] as $candidate) {
            if (empty($candidate['content']['parts']) || !is_array($candidate['content']['parts'])) {
                continue;
            }

            foreach ($candidate['content']['parts'] as $part) {
                $inline = null;
                if (!empty($part['inlineData']) && is_array($part['inlineData'])) {
                    $inline = $part['inlineData'];
                } elseif (!empty($part['inline_data']) && is_array($part['inline_data'])) {
                    $inline = $part['inline_data'];
                }

                if ($inline && !empty($inline['data'])) {
                    return array('b64_json' => $inline['data']);
                }
            }
        }

        return new WP_Error('pai_gemini_no_image', 'Gemini did not return an image. Check Debug Logs.');
    }

    private function extract_gemini_text_preview($json) {
        if (!is_array($json) || empty($json['candidates']) || !is_array($json['candidates'])) {
            return '';
        }

        $pieces = array();
        foreach ($json['candidates'] as $candidate) {
            if (empty($candidate['content']['parts']) || !is_array($candidate['content']['parts'])) {
                continue;
            }
            foreach ($candidate['content']['parts'] as $part) {
                if (!empty($part['text']) && is_string($part['text'])) {
                    $pieces[] = $part['text'];
                }
            }
        }

        return substr(implode("\n", $pieces), 0, 600);
    }

    private function save_image($result, $slug, $id) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $filename = 'portfolio-ai-' . sanitize_file_name($slug) . '-' . (int) $id . '-' . time() . '.png';

        if (!empty($result['url'])) {
            $tmp = download_url($result['url'], 120);
            if (is_wp_error($tmp)) {
                return $tmp;
            }

            $file = array(
                'name' => $filename,
                'type' => 'image/png',
                'tmp_name' => $tmp,
                'error' => 0,
                'size' => filesize($tmp),
            );

            $attachment_id = media_handle_sideload($file, 0, null, array('post_title' => $filename));
            if (is_wp_error($attachment_id)) {
                @unlink($tmp);
                return $attachment_id;
            }

            return array(
                'attachment_id' => (int) $attachment_id,
                'url' => wp_get_attachment_url($attachment_id),
                'path' => get_attached_file($attachment_id) ?: '',
            );
        }

        if (!empty($result['b64_json'])) {
            $binary = base64_decode($result['b64_json'], true);
            if (!$binary) {
                return new WP_Error('pai_b64', 'Invalid base64 image.');
            }
            return $this->save_binary_image($binary, $filename);
        }

        if (!empty($result['binary'])) {
            return $this->save_binary_image($result['binary'], $filename);
        }

        return new WP_Error('pai_missing_image', 'No image data was available to save.');
    }

    private function save_binary_image($binary, $filename) {
        $upload = wp_upload_bits($filename, null, $binary);
        if (!empty($upload['error'])) {
            return new WP_Error('pai_upload', $upload['error']);
        }

        $attachment_id = wp_insert_attachment(
            array(
                'post_mime_type' => 'image/png',
                'post_title' => sanitize_file_name(pathinfo($filename, PATHINFO_FILENAME)),
                'post_status' => 'inherit',
            ),
            $upload['file']
        );

        if (!is_wp_error($attachment_id)) {
            wp_update_attachment_metadata($attachment_id, wp_generate_attachment_metadata($attachment_id, $upload['file']));
        }

        return array(
            'attachment_id' => (int) $attachment_id,
            'url' => $upload['url'],
            'path' => $upload['file'],
        );
    }

    private function compile_prompt($project, $user, $ratio) {
        $template = $project['user_template'] ?: 'Create an image based on: {{user_prompt}}';
        $user_section = str_replace(array('{{user_prompt}}', '{{aspect_ratio}}'), array($user, $ratio), $template);

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

    private function rate_ok($slug, $limit) {
        $key = 'pai_rate_' . $this->ip_hash($slug);
        $count = (int) get_transient($key);
        if ($count >= $limit) {
            return false;
        }
        set_transient($key, $count + 1, DAY_IN_SECONDS);
        return true;
    }

    private function ip_hash($slug) {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : 'unknown';
        return hash('sha256', $slug . '|' . gmdate('Y-m-d') . '|' . $ip . '|' . wp_salt('auth'));
    }

    private function provider() {
        $provider = get_option(self::OPT_PROVIDER, 'custom_route');
        return in_array($provider, array('custom_route', 'gemini_direct'), true) ? $provider : 'custom_route';
    }

    private function gemini_model() {
        $model = trim((string) get_option(self::OPT_GEMINI_MODEL, 'gemini-2.5-flash-image'));
        if (!$model) {
            $model = 'gemini-2.5-flash-image';
        }
        if (strpos($model, 'models/') === 0) {
            $model = substr($model, 7);
        }
        return $model;
    }

    private function size($ratio) {
        if ($ratio === 'landscape') {
            return '1792x1024';
        }
        if ($ratio === 'portrait') {
            return '1024x1792';
        }
        return '1024x1024';
    }

    private function dimensions($ratio) {
        if ($ratio === 'landscape') {
            return array(1344, 768);
        }
        if ($ratio === 'portrait') {
            return array(768, 1344);
        }
        return array(1024, 1024);
    }

    private function endpoint_mode($endpoint) {
        $mode = get_option(self::OPT_ENDPOINT_MODE, 'auto');
        if ($mode === 'auto') {
            return stripos((string) $endpoint, 'nvidia-flux') !== false ? 'nvidia_flux' : 'openai';
        }
        return in_array($mode, array('openai', 'nvidia_flux'), true) ? $mode : 'openai';
    }

    private function auth_mode($endpoint) {
        $mode = get_option(self::OPT_AUTH_MODE, 'auto');
        if ($mode === 'auto') {
            return stripos((string) $endpoint, 'nvidia-flux') !== false ? 'raw' : 'bearer';
        }
        return in_array($mode, array('bearer', 'raw', 'none'), true) ? $mode : 'bearer';
    }

    private function auth_header($key, $endpoint) {
        $key = trim((string) $key);
        $mode = $this->auth_mode($endpoint);
        if ($mode === 'none' || !$key) {
            return '';
        }
        return $mode === 'raw' ? $key : 'Bearer ' . $key;
    }

    private function api_error_message($json, $fallback) {
        if (is_array($json)) {
            if (!empty($json['error']['message'])) {
                return sanitize_text_field((string) $json['error']['message']);
            }
            if (!empty($json['detail'])) {
                return sanitize_text_field(is_string($json['detail']) ? $json['detail'] : wp_json_encode($json['detail']));
            }
        }
        return $fallback;
    }

    private function safe_response_preview($raw) {
        $json = json_decode((string) $raw, true);
        if (is_array($json)) {
            $json = $this->redact_large_image_data($json);
            return substr(wp_json_encode($json), 0, 1200);
        }
        return substr(wp_strip_all_tags((string) $raw), 0, 1200);
    }

    private function redact_large_image_data($value) {
        if (!is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $inner) {
            $key_lc = strtolower((string) $key);
            if (in_array($key_lc, array('base64', 'b64_json'), true)) {
                $value[$key] = '[image data redacted]';
            } elseif ($key_lc === 'data' && is_string($inner) && strlen($inner) > 200) {
                $value[$key] = '[large data redacted]';
            } elseif (is_array($inner)) {
                $value[$key] = $this->redact_large_image_data($inner);
            }
        }

        return $value;
    }

    private function assets() {
        wp_enqueue_style('pai-frontend', plugins_url('assets/css/pai-frontend.css', __FILE__), array(), self::VERSION);
        wp_enqueue_script('pai-frontend', plugins_url('assets/js/pai-frontend.js', __FILE__), array('jquery'), self::VERSION, true);
        wp_localize_script('pai-frontend', 'PortfolioAI', array('ajaxUrl' => admin_url('admin-ajax.php')));
    }

    private function log($level, $message, $data = array()) {
        if (!get_option(self::OPT_DEBUG)) {
            return;
        }

        $logs = get_option(self::OPT_LOGS, array());
        if (!is_array($logs)) {
            $logs = array();
        }

        $logs[] = array(
            'time' => current_time('mysql'),
            'level' => sanitize_key($level),
            'message' => sanitize_text_field($message),
            'data' => $this->safe_log_data($data),
        );

        $logs = array_slice($logs, -100);
        update_option(self::OPT_LOGS, $logs, false);
    }

    private function safe_log_data($data) {
        if (!is_array($data)) {
            return array();
        }

        foreach ($data as $key => $value) {
            $key_lc = strtolower((string) $key);
            if (strpos($key_lc, 'key') !== false || strpos($key_lc, 'authorization') !== false || strpos($key_lc, 'prompt') !== false || strpos($key_lc, 'base64') !== false || strpos($key_lc, 'b64') !== false) {
                $data[$key] = '[redacted]';
            } elseif (is_array($value)) {
                $data[$key] = $this->safe_log_data($value);
            } elseif (is_string($value)) {
                $data[$key] = substr($value, 0, 1200);
            }
        }

        return $data;
    }
}

register_activation_hook(__FILE__, array('Portfolio_AI_Generator', 'activate'));
add_action('plugins_loaded', function () {
    new Portfolio_AI_Generator();
});
