# Portfolio AI Generator

**Current stable version:** v1.6.2  
**Branch:** `main`  
**Project:** WordPress plugin for controlled AI image generation on portfolio project pages

Portfolio AI Generator lets visitors generate AI images inside a controlled project style. It is designed for creative portfolios, AI art projects, campaign demos, case studies, and interactive project pages where the site owner wants consistent outputs rather than completely open-ended prompting.

The plugin supports hidden project prompts, public style summaries, multi-provider image generation, WordPress Media Library saving, moderation, configurable galleries, reference images, prompt relevance checks, frontend branding controls, provider testing foundations, expanded backend guidance, and safe debug logging.

---

## Current status

`main` now contains the latest tested stable version:

```text
v1.6.2
```

v1.6.2 includes:

```text
v1.4.1 multi-provider generation
v1.5 gallery customisation
v1.6 relevance guard and frontend branding
v1.6.1 expanded backend admin guidance
v1.6.2 production-readiness foundations
```

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

Use the plugin README for setup, provider configuration, shortcode usage, reference image guidance, gallery styling, moderation flow, relevance checks, testing, and rollback notes.

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
- Frontend branding controls
- Prompt relevance guard system
- Gallery image limit for latest approved images
- Auto-refresh gallery after approved submission
- Backend image actions: approve, reject, hide, soft delete
- Admin moderation
- History view
- Debug logs with redaction
- Daily generation limits
- Expanded backend field descriptions
- Provider connection testing foundations
- Safer production-oriented defaults
- Modular PHP structure for safer maintenance

---

## Production readiness direction

v1.6.2 begins the shift from:

```text
developer prototype
```

toward:

```text
public-facing production plugin
```

The current focus is:

```text
stability
clarity
safe defaults
better onboarding
support reduction
```

rather than rapid feature expansion.

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

## Provider connection testing

v1.6.2 introduces the foundation for provider testing tools.

Planned supported checks:

```text
OpenAI connection test
Gemini connection test
Custom Route connection test
```

These tools are designed to reduce setup confusion and help users diagnose invalid API credentials or endpoint issues.

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

## Frontend branding controls

Each project can now customise the public generator text.

Available controls:

```text
Generator heading
Generator description
Prompt placeholder text
Generate button text
```

This allows each project to feel visually and tonally distinct instead of sharing the same default generator wording.

---

## Prompt relevance & safety system

Projects can now optionally validate prompts before image generation.

Available modes:

```text
Off
Basic local filter
Smart AI check
```

Smart mode uses the selected provider to classify whether a prompt fits the intended project theme before spending image-generation credits.

Additional controls:

```text
Allowed prompt intent
Custom rejection message
Basic blocked-term list
```

---

## Gallery customisation in v1.5+

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

## Backend UX improvements in v1.6+

- Add Project form hidden by default
- Existing projects prioritised in admin workflow
- Cleaner edit flow
- Expanded inline help text for important settings
- Guidance for prompts, providers, relevance checks, and galleries
- Improved onboarding descriptions for non-technical users

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

## v1.6.2 highlights

- Added production-readiness groundwork.
- Added provider connection testing foundation.
- Improved onboarding direction.
- Improved production-oriented admin wording.
- Improved stability-focused roadmap direction.

---

## v1.6.1 highlights

- Added expanded backend admin descriptions.
- Added inline explanations for prompts, providers, relevance checks, and galleries.
- Improved admin usability for non-technical users.
- Continued public-release preparation work.

---

## v1.6.0 highlights

- Added project-level frontend branding controls.
- Added prompt relevance guard system.
- Added Smart AI relevance checking.
- Added custom rejection messaging.
- Added backend UX cleanup.
- Added cleaner project management flow.

---

## Do not commit secrets

Do not commit provider credentials or server credentials to this repository.

Provider credentials should be entered in WordPress admin settings or stored in private server-side configuration.

---

## Testing checklist

Before treating a fresh install as stable, test:

- plugin activation
- project edit page loads
- Frontend Text & Branding settings save correctly
- relevance settings save correctly
- relevance rejection flow works
- smart relevance guard works
- Gallery Layout & Style section appears
- desktop/tablet/mobile columns save correctly
- column counts affect frontend gallery layout
- gallery styling still works correctly
- gallery still shows latest approved images
- gallery auto-refresh still works
- OpenAI Direct still works
- OpenAI Direct reference-image generation still works
- Gemini Direct still works
- Custom Route still works if needed
- provider test notices display correctly
- debug logs do not expose provider credentials

Keep `main` as the latest stable tested version.
