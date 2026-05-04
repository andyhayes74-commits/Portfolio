# V1 Plugin Design for Portfolio AI Generator

## Overview

This plugin will let visitors generate images in the style of specific portfolio projects and will allow the site owner to moderate and display those images.  Each project (e.g. *Mid‑Century Travel Posters*) defines a *prompt template*, optional negative prompts and a reference image.  The plugin uses these templates to combine the visitor’s prompt with the hidden style context and sends the request to an AI image service (via a cost‑managed [litellm](https://github.com/BerriAI/litellm) instance).  Images are saved to the WordPress uploads folder and, after approval, displayed in a gallery.  The plugin keeps API keys and templates secure, follows WordPress security practices and limits daily generations to control costs.

## User‑facing features

Visitors interact with the generator through a **shortcode or Gutenberg block** inserted on a project page.  The block contains a prompt input and a *Generate* button.  Once generation is complete, the resulting image appears below the form with options to download or regenerate.  If gallery submissions are allowed, the visitor can submit the image for consideration.  A separate shortcode displays the curated gallery grid for that project, showing thumbnails that open in a lightbox with metadata.

## Admin / back‑end features

An admin settings page lets the site owner define **projects** and their parameters:

- *Project name and slug* – the slug is used in shortcodes (e.g. `travel_posters`).
- *Hidden prompt*, *negative prompt* and *user prompt template* – kept in the database and never exposed on the front end.  The hidden prompt provides style and continuity rules; the user template describes how to insert the visitor’s text.
- *Optional reference image* – stored via the Media Library; if the provider supports image‑to‑image generation, the plugin reads the file and sends it to litellm.
- *Model/provider settings* – the base URL of the litellm endpoint and the model name; litellm will internally manage provider API keys.
- *Aspect ratio options* – allowed formats (square, landscape, portrait) for each project.
- *Rate limit* – maximum generations per day or per IP to control costs.
- *Gallery moderation* – whether submissions require admin approval before appearing publicly.

The plugin creates a **custom database table** to store each generation.  The record stores the project slug, user prompt, the full compiled prompt sent to litellm, the generated image URL, status (private, pending, approved), timestamps and any model metadata.  When an image is approved, the plugin creates an attachment in WordPress so it appears in the Media Library.

## Architecture and workflow

1. **Front‑end submission** – A visitor enters a prompt and chooses an aspect ratio.  The plugin adds a WordPress nonce to the request to prevent CSRF.
2. **AJAX/REST handler** – The request hits a server‑side function that checks the nonce and rate limits, then sanitizes the prompt using `sanitize_text_field()`.  WordPress security guidelines emphasise validating and sanitising input【4950632485705†L181-L190】 before use and escaping output【4950632485705†L193-L201】.
3. **Prompt compilation** – The handler fetches the project’s hidden prompt, negative prompt, template and optional reference image.  It builds the full prompt to send to litellm.
4. **Call to litellm** – Using `wp_remote_post()`, the plugin sends the compiled prompt (and image if needed) to the configured litellm endpoint over HTTPS.  Best practices recommend using HTTPS and secure tokens when integrating third‑party APIs【4950632485705†L573-L601】.
5. **Saving the image** – Once litellm returns a URL or binary data for the generated image, the plugin downloads it and stores it in `wp-content/uploads/portfolio‑ai/{project}`.  A record is added to the custom table with status `private` or `pending`.
6. **Displaying the result** – The handler sends a JSON response back to the front‑end with the image URL and status.  The front‑end script updates the page to display the image and controls.
7. **Gallery management** – An admin screen lists pending images.  The admin can approve or reject each entry.  Approved images are marked as `approved`, and the gallery shortcode displays only approved items.

### Data and security considerations

- **Secrets storage** – API keys or tokens must never be hard‑coded in the plugin.  WordPress documentation recommends storing secrets outside the codebase, typically in `wp‑config.php` or in environment variables【291513125991635†L54-L65】.  The plugin will store the litellm endpoint in an option but rely on litellm to manage upstream keys.
- **Nonces and capability checks** – All AJAX requests use `wp_create_nonce()` and `check_ajax_referer()` to verify the request origin.  Administrative screens check user capabilities (e.g. `manage_options`) to ensure only authorised users modify settings.
- **Input validation** – All incoming data is validated and sanitised.  This prevents injection attacks and cross‑site scripting (XSS)【4950632485705†L157-L210】.
- **HTTPS** – The plugin makes remote API calls using `wp_remote_post()` with SSL verification to ensure encrypted communication【4950632485705†L573-L601】.
- **Rate limiting** – To avoid cost overruns, the plugin counts the number of generations per IP or per logged‑in user in a transient that resets daily.  If the limit is exceeded, the handler returns an error.
- **File uploads and downloads** – Generated images are saved using WordPress’s file functions (`wp_upload_bits()`, `wp_insert_attachment()`) and served via the local site, avoiding hotlinks to external services.
- **Updates and maintenance** – WordPress advises developers to keep their code up to date and to apply security updates regularly【826780489911356†L81-L94】.

## Implementation plan for version 1

1. **Initial scaffold** – Create `portfolio‑ai-generator.php` with plugin headers.  Register activation/deactivation hooks to create the custom table.
2. **Settings page** – Build an admin menu page under *Settings → Portfolio AI* that lists projects.  Use WordPress Settings API to register options securely.  Each project can be created or edited via a form that stores its hidden prompt, negative prompt, template image ID, aspect ratio options and rate limit.
3. **Data storage** – Create a custom database table (e.g. `wp_portfolio_ai_images`) with fields: `id`, `project_slug`, `user_prompt`, `full_prompt`, `image_path`, `status`, `created_at`, `model_name`, etc.  Use `dbDelta` to create the table.
4. **Shortcodes/blocks** – Register two shortcodes: `[portfolio_ai_generator project="slug"]` and `[portfolio_ai_gallery project="slug"]`.  Enqueue a front‑end JS file and CSS only on pages with the shortcode to avoid bloat (Freemius advises loading scripts only where needed【4950632485705†L649-L652】).  For Gutenberg, create equivalent blocks.
5. **AJAX/REST handlers** – Use `wp_ajax_nopriv_portfolio_ai_generate` and `wp_ajax_portfolio_ai_generate` to process generation requests.  Validate the nonce, check the rate limit, compile the prompt and call litellm.  Save the result and return JSON.  For gallery submissions, update the record status to `pending`.
6. **Gallery management** – Add a sub‑menu page listing pending images with thumbnails, prompts and approval buttons.  Use `check_admin_referer()` to secure actions.  Approved images update their status; rejected ones set status `rejected` and are optionally deleted.
7. **Frontend UI** – In the generator block, display the input box, aspect ratio selector and a *Generate* button.  Show a spinner while waiting for a response.  After generation, display the image with download and submit buttons.  Use JavaScript (ES5 for broad support) and WordPress’s `wp.ajax` API for requests.  Escape all output and attributes.
8. **Rate limiting** – Implement a simple count in a transient using `set_transient( 'pai_rate_' . $ip, $count, DAY_IN_SECONDS )`.  On each request, increment the counter and check against the project’s limit.  Optionally, integrate with a dedicated limiter service.
9. **Testing and review** – Test with multiple projects, ensure sanitisation, test negative prompts and template images.  Verify that nonces and capability checks work.  Review for cross‑site scripting, SQL injections, and insecure file operations.
10. **Documentation** – Document how to create projects, insert shortcodes, set up the litellm endpoint and moderate images.  Mention that API calls go through litellm to control provider costs and that only limited information is stored.

## Next steps

- **Future enhancements** – After V1, consider adding user accounts, likes, or categories in the gallery, supporting more providers or dynamic model selection.  Explore variations or style transfer options if the AI provider supports them.  Add a dashboard widget summarising daily usage.
- **Security hardening** – Evaluate using JSON Web Tokens (JWT) for authenticated API requests【4950632485705†L589-L605】.  Implement logging and alerts for suspicious activity.
- **Deployment** – Use environment variables or a secrets management system (Pantheon Secrets or a `.env` file) to store litellm endpoint credentials【291513125991635†L54-L65】.  Deploy to staging first and run code quality tools (e.g. PHP_CodeSniffer, PHPStan) as recommended【4950632485705†L656-L667】.

This design provides a robust foundation for a WordPress plugin that offers controlled AI image generation, hides proprietary prompts, and allows the portfolio owner to curate results.  By following WordPress security guidelines (sanitisation, escaping, nonces and capability checks) and storing secrets outside the codebase, the plugin minimises risks while showcasing advanced AI capabilities.
