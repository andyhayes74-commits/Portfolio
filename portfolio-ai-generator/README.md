# Portfolio AI Generator

**Version:** 1.3.1  
**Status:** Stable on `main`  
**Plugin type:** WordPress image generation plugin for portfolio and project pages

Portfolio AI Generator lets visitors generate AI images inside a controlled project style. Each project can have hidden master prompts, public style descriptions, generation limits, gallery moderation, provider settings, and optional reference image guidance.

The plugin is designed for creative portfolios where consistency matters more than raw prompt freedom.

---

## What v1.3.1 includes

Version 1.3.1 includes the v1.3 modular refactor plus reliability and security improvements.

### Main changes

- Replaces the single large PHP file with a modular file structure.
- Keeps the existing database table and stored generated images.
- Keeps the existing shortcodes.
- Keeps Gemini Direct image generation.
- Keeps Custom Route / LiteLLM-style image generation.
- Keeps project settings, moderation, history, and debug logs.
- Adds cleaner separation between admin logic, provider logic, media handling, gallery display, and generation.
- Adds Gemini reference image support through the existing reference image attachment ID field.
- Improves frontend image sizing for generated previews and gallery thumbnails.
- Hardens admin history/moderation query handling.
- Improves frontend AJAX error display.
- Reduces fatal error detail leakage to visitors.
- Improves rate-limit behaviour.
- Improves binary image save failure handling.

---

## Folder structure

```text
portfolio-ai-generator/
├── portfolio-ai-generator.php
├── README.md
├── CHANGELOG.md
├── includes/
│   ├── class-pai-admin.php
│   ├── class-pai-constants.php
│   ├── class-pai-gallery.php
│   ├── class-pai-generator.php
│   ├── class-pai-logger.php
│   ├── class-pai-media.php
│   ├── class-pai-plugin.php
│   ├── class-pai-projects.php
│   └── providers/
│       ├── class-pai-provider-custom-route.php
│       └── class-pai-provider-gemini-direct.php
└── assets/
    ├── css/
    │   └── pai-frontend.css
    └── js/
        └── pai-frontend.js
```

---

## File responsibilities

### `portfolio-ai-generator.php`

The plugin loader. It defines version constants, loads required files, registers the activation hook, and starts the plugin.

### `includes/class-pai-plugin.php`

Main plugin coordinator. It creates the admin, generator, and gallery objects, registers hooks, creates the database table on activation, and loads frontend assets.

### `includes/class-pai-constants.php`

Central location for version number, option names, and the generated image database table name.

### `includes/class-pai-admin.php`

Handles WordPress admin pages:

- Projects
- API Settings
- Moderation
- History
- Debug Logs

Also handles saving settings, saving projects, clearing logs, and moderating images.

### `includes/class-pai-projects.php`

Handles project defaults, project retrieval, project saving, and prompt compilation.

### `includes/class-pai-generator.php`

Handles the frontend generator shortcode and AJAX image generation.

Shortcode:

```text
[portfolio_ai_generator project="project_slug"]
```

### `includes/class-pai-gallery.php`

Handles the frontend gallery shortcode and gallery submission AJAX.

Shortcode:

```text
[portfolio_ai_gallery project="project_slug"]
```

### `includes/class-pai-media.php`

Handles saving generated images into the WordPress Media Library. Also prepares reference images for Gemini Direct.

### `includes/class-pai-logger.php`

Handles safe debug logging. Logs are designed to avoid storing API keys, full hidden prompts, or base64 image data.

### `includes/providers/class-pai-provider-gemini-direct.php`

Handles direct calls from WordPress to Google Gemini image generation.

### `includes/providers/class-pai-provider-custom-route.php`

Handles Custom Route image generation, including LiteLLM/NVIDIA-style routes and OpenAI-compatible image endpoints.

---

## Installation guide

### 1. Download or copy the plugin folder

Use the full `portfolio-ai-generator` folder from `main`.

Upload it to:

```text
wp-content/plugins/portfolio-ai-generator/
```

### 2. Check PHP syntax before activating

From the server, run:

```bash
sudo find /home/portfolio.hayfam.co.uk/public_html/wp-content/plugins/portfolio-ai-generator -name '*.php' -print0 | xargs -0 -n1 sudo php -l
```

Every file should return:

```text
No syntax errors detected
```

### 3. Activate the plugin

In WordPress admin:

```text
Plugins → Installed Plugins → Portfolio AI Generator → Activate
```

### 4. Open plugin settings

```text
Settings → Portfolio AI
```

---

## Provider setup

The plugin supports two provider modes.

### Gemini Direct

Gemini Direct calls Google Gemini directly from the WordPress server.

Use this for the main portfolio demo if Gemini is producing better images than the custom route.

Settings:

```text
Provider: Gemini Direct
Gemini API key: your Google AI Studio API key
Gemini model: gemini-2.5-flash-image
Gemini prompt character limit: 4000
Debug logging: enabled while testing
```

The API key is stored server-side and is not exposed to browser JavaScript.

### Custom Route

Custom Route keeps support for LiteLLM/NVIDIA-style image routes.

Example settings:

```text
Provider: Custom Route
Base URL: https://litellm.example.com
Endpoint path: /nvidia-flux
Endpoint mode: Custom image route
Auth mode: Raw Authorization value
Custom route API key: your key
```

This provider sends:

```text
prompt
width
height
samples
steps
seed
```

It also supports OpenAI-compatible image response shapes.

---

## Creating a project

Go to:

```text
Settings → Portfolio AI → Projects
```

Add or edit a project.

### Important project fields

#### Project name

The human-friendly name shown in admin and frontend headings.

Example:

```text
UK Grand Tour
```

#### Project slug

Used in shortcodes. Use lowercase letters, numbers, hyphens, or underscores.

