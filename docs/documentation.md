# Site Checkup Pro Documentation

Welcome to the official documentation for **Site Checkup Pro**, the agency standard operating procedure (SOP) security hardening orchestrator for WordPress.

## Overview

Site Checkup Pro bridges the gap between high-level security checklists and technical execution. It structures WordPress security audits into four clear levels:
- **Level A (Safe Automation):** One-click tasks that apply proven hardening measures directly to server configuration (`.htaccess`, `wp-config.php`, HTTP response headers) without risking site breakage.
- **Level B (Guided Bridges):** Checklists and direct deep links to configure established security plugins (Wordfence, WPS Hide Login, Two-Factor Authentication).
- **Level C (Manual Audits & Reminders):** Recurring 15-day and 90-day task tracking for credentials, vault backups, and Search Console audits.
- **Level D (Executive Reports):** One-click client report generator producing PDF/printable documentation of all security controls applied.

## Key Modules

### 1. Server & Environment Security
- **TLS Version Probing:** Directly tests whether older, insecure TLS versions (`TLSv1.0` or `TLSv1.1`) are accepted by the server, while verifying certificate chain depth.
- **File Permissions Audit:** Scans critical files against strict baselines (<= 0640 for `wp-config.php`, <= 0644 for root files, <= 0755 for directories).
- **PHP Restrictions:** Detects whether `disable_functions` in `php.ini` blocks dangerous execution commands (`exec`, `shell_exec`, `passthru`, etc.).

### 2. Vulnerability Intelligence
- Connects with the Patchstack API to cross-reference installed plugins and themes against active CVE vulnerabilities.
- Features match-confidence scoring (`high`, `medium`, `unverified/low`) so non-indexed or custom plugins are clearly labeled.

### 3. Emergency Lockout Recovery
If you ever experience a lockout when using the custom login URL feature:
1. Access your site files via FTP, SSH, or your hosting control panel.
2. Edit `wp-config.php` and add:
   ```php
   define( 'WPSG_DISABLE_LOGIN_RENAME', true );
   ```
3. Save the file. Access to `/wp-login.php` will immediately be restored.
