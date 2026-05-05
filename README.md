# Portfolio AI Generator

**Current stable version:** v1.3.1  
**Branch:** `main`  
**Project:** WordPress plugin for controlled AI image generation on portfolio project pages

Portfolio AI Generator lets visitors generate AI images inside a controlled project style. It is designed for creative portfolios, case studies, AI art projects, campaign demos, and interactive project pages where the site owner wants consistent results rather than completely open-ended prompting.

The plugin supports hidden project prompts, public style summaries, Gemini Direct image generation, custom image routes, WordPress Media Library saving, moderation, galleries, reference images, and safe debug logging.

---

## Current status

`main` now contains the latest tested stable version:

```text
v1.3.1
```

v1.3.1 includes the v1.3 modular refactor plus reliability fixes from the Codex review.

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

Use that plugin README for install instructions, provider setup, shortcodes, reference image guidance, testing checklist, and rollback notes.

---

## Main features

- Project-specific hidden master prompts
- Public style summaries
- Visitor prompt input
- Gemini Direct provider
- Custom Route provider for LiteLLM/NVIDIA-style routes
- Optional Gemini reference image attachment ID
- Generated images saved to WordPress Media Library
- Gallery submission workflow
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

Only approved images appear in the public gallery.

---

## v1.3.1 structure

The plugin is now split into smaller files:

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
    ├── css/pai-frontend.css
    └── js/pai-frontend.js
```

This structure makes the plugin safer to maintain and easier to extend.

---

## v1.3.1 highlights

- Refactored the plugin from one large PHP file into modular classes.
- Preserved existing shortcodes and database table.
- Kept Gemini Direct and Custom Route providers.
- Added Gemini reference image support.
- Improved gallery/result image sizing.
- Hardened admin history/moderation query handling.
- Improved frontend AJAX error display.
- Reduced fatal error detail leakage to visitors.
- Improved rate-limit behaviour.
- Improved binary image save failure handling.

---

## Do not commit secrets

Do not commit API keys, Gemini keys, LiteLLM keys, or server credentials to this repository.

Provider keys should be entered in WordPress admin settings or stored in private server-side configuration.

---

## Next planned version

### v1.4.0 — Gallery Styling, Management & Backend Generation Format

Planned scope:

- Per-project gallery style settings.
- Gallery limit, for example latest 12 approved images.
- Backend image management: approve, reject, hide, soft delete.
- Auto-refresh gallery after image submission.
- Caption and download-button controls.
- Backend-only generation format dropdown.
- Public frontend remains simple: prompt box and generate button.

Keep `main` as the latest stable tested version.
