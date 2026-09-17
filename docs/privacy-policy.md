# Privacy Policy for Site Checkup Pro

*Last Updated: September 17, 2026*  
*Website: [https://www.genioussonu.me/plugin/site-checkup-pro/](https://www.genioussonu.me/plugin/site-checkup-pro/)*

This Privacy Policy explains what information is collected, processed, or transmitted by **Site Checkup Pro**, a WordPress security and SOP hardening orchestrator developed by **SK Sahinur Islam** ([https://www.genioussonu.me/](https://www.genioussonu.me/)).

## 1. Zero Tracking & Local Operation

Site Checkup Pro operates almost exclusively on your local WordPress installation. We do **not** run telemetry, tracking beacons, usage analytics, or remote diagnostics. We do not sell, rent, or collect any personal information about your website visitors, registered users, or administrators.

## 2. Outbound Network Requests & Third-Party Services

To provide security integrity checks, vulnerability notifications, and administrative alerts, the plugin makes outbound HTTPS connections under specific, controlled circumstances:

### A. WordPress.org Core & Plugin APIs
- **Endpoints:** `https://api.wordpress.org/core/checksums/1.0/`, `https://api.wordpress.org/plugins/info/1.0/`
- **Purpose:** Verifies the cryptographic hashes of WordPress core files against official WordPress.org releases to detect malicious tampering, and checks whether installed plugins remain active in the directory.
- **Data Transmitted:** Current WordPress version number, installed plugin slugs, and site locale.
- **Privacy Policy:** [WordPress.org Privacy Policy](https://wordpress.org/about/privacy/)

### B. Patchstack Vulnerability Database API
- **Endpoints:** `https://patchstack.com/database/api/v2/`
- **Purpose:** Compares installed plugin and theme versions against the Patchstack vulnerability database to alert you to known CVEs and security advisories.
- **Data Transmitted:** Software slugs and version numbers of installed themes and plugins. If configured by an administrator, an optional API token is transmitted.
- **Privacy Policy:** [Patchstack Privacy Policy](https://patchstack.com/privacy-policy/)

### C. Administrator-Configured Alert Webhooks (Optional)
- **Endpoints:** User-specified webhook URLs (e.g., Slack, Discord, Microsoft Teams, or custom HTTP endpoints).
- **Purpose:** Delivers real-time notifications to administrators when security events occur (e.g., brute-force lockouts or rogue administrator creation).
- **Data Transmitted:** Factual event summaries (timestamp, alert severity, event description). All passwords, security keys, salts, and database credentials are automatically redacted before transmission.

## 3. Local Data Storage

The plugin creates three custom database tables on your server:
- `wp_wpsg_task_status`: Stores task completion status, timestamps, and manual notes.
- `wp_wpsg_audit_log`: Stores an immutable log of security hardening actions with automated secret redaction.
- `wp_wpsg_rate_limits`: Stores atomic rate-limiting counters and lockout timestamps for login protection.

Upon plugin uninstallation (deletion), all custom database tables, stored options (`wpsg_*`), and transients are completely purged from your database.

## 4. Contact Information

If you have questions about this privacy policy, please contact:
- **Author:** SK Sahinur Islam
- **Website:** [https://www.genioussonu.me/](https://www.genioussonu.me/)
- **Email:** `privacy@genioussonu.me`