Example:

```text
uk_grand_tour
```

#### Hidden master prompt

The private style system for the project. Visitors do not see this.

Use this to lock the creative style.

Example:

```text
Create a stylised mid-century travel poster illustration with Art Deco influence. Use flat geometry, clean lines, muted teal, sage, cream, warm orange, and soft blue. Preserve recognisable architecture while avoiding photorealism.
```

#### Negative prompt

Optional. Use sparingly.

Example:

```text
photorealism, clutter, logos, watermarks, messy text
```

#### User prompt template

Controls how visitor input is inserted into the final prompt.

Default:

```text
Create an image based on: {{user_prompt}}. Aspect ratio: {{aspect_ratio}}.
```

Supported placeholders:

```text
{{user_prompt}}
{{aspect_ratio}}
```

#### Public style summary

Shown on the frontend. This should describe the style without revealing the hidden master prompt.

#### Reference image attachment ID

Optional.

For Gemini Direct, this can be used as a visual reference image. Upload an image to the Media Library, open its details, find its attachment ID, and paste that ID here.

#### Aspect ratios

Comma-separated list:

```text
square,landscape,portrait
```

#### Daily limit per IP

Controls how many generations a visitor can make per project per day.

#### Gallery mode

Options:

```text
Off
Private only
Submit to pending
Auto approve on submit
```

Recommended while testing:

```text
Auto approve on submit
```

Recommended for a public site:

```text
Submit to pending
```

---

## Shortcodes

### Generator shortcode

```text
[portfolio_ai_generator project="uk_grand_tour"]
```

Displays the visitor prompt box and image generation UI.

### Gallery shortcode

```text
[portfolio_ai_gallery project="uk_grand_tour"]
```

Displays approved generated images for that project.

Only images with status `approved` appear in the public gallery.

---

## Gallery behaviour

Generated images are saved to the WordPress Media Library and recorded in the plugin database table.

Default flow:

```text
Generate image
→ image saved as private
→ visitor clicks Submit to Gallery
→ image becomes pending or approved depending on project settings
→ approved images appear in gallery shortcode
```

If the gallery is not showing a generated image, check:

1. The image was submitted to the gallery.
2. The project gallery mode is not Off.
3. The image status is Approved.
4. The shortcode project slug matches the generator project slug.

---

## Debug logs

Debug logs are available here:

```text
Settings → Portfolio AI → Debug Logs
```

Logs are useful while testing providers and prompt issues.

They intentionally avoid storing:

```text
API keys
full hidden prompts
base64 image data
```

Enable debug logging during setup, then disable it once the plugin is stable.

---

## Reference image guide

To use a reference image with Gemini Direct:

1. Go to WordPress Media Library.
2. Upload the reference image.
3. Open the image details.
4. Find the attachment ID from the URL or media details.
5. Paste that ID into the project field:

```text
Reference image attachment ID
```

When Gemini Direct is used, the plugin reads the image server-side and sends it as inline image data with the text prompt.

Use reference images for style consistency, composition guidance, or project visual identity.

Avoid using very large reference images if generation becomes slow. A smaller image is usually enough for style guidance.

---

## Security notes

Do not commit API keys to GitHub.

Do not paste API keys into this README.

API keys should only be entered in WordPress admin settings or stored using server-side constants.

Recommended:

```php
define('PORTFOLIO_AI_LITELLM_BASE_URL', 'https://example.com');
define('PORTFOLIO_AI_LITELLM_API_KEY', 'your-key');
```

Only use constants in private server config files, not in the public repository.

---

## Testing checklist

Before deploying a new plugin version, test the following on WordPress:

- Plugin activates without fatal errors.
- API Settings page loads.
- Existing project settings still appear.
- Gemini Direct generates an image.
- Generated image is saved to Media Library.
- Generated image appears in History.
- Submit to Gallery works.
- Approved images appear in gallery shortcode.
- Custom Route still works if selected.
- Debug logs populate when enabled.
- Debug logs do not expose API keys.
- Reference image attachment ID works with Gemini Direct.
- Gallery thumbnails display at a sensible size.
- Existing shortcodes still work.

---

## Rollback plan

If the plugin fails during testing, roll back to the previous stable version.

If the site breaks after installing a new version, disable the plugin by renaming its folder:

```bash
sudo mv /home/portfolio.hayfam.co.uk/public_html/wp-content/plugins/portfolio-ai-generator \
/home/portfolio.hayfam.co.uk/public_html/wp-content/plugins/portfolio-ai-generator-disabled
```

Then reinstall the last stable plugin folder.

---

## Known limitations

- Gallery styling is basic but improved from v1.2.0.
- Gallery auto-refresh is not included yet.
- Backend gallery personalisation settings are not included yet.
- Album/taxonomy support is not included yet.
- Provider-specific cost tracking is not included yet.
- Public release packaging is not complete yet.

---

## Suggested next versions

### v1.4.0 — Gallery Styling, Management and Auto Refresh

Planned features:

- Per-project gallery layout settings.
- Gallery image limit, for example latest 12 approved images.
- Backend image actions: approve, reject, hide, soft delete.
- Thumbnail size options.
- Caption on/off.
- Download button on/off.
- Crop mode options.
- Backend-only generation format dropdown.
- Auto-refresh gallery after image submission.

### v1.5.0 — Provider and Reference Image Polish

Planned features:

- Better provider diagnostics.
- Reference image preview in admin.
- Reference image size handling.
- Clearer prompt budget tools.

### v1.6.0 — Public Beta Packaging

Planned features:

- Public README.
- Screenshots.
- Privacy text.
- Setup wizard.
- WordPress.org compatibility review.

---

## Current recommendation

Use `main` as the latest stable plugin version.

Build new work on feature branches and merge only after testing on WordPress.
