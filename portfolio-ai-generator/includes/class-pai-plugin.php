<?php
if (!defined('ABSPATH')) {
    exit;
}

final class PAI_Plugin {
    private static $instance = null;
    private $admin;
    private $generator;
    private $gallery;

    public static function init() {
        if (self::$instance === null) {
            self::$instance = new self();
            self::$instance->register();
        }
        return self::$instance;
    }

    public static function activate() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table = PAI_Constants::table();
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

    private function __construct() {
        $this->admin = new PAI_Admin();
        $this->generator = new PAI_Generator();
        $this->gallery = new PAI_Gallery();
    }

    private function register() {
        $this->admin->register();
        $this->generator->register();
        $this->gallery->register();
    }

    public static function assets() {
        wp_enqueue_style('pai-frontend', PAI_PLUGIN_URL . 'assets/css/pai-frontend.css', array(), PAI_Constants::VERSION);
        wp_enqueue_script('pai-frontend', PAI_PLUGIN_URL . 'assets/js/pai-frontend.js', array('jquery'), PAI_Constants::VERSION, true);
        wp_localize_script('pai-frontend', 'PortfolioAI', array('ajaxUrl' => admin_url('admin-ajax.php')));
    }
}
