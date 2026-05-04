<?php
/**
 * Plugin Name: Portfolio AI Generator
 * Description: V1 controlled AI image generator for portfolio project pages using LiteLLM, hidden project prompts, moderation, and galleries.
 * Version: 1.0.0
 * Author: Andy Hayes
 * Text Domain: portfolio-ai-generator
 */
if (!defined('ABSPATH')) exit;

final class Portfolio_AI_Generator {
    const VERSION = '1.0.0';
    const OPT_PROJECTS = 'pai_projects';
    const OPT_BASE_URL = 'pai_litellm_base_url';
    const OPT_API_KEY = 'pai_litellm_api_key';
    const OPT_DISABLED = 'pai_emergency_disabled';

    public static function table() { global $wpdb; return $wpdb->prefix . 'portfolio_ai_images'; }

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
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_post_pai_save_settings', [$this, 'save_settings']);
        add_action('admin_post_pai_save_project', [$this, 'save_project']);
        add_action('admin_post_pai_moderate_image', [$this, 'moderate_image']);
        add_shortcode('portfolio_ai_generator', [$this, 'generator_shortcode']);
        add_shortcode('portfolio_ai_gallery', [$this, 'gallery_shortcode']);
        add_action('wp_ajax_pai_generate', [$this, 'ajax_generate']);
        add_action('wp_ajax_nopriv_pai_generate', [$this, 'ajax_generate']);
        add_action('wp_ajax_pai_submit_gallery', [$this, 'ajax_submit_gallery']);
        add_action('wp_ajax_nopriv_pai_submit_gallery', [$this, 'ajax_submit_gallery']);
    }

    public function admin_menu() {
        add_options_page('Portfolio AI', 'Portfolio AI', 'manage_options', 'portfolio-ai-generator', [$this, 'admin_page']);
    }

    private function projects() { $p = get_option(self::OPT_PROJECTS, []); return is_array($p) ? $p : []; }
    private function project($slug) { $p = $this->projects(); return isset($p[$slug]) ? wp_parse_args($p[$slug], $this->default_project($slug)) : null; }
    private function default_project($slug = '') { return [
        'name' => '', 'slug' => $slug, 'enabled' => 1, 'hidden_prompt' => '', 'negative_prompt' => '',
        'user_template' => 'Create an image based on: {{user_prompt}}. Aspect ratio: {{aspect_ratio}}.',
        'style_summary' => '', 'model_name' => 'image-generation-model', 'reference_image_id' => 0,
        'aspect_ratios' => ['square','landscape','portrait'], 'daily_limit' => 20, 'gallery_mode' => 'pending'
    ]; }

    public function admin_page() {
        if (!current_user_can('manage_options')) wp_die('Permission denied.');
        $tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'projects';
        echo '<div class="wrap"><h1>Portfolio AI Generator</h1><h2 class="nav-tab-wrapper">';
        foreach (['projects'=>'Projects','settings'=>'LiteLLM Settings','moderation'=>'Moderation','history'=>'History'] as $key=>$label) {
            $active = $tab === $key ? ' nav-tab-active' : '';
            echo '<a class="nav-tab' . esc_attr($active) . '" href="' . esc_url(admin_url('options-general.php?page=portfolio-ai-generator&tab=' . $key)) . '">' . esc_html($label) . '</a>';
        }
        echo '</h2>';
        if ($tab === 'settings') $this->settings_tab();
        elseif ($tab === 'moderation') $this->images_table("WHERE status = 'pending'");
        elseif ($tab === 'history') $this->images_table('');
        else $this->projects_tab();
        echo '</div>';
    }

    private function settings_tab() { ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('pai_save_settings'); ?><input type="hidden" name="action" value="pai_save_settings">
            <table class="form-table"><tbody>
            <tr><th>Emergency disable</th><td><label><input type="checkbox" name="disabled" value="1" <?php checked(get_option(self::OPT_DISABLED)); ?>> Disable all public generations</label></td></tr>
            <tr><th>LiteLLM base URL</th><td><input class="regular-text" type="url" name="base_url" value="<?php echo esc_attr(get_option(self::OPT_BASE_URL,'')); ?>" <?php disabled(defined('PORTFOLIO_AI_LITELLM_BASE_URL')); ?>></td></tr>
            <tr><th>LiteLLM API key</th><td><input class="regular-text" type="password" name="api_key" value="<?php echo esc_attr(get_option(self::OPT_API_KEY,'')); ?>" <?php disabled(defined('PORTFOLIO_AI_LITELLM_API_KEY')); ?> autocomplete="off"></td></tr>
            </tbody></table><?php submit_button('Save settings'); ?>
        </form><?php
    }

    private function projects_tab() {
        $projects = $this->projects();
        $edit = isset($_GET['edit']) ? sanitize_key(wp_unslash($_GET['edit'])) : '';
        $p = $edit && isset($projects[$edit]) ? wp_parse_args($projects[$edit], $this->default_project($edit)) : $this->default_project(); ?>
        <h2><?php echo $edit ? 'Edit project' : 'Add project'; ?></h2>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('pai_save_project'); ?><input type="hidden" name="action" value="pai_save_project"><input type="hidden" name="original_slug" value="<?php echo esc_attr($edit); ?>">
            <table class="form-table"><tbody>
            <tr><th>Project name</th><td><input class="regular-text" name="name" value="<?php echo esc_attr($p['name']); ?>" required></td></tr>
            <tr><th>Project slug</th><td><input class="regular-text" name="slug" value="<?php echo esc_attr($p['slug']); ?>" required pattern="[a-z0-9_\-]+"><p class="description">Example: travel_posters</p></td></tr>
            <tr><th>Enabled</th><td><label><input type="checkbox" name="enabled" value="1" <?php checked($p['enabled']); ?>> Allow public generation</label></td></tr>
            <tr><th>Hidden master prompt</th><td><textarea class="large-text code" rows="7" name="hidden_prompt"><?php echo esc_textarea($p['hidden_prompt']); ?></textarea></td></tr>
            <tr><th>Negative prompt</th><td><textarea class="large-text code" rows="3" name="negative_prompt"><?php echo esc_textarea($p['negative_prompt']); ?></textarea></td></tr>
            <tr><th>User prompt template</th><td><textarea class="large-text code" rows="4" name="user_template"><?php echo esc_textarea($p['user_template']); ?></textarea><p class="description">Use {{user_prompt}} and {{aspect_ratio}}.</p></td></tr>
            <tr><th>Public style summary</th><td><textarea class="large-text" rows="3" name="style_summary"><?php echo esc_textarea($p['style_summary']); ?></textarea></td></tr>
            <tr><th>LiteLLM model</th><td><input class="regular-text" name="model_name" value="<?php echo esc_attr($p['model_name']); ?>" required></td></tr>
            <tr><th>Reference image attachment ID</th><td><input type="number" min="0" name="reference_image_id" value="<?php echo esc_attr((string) $p['reference_image_id']); ?>"></td></tr>
            <tr><th>Aspect ratios</th><td><input class="regular-text" name="aspect_ratios" value="<?php echo esc_attr(implode(',', (array)$p['aspect_ratios'])); ?>"><p class="description">Allowed: square, landscape, portrait</p></td></tr>
            <tr><th>Daily limit per IP</th><td><input type="number" min="1" max="1000" name="daily_limit" value="<?php echo esc_attr((string) $p['daily_limit']); ?>"></td></tr>
            <tr><th>Gallery mode</th><td><select name="gallery_mode"><?php foreach(['off'=>'Off','private'=>'Private only','pending'=>'Submit to pending','approved'=>'Auto approve on submit'] as $v=>$l) echo '<option value="'.esc_attr($v).'" '.selected($p['gallery_mode'],$v,false).'>'.esc_html($l).'</option>'; ?></select></td></tr>
            </tbody></table><?php submit_button($edit ? 'Update project' : 'Add project'); ?>
        </form>
        <h2>Configured projects</h2><table class="widefat striped"><thead><tr><th>Name</th><th>Slug</th><th>Shortcodes</th><th>Actions</th></tr></thead><tbody>
        <?php if (!$projects) echo '<tr><td colspan="4">No projects configured yet.</td></tr>'; foreach ($projects as $slug=>$row) : ?>
            <tr><td><?php echo esc_html($row['name']); ?></td><td><code><?php echo esc_html($slug); ?></code></td><td><code>[portfolio_ai_generator project="<?php echo esc_attr($slug); ?>"]</code><br><code>[portfolio_ai_gallery project="<?php echo esc_attr($slug); ?>"]</code></td><td><a class="button" href="<?php echo esc_url(admin_url('options-general.php?page=portfolio-ai-generator&tab=projects&edit=' . rawurlencode($slug))); ?>">Edit</a></td></tr>
        <?php endforeach; ?></tbody></table><?php
    }

    private function images_table($where) {
        global $wpdb; $rows = $wpdb->get_results("SELECT * FROM " . self::table() . " $where ORDER BY created_at DESC LIMIT 100"); ?>
        <table class="widefat striped"><thead><tr><th>Image</th><th>Project</th><th>Prompt</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead><tbody>
        <?php if (!$rows) echo '<tr><td colspan="6">No images found.</td></tr>'; foreach ($rows as $r) : ?>
            <tr><td><?php if ($r->image_url) echo '<img src="'.esc_url($r->image_url).'" style="width:90px;height:auto" alt="">'; ?></td><td><code><?php echo esc_html($r->project_slug); ?></code></td><td><?php echo esc_html(wp_trim_words($r->user_prompt, 14)); ?></td><td><?php echo esc_html($r->status); ?></td><td><?php echo esc_html($r->created_at); ?></td><td>
            <?php foreach(['approved'=>'Approve','rejected'=>'Reject','deleted'=>'Delete'] as $status=>$label) : ?><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block"><?php wp_nonce_field('pai_moderate_' . (int)$r->id); ?><input type="hidden" name="action" value="pai_moderate_image"><input type="hidden" name="id" value="<?php echo esc_attr((string)$r->id); ?>"><input type="hidden" name="status" value="<?php echo esc_attr($status); ?>"><button class="button" type="submit"><?php echo esc_html($label); ?></button></form><?php endforeach; ?>
            </td></tr><?php endforeach; ?></tbody></table><?php
    }

    public function save_settings() {
        if (!current_user_can('manage_options')) wp_die('Permission denied.');
        check_admin_referer('pai_save_settings');
        update_option(self::OPT_DISABLED, isset($_POST['disabled']) ? 1 : 0, false);
        if (!defined('PORTFOLIO_AI_LITELLM_BASE_URL')) update_option(self::OPT_BASE_URL, esc_url_raw(wp_unslash($_POST['base_url'] ?? '')), false);
        if (!defined('PORTFOLIO_AI_LITELLM_API_KEY')) update_option(self::OPT_API_KEY, sanitize_text_field(wp_unslash($_POST['api_key'] ?? '')), false);
        wp_safe_redirect(admin_url('options-general.php?page=portfolio-ai-generator&tab=settings&updated=1')); exit;
    }

    public function save_project() {
        if (!current_user_can('manage_options')) wp_die('Permission denied.');
        check_admin_referer('pai_save_project');
        $projects = $this->projects(); $original = sanitize_key(wp_unslash($_POST['original_slug'] ?? '')); $slug = sanitize_key(wp_unslash($_POST['slug'] ?? ''));
        if (!$slug) wp_die('Project slug is required.');
        if ($original && $original !== $slug) unset($projects[$original]);
        $ratios = array_values(array_intersect(['square','landscape','portrait'], array_map('sanitize_key', array_map('trim', explode(',', wp_unslash($_POST['aspect_ratios'] ?? 'square'))))));
        $gallery_mode = sanitize_key(wp_unslash($_POST['gallery_mode'] ?? 'pending')); if (!in_array($gallery_mode, ['off','private','pending','approved'], true)) $gallery_mode = 'pending';
        $projects[$slug] = [
            'name'=>sanitize_text_field(wp_unslash($_POST['name'] ?? '')), 'slug'=>$slug, 'enabled'=>isset($_POST['enabled']) ? 1 : 0,
            'hidden_prompt'=>sanitize_textarea_field(wp_unslash($_POST['hidden_prompt'] ?? '')), 'negative_prompt'=>sanitize_textarea_field(wp_unslash($_POST['negative_prompt'] ?? '')),
            'user_template'=>sanitize_textarea_field(wp_unslash($_POST['user_template'] ?? '')), 'style_summary'=>sanitize_textarea_field(wp_unslash($_POST['style_summary'] ?? '')),
            'model_name'=>sanitize_text_field(wp_unslash($_POST['model_name'] ?? '')), 'reference_image_id'=>absint($_POST['reference_image_id'] ?? 0),
            'aspect_ratios'=>$ratios ?: ['square'], 'daily_limit'=>max(1, min(1000, absint($_POST['daily_limit'] ?? 20))), 'gallery_mode'=>$gallery_mode
        ];
        update_option(self::OPT_PROJECTS, $projects, false);
        wp_safe_redirect(admin_url('options-general.php?page=portfolio-ai-generator&tab=projects&updated=1')); exit;
    }

    public function moderate_image() {
        if (!current_user_can('manage_options')) wp_die('Permission denied.');
        global $wpdb; $id = absint($_POST['id'] ?? 0); check_admin_referer('pai_moderate_' . $id);
        $status = sanitize_key(wp_unslash($_POST['status'] ?? 'rejected')); if (!in_array($status, ['approved','rejected','deleted'], true)) $status = 'rejected';
        $wpdb->update(self::table(), ['status'=>$status,'updated_at'=>current_time('mysql')], ['id'=>$id], ['%s','%s'], ['%d']);
        wp_safe_redirect(admin_url('options-general.php?page=portfolio-ai-generator&tab=moderation&updated=1')); exit;
    }

    public function generator_shortcode($atts) {
        $slug = sanitize_key(shortcode_atts(['project'=>''], $atts)['project']); $p = $this->project($slug);
        if (!$p || empty($p['enabled'])) return current_user_can('manage_options') ? '<p>Portfolio AI project missing or disabled.</p>' : '';
        $this->assets(); $nonce = wp_create_nonce('pai_generate_' . $slug); ob_start(); ?>
        <div class="pai-generator" data-project="<?php echo esc_attr($slug); ?>"><h3><?php echo esc_html('Create an image in the ' . $p['name'] . ' style'); ?></h3><?php if($p['style_summary']) echo '<p>'.esc_html($p['style_summary']).'</p>'; ?>
        <form class="pai-generator__form"><input type="hidden" name="nonce" value="<?php echo esc_attr($nonce); ?>"><input type="hidden" name="project" value="<?php echo esc_attr($slug); ?>"><label class="pai-label">Describe the image</label><textarea name="prompt" maxlength="500" required></textarea><label class="pai-label">Aspect ratio</label><select name="aspect_ratio"><?php foreach($p['aspect_ratios'] as $r) echo '<option value="'.esc_attr($r).'">'.esc_html(ucfirst($r)).'</option>'; ?></select><button class="pai-button" type="submit">Generate Image</button></form><div class="pai-status" aria-live="polite"></div><div class="pai-result" hidden></div></div>
        <?php return ob_get_clean();
    }

    public function gallery_shortcode($atts) {
        $atts = shortcode_atts(['project'=>'','limit'=>24], $atts); $slug = sanitize_key($atts['project']); $limit = max(1, min(100, absint($atts['limit']))); $p = $this->project($slug);
        if (!$p) return current_user_can('manage_options') ? '<p>Portfolio AI gallery project missing.</p>' : '';
        $this->assets(); global $wpdb; $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM " . self::table() . " WHERE project_slug=%s AND status='approved' ORDER BY created_at DESC LIMIT %d", $slug, $limit));
        ob_start(); echo '<div class="pai-gallery">'; if(!$rows) echo '<p>No approved generated images yet.</p>'; foreach($rows as $r) echo '<figure class="pai-gallery__item"><a href="'.esc_url($r->image_url).'" target="_blank" rel="noopener noreferrer"><img src="'.esc_url($r->image_url).'" alt="'.esc_attr(wp_trim_words($r->user_prompt, 10)).'" loading="lazy"></a><figcaption>'.esc_html(wp_trim_words($r->user_prompt, 10)).'</figcaption></figure>'; echo '</div>'; return ob_get_clean();
    }

    public function ajax_generate() {
        $slug = sanitize_key(wp_unslash($_POST['project'] ?? '')); check_ajax_referer('pai_generate_' . $slug, 'nonce');
        if (get_option(self::OPT_DISABLED)) wp_send_json_error(['message'=>'Generation is temporarily disabled.'], 403);
        $p = $this->project($slug); if (!$p || empty($p['enabled'])) wp_send_json_error(['message'=>'Project unavailable.'], 404);
        $user_prompt = trim(sanitize_textarea_field(wp_unslash($_POST['prompt'] ?? ''))); if (wp_strlen($user_prompt) < 3 || wp_strlen($user_prompt) > 500) wp_send_json_error(['message'=>'Prompt must be 3 to 500 characters.'], 400);
        $ratio = sanitize_key(wp_unslash($_POST['aspect_ratio'] ?? 'square')); if (!in_array($ratio, $p['aspect_ratios'], true)) wp_send_json_error(['message'=>'Invalid aspect ratio.'], 400);
        if (!$this->rate_ok($slug, (int)$p['daily_limit'])) wp_send_json_error(['message'=>'Daily generation limit reached.'], 429);
        $full = $this->compile_prompt($p, $user_prompt, $ratio); global $wpdb; $now = current_time('mysql');
        $wpdb->insert(self::table(), ['project_slug'=>$slug,'user_prompt'=>$user_prompt,'full_prompt'=>$full,'status'=>'private','aspect_ratio'=>$ratio,'model_name'=>$p['model_name'],'ip_hash'=>$this->ip_hash($slug),'created_at'=>$now,'updated_at'=>$now], ['%s','%s','%s','%s','%s','%s','%s','%s','%s']);
        $id = (int)$wpdb->insert_id; $api = $this->call_litellm($p, $full, $ratio);
        if (is_wp_error($api)) { $wpdb->update(self::table(), ['status'=>'failed','error_message'=>$api->get_error_message(),'updated_at'=>current_time('mysql')], ['id'=>$id]); wp_send_json_error(['message'=>$api->get_error_message()], 500); }
        $saved = $this->save_image($api, $slug, $id);
        if (is_wp_error($saved)) { $wpdb->update(self::table(), ['status'=>'failed','error_message'=>$saved->get_error_message(),'updated_at'=>current_time('mysql')], ['id'=>$id]); wp_send_json_error(['message'=>$saved->get_error_message()], 500); }
        $wpdb->update(self::table(), ['image_url'=>$saved['url'],'image_path'=>$saved['path'],'image_attachment_id'=>$saved['attachment_id'],'updated_at'=>current_time('mysql')], ['id'=>$id]);
        wp_send_json_success(['id'=>$id,'image_url'=>esc_url_raw($saved['url']),'can_submit_gallery'=>$p['gallery_mode'] !== 'off','message'=>'Image generated successfully.']);
    }

    public function ajax_submit_gallery() {
        $slug = sanitize_key(wp_unslash($_POST['project'] ?? '')); check_ajax_referer('pai_generate_' . $slug, 'nonce'); $p = $this->project($slug);
        if (!$p || $p['gallery_mode'] === 'off') wp_send_json_error(['message'=>'Gallery submissions are disabled.'], 403);
        $id = absint($_POST['id'] ?? 0); $status = $p['gallery_mode'] === 'approved' ? 'approved' : 'pending'; global $wpdb;
        $ok = $wpdb->update(self::table(), ['status'=>$status,'updated_at'=>current_time('mysql')], ['id'=>$id,'project_slug'=>$slug,'status'=>'private']);
        if (!$ok) wp_send_json_error(['message'=>'Could not submit this image.'], 400);
        wp_send_json_success(['status'=>$status,'message'=>$status === 'approved' ? 'Image added to gallery.' : 'Image submitted for approval.']);
    }

    private function call_litellm($p, $prompt, $ratio) {
        $base = defined('PORTFOLIO_AI_LITELLM_BASE_URL') ? PORTFOLIO_AI_LITELLM_BASE_URL : get_option(self::OPT_BASE_URL, '');
        $key = defined('PORTFOLIO_AI_LITELLM_API_KEY') ? PORTFOLIO_AI_LITELLM_API_KEY : get_option(self::OPT_API_KEY, '');
        $base = untrailingslashit(trim($base)); if (!$base || !$key) return new WP_Error('pai_config', 'LiteLLM base URL or API key is not configured.');
        $body = ['model'=>$p['model_name'], 'prompt'=>$prompt, 'n'=>1, 'size'=>$this->size($ratio), 'response_format'=>'url'];
        if (!empty($p['reference_image_id'])) { $ref = wp_get_attachment_url((int)$p['reference_image_id']); if ($ref) $body['reference_image_url'] = esc_url_raw($ref); }
        $res = wp_remote_post($base . '/v1/images/generations', ['timeout'=>120, 'headers'=>['Authorization'=>'Bearer '.$key, 'Content-Type'=>'application/json'], 'body'=>wp_json_encode($body)]);
        if (is_wp_error($res)) return $res; $code = (int)wp_remote_retrieve_response_code($res); $json = json_decode(wp_remote_retrieve_body($res), true);
        if ($code < 200 || $code >= 300) return new WP_Error('pai_api', isset($json['error']['message']) ? sanitize_text_field($json['error']['message']) : 'LiteLLM request failed.');
        if (!empty($json['data'][0]['url'])) return ['url'=>esc_url_raw($json['data'][0]['url'])];
        if (!empty($json['data'][0]['b64_json'])) return ['b64_json'=>$json['data'][0]['b64_json']];
        if (!empty($json['url'])) return ['url'=>esc_url_raw($json['url'])];
        return new WP_Error('pai_no_image', 'LiteLLM response did not contain an image.');
    }

    private function save_image($result, $slug, $id) {
        require_once ABSPATH.'wp-admin/includes/file.php'; require_once ABSPATH.'wp-admin/includes/media.php'; require_once ABSPATH.'wp-admin/includes/image.php';
        $filename = 'portfolio-ai-' . sanitize_file_name($slug) . '-' . (int)$id . '-' . time() . '.png';
        if (!empty($result['url'])) { $tmp = download_url($result['url'], 120); if (is_wp_error($tmp)) return $tmp; $file = ['name'=>$filename,'type'=>'image/png','tmp_name'=>$tmp,'error'=>0,'size'=>filesize($tmp)]; $aid = media_handle_sideload($file, 0, null, ['post_title'=>$filename]); if (is_wp_error($aid)) { @unlink($tmp); return $aid; } return ['attachment_id'=>(int)$aid,'url'=>wp_get_attachment_url($aid),'path'=>get_attached_file($aid) ?: '']; }
        if (!empty($result['b64_json'])) { $binary = base64_decode($result['b64_json'], true); if (!$binary) return new WP_Error('pai_b64', 'Invalid base64 image.'); $upload = wp_upload_bits($filename, null, $binary); if (!empty($upload['error'])) return new WP_Error('pai_upload', $upload['error']); $aid = wp_insert_attachment(['post_mime_type'=>'image/png','post_title'=>sanitize_file_name(pathinfo($filename, PATHINFO_FILENAME)),'post_status'=>'inherit'], $upload['file']); if (!is_wp_error($aid)) wp_update_attachment_metadata($aid, wp_generate_attachment_metadata($aid, $upload['file'])); return ['attachment_id'=>(int)$aid,'url'=>$upload['url'],'path'=>$upload['file']]; }
        return new WP_Error('pai_missing_image', 'No image data was available to save.');
    }

    private function compile_prompt($p, $user, $ratio) { $t = $p['user_template'] ?: 'Create an image based on: {{user_prompt}}'; $u = str_replace(['{{user_prompt}}','{{aspect_ratio}}'], [$user,$ratio], $t); $parts = []; if ($p['hidden_prompt']) $parts[] = "Project master prompt:\n" . $p['hidden_prompt']; $parts[] = "User request:\n" . $u; if ($p['negative_prompt']) $parts[] = "Avoid:\n" . $p['negative_prompt']; return implode("\n\n", $parts); }
    private function rate_ok($slug, $limit) { $key = 'pai_rate_' . $this->ip_hash($slug); $count = (int)get_transient($key); if ($count >= $limit) return false; set_transient($key, $count + 1, DAY_IN_SECONDS); return true; }
    private function ip_hash($slug) { $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : 'unknown'; return hash('sha256', $slug . '|' . gmdate('Y-m-d') . '|' . $ip . '|' . wp_salt('auth')); }
    private function size($ratio) { return $ratio === 'landscape' ? '1792x1024' : ($ratio === 'portrait' ? '1024x1792' : '1024x1024'); }
    private function assets() { wp_enqueue_style('pai-frontend', plugins_url('assets/css/pai-frontend.css', __FILE__), [], self::VERSION); wp_enqueue_script('pai-frontend', plugins_url('assets/js/pai-frontend.js', __FILE__), ['jquery'], self::VERSION, true); wp_localize_script('pai-frontend', 'PortfolioAI', ['ajaxUrl'=>admin_url('admin-ajax.php')]); }
}
register_activation_hook(__FILE__, ['Portfolio_AI_Generator','activate']);
add_action('plugins_loaded', function(){ new Portfolio_AI_Generator(); });
