<?php
if (!defined('ABSPATH')) {
    exit;
}

final class PAI_Admin {
    public function register() {
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_post_pai_save_settings', array($this, 'save_settings'));
        add_action('admin_post_pai_save_project', array($this, 'save_project'));
        add_action('admin_post_pai_moderate_image', array($this, 'moderate_image'));
        add_action('admin_post_pai_clear_logs', array($this, 'clear_logs'));
    }

    public function admin_menu() {
        add_options_page('Portfolio AI', 'Portfolio AI', 'manage_options', 'portfolio-ai-generator', array($this, 'admin_page'));
    }

    public function admin_page() {
        if (!current_user_can('manage_options')) {
            wp_die('Permission denied.');
        }

        $tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'projects';
        echo '<div class="wrap">';
        echo '<h1>Portfolio AI Generator <small>v' . esc_html(PAI_Constants::VERSION) . '</small></h1>';
        echo '<h2 class="nav-tab-wrapper">';

        foreach (array('projects' => 'Projects', 'settings' => 'API Settings', 'moderation' => 'Moderation', 'history' => 'History', 'logs' => 'Debug Logs') as $key => $label) {
            $active = $tab === $key ? ' nav-tab-active' : '';
            echo '<a class="nav-tab' . esc_attr($active) . '" href="' . esc_url(admin_url('options-general.php?page=portfolio-ai-generator&tab=' . $key)) . '">' . esc_html($label) . '</a>';
        }

        echo '</h2>';

        if ($tab === 'settings') {
            $this->settings_tab();
        } elseif ($tab === 'moderation') {
            $this->images_table('pending');
        } elseif ($tab === 'history') {
            $this->images_table('all');
        } elseif ($tab === 'logs') {
            $this->logs_tab();
        } else {
            $this->projects_tab();
        }

        echo '</div>';
    }

