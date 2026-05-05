# Portfolio AI Generator

**Current branch version:** v1.4.0 candidate  
**Stable main version:** v1.3.1  
**Project:** WordPress plugin for controlled AI image generation on portfolio project pages

Portfolio AI Generator lets visitors generate AI images inside a controlled project style. It is designed for creative portfolios, case studies, AI art projects, campaign demos, and interactive project pages where the site owner wants consistent results rather than completely open-ended prompting.

The plugin supports hidden project prompts, public style summaries, Gemini Direct image generation, custom image routes, WordPress Media Library saving, moderation, configurable galleries, reference images, and safe debug logging.

---

## Current status

`main` contains the latest stable tested version:

```text
v1.3.1
```

This branch contains the v1.4.0 gallery styling and management candidate:

```text
feature/portfolio-ai-generator-v1.4.0-gallery-styling
```

Do not merge v1.4.0 into `main` until it has been tested on WordPress.

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
- Gemini Direct provider
- Custom Route provider for LiteLLM/NVIDIA-style routes
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

## v1.4.0 highlights

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

---

## Do not commit secrets

Do not commit API keys, Gemini keys, LiteLLM keys, or server credentials to this repository.

Provider keys should be entered in WordPress admin settings or stored in private server-side configuration.

---

## Testing before merge

Before merging v1.4.0 into `main`, test:

- plugin activation
- API Settings page
- existing project settings
- backend generation format dropdown
- frontend generator without aspect ratio selector
- Gemini Direct generation
- Custom Route generation if still needed
- Media Library image saving
- History view
- gallery image limit
- gallery styling settings
- gallery submission
- approved gallery display
- auto-refresh after approved submission
- Hide and soft Delete actions
- debug logs
- reference image attachment ID and preview

Keep `main` as the latest stable tested version.
