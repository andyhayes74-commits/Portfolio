# Portfolio AI Generator

**Current branch version:** v1.5.0 candidate  
**Stable main version:** v1.4.1  
**Project:** WordPress plugin for controlled AI image generation on portfolio project pages

Portfolio AI Generator lets visitors generate AI images inside a controlled project style. It is designed for creative portfolios, case studies, AI art projects, campaign demos, and interactive project pages where the site owner wants consistent results rather than completely open-ended prompting.

The plugin supports hidden project prompts, public style summaries, multi-provider image generation, WordPress Media Library saving, moderation, configurable galleries, reference images, and safe debug logging.

---

## Current status

`main` contains the latest stable tested version:

```text
v1.4.1
```

This branch contains the v1.5.0 gallery customisation candidate:

```text
feature/portfolio-ai-generator-v1.5.0-gallery-customisation
```

Do not merge v1.5.0 into `main` until it has been tested on WordPress.

---

## v1.5.0 focus

v1.5.0 adds project-level gallery layout and style controls so every project gallery can visually match its own portfolio page.

Each project can now define its own gallery presentation without changing the shortcode.

Example:

```text
[portfolio_ai_gallery project="uk_grand_tour"]
```

The shortcode uses the gallery settings saved for that project.

---

## New gallery customisation controls

Per project:

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

Supported colour values:

```text
#111827
#fff
rgb(20,20,20)
rgba(20,20,20,0.7)
transparent
inherit
```

---

## Main features retained from v1.4.1

- Project-specific hidden master prompts
- Public style summaries
- Visitor prompt input
- Backend-only generation format setting
- Multi-provider image generation
- OpenAI Direct provider
- Gemini Direct provider
- Custom Route provider
- Per-project provider selection
- Reference image attachment ID for Gemini and OpenAI
- Generated images saved to WordPress Media Library
- Gallery submission workflow
- Gallery image limit for latest N approved images
- Auto-refresh gallery after approved submission
- Backend image actions: approve, reject, hide, soft delete
- Admin moderation
- History view
- Debug logs with redaction
- Daily generation limits
- Modular PHP structure for safer maintenance

---

## Implementation note

The gallery renderer now uses CSS variables on the gallery wrapper.

That allows each project to have a different look while keeping the frontend CSS stable and reusable.

---

## Testing checklist before merge

Before merging v1.5.0 into `main`, test:

- plugin activation
- project edit page loads
- new Gallery Layout & Style section appears
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
- gallery still shows latest N approved images
- gallery auto-refresh still works
- OpenAI Direct still works
- Gemini Direct still works
- Custom Route still works if needed
- debug logs do not expose provider credentials

Keep `main` as the latest stable tested version.