    private function settings_tab() {
        $provider = get_option(PAI_Constants::OPT_PROVIDER, 'custom_route');
        $endpoint = get_option(PAI_Constants::OPT_ENDPOINT_PATH, '/v1/images/generations');
        $endpoint_mode = get_option(PAI_Constants::OPT_ENDPOINT_MODE, 'auto');
        $auth_mode = get_option(PAI_Constants::OPT_AUTH_MODE, 'auto');
        $gemini_model = get_option(PAI_Constants::OPT_GEMINI_MODEL, 'gemini-2.5-flash-image');
        $gemini_limit = (int) get_option(PAI_Constants::OPT_GEMINI_PROMPT_LIMIT, 4000);
        $gemini_limit = $gemini_limit > 0 ? $gemini_limit : 4000;
        ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('pai_save_settings'); ?>
            <input type="hidden" name="action" value="pai_save_settings">
            <table class="form-table"><tbody>
                <tr><th>Emergency disable</th><td><label><input type="checkbox" name="disabled" value="1" <?php checked(get_option(PAI_Constants::OPT_DISABLED)); ?>> Disable all public generations</label></td></tr>
                <tr><th>Debug logging</th><td><label><input type="checkbox" name="debug" value="1" <?php checked(get_option(PAI_Constants::OPT_DEBUG)); ?>> Store safe request/response logs in the Debug Logs tab</label></td></tr>
                <tr><th>Provider</th><td><select name="provider">
                    <?php foreach (array('custom_route' => 'Custom Route', 'gemini_direct' => 'Gemini Direct') as $value => $label) echo '<option value="' . esc_attr($value) . '" ' . selected($provider, $value, false) . '>' . esc_html($label) . '</option>'; ?>
                </select><p class="description">Gemini Direct calls Google Gemini from WordPress server-side. Custom Route keeps the LiteLLM/NVIDIA-style route.</p></td></tr>
            </tbody></table>

            <h2>Gemini Direct Settings</h2>
            <table class="form-table"><tbody>
                <tr><th>Gemini API key</th><td><input class="regular-text" type="password" name="gemini_api_key" value="<?php echo esc_attr(get_option(PAI_Constants::OPT_GEMINI_API_KEY, '')); ?>" autocomplete="off"><p class="description">Stored server-side only. Never exposed to browser JavaScript.</p></td></tr>
                <tr><th>Gemini model</th><td><input class="regular-text" type="text" name="gemini_model" value="<?php echo esc_attr($gemini_model); ?>"><p class="description">Default: gemini-2.5-flash-image</p></td></tr>
                <tr><th>Gemini prompt character limit</th><td><input type="number" min="200" max="12000" name="gemini_prompt_limit" value="<?php echo esc_attr((string) $gemini_limit); ?>"><p class="description">Long hidden prompts can increase cost and failure rate. Default: 4000 characters.</p></td></tr>
            </tbody></table>

            <h2>Custom Route Settings</h2>
            <table class="form-table"><tbody>
                <tr><th>Base URL</th><td><input class="regular-text" type="url" name="base_url" value="<?php echo esc_attr(get_option(PAI_Constants::OPT_BASE_URL, '')); ?>" <?php disabled(defined('PORTFOLIO_AI_LITELLM_BASE_URL')); ?>><p class="description">Example: https://litellm.hayfam.co.uk</p></td></tr>
                <tr><th>Endpoint path or full endpoint</th><td><input class="regular-text" type="text" name="endpoint_path" value="<?php echo esc_attr($endpoint); ?>"><p class="description">Examples: /v1/images/generations, /nvidia-flux, or a full endpoint URL.</p></td></tr>
                <tr><th>Endpoint mode</th><td><select name="endpoint_mode">
                    <?php foreach (array('auto' => 'Auto detect', 'openai' => 'OpenAI-compatible images', 'nvidia_flux' => 'Custom image route') as $value => $label) echo '<option value="' . esc_attr($value) . '" ' . selected($endpoint_mode, $value, false) . '>' . esc_html($label) . '</option>'; ?>
                </select><p class="description">Custom image route sends prompt, width, height, samples, steps, and seed.</p></td></tr>
                <tr><th>Auth mode</th><td><select name="auth_mode">
                    <?php foreach (array('auto' => 'Auto detect', 'bearer' => 'Bearer token', 'raw' => 'Raw Authorization value', 'none' => 'No Authorization header') as $value => $label) echo '<option value="' . esc_attr($value) . '" ' . selected($auth_mode, $value, false) . '>' . esc_html($label) . '</option>'; ?>
                </select><p class="description">Use Raw if your working curl uses Authorization: sk-... instead of Authorization: Bearer sk-...</p></td></tr>
                <tr><th>Custom route API key</th><td><input class="regular-text" type="password" name="api_key" value="<?php echo esc_attr(get_option(PAI_Constants::OPT_API_KEY, '')); ?>" <?php disabled(defined('PORTFOLIO_AI_LITELLM_API_KEY')); ?> autocomplete="off"><p class="description">Stored server-side only. Never shown to visitors.</p></td></tr>
            </tbody></table>
            <?php submit_button('Save settings'); ?>
        </form>
        <?php
    }

    private function projects_tab() {
        $projects = PAI_Projects::all();
        $edit = isset($_GET['edit']) ? sanitize_key(wp_unslash($_GET['edit'])) : '';
        $project = ($edit && isset($projects[$edit])) ? wp_parse_args($projects[$edit], PAI_Projects::defaults($edit)) : PAI_Projects::defaults();
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
                <tr><th>Reference image attachment ID</th><td><input type="number" min="0" name="reference_image_id" value="<?php echo esc_attr((string) $project['reference_image_id']); ?>"><p class="description">Gemini Direct can use this image as an inline visual reference.</p></td></tr>
                <tr><th>Aspect ratios</th><td><input class="regular-text" name="aspect_ratios" value="<?php echo esc_attr(implode(',', (array) $project['aspect_ratios'])); ?>"><p class="description">Allowed: square, landscape, portrait</p></td></tr>
                <tr><th>Daily limit per IP</th><td><input type="number" min="1" max="1000" name="daily_limit" value="<?php echo esc_attr((string) $project['daily_limit']); ?>"></td></tr>
                <tr><th>Gallery mode</th><td><select name="gallery_mode">
                    <?php foreach (array('off' => 'Off', 'private' => 'Private only', 'pending' => 'Submit to pending', 'approved' => 'Auto approve on submit') as $value => $label) echo '<option value="' . esc_attr($value) . '" ' . selected($project['gallery_mode'], $value, false) . '>' . esc_html($label) . '</option>'; ?>
                </select></td></tr>
            </tbody></table>
            <?php submit_button($edit ? 'Update project' : 'Add project'); ?>
        </form>
        <h2>Configured projects</h2>
        <table class="widefat striped"><thead><tr><th>Name</th><th>Slug</th><th>Shortcodes</th><th>Actions</th></tr></thead><tbody>
        <?php
        if (!$projects) echo '<tr><td colspan="4">No projects configured yet.</td></tr>';
        foreach ($projects as $slug => $row) {
            echo '<tr><td>' . esc_html($row['name']) . '</td><td><code>' . esc_html($slug) . '</code></td><td><code>[portfolio_ai_generator project="' . esc_attr($slug) . '"]</code><br><code>[portfolio_ai_gallery project="' . esc_attr($slug) . '"]</code></td><td><a class="button" href="' . esc_url(admin_url('options-general.php?page=portfolio-ai-generator&tab=projects&edit=' . rawurlencode($slug))) . '">Edit</a></td></tr>';
        }
        ?>
        </tbody></table>
        <?php
    }

