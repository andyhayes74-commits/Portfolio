<?php
if (!defined('ABSPATH')) {
    exit;
}

final class PAI_Admin_Hardening {
    public static function init() {
        add_action('admin_init', array(__CLASS__, 'scrub_secret_fields_buffer'), 0);
        add_action('admin_post_pai_save_settings', array(__CLASS__, 'save_settings_safely'), 1);
    }

    public static function scrub_secret_fields_buffer() {
        if (!is_admin() || !current_user_can('manage_options')) {
            return;
        }

        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        if ($page !== 'portfolio-ai-generator') {
            return;
        }

        ob_start(array(__CLASS__, 'scrub_password_values'));
    }

    public static function scrub_password_values($html) {
        foreach (array('openai_api_key', 'gemini_api_key', 'api_key') as $name) {
            $pattern = '/(<input\b(?=[^>]*\btype=["\']password["\'])(?=[^>]*\bname=["\']' . preg_quote($name, '/') . '["\'])([^>]*?)\bvalue=["\'][^"\']*["\']([^>]*>)/i';
            $html = preg_replace($pattern, '$1$2value="" placeholder="Configured / leave blank to keep existing"$3', $html);
        }

        return $html;
    }

    public static function save_settings_safely() {
        if (!current_user_can('manage_options')) {
            wp_die('Permission denied.');
        }

        check_admin_referer('pai_save_settings');

        update_option(PAI_Constants::OPT_DISABLED, isset($_POST['disabled']) ? 1 : 0, false);
        update_option(PAI_Constants::OPT_DEBUG, isset($_POST['debug']) ? 1 : 0, false);

        $provider = sanitize_key(wp_unslash($_POST['provider'] ?? 'custom_route'));
        update_option(PAI_Constants::OPT_PROVIDER, in_array($provider, array('custom_route', 'gemini_direct', 'openai_direct'), true) ? $provider : 'custom_route', false);

        self::update_secret_if_present(PAI_Constants::OPT_OPENAI_API_KEY, 'openai_api_key', defined('PORTFOLIO_AI_OPENAI_API_KEY'));

        if (!defined('PORTFOLIO_AI_OPENAI_BASE_URL')) {
            update_option(PAI_Constants::OPT_OPENAI_BASE_URL, esc_url_raw(wp_unslash($_POST['openai_base_url'] ?? 'https://api.openai.com/v1')), false);
        }

        $openai_model = sanitize_text_field(wp_unslash($_POST['openai_model'] ?? 'gpt-image-1-mini'));
        update_option(PAI_Constants::OPT_OPENAI_MODEL, in_array($openai_model, array('gpt-image-1-mini', 'chatgpt-image-latest', 'gpt-image-1.5', 'gpt-image-1'), true) ? $openai_model : 'gpt-image-1-mini', false);

        $openai_quality = sanitize_key(wp_unslash($_POST['openai_quality'] ?? 'medium'));
        update_option(PAI_Constants::OPT_OPENAI_QUALITY, in_array($openai_quality, array('low', 'medium', 'high', 'auto'), true) ? $openai_quality : 'medium', false);

        if (!defined('PORTFOLIO_AI_LITELLM_BASE_URL')) {
            update_option(PAI_Constants::OPT_BASE_URL, esc_url_raw(wp_unslash($_POST['base_url'] ?? '')), false);
        }

        self::update_secret_if_present(PAI_Constants::OPT_API_KEY, 'api_key', defined('PORTFOLIO_AI_LITELLM_API_KEY'));

        update_option(PAI_Constants::OPT_ENDPOINT_PATH, sanitize_text_field(wp_unslash($_POST['endpoint_path'] ?? '/v1/images/generations')), false);

        $endpoint_mode = sanitize_key(wp_unslash($_POST['endpoint_mode'] ?? 'auto'));
        update_option(PAI_Constants::OPT_ENDPOINT_MODE, in_array($endpoint_mode, array('auto', 'openai', 'nvidia_flux'), true) ? $endpoint_mode : 'auto', false);

        $auth_mode = sanitize_key(wp_unslash($_POST['auth_mode'] ?? 'auto'));
        update_option(PAI_Constants::OPT_AUTH_MODE, in_array($auth_mode, array('auto', 'bearer', 'raw', 'none'), true) ? $auth_mode : 'auto', false);

        self::update_secret_if_present(PAI_Constants::OPT_GEMINI_API_KEY, 'gemini_api_key', defined('PORTFOLIO_AI_GEMINI_API_KEY'));
        update_option(PAI_Constants::OPT_GEMINI_MODEL, sanitize_text_field(wp_unslash($_POST['gemini_model'] ?? 'gemini-2.5-flash-image')), false);
        update_option(PAI_Constants::OPT_GEMINI_PROMPT_LIMIT, max(200, min(12000, absint($_POST['gemini_prompt_limit'] ?? 4000))), false);

        wp_safe_redirect(admin_url('options-general.php?page=portfolio-ai-generator&tab=settings&updated=1'));
        exit;
    }

    private static function update_secret_if_present($option, $post_key, $constant_defined) {
        if ($constant_defined) {
            return;
        }

        if (!isset($_POST[$post_key])) {
            return;
        }

        $value = trim(sanitize_text_field(wp_unslash($_POST[$post_key])));
        if ($value === '') {
            return;
        }

        update_option($option, $value, false);
    }
}

PAI_Admin_Hardening::init();
