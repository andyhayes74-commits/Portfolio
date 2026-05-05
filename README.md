# Portfolio AI Generator

**Current branch version:** v1.4.1 candidate  
**Stable main version:** v1.3.1  
**Project:** WordPress plugin for controlled AI image generation on portfolio project pages

Portfolio AI Generator lets visitors generate AI images inside a controlled project style. It is designed for creative portfolios, case studies, AI art projects, campaign demos, and interactive project pages where the site owner wants consistent results rather than completely open-ended prompting.

The plugin supports hidden project prompts, public style summaries, multi-provider image generation, WordPress Media Library saving, moderation, configurable galleries, reference images, and safe debug logging.

---

## Current status

`main` contains the latest stable tested version:

```text
v1.3.1
```

The v1.4.0 branch contains gallery styling and management work:

```text
feature/portfolio-ai-generator-v1.4.0-gallery-styling
```

This branch contains the v1.4.1 multi-provider candidate:

```text
feature/portfolio-ai-generator-v1.4.1-multi-provider
```

Do not merge v1.4.1 into `main` until it has been tested on WordPress.

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
- Optional Gemini reference image attachment ID
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
OpenAI API key
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

Recommended first test:

```text
gpt-image-1-mini + medium
```

Recommended style-critical test:

```text
chatgpt-image-latest + medium
```

### Gemini Direct

Use for Gemini image generation and current reference-image byte sending.

Gemini Direct can send the Media Library reference image as inline image data.

### Custom Route

Use for LiteLLM/NVIDIA-style routes or other OpenAI-compatible/custom endpoints.

---

## Reference image note

Gemini Direct currently sends the actual reference image bytes to Google.

OpenAI Direct v1.4.1 includes the reference-image guidance in the prompt, but does not yet send the image file itself through an image-edit/reference endpoint. That should be treated as a later OpenAI reference-image patch.

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
- Added OpenAI settings for API key, base URL, image model, and quality.
- Added OpenAI Direct to the global default provider selector.
- Added per-project image provider selector.
- Kept Gemini Direct and Custom Route providers.
- Routed generation through the selected project provider.
- Preserved the shared Media Library, History, Moderation, and Gallery pipeline.
- Added OpenAI provider logging for model, quality, size, and generation format.

---

## v1.4.0 highlights included in this branch

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

Do not commit API keys, Gemini keys, OpenAI keys, LiteLLM keys, or server credentials to this repository.

Provider keys should be entered in WordPress admin settings or stored in private server-side configuration.

---

## Testing before merge

Before merging v1.4.1 into `main`, test:

- plugin activation
- API Settings page
- OpenAI settings save correctly
- project provider dropdown saves correctly
- OpenAI Direct generation with `gpt-image-1-mini`
- OpenAI Direct generation with `chatgpt-image-latest` if cost is acceptable
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
- debug logs do not expose provider keys
- reference image attachment ID and preview

Keep `main` as the latest stable tested version.
