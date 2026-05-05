# Changelog

## v1.3.1 - 2026-05-05

### Security and reliability fixes
- Hardened admin history/moderation query construction by replacing dynamic SQL fragments with prepared query paths.
- Removed frontend fatal exception detail leakage; errors now return a generic message with a reference ID while full details remain in debug logs.
- Improved rate-limit key stability by enforcing a minimum limit and including a user-agent component.
- Added constrained proxy-aware visitor IP detection for local reverse-proxy deployments.
- Updated daily limit windows to expire at UTC day boundaries instead of rolling 24-hour windows.
- Removed user-agent from rate-limit hashing to avoid easy bypass via header rotation.

### Media pipeline fixes
- Fixed binary save handling so `wp_insert_attachment()` failures now return `WP_Error` instead of silently returning a partial success payload.
- Added cleanup of uploaded file if attachment insert fails.

### Frontend UX improvements
- Improved AJAX failure messaging so server-provided JSON errors are displayed when available for both generate and gallery submission flows.
