# Frank Dead Link Checker

A WordPress plugin to find and fix broken links on your website.

## Changelog

### 1.0.5 (2026-06-13)
- UX: Conditional bulk action options based on active status tab.
- Feedback: Clearer, pluralized bulk action success and fallback messages.
- Visibility: Extended toast duration (5s) and delayed reload timeout (4s) to prevent notice cutoffs.
- Source UI: Unlinked source title text with dedicated Edit (pencil) and View (eye) buttons opening in a new tab/window.
- Layout: Handles long titles with CSS single-line truncation (ellipsis).
- URL limit: Expanded URL character display limit from 50 to 80.

### 1.0.3 (2026-06-11)
- Security: Handled all unescaped DB parameters to resolve WordPress.org Plugin Check tool warnings.
- Layout: Fixed page-load layout shift and notice flashing on dashboard refresh by introducing server-rendered custom notices container.
- Scanner: Implemented memory-efficient post query chunking (batches of 50) and active cache clearing to prevent memory exhaustion on large sites.
- Mutex: Added transient-based scan initialization mutex to prevent double-scan race conditions.
- Permissions: Added braced capability checks for all AJAX action endpoints and validated post-level edit capabilities on link updates.
- Validation: Enforced strict URL scheme validation (HTTP/HTTPS only) on link updates.
- Uninstall: Cleaned up uninstaller database variables and removed redundant rewrite rules flush.

### 1.0.0 (2024-02-07)
- Initial release

## License

GPL-2.0-or-later
