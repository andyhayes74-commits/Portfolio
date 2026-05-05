# Changelog

## v1.4.0 - 2026-05-05

### Gallery styling and display controls
- Added per-project gallery display settings.
- Added gallery image limit to show the latest N approved images.
- Added thumbnail shape controls: square, portrait, landscape, and natural.
- Added thumbnail size controls: small, medium, and large.
- Added caption display controls: hidden, prompt excerpt, date, or prompt plus date.
- Added card style controls: minimal, soft card, and framed.
- Added optional gallery download links.

### Gallery management
- Added Hide action in backend image history to move images back to private.
- Kept Delete as a soft delete by setting image status to `deleted`.
- Added AJAX gallery refresh endpoint.
- Added auto-refresh of matching frontend galleries after approved gallery submission.

### Generation UX
- Removed the public frontend aspect-ratio selector.
- Added backend-only project generation format setting: portrait, square, or landscape.
- Default generation format is portrait.
- Updated custom-route generation sizes to use faster showcase-friendly dimensions.
- Added reference image thumbnail preview in project settings.

## v1.3.1 - 2026-05-05

### Security and reliability fixes
- Hardened admin history/moderation query construction by replacing dynamic SQL fragments with prepared query paths.
- Removed frontend fatal exception detail leakage; errors now return a generic message with a reference ID while full details remain in debug logs.
- Improved rate-limit stability by enforcing a minimum limit and using a consistent daily key.
- Added constrained proxy-aware visitor IP detection for local reverse-proxy deployments.
- Updated daily limit windows to expire at UTC day boundaries instead of rolling 24-hour windows.
- Removed user-agent from rate-limit hashing to avoid easy bypass via header rotation.

### Media pipeline fixes
- Fixed binary save handling so `wp_insert_attachment()` failures now return `WP_Error` instead of silently returning a partial success payload.
- Added cleanup of uploaded file if attachment insert fails.

### Frontend UX improvements
- Improved AJAX failure messaging so server-provided JSON errors are displayed when available for both generate and gallery submission flows.