    private function logs_tab() {
        $logs = PAI_Logger::all();
        ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin:1em 0;">
            <?php wp_nonce_field('pai_clear_logs'); ?>
            <input type="hidden" name="action" value="pai_clear_logs">
            <?php submit_button('Clear logs', 'secondary', 'submit', false); ?>
        </form>
        <p>Logs intentionally exclude API keys, full hidden prompts, and image base64 data.</p>
        <table class="widefat striped"><thead><tr><th>Time</th><th>Level</th><th>Message</th><th>Data</th></tr></thead><tbody>
        <?php
        if (!$logs) echo '<tr><td colspan="4">No logs yet.</td></tr>';
        foreach (array_reverse($logs) as $log) {
            echo '<tr><td>' . esc_html($log['time'] ?? '') . '</td><td>' . esc_html($log['level'] ?? '') . '</td><td>' . esc_html($log['message'] ?? '') . '</td><td><pre style="white-space:pre-wrap;max-width:760px;">' . esc_html(wp_json_encode($log['data'] ?? array(), JSON_PRETTY_PRINT)) . '</pre></td></tr>';
        }
        ?>
        </tbody></table>
        <?php
    }

    private function images_table($filter) {
        global $wpdb;
        $table = PAI_Constants::table();
        if ($filter === 'pending') {
            $query = $wpdb->prepare(
                "SELECT * FROM $table WHERE status = %s ORDER BY created_at DESC LIMIT %d",
                'pending',
                100
            );
        } else {
            $query = $wpdb->prepare(
                "SELECT * FROM $table ORDER BY created_at DESC LIMIT %d",
                100
            );
        }
        $rows = $wpdb->get_results($query);
        ?>
        <table class="widefat striped"><thead><tr><th>Image</th><th>Project</th><th>Prompt</th><th>Status</th><th>Created</th><th>Error</th><th>Actions</th></tr></thead><tbody>
        <?php
        if (!$rows) echo '<tr><td colspan="7">No images found.</td></tr>';
        foreach ($rows as $row) {
            echo '<tr>';
            echo '<td>' . ($row->image_url ? '<img src="' . esc_url($row->image_url) . '" style="width:90px;height:auto" alt="">' : '') . '</td>';
            echo '<td><code>' . esc_html($row->project_slug) . '</code></td>';
            echo '<td>' . esc_html(wp_trim_words($row->user_prompt, 14)) . '</td>';
            echo '<td>' . esc_html($row->status) . '</td>';
            echo '<td>' . esc_html($row->created_at) . '</td>';
            echo '<td>' . esc_html(wp_trim_words($row->error_message, 12)) . '</td><td>';
            foreach (array('approved' => 'Approve', 'rejected' => 'Reject', 'deleted' => 'Delete') as $status => $label) {
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline-block">';
                wp_nonce_field('pai_moderate_' . (int) $row->id);
                echo '<input type="hidden" name="action" value="pai_moderate_image"><input type="hidden" name="id" value="' . esc_attr((string) $row->id) . '"><input type="hidden" name="status" value="' . esc_attr($status) . '"><button class="button" type="submit">' . esc_html($label) . '</button></form> ';
            }
            echo '</td></tr>';
        }
        ?>
        </tbody></table>
        <?php
    }

