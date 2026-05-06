<?php
if (!defined('ABSPATH')) {
    exit;
}

final class PAI_Admin_Provider_Tests {
    const TRANSIENT = 'pai_provider_test_notice';

    public static function init() {
        add_action('admin_footer-settings_page_portfolio-ai-generator', array(__CLASS__, 'inject_test_buttons'));
        add_action('admin_post_pai_test_provider', array(__CLASS__, 'handle_test'));
        add_action('admin_notices', array(__CLASS__, 'render_notice'));
    }

    public static function inject_test_buttons() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'projects';
        if ($tab !== 'settings') {
            return;
        }

        $base = admin_url('admin-post.php?action=pai_test_provider');
        $providers = array(
            'openai_direct' => 'Test OpenAI connection',
            'gemini_direct' => 'Test Gemini connection',
            'custom_route' => 'Test Custom Route endpoint',
        );
        ?>
        <script>
        (function () {
            var buttons = <?php echo wp_json_encode(array_map(function ($provider, $label) use ($base) {
                return array(
                    'provider' => $provider,
                    'label' => $label,
                    'url' => wp_nonce_url(add_query_arg('provider', $provider, $base), 'pai_test_provider_' . $provider),
                );
            }, array_keys($providers), $providers)); ?>;

            var headings = Array.prototype.slice.call(document.querySelectorAll('h2'));
            buttons.forEach(function (item) {
                var targetText = item.provider === 'openai_direct' ? 'OpenAI Direct Settings' : item.provider === 'gemini_direct' ? 'Gemini Direct Settings' : 'Custom Route Settings';
                var heading = headings.find(function (h) { return h.textContent.trim() === targetText; });
                if (!heading || heading.dataset.paiProviderTestAdded === '1') {
                    return;
                }

                var p = document.createElement('p');
                p.innerHTML = '<a class="button button-secondary" href="' + item.url + '">' + item.label + '</a> <span class="description">Runs a small server-side check using the saved settings.</span>';
                heading.insertAdjacentElement('afterend', p);
                heading.dataset.paiProviderTestAdded = '1';
            });
        })();
        </script>
        <?php
    }

    public static function handle_test() {
        if (!current_user_can('manage_options')) {
            wp_die('Permission denied.');
        }

        $provider = sanitize_key(wp_unslash($_GET['provider'] ?? ''));
        if (!in_array($provider, array('openai_direct', 'gemini_direct', 'custom_route'), true)) {
            wp_die('Invalid provider.');
        }

        check_admin_referer('pai_test_provider_' . $provider);

        if ($provider === 'openai_direct') {
            $result = self::test_openai();
        } elseif ($provider === 'gemini_direct') {
            $result = self::test_gemini();
        } else {
            $result = self::test_custom_route();
        }

        set_transient(self::TRANSIENT, array(
            'provider' => $provider,
            'success' => !is_wp_error($result),
            'message' => is_wp_error($result) ? $result->get_error_message() : $result,
        ), 60);

        wp_safe_redirect(admin_url('options-general.php?page=portfolio-ai-generator&tab=settings'));
        exit;
    }

    public static function render_notice() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $notice = get_transient(self::TRANSIENT);
        if (!$notice || !is_array($notice)) {
            return;
        }
        delete_transient(self::TRANSIENT);

        $class = !empty($notice['success']) ? 'notice-success' : 'notice-error';
        echo '<div class="notice ' . esc_attr($class) . ' is-dismissible"><p><strong>Portfolio AI provider test:</strong> ' . esc_html($notice['message'] ?? '') . '</p></div>';
    }

    private static function test_openai() {
        $key = defined('PORTFOLIO_AI_OPENAI_API_KEY') ? PORTFOLIO_AI_OPENAI_API_KEY : get_option(PAI_Constants::OPT_OPENAI_API_KEY, '');
        $base = defined('PORTFOLIO_AI_OPENAI_BASE_URL') ? PORTFOLIO_AI_OPENAI_BASE_URL : get_option(PAI_Constants::OPT_OPENAI_BASE_URL, 'https://api.openai.com/v1');
        $key = trim((string) $key);
        $base = untrailingslashit(trim((string) $base));

        if ($key === '') {
            return new WP_Error('missing_openai_key', 'OpenAI API key is missing.');
        }
        if ($base === '') {
            $base = 'https://api.openai.com/v1';
        }

        $response = wp_remote_get($base . '/models', array(
            'timeout' => 20,
            'headers' => array('Authorization' => 'Bearer ' . $key),
        ));

        return self::http_result($response, 'OpenAI connection OK. Credentials were accepted by the models endpoint.');
    }

    private static function test_gemini() {
        $key = defined('PORTFOLIO_AI_GEMINI_API_KEY') ? PORTFOLIO_AI_GEMINI_API_KEY : get_option(PAI_Constants::OPT_GEMINI_API_KEY, '');
        $key = trim((string) $key);

        if ($key === '') {
            return new WP_Error('missing_gemini_key', 'Gemini API key is missing.');
        }

        $response = wp_remote_get('https://generativelanguage.googleapis.com/v1beta/models?key=' . rawurlencode($key), array('timeout' => 20));

        return self::http_result($response, 'Gemini connection OK. Credentials were accepted by the models endpoint.');
    }

    private static function test_custom_route() {
        $base = defined('PORTFOLIO_AI_LITELLM_BASE_URL') ? PORTFOLIO_AI_LITELLM_BASE_URL : get_option(PAI_Constants::OPT_BASE_URL, '');
        $key = defined('PORTFOLIO_AI_LITELLM_API_KEY') ? PORTFOLIO_AI_LITELLM_API_KEY : get_option(PAI_Constants::OPT_API_KEY, '');
        $endpoint = trim((string) get_option(PAI_Constants::OPT_ENDPOINT_PATH, '/v1/images/generations'));
        $url = self::custom_route_url($base, $endpoint);
        $key = trim((string) $key);

        if ($url === '') {
            return new WP_Error('missing_custom_route_endpoint', 'Custom Route endpoint is missing.');
        }

        $headers = array();
        $auth = self::custom_route_auth_header($key, $endpoint);
        if ($auth !== '') {
            $headers['Authorization'] = $auth;
        }

        $response = wp_remote_get($url, array(
            'timeout' => 20,
            'headers' => $headers,
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code === 401 || $code === 403) {
            return new WP_Error('custom_route_auth_failed', 'Custom Route endpoint rejected the configured authentication with HTTP ' . $code . '.');
        }

        if ($code === 404 || $code >= 500 || $code < 200) {
            return new WP_Error('custom_route_unreachable', 'Custom Route endpoint returned HTTP ' . $code . '.');
        }

        return 'Custom Route endpoint responded with HTTP ' . $code . '. This verifies endpoint reachability and auth mode; run a generation to verify provider-specific image payload support.';
    }

    private static function custom_route_url($base, $endpoint) {
        $endpoint = trim((string) $endpoint);
        if (preg_match('#^https?://#i', $endpoint)) {
            return esc_url_raw($endpoint);
        }

        $base = untrailingslashit(trim((string) $base));
        if ($base === '') {
            return '';
        }

        return esc_url_raw($base . '/' . ltrim($endpoint ?: '/v1/images/generations', '/'));
    }

    private static function custom_route_auth_header($key, $endpoint) {
        $key = trim((string) $key);
        $mode = self::custom_route_auth_mode($endpoint);
        if ($mode === 'none' || $key === '') {
            return '';
        }

        return $mode === 'raw' ? $key : 'Bearer ' . $key;
    }

    private static function custom_route_auth_mode($endpoint) {
        $mode = get_option(PAI_Constants::OPT_AUTH_MODE, 'auto');
        if ($mode === 'auto') {
            return stripos((string) $endpoint, 'nvidia-flux') !== false ? 'raw' : 'bearer';
        }

        return in_array($mode, array('bearer', 'raw', 'none'), true) ? $mode : 'bearer';
    }

    private static function http_result($response, $success_message) {
        if (is_wp_error($response)) {
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $json = json_decode($body, true);

        if ($code >= 200 && $code < 300) {
            return $success_message;
        }

        $message = $json['error']['message'] ?? $json['error']['status'] ?? ('HTTP ' . $code);
        return new WP_Error('provider_test_failed', 'Provider test failed: ' . sanitize_text_field((string) $message));
    }
}

PAI_Admin_Provider_Tests::init();
