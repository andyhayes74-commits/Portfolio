# Portfolio AI Generator

**Current stable version:** v1.5.0  
**Branch:** `main`  
**Project:** WordPress plugin for controlled AI image generation on portfolio project pages

Portfolio AI Generator lets visitors generate AI images inside a controlled project style. It is designed for creative portfolios, AI art projects, campaign demos, case studies, and interactive project pages where the site owner wants consistent outputs rather than completely open-ended prompting.

The plugin supports hidden project prompts, public style summaries, multi-provider image generation, WordPress Media Library saving, moderation, configurable galleries, reference images, and safe debug logging.

---

## Current status

`main` now contains the latest tested stable version:

```text
v1.5.0
```

v1.5.0 includes the v1.4.1 multi-provider system and adds deeper per-project gallery customisation.

Continue new work from `main`.

---

## Plugin folder

The plugin lives here:

```text
portfolio-ai-generator/
```

The detailed plugin README is here:

```text
portfolio-ai-generator/README.md
```

Use the plugin README for setup, provider configuration, shortcode usage, reference image guidance, gallery styling, testing, and rollback notes.

---

## Main features

- Project-specific hidden master prompts
- Public style summaries
- Visitor prompt input
- Backend-only generation format setting
- Multi-provider image generation
- OpenAI Direct provider
- Gemini Direct provider
- Custom Route provider for LiteLLM/NVIDIA-style routes
- Per-project provider selection
- Reference image attachment ID for Gemini and OpenAI
- Generated images saved to WordPress Media Library
- Gallery submission workflow
- Project-level gallery customisation
- Gallery image limit for latest approved images
- Auto-refresh gallery after approved submission
- Backend image actions: approve, reject, hide, soft delete
- Admin moderation
- History view
- Debug logs with redaction
- Daily generation limits
- Modular PHP structure for safer maintenance

---

## Provider support

### OpenAI Direct

Use for style-critical portfolio projects where matching the original ChatGPT/OpenAI image style matters.

Settings include:

```text
OpenAI credential
OpenAI base URL
OpenAI image model
OpenAI quality
```

Included model choices:

```text
gpt-image-1-mini
chatgpt-image-latest
gpt-image-1.5
gpt-image-1
```

When a project has a reference image attachment ID, OpenAI Direct sends that Media Library image through the OpenAI image edits endpoint.

### Gemini Direct

Use for Gemini image generation and reference-image testing. Gemini Direct can send the Media Library reference image as inline image data.

### Custom Route

Use for LiteLLM/NVIDIA-style routes or other OpenAI-compatible/custom endpoints.

---

## Reference image support

Gemini Direct sends the actual reference image bytes to Google.

OpenAI Direct supports reference-image generation by sending the selected Media Library image to the OpenAI image edits endpoint.

Debug logs should show this when OpenAI uses a reference image:

```text
request_mode: edit_with_reference
has_reference_image: true
```

---

## Gallery customisation in v1.5.0

Each project can now have its own gallery layout and visual style.

Project-level gallery controls include:

```text
Desktop columns
Tablet columns
Mobile columns
Gallery gap
Gallery max width
Gallery alignment
Gallery background colour
Image crop mode
Card background colour
Card text colour
Card border colour
Card border on/off
Card radius
Card padding
Card shadow
Caption position
Caption text size
Caption text colour
Caption overlay background
Caption prompt word limit
```

This allows different portfolio projects to have different gallery styles while using the same shortcode.

---

## Shortcodes

Generator:

```text
[portfolio_ai_generator project="uk_grand_tour"]
```

Gallery:

```text
[portfolio_ai_gallery project="uk_grand_tour"]
```

Gallery with overrides:

```text
[portfolio_ai_gallery project="uk_grand_tour" limit="8" caption="hide"]
```

Only approved images appear in the public gallery.

---

## v1.5.0 highlights

- Added per-project gallery layout and style controls.
- Added desktop, tablet, and mobile column controls.
- Added gallery gap, max width, and alignment controls.
- Added card background, text, border, radius, padding, and shadow controls.
- Added caption position, colour, overlay background, size, and word-limit controls.
- Added scoped inline gallery styles so Elementor/theme CSS is less likely to override project settings.
- Kept all v1.4.1 multi-provider generation features.

---

## v1.4.1 highlights included

- Added OpenAI Direct image provider.
- Added OpenAI settings for credential, base URL, image model, and quality.
- Added OpenAI Direct to the global default provider selector.
- Added per-project image provider selector.
- Added OpenAI reference-image support through the image edits endpoint.
- Fixed gallery shortcode empty-limit handling so galleries no longer collapse to one image.
- Kept Gemini Direct and Custom Route providers.
- Routed generation through the selected project provider.
- Preserved the shared Media Library, History, Moderation, and Gallery pipeline.

---

## Do not commit secrets

Do not commit provider credentials or server credentials to this repository.

Provider credentials should be entered in WordPress admin settings or stored in private server-side configuration.

---

## Testing checklist

Before treating a fresh install as stable, test:

- plugin activation
- project edit page loads
- Gallery Layout & Style section appears
- desktop/tablet/mobile columns save correctly
- column counts affect frontend gallery layout
- card background colour works
- card text colour works
- card border and border colour work
- card radius works
- card padding works
- card shadow works
- caption below image works
- caption overlay works
- gallery max width and alignment work
- gallery still shows latest approved images
- gallery auto-refresh still works
- OpenAI Direct still works
- OpenAI Direct reference-image generation still works
- Gemini Direct still works
- Custom Route still works if needed
- debug logs do not expose provider credentials

Keep `main` as the latest stable tested version.
