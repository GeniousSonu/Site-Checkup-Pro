# Changelog

All notable changes to **Site Checkup Pro** will be documented in this file.

## [1.0.0] - 2026-09-17
### Added
- Complete SOP task registry with Level A, B, C, and D checklist items.
- Client-driven sequential batch runner with live progress indicator.
- Marker-delimited `.htaccess` and `wp-config.php` managers with pre-flight backups and health check rollbacks.
- Multi-protocol TLS probe (`TLSv1.0` through `TLSv1.3`) and certificate chain depth analyzer.
- Patchstack vulnerability database API integration with confidence matching.
- Strict file permission auditor (enforcing 640/600 on `wp-config.php`).
- RFC 9116 `/.well-known/security.txt` generator.
- Informational SPF, DKIM, and DMARC domain health checker.
- Incident response contact sheet surfaced directly in executive client reports.
- Scoped REST API with strict allowlisting against mass-assignment attacks.
- Dual distribution build targets (`bin/build-release.sh`) cleanly stripping self-hosted update machinery for WordPress.org SVN distribution (Guideline 8).
- Explicit opt-in consent checkboxes for Patchstack CVE lookups and security webhooks (Guideline 7).
- Comprehensive license audit verifying 100% GPLv2+ compatibility for all files, scripts, and SVG assets (Guideline 2).
- Tag capping strictly at 5 search-relevant terms (Guideline 12).
- CI/CD release workflow configured for tag-only SVN deployment synchronizing `trunk` and `tags` simultaneously (Guidelines 14 & 15).
- Public GitHub repository link and unminified source declaration (Guideline 4).
