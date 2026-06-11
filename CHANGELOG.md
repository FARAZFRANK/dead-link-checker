# Frank Dead Link Checker

A WordPress plugin to find and fix broken links on your website.

## Changelog

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
