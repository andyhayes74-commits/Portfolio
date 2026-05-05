<?php
/**
 * Plugin Name: Portfolio AI Generator
 * Description: Controlled AI image generator for portfolio project pages with modular Gemini, OpenAI, and custom-route providers, hidden project prompts, moderation, highly customisable galleries, and safe debug logging.
 * Version: 1.5.0
 * Author: Andy Hayes
 * Text Domain: portfolio-ai-generator
 */

if (!defined('ABSPATH')) {
    exit;
}

define('PAI_VERSION', '1.5.0');
define('PAI_PLUGIN_FILE', __FILE__);
define('PAI_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PAI_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once PAI_PLUGIN_DIR . 'includes/class-pai-constants.php';
require_once PAI_PLUGIN_DIR . 'includes/class-pai-logger.php';
require_once PAI_PLUGIN_DIR . 'includes/class-pai-projects.php';
require_once PAI_PLUGIN_DIR . 'includes/class-pai-media.php';
require_once PAI_PLUGIN_DIR . 'includes/providers/class-pai-provider-custom-route.php';
require_once PAI_PLUGIN_DIR . 'includes/providers/class-pai-provider-gemini-direct.php';
require_once PAI_PLUGIN_DIR . 'includes/providers/class-pai-provider-openai-direct.php';
require_once PAI_PLUGIN_DIR . 'includes/class-pai-generator.php';
require_once PAI_PLUGIN_DIR . 'includes/class-pai-gallery.php';
require_once PAI_PLUGIN_DIR . 'includes/class-pai-admin.php';
require_once PAI_PLUGIN_DIR . 'includes/class-pai-plugin.php';

register_activation_hook(__FILE__, array('PAI_Plugin', 'activate'));
add_action('plugins_loaded', array('PAI_Plugin', 'init'));
