# Portfolio AI Generator

**Current branch version:** v1.3.1 candidate  
**Stable main version:** v1.2.0 Gemini Direct  
**Project:** WordPress plugin for controlled AI image generation on portfolio project pages

Portfolio AI Generator lets visitors generate AI images inside a controlled project style. It is designed for creative portfolios, case studies, AI art projects, campaign demos, and interactive project pages where the site owner wants consistent results rather than completely open-ended prompting.

The plugin supports hidden project prompts, public style summaries, Gemini Direct image generation, custom image routes, WordPress Media Library saving, moderation, galleries, and safe debug logging.

---

## Version status

### `main`

The `main` branch currently contains the stable working version:

```text
v1.2.0 Gemini Direct
```

This version has been merged into `main` and is the current stable baseline.

### `codex/analyze-wordpress-plugin-v1.3-for-errors`

This branch contains the current v1.3.1 candidate.

It builds on the v1.3.0 refactor and includes Codex review fixes:

- safer admin query handling
- better frontend AJAX error display
- safer fatal error messages for visitors
- improved rate-limit behaviour
- proxy-aware visitor IP detection
- improved image save failure handling
- `CHANGELOG.md`

This branch should be tested on WordPress before being merged into `main`.

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

Use that README for install instructions, provider setup, shortcodes, reference image guidance, testing checklist, and rollback notes.

---

## Main features

- Project-specific hidden master prompts
- Public style summaries
- Visitor prompt input
- Aspect ratio options
- Gemini Direct provider
- Custom Route provider for LiteLLM/NVIDIA-style routes
- Generated images saved to WordPress Media Library
- Gallery submission workflow
- Admin moderation
- Debug logs with redaction
- Daily generation limits
- Optional Gemini reference image attachment ID

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

## v1.3.x modular structure

The v1.3.x branch refactors the plugin from one large PHP file into smaller files:

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

## Do not commit secrets

Do not commit API keys, Gemini keys, LiteLLM keys, or server credentials to this repository.

Provider keys should be entered in WordPress admin settings or stored in private server-side configuration.

---

## Testing before merge

Before merging v1.3.1 into `main`, test:

- plugin activation
- API Settings page
- existing project settings
- Gemini Direct generation
- Media Library image saving
- History view
- gallery submission
- approved gallery display
- debug logs
- reference image attachment ID
- Custom Route provider if still needed

Keep `main` as the latest stable tested version.
