<?php
if (!defined('ABSPATH')) {
    exit;
}

final class PAI_Generator {
    public function register() {
        add_shortcode('portfolio_ai_generator', array($this, 'shortcode'));
        add_action('wp_ajax_pai_generate', array($this, 'ajax_generate'));
        add_action('wp_ajax_nopriv_pai_generate', array($this, 'ajax_generate'));
    }

    public function shortcode($atts) {
        $atts = shortcode_atts(array('project' => ''), $atts);
        $slug = sanitize_key($atts['project']);
        $project = PAI_Projects::get($slug);

        if (!$project || empty($project['enabled'])) {
            return current_user_can('manage_options') ? '<p>Portfolio AI project missing or disabled.</p>' : '';
        }

        PAI_Plugin::assets();
        $nonce = wp_create_nonce('pai_generate_' . $slug);
        $format = $this->generation_format($project);

        $heading = trim((string) ($project['frontend_heading'] ?? ''));
        if ($heading === '') {
            $heading = 'Create an image in the ' . $project['name'] . ' style';
        }

        $description = trim((string) ($project['frontend_description'] ?? ''));
        if ($description === '') {
            $description = (string) ($project['style_summary'] ?? '');
        }

        $placeholder = trim((string) ($project['frontend_prompt_placeholder'] ?? ''));
        if ($placeholder === '') {
            $placeholder = 'Describe the image';
        }

        $button = trim((string) ($project['frontend_generate_button'] ?? ''));
        if ($button === '') {
            $button = 'Generate Image';
        }

        ob_start();
        ?>
        <div class="pai-generator" data-project="<?php echo esc_attr($slug); ?>">
            <h3><?php echo esc_html($heading); ?></h3>
            <?php if (!empty($description)) : ?>
                <p><?php echo esc_html($description); ?></p>
            <?php endif; ?>
            <form class="pai-generator__form">
                <input type="hidden" name="nonce" value="<?php echo esc_attr($nonce); ?>">
                <input type="hidden" name="project" value="<?php echo esc_attr($slug); ?>">
                <input type="hidden" name="generation_format" value="<?php echo esc_attr($format); ?>">
                <label class="pai-label"><?php echo esc_html($placeholder); ?></label>
                <textarea name="prompt" maxlength="500" placeholder="<?php echo esc_attr($placeholder); ?>" required></textarea>
                <button class="pai-button" type="submit"><?php echo esc_html($button); ?></button>
            </form>
            <div class="pai-status" aria-live="polite"></div>
            <div class="pai-result" hidden></div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function ajax_generate() {
        try {
            $this->ajax_generate_inner();
        } catch (Throwable $e) {
            $error_id = wp_generate_uuid4();
            PAI_Logger::log('fatal', 'Generate handler crashed', array(
                'error_id' => $error_id,
                'error' => $e->getMessage(),
                'file' => basename($e->getFile()),
                'line' => $e->getLine(),
            ));
            wp_send_json_error(array('message' => 'Unexpected plugin error. Please try again later. Ref: ' . $error_id), 500);
        }
    }

    private function ajax_generate_inner() {
        $slug = sanitize_key(wp_unslash($_POST['project'] ?? ''));

        PAI_Logger::log('info', 'Generate request received', array(
            'project' => $slug,
            'ajax_action' => sanitize_key(wp_unslash($_POST['action'] ?? '')),
        ));

        $nonce = sanitize_text_field(wp_unslash($_POST['nonce'] ?? ''));

        if (!$slug || !wp_verify_nonce($nonce, 'pai_generate_' . $slug)) {
            PAI_Logger::log('error', 'Nonce check failed', array('project' => $slug));
            wp_send_json_error(array('message' => 'Security check failed. Refresh the page and try again.'), 403);
        }

        if (get_option(PAI_Constants::OPT_DISABLED)) {
            wp_send_json_error(array('message' => 'Generation is temporarily disabled.'), 403);
        }

        $project = PAI_Projects::get($slug);

        if (!$project || empty($project['enabled'])) {
            wp_send_json_error(array('message' => 'Project unavailable.'), 404);
        }

        $user_prompt = trim(sanitize_textarea_field(wp_unslash($_POST['prompt'] ?? '')));
        $prompt_length = function_exists('mb_strlen') ? mb_strlen($user_prompt) : strlen($user_prompt);

        if ($prompt_length < 3 || $prompt_length > 500) {
            wp_send_json_error(array('message' => 'Prompt must be 3 to 500 characters.'), 400);
        }

        $guard = PAI_Relevance_Guard::check($project, $user_prompt);

        if (($guard['decision'] ?? 'allow') === 'reject') {
            $message = trim((string) ($project['relevance_rejection_message'] ?? ''));

            if ($message === '') {
                $message = 'That prompt does not fit this project.';
            }

            wp_send_json_error(array(
                'message' => $message,
                'reason' => $guard['reason'] ?? '',
            ), 400);
        }

        $format = $this->generation_format($project);

        if (!$this->rate_ok($slug, (int) $project['daily_limit'])) {
            wp_send_json_error(array('message' => 'Daily generation limit reached.'), 429);
        }

        $provider_name = PAI_Projects::resolve_provider($project);
        $model_name = $this->model_name_for_provider($provider_name, $project);
        $full_prompt = PAI_Projects::compile_prompt($project, $user_prompt, $format);
        $image_id = $this->insert_image_row($slug, $user_prompt, $full_prompt, $format, $model_name);

        PAI_Logger::log('info', 'Generation started', array(
            'id' => $image_id,
            'project' => $slug,
            'provider' => $provider_name,
            'model' => $model_name,
            'generation_format' => $format,
        ));

        $provider = $this->provider($provider_name);
        $api_result = $provider->generate($project, $full_prompt, $format);

        if (is_wp_error($api_result)) {
            $this->mark_failed($image_id, $api_result->get_error_message());

            PAI_Logger::log('error', 'Generation failed', array(
                'id' => $image_id,
                'provider' => $provider_name,
                'error' => $api_result->get_error_message(),
            ));

            wp_send_json_error(array('message' => $api_result->get_error_message()), 500);
        }

        $saved = PAI_Media::save_generated_image($api_result, $slug, $image_id);

        if (is_wp_error($saved)) {
            $this->mark_failed($image_id, $saved->get_error_message());

            PAI_Logger::log('error', 'Image save failed', array(
                'id' => $image_id,
                'provider' => $provider_name,
                'error' => $saved->get_error_message(),
            ));

            wp_send_json_error(array('message' => $saved->get_error_message()), 500);
        }

        global $wpdb;

        $wpdb->update(
            PAI_Constants::table(),
            array(
                'image_url' => $saved['url'],
                'image_path' => $saved['path'],
                'image_attachment_id' => $saved['attachment_id'],
                'updated_at' => current_time('mysql'),
            ),
            array('id' => $image_id),
            array('%s', '%s', '%d', '%s'),
            array('%d')
        );

        PAI_Logger::log('info', 'Generation completed', array(
            'id' => $image_id,
            'provider' => $provider_name,
            'image_url' => $saved['url'],
        ));

        wp_send_json_success(array(
            'id' => $image_id,
            'image_url' => esc_url_raw($saved['url']),
            'can_submit_gallery' => $project['gallery_mode'] !== 'off',
            'message' => 'Image generated successfully.',
        ));
    }

    private function provider($provider_name) {
        if ($provider_name === 'gemini_direct') {
            return new PAI_Provider_Gemini_Direct();
        }

        if ($provider_name === 'openai_direct') {
            return new PAI_Provider_OpenAI_Direct();
        }

        return new PAI_Provider_Custom_Route();
    }

    private function model_name_for_provider($provider_name, $project) {
        if ($provider_name === 'gemini_direct') {
            return get_option(PAI_Constants::OPT_GEMINI_MODEL, 'gemini-2.5-flash-image');
        }

        if ($provider_name === 'openai_direct') {
            return get_option(PAI_Constants::OPT_OPENAI_MODEL, 'gpt-image-1-mini');
        }

        return $project['model_name'];
    }

    private function generation_format($project) {
        $format = sanitize_key($project['generation_format'] ?? 'portrait');
        return in_array($format, PAI_Projects::allowed_generation_formats(), true) ? $format : 'portrait';
    }

    private function insert_image_row($slug, $user_prompt, $full_prompt, $format, $model_name) {
        global $wpdb;
        $now = current_time('mysql');

        $wpdb->insert(
            PAI_Constants::table(),
            array(
                'project_slug' => $slug,
                'user_prompt' => $user_prompt,
                'full_prompt' => $full_prompt,
                'status' => 'private',
                'aspect_ratio' => $format,
                'model_name' => $model_name,
                'ip_hash' => $this->ip_hash($slug),
                'created_at' => $now,
                'updated_at' => $now,
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );

        return (int) $wpdb->insert_id;
    }

    private function mark_failed($id, $message) {
        global $wpdb;

        $wpdb->update(
            PAI_Constants::table(),
            array(
                'status' => 'failed',
                'error_message' => $message,
                'updated_at' => current_time('mysql'),
            ),
            array('id' => (int) $id),
            array('%s', '%s', '%s'),
            array('%d')
        );
    }

    private function rate_ok($slug, $limit) {
        $limit = max(1, (int) $limit);
        $key = 'pai_rate_' . $this->ip_hash($slug);
        $count = (int) get_transient($key);

        if ($count >= $limit) {
            return false;
        }

        $expires = strtotime('tomorrow 00:00:00 UTC') - time();

        if ($expires < HOUR_IN_SECONDS || $expires > DAY_IN_SECONDS) {
            $expires = DAY_IN_SECONDS;
        }

        set_transient($key, $count + 1, $expires);

        return true;
    }

    private function ip_hash($slug) {
        $ip = $this->visitor_ip();
        return hash('sha256', $slug . '|' . gmdate('Y-m-d') . '|' . $ip . '|' . wp_salt('auth'));
    }

    private function visitor_ip() {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';

        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            $ip = '';
        }

        $trusted = array('127.0.0.1', '::1');

        if ($ip && in_array($ip, $trusted, true) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $forwarded = explode(',', sanitize_text_field(wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR'])));
            $candidate = trim($forwarded[0]);

            if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                return $candidate;
            }
        }

        return $ip ?: 'unknown';
    }
}
