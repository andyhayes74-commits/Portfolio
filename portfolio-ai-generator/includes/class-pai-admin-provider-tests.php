<?php
if (!defined('ABSPATH')) {
    exit;
}

final class PAI_Admin_Provider_Tests {
    public static function init() {
        add_action('admin_notices', array(__CLASS__, 'render_notice'));
    }

    public static function render_notice() {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (!isset($_GET['pai_provider_test'])) {
            return;
        }

        $provider = sanitize_key(wp_unslash($_GET['pai_provider_test']));

        $messages = array(
            'openai_direct' => 'OpenAI connection test completed. If image generation later fails, double-check billing access and selected model availability.',
            'gemini_direct' => 'Gemini connection test completed. If reference images fail later, verify the configured Gemini image model supports inline image input.',
            'custom_route' => 'Custom Route connection test completed. Verify your endpoint path and authentication mode match your external provider.',
        );

        if (!isset($messages[$provider])) {
            return;
        }

        echo '<div class="notice notice-success is-dismissible"><p><strong>Portfolio AI:</strong> ' . esc_html($messages[$provider]) . '</p></div>';
    }
}

PAI_Admin_Provider_Tests::init();
