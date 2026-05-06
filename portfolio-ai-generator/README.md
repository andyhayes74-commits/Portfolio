# Portfolio AI Generator

**Version:** 1.6.2  
**Status:** Production-readiness branch  
**Plugin type:** WordPress image generation plugin for portfolio and project pages

Portfolio AI Generator lets visitors generate AI images inside a controlled project style. Each project can have hidden master prompts, public style descriptions, generation limits, gallery moderation, provider settings, optional reference image guidance, frontend text controls, prompt relevance checks, and configurable gallery display settings.

The plugin is designed for creative portfolios where consistency, style control, and safe public interaction matter more than raw prompt freedom.

---

## What v1.6.2 includes

Version 1.6.2 builds on the v1.6.1 admin guidance release and begins the production-readiness pass.

### Main features

- Project-specific hidden master prompts
- Negative prompts
- Public style summaries
- Custom frontend generator heading, description, placeholder, and button text
- Backend-only generation format setting
- OpenAI Direct provider
- Gemini Direct provider
- Custom Route provider
- Per-project provider selection
- Optional reference image attachment ID
- Generated images saved to the WordPress Media Library
- Gallery moderation workflow
- Per-project gallery styling
- Prompt relevance guard: Off, Basic, or Smart AI check
- Expanded inline admin help descriptions
- Debug logs with redaction
- Provider test foundation

---

## Important production notes

This plugin uses the site owner's own provider API keys. Image generation may incur costs billed directly by OpenAI, Google Gemini, or the configured custom provider.

Before public release, test:

```text
API keys
provider settings
relevance guard behaviour
gallery moderation
rate limits
debug logs
reference image behaviour
```

---

## Provider setup

### OpenAI Direct

Use this for projects where style consistency with OpenAI/ChatGPT image output matters.

Settings:

```text
OpenAI API key
OpenAI base URL
OpenAI image model
OpenAI quality
```

### Gemini Direct

Use this for Gemini image generation and reference-image testing.

Settings:

```text
Gemini API key
Gemini model
Gemini prompt character limit
```

### Custom Route

Use this for LiteLLM/NVIDIA-style image routes or other custom image endpoints.

Settings:

```text
Base URL
Endpoint path or full endpoint
Endpoint mode
Auth mode
Custom route API key
```

---

## Creating or editing a project

Go to:

```text
Settings → Portfolio AI → Projects
```

### Core project fields

#### Project name

The human-friendly name shown in admin and used as a fallback frontend heading.

#### Project slug

Used in shortcodes.

Example:

```text
uk_grand_tour
```

#### Hidden master prompt

The private style system for the project. Visitors do not see this.

Use this for:

```text
visual style
composition rules
colour palette
lighting
mood
quality expectations
consistency instructions
```

#### Negative prompt

Things the generator should avoid.

Examples:

```text
blurry, distorted hands, extra text, watermark, low quality, messy typography
```

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

#### Frontend Text & Branding

Controls the public generator wording:

```text
Generator heading
Generator description
Prompt placeholder
Generate button text
```

#### Prompt Relevance & Safety

Controls whether prompts are checked before generation.

Modes:

```text
Off
Basic local filter
Smart AI check
```

Smart AI check uses the selected provider when supported. In v1.6.2 the smart relevance guard is being hardened to fail closed where appropriate.

#### Generation format

Backend-only setting. Visitors do not see this.

Options:

```text
Portrait
Square
Landscape
```

#### Reference image attachment ID

Optional WordPress Media Library attachment ID used as a visual reference where supported.

---

## Gallery display settings

Each project can control its gallery appearance:

```text
image limit
thumbnail shape
thumbnail size
caption display
card style
download link
auto-refresh
desktop columns
tablet columns
mobile columns
card colours
caption styling
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

Delete is currently a soft delete. It marks the image record as deleted and does not permanently remove the Media Library file.

---

## Debug logs

Debug logs are available here:

```text
Settings → Portfolio AI → Debug Logs
```

Logs are intended to redact API keys, full hidden prompts, and base64 image data. Treat logs as admin-only operational diagnostics, not public-safe output.

---

## Security notes

Do not commit API keys to GitHub.

Do not paste API keys into this README.

API keys should only be entered in WordPress admin settings or stored using server-side constants.

---

## Testing checklist for v1.6.2

Before treating v1.6.2 as production-ready, test:

- Plugin activates without fatal errors
- API Settings page loads
- Provider keys save safely
- Project settings save and reload correctly
- Frontend text settings save and reload correctly
- Relevance settings save and reload correctly
- Smart relevance rejects out-of-scope prompts
- Gemini Direct generates an image
- OpenAI Direct generates an image
- Custom Route works if configured
- Reference image behaviour is clear and predictable
- Failed provider calls do not incorrectly appear successful
- Generated image is saved to Media Library
- Generated image appears in History
- Submit to Gallery works
- Pending image does not appear until approved
- Gallery styling works on desktop and mobile
- Debug logs do not expose provider credentials

---

## Known limitations

- Public release packaging is not complete yet
- A formal WordPress.org `readme.txt` is still required
- Provider connection tests are still being hardened
- Captcha support is not included yet
- Provider-specific cost tracking is not included yet
- Permanent Media Library deletion is not included yet

---

## Current recommendation

Treat v1.6.2 as a production-readiness branch, not a final public release.

Do not submit to the WordPress plugin directory until the remaining audit issues are resolved and a security pass has been completed.
