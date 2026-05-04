<div class="wrap">
<h1>Portfolio AI Settings</h1>
<form method="post"><?php wp_nonce_field('pai_save_settings'); ?>
<table class="form-table"><tr><th>LiteLLM Base URL</th><td><input type="url" name="pai_litellm_url" class="regular-text" value="<?php echo esc_attr($litellm_url); ?>" /></td></tr></table>
<p><button class="button button-primary" name="pai_save_settings" value="1">Save Settings</button></p></form>
<hr /><h2>Add / Update Project</h2>
<form method="post"><?php wp_nonce_field('pai_save_project'); ?><table class="form-table">
<tr><th>Name</th><td><input name="project_name" class="regular-text" required /></td></tr>
<tr><th>Slug</th><td><input name="project_slug" class="regular-text" required /></td></tr>
<tr><th>Hidden Prompt</th><td><textarea name="hidden_prompt" rows="4" class="large-text" required></textarea></td></tr>
<tr><th>Negative Prompt</th><td><textarea name="negative_prompt" rows="3" class="large-text"></textarea></td></tr>
<tr><th>User Prompt Template</th><td><textarea name="user_prompt_template" rows="3" class="large-text" placeholder="Create artwork of: {{user_prompt}}"></textarea></td></tr>
<tr><th>Reference Image ID</th><td><input name="reference_image_id" type="number" min="0" value="0" /></td></tr>
<tr><th>Model Name</th><td><input name="model_name" class="regular-text" required /></td></tr>
<tr><th>Aspect Ratios</th><td><label><input type="checkbox" name="aspect_ratios[]" value="square" checked /> square</label> <label><input type="checkbox" name="aspect_ratios[]" value="landscape" /> landscape</label> <label><input type="checkbox" name="aspect_ratios[]" value="portrait" /> portrait</label></td></tr>
<tr><th>Rate Limit / day / IP</th><td><input name="rate_limit" type="number" min="1" value="10" /></td></tr>
<tr><th>Require Approval</th><td><label><input name="require_approval" type="checkbox" value="1" checked /> submissions need moderation</label></td></tr>
</table><p><button class="button button-primary" name="pai_save_project" value="1">Save Project</button></p></form>
<h2>Projects</h2><table class="widefat"><thead><tr><th>Name</th><th>Slug</th><th>Model</th><th>Actions</th></tr></thead><tbody>
<?php foreach ($projects as $project): ?><tr><td><?php echo esc_html($project['name']); ?></td><td><?php echo esc_html($project['slug']); ?></td><td><?php echo esc_html($project['model_name']); ?></td><td><a href="<?php echo esc_url(wp_nonce_url(add_query_arg(array('pai_delete_project' => $project['slug'])), 'pai_delete_project')); ?>">Delete</a></td></tr><?php endforeach; ?>
</tbody></table></div>
