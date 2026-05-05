# Portfolio AI Generator

**Current stable version:** v1.4.1  
**Branch:** `main`  
**Project:** WordPress plugin for controlled AI image generation on portfolio project pages

Portfolio AI Generator lets visitors generate AI images inside a controlled project style. It is designed for creative portfolios, case studies, AI art projects, campaign demos, and interactive project pages where the site owner wants consistent results rather than completely open-ended prompting.

The plugin supports hidden project prompts, public style summaries, multi-provider image generation, WordPress Media Library saving, moderation, configurable galleries, reference images, and safe debug logging.

---

## Current status

`main` now contains the latest tested stable version:

```text
v1.4.1
```

The old v1.4.x feature branches have been merged and removed. Continue new work from `main`.

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

Use that plugin README for install instructions, provider setup, shortcodes, reference image guidance, gallery settings, testing checklist, and rollback notes.

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
- Configurable gallery styling
- Gallery image limit for latest N approved images
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

Use for style-critical portfolio projects where matching the original ChatGPT/OpenAI look matters.

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

## Reference image note

Gemini Direct sends the actual reference image bytes to Google.

OpenAI Direct also supports reference-image generation by sending the selected Media Library image to the OpenAI image edits endpoint. This should improve project-level consistency for style-driven portfolio work.

Debug logs should show this when OpenAI uses a reference image:

```text
request_mode: edit_with_reference
has_reference_image: true
```

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

Gallery with override:

```text
[portfolio_ai_gallery project="uk_grand_tour" limit="8" caption="hide"]
```

Only approved images appear in the public gallery.

---

## v1.4.1 highlights

- Added OpenAI Direct image provider.
- Added OpenAI settings for credential, base URL, image model, and quality.
- Added OpenAI Direct to the global default provider selector.
- Added per-project image provider selector.
- Added OpenAI reference-image support through the image edits endpoint.
- Fixed gallery shortcode empty-limit handling so galleries no longer collapse to one image.
- Kept Gemini Direct and Custom Route providers.
- Routed generation through the selected project provider.
- Preserved the shared Media Library, History, Moderation, and Gallery pipeline.
- Added OpenAI provider logging for model, quality, size, generation format, and request mode.

---

## v1.4.0 highlights included

- Removed public frontend aspect-ratio dropdown.
- Added backend-only generation format dropdown: portrait, square, landscape.
- Added per-project gallery display settings.
- Added gallery image limit.
- Added gallery thumbnail shape and size controls.
- Added caption display options.
- Added card style options.
- Added optional gallery download links.
- Added frontend gallery auto-refresh after approved submission.
- Added backend Hide action.
- Kept Delete as a soft delete.
- Added reference image thumbnail preview in project settings.
- Strengthened reference-image prompt guidance.

---

## Do not commit secrets

Do not commit provider credentials or server credentials to this repository.

Provider credentials should be entered in WordPress admin settings or stored in private server-side configuration.

---

## Testing checklist

Before treating a fresh install as stable, test:

- plugin activation
- API Settings page
- OpenAI settings save correctly
- project provider dropdown saves correctly
- OpenAI Direct generation
- OpenAI Direct reference-image generation with a Media Library attachment ID
- OpenAI debug log shows `request_mode: edit_with_reference`
- Gemini Direct generation still works
- Custom Route generation still works if needed
- backend generation format dropdown
- frontend generator without aspect-ratio selector
- Media Library image saving
- History view
- gallery image limit
- gallery styling settings
- gallery submission
- approved gallery display
- auto-refresh after approved submission
- Hide and soft Delete actions
- debug logs do not expose provider credentials
- reference image attachment ID and preview

Keep `main` as the latest stable tested version.