    public function save_settings() {
        if (!current_user_can('manage_options')) wp_die('Permission denied.');
        check_admin_referer('pai_save_settings');

        update_option(PAI_Constants::OPT_DISABLED, isset($_POST['disabled']) ? 1 : 0, false);
        update_option(PAI_Constants::OPT_DEBUG, isset($_POST['debug']) ? 1 : 0, false);

        $provider = sanitize_key(wp_unslash($_POST['provider'] ?? 'custom_route'));
        update_option(PAI_Constants::OPT_PROVIDER, in_array($provider, array('custom_route', 'gemini_direct'), true) ? $provider : 'custom_route', false);

        if (!defined('PORTFOLIO_AI_LITELLM_BASE_URL')) update_option(PAI_Constants::OPT_BASE_URL, esc_url_raw(wp_unslash($_POST['base_url'] ?? '')), false);
        if (!defined('PORTFOLIO_AI_LITELLM_API_KEY')) update_option(PAI_Constants::OPT_API_KEY, sanitize_text_field(wp_unslash($_POST['api_key'] ?? '')), false);

        update_option(PAI_Constants::OPT_ENDPOINT_PATH, sanitize_text_field(wp_unslash($_POST['endpoint_path'] ?? '/v1/images/generations')), false);

        $endpoint_mode = sanitize_key(wp_unslash($_POST['endpoint_mode'] ?? 'auto'));
        update_option(PAI_Constants::OPT_ENDPOINT_MODE, in_array($endpoint_mode, array('auto', 'openai', 'nvidia_flux'), true) ? $endpoint_mode : 'auto', false);

        $auth_mode = sanitize_key(wp_unslash($_POST['auth_mode'] ?? 'auto'));
        update_option(PAI_Constants::OPT_AUTH_MODE, in_array($auth_mode, array('auto', 'bearer', 'raw', 'none'), true) ? $auth_mode : 'auto', false);

        update_option(PAI_Constants::OPT_GEMINI_API_KEY, sanitize_text_field(wp_unslash($_POST['gemini_api_key'] ?? '')), false);
        update_option(PAI_Constants::OPT_GEMINI_MODEL, sanitize_text_field(wp_unslash($_POST['gemini_model'] ?? 'gemini-2.5-flash-image')), false);
        update_option(PAI_Constants::OPT_GEMINI_PROMPT_LIMIT, max(200, min(12000, absint($_POST['gemini_prompt_limit'] ?? 4000))), false);

        wp_safe_redirect(admin_url('options-general.php?page=portfolio-ai-generator&tab=settings&updated=1'));
        exit;
    }

    public function save_project() {
        if (!current_user_can('manage_options')) wp_die('Permission denied.');
        check_admin_referer('pai_save_project');
        PAI_Projects::save_from_post();
        wp_safe_redirect(admin_url('options-general.php?page=portfolio-ai-generator&tab=projects&updated=1'));
        exit;
    }

    public function moderate_image() {
        if (!current_user_can('manage_options')) wp_die('Permission denied.');
        global $wpdb;
        $id = absint($_POST['id'] ?? 0);
        check_admin_referer('pai_moderate_' . $id);
        $status = sanitize_key(wp_unslash($_POST['status'] ?? 'rejected'));
        if (!in_array($status, array('approved', 'rejected', 'deleted'), true)) $status = 'rejected';
        $wpdb->update(PAI_Constants::table(), array('status' => $status, 'updated_at' => current_time('mysql')), array('id' => $id), array('%s', '%s'), array('%d'));
        wp_safe_redirect(admin_url('options-general.php?page=portfolio-ai-generator&tab=moderation&updated=1'));
        exit;
    }

    public function clear_logs() {
        if (!current_user_can('manage_options')) wp_die('Permission denied.');
        check_admin_referer('pai_clear_logs');
        PAI_Logger::clear();
        wp_safe_redirect(admin_url('options-general.php?page=portfolio-ai-generator&tab=logs&updated=1'));
        exit;
    }
}
