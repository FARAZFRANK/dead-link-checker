# Frank Dead Link Checker

A WordPress plugin to find and fix broken links on your website.

## Changelog

### 1.0.7
- Security: Added SSRF hardening to the link checker (blocks requests to internal/private-network addresses, including through redirect chains).
- Security: Fixed a link-editing attribute-escaping gap that could allow malformed HTML when saving an edited link.
- Fix: Corrected a settings sanitizer so the request timeout is properly clamped to its intended 5-120 second range.
- Fix: Corrected a display bug where broken links without an HTTP status code always showed the same fallback text.
- Compliance: Resolved WordPress.org Plugin Check findings and brought the codebase in line with WordPress Coding Standards (WPCS).
- i18n: Fixed a missing translator string and stale Pro-plugin file path references on the Help page.

### 1.0.6 (2026-06-13)
- Feature: Added a "Copy" icon badge in the URL column to easily copy anchor text.
- UI: Changed "View Source" icon to an external link icon for better consistency.

### 1.0.5 (2026-06-13)
- UX: Conditional bulk action options based on active status tab.
- Feedback: Clearer, pluralized bulk action success and fallback messages.
- Visibility: Extended toast duration (5s) and delayed reload timeout (4s) to prevent notice cutoffs.
- Source UI: Unlinked source title text with dedicated Edit (pencil) and View (eye) buttons opening in a new tab/window.
- Layout: Handles long titles with CSS single-line truncation (ellipsis).
- URL limit: Expanded URL character display limit from 50 to 80.

### 1.0.4
- Fixed wpdb::prepare query missing placeholder notice.
- Added 'Recheck' and 'Edit' buttons to link actions with 'Upgrade to Pro' prompt.
- Added 'Export' button dropdown (CSV & JSON) as a PRO preview feature.
- Added 'Notifications' tab in Settings displaying disabled email notification options for PRO preview.
- Replaced native browser confirm alerts with stylized custom HTML modals across the dashboard.

### 1.0.3 (2026-06-11)
- Security: Handled all unescaped DB parameters to resolve WordPress.org Plugin Check tool warnings.
- Layout: Fixed page-load layout shift and notice flashing on dashboard refresh by introducing server-rendered custom notices container.
- Scanner: Implemented memory-efficient post query chunking (batches of 50) and active cache clearing to prevent memory exhaustion on large sites.
- Mutex: Added transient-based scan initialization mutex to prevent double-scan race conditions.
- Permissions: Added braced capability checks for all AJAX action endpoints and validated post-level edit capabilities on link updates.
- Validation: Enforced strict URL scheme validation (HTTP/HTTPS only) on link updates.
- Uninstall: Cleaned up uninstaller database variables and removed redundant rewrite rules flush.

### 1.0.2
- Removed edit button and edit link functionality from the dashboard.
- Removed recheck option and its functionality.
- Removed images option from link type settings.
- Added "Upgrade to Pro" submenu page with feature highlights and comparison table.
- Centered help page content for improved readability.
- Fixed SQL prepared statement warnings for WordPress Plugin Check compliance.
- Fixed unescaped database parameter warnings in scanner and uninstall files.
- Rewrote readme.txt for WordPress.org guideline compliance.
- Updated plugin name to "Dead Link Checker" for consistency.

### 1.0.1
- Maintenance release.
- Updated readme for WordPress.org compliance.

### 1.0.0 (2024-02-07)
- Initial release

## License

GPL-2.0-or-later
