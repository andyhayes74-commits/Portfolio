<?php
if (!defined('ABSPATH')) {
    exit;
}

final class PAI_Constants {
    const VERSION = '1.6.2';

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

    const OPT_OPENAI_API_KEY = 'pai_openai_api_key';
    const OPT_OPENAI_BASE_URL = 'pai_openai_base_url';
    const OPT_OPENAI_MODEL = 'pai_openai_model';
    const OPT_OPENAI_QUALITY = 'pai_openai_quality';

    const OPT_DISABLED = 'pai_emergency_disabled';
    const OPT_DEBUG = 'pai_debug_enabled';
    const OPT_LOGS = 'pai_debug_logs';

    public static function table() {
        global $wpdb;
        return $wpdb->prefix . 'portfolio_ai_images';
    }
}
