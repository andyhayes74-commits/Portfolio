# Portfolio AI Generator

**Version:** 1.4.0 candidate  
**Status:** Test branch, not yet merged into `main`  
**Plugin type:** WordPress image generation plugin for portfolio and project pages

Portfolio AI Generator lets visitors generate AI images inside a controlled project style. Each project can have hidden master prompts, public style descriptions, generation limits, gallery moderation, provider settings, optional reference image guidance, and configurable gallery display settings.

The plugin is designed for creative portfolios where consistency matters more than raw prompt freedom.

---

## What v1.4.0 includes

Version 1.4.0 builds on the stable v1.3.1 modular plugin.

### Main changes

- Removes the public frontend aspect-ratio selector.
- Adds backend-only generation format setting: portrait, square, or landscape.
- Uses faster showcase-friendly generation sizes.
- Adds per-project gallery display settings.
- Adds gallery image limit for latest N approved images.
- Adds thumbnail shape controls: square, portrait, landscape, natural.
- Adds thumbnail size controls: small, medium, large.
- Adds caption display controls: hidden, prompt excerpt, date, prompt and date.
- Adds card style controls: minimal, soft card, framed.
- Adds optional gallery download links.
- Adds AJAX gallery refresh endpoint.
- Auto-refreshes matching frontend galleries after approved submission.
- Adds backend Hide action to move images back to private.
- Keeps Delete as soft delete by setting status to `deleted`.
- Adds reference image thumbnail preview in project settings.

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

## Installation guide

### 1. Download or copy the plugin folder

Use the full `portfolio-ai-generator` folder from this branch:

```text
feature/portfolio-ai-generator-v1.4.0-gallery-styling
```

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

---

## Creating or editing a project

Go to:

```text
Settings → Portfolio AI → Projects
```

### Core project fields

#### Project name

The human-friendly name shown in admin and frontend headings.

#### Project slug

Used in shortcodes.

Example:

```text
uk_grand_tour
```

#### Hidden master prompt

The private style system for the project. Visitors do not see this.

#### Negative prompt

Optional. Use sparingly.

#### User prompt template

Controls how visitor input is inserted into the final prompt.

Default:

```text
Create a {{generation_format}} showcase image based on: {{user_prompt}}.
```

Supported placeholders:

```text
{{user_prompt}}
{{generation_format}}
{{aspect_ratio}}
```

`{{aspect_ratio}}` is kept for backwards compatibility and maps to the backend generation format.

#### Generation format

Backend-only setting. Visitors do not see this.

Options:

```text
Portrait - 768 × 1024
Square - 1024 × 1024
Landscape - 1024 × 768
```

Default:

```text
Portrait
```

#### Reference image attachment ID

Optional.

For Gemini Direct, this can be used as a visual reference image. Upload an image to the Media Library, open its details, find its attachment ID, and paste that ID here.

v1.4.0 shows a thumbnail preview if the attachment ID points to a valid image.

---

## Gallery display settings

v1.4.0 adds per-project gallery controls.

### Gallery image limit

Controls how many approved images appear publicly.

Example:

```text
12
```

The gallery displays the latest approved images first.

### Thumbnail shape

Options:

```text
Square
Portrait
Landscape
Natural
```

### Thumbnail size

Options:

```text
Small
Medium
Large
```

### Caption display

Options:

```text
Hide captions
Prompt excerpt
Date
Prompt and date
```

### Card style

Options:

```text
Minimal
Soft card
Framed
```

### Download button

Optional. Shows a download link on gallery cards.

### Auto-refresh gallery

When enabled, a matching gallery on the same page refreshes automatically after an image is submitted and approved.

If gallery mode is `Submit to pending`, the image will not appear until approved by an admin.

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

Displays approved generated images for that project using the project gallery settings.

### Gallery shortcode overrides

You can override some display settings per shortcode:

```text
[portfolio_ai_gallery project="uk_grand_tour" limit="8"]
```

```text
[portfolio_ai_gallery project="uk_grand_tour" limit="8" caption="hide" shape="portrait" size="large" download="yes"]
```

Only approved images appear in the public gallery.

---

## Backend image management

In:

```text
Settings → Portfolio AI → History
```

Available image actions:

```text
Approve
Reject
Hide
Delete
```

Meaning:

```text
Approve = show in public gallery
Reject = mark rejected
Hide = set back to private
Delete = soft delete, status becomes deleted
```

Delete does not permanently remove the Media Library file in v1.4.0.

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
5. The gallery image limit is high enough to include it.

---

## Debug logs

Debug logs are available here:

```text
Settings → Portfolio AI → Debug Logs
```

Logs intentionally avoid storing:

```text
API keys
full hidden prompts
base64 image data
```

Enable debug logging during setup, then disable it once the plugin is stable.

---

## Security notes

Do not commit API keys to GitHub.

Do not paste API keys into this README.

API keys should only be entered in WordPress admin settings or stored using server-side constants.

---

## Testing checklist for v1.4.0

Before merging v1.4.0 into `main`, test the following on WordPress:

- Plugin activates without fatal errors.
- API Settings page loads.
- Existing project settings still appear.
- Frontend generator no longer shows aspect-ratio dropdown.
- Backend generation format dropdown saves correctly.
- Gemini Direct generates an image.
- Custom Route generates an image if still needed.
- Generated image is saved to Media Library.
- Generated image appears in History.
- Submit to Gallery works.
- Auto-approved image appears in gallery after auto-refresh.
- Pending image does not appear until approved.
- Gallery limit works.
- Thumbnail shape settings work.
- Thumbnail size settings work.
- Caption settings work.
- Download link setting works.
- Hide action removes image from public gallery.
- Delete action soft-deletes image.
- Reference image preview appears in project settings.
- Debug logs populate when enabled.
- Debug logs do not expose API keys.

---

## Rollback plan

If v1.4.0 fails during testing, roll back to stable v1.3.1 on `main`.

If the site breaks after installing v1.4.0, disable the plugin by renaming its folder:

```bash
sudo mv /home/portfolio.hayfam.co.uk/public_html/wp-content/plugins/portfolio-ai-generator \
/home/portfolio.hayfam.co.uk/public_html/wp-content/plugins/portfolio-ai-generator-disabled
```

Then reinstall the stable v1.3.1 plugin folder from `main`.

---

## Known limitations

- Lightbox is not included yet.
- Masonry layout is not included yet.
- Album/taxonomy support is not included yet.
- Permanent Media Library deletion is not included yet.
- Provider-specific cost tracking is not included yet.
- Public release packaging is not complete yet.

---

## Current recommendation

Treat v1.4.0 as a test branch until it has been installed and tested on the live WordPress site.

Do not merge v1.4.0 into `main` until the testing checklist passes.
