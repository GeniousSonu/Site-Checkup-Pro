=== Site Checkup Pro – WordPress Security Audit & Site Hardening ===
Contributors: genioussonu
Author: SK Sahinur Islam
Author URI: https://www.genioussonu.me/
Plugin URI: https://www.genioussonu.me/plugin/site-checkup-pro/
Tags: security, hardening, security audit, login security, firewall
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Complete security audit, site hardening checklist, and vulnerability scanner for WordPress. One-click safe hardening, login protection, and client reports.

== Description ==

**Site Checkup Pro** delivers professional **WordPress security** auditing and **site hardening** through an actionable, SOP-driven dashboard. Whether you need an instant **security checkup**, automated **hardening** for sensitive files and server headers, or a comprehensive **security audit** checklist across client installations, Site Checkup Pro organizes and executes your entire security workflow.

Rather than competing with or replacing mature firewalls and backup tools, Site Checkup Pro audits your environment, detects vulnerabilities, and applies verified, reversible **hardening** fixes with zero bloat. Automate safe security tasks, guide complex configurations with seamless plugin bridges, track routine credential rotations, and generate print-ready executive client audit reports:

* **Level A (Safe Automation & Scanners):** Disable XML-RPC, restrict unauthenticated REST user enumeration, hide PHP versions, enforce clickjacking protection (X-Frame-Options), MIME sniffing protection, HSTS, disable front-end `WP_DEBUG_DISPLAY`, audit file permissions (strict 640 baseline on `wp-config.php`), detect risky PHP `disable_functions` / `open_basedir`, check default `wp_` database prefix, and probe TLS protocols (`TLSv1.0` through `TLSv1.3`) and certificate chain depth.
* **Level B (Guided Actions & Bridges):** Seamlessly detects and bridges to mature, audited plugins (WPS Hide Login, Wordfence, Two-Factor Authentication, and Backup plugins) with recommended setting checklists. Generates RFC 9116 `/.well-known/security.txt` responsible disclosure contact files.
* **Level C (Manual Audits & Reminders):** Track 15-day credential rotations (cPanel, WordPress admin), quarterly (90-day) backup restore test rehearsals, staging site HTTP basic authentication, and 6-month Google Search Console URL removal reviews with automated dashboard reminder notices.
* **Level D (Executive Client Reports):** Generate print-ready HTML and PDF audit summaries detailing completed hardening, SOP coverage percentage, emergency incident-response escalation sheets, and timestamped audit logs for client handoff.

### Built with Enterprise Safeguards

* **Vulnerability Intelligence:** Queries the Patchstack vulnerability database API for installed plugins and themes with explicit confidence scoring (`high`, `medium`, `unverified/low`), ensuring custom or renamed plugins are never falsely reported as clean.
* **Server Environment Aware:** Automatically detects Apache/LiteSpeed vs Nginx. If your site runs on Nginx, .htaccess tasks display copyable Nginx server directives rather than false positive checkmarks.
* **Zero Secret Leakage:** Audit logs strictly redact passwords, auth keys, and database credentials. Constant changes log only boolean states; salt rotation logs zero key material.
* **Scoped REST API:** Options updates are restricted to a hardcoded whitelist with strict per-field sanitizers, preventing mass-assignment vulnerabilities.
* **Pre-Execution Diff Previews:** Inspect the exact marker-delimited configuration blocks before writing to `.htaccess` or `wp-config.php`.
* **Staging-Safe Health Checks:** Automatic loopback tests detect staging HTTP Basic Auth walls and only roll back upon true 5xx server errors.
* **Safe Plugin Deletion:** Unwanted migration tools are zipped to `wp-content/uploads/wpsg-backups/plugins/` before deletion, making Undo 100% genuine.
* **Fail-Safe Emergency Lockout Recovery:** Custom login renamer contains zero query-string backdoors. Recovery is exclusively controlled via the `WPSG_DISABLE_LOGIN_RENAME` constant in `wp-config.php`.

== Third Party Services ==

Site Checkup Pro connects to the following external third-party services to deliver security auditing and alerting functionality:

1. **WordPress.org APIs (`https://api.wordpress.org`)**
   - **Service & Purpose:** Used for core checksum validation (`/core/checksums/1.0/`) and verifying plugin directory status (`/plugins/info/1.0/`).
   - **Data Sent:** Current WordPress version, plugin slugs, and locale. No personally identifiable information (PII) or credentials are transmitted.
   - **Privacy Policy:** [WordPress.org Privacy Policy](https://wordpress.org/about/privacy/)

2. **Patchstack Vulnerability Database API (`https://patchstack.com`)**
   - **Service & Purpose:** Checks installed plugin and theme versions against the Patchstack vulnerability intelligence database to identify known CVEs.
   - **Explicit Consent Required:** Outbound queries to Patchstack require an explicit opt-in checkbox to be enabled in Plugin Settings per WordPress.org Guideline 7. If disabled, local plugin assessments are performed with zero outbound network calls.
   - **Data Sent:** Plugin and theme software slugs and version numbers. If configured by an administrator, an optional API authentication key is transmitted over HTTPS. No site visitor data, user credentials, or database contents are sent.
   - **Privacy Policy:** [Patchstack Privacy Policy](https://patchstack.com/privacy-policy/)
   - **Terms of Service:** [Patchstack Terms of Service](https://patchstack.com/terms-of-service/)

3. **User-Configured Alert Webhooks (Optional)**
   - **Service & Purpose:** When configured by an administrator, outbound security event notifications (e.g., brute-force lockouts, rogue admin detections) are dispatched to the customer's chosen webhook endpoint (e.g., Slack, Discord, or agency automation endpoint).
   - **Explicit Consent Required:** Outbound alert dispatch requires an explicit opt-in checkbox to be enabled in Plugin Settings per WordPress.org Guideline 7.
   - **Data Sent:** Factual incident event summaries (timestamp, alert type, sanitized technical message). Audit logs and webhook payloads strictly redact passwords, security salts, and authentication tokens.
   - **Terms & Privacy:** Governed by the destination endpoint provider chosen by the site administrator.

== Source Code & Development ==

The full, unminified source code for Site Checkup Pro is developed publicly on GitHub:
https://github.com/GeniousSonu/site-checkup-pro

All JavaScript and CSS distributed in this plugin are 100% human-readable, unminified, locally bundled, and licensed under the GNU General Public License v2 or later per WordPress.org Guidelines 2 and 4.

== Installation ==

1. Upload the `site-checkup-pro` folder to your `/wp-content/plugins/` directory, or upload the `.zip` archive via **Plugins &rarr; Add New &rarr; Upload Plugin**.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Navigate to the new **Site Checkup** top-level menu in your admin dashboard.
4. Review your initial SOP Coverage score and click **Run All Safe Tasks** to begin.

== Frequently Asked Questions ==

= What happens if my server runs Nginx instead of Apache? =
Site Checkup Pro detects Nginx server software (`$_SERVER['SERVER_SOFTWARE']`). Tasks that rely on `.htaccess` are marked as "Not Applicable on this Server" and feature a "View Nginx Snippet" button with 1-click copy for your server configuration.

= What if I get locked out using the custom login URL? =
Emergency recovery strictly requires filesystem access. Open `wp-config.php` via FTP, SSH, or your hosting control panel and add:
`define( 'WPSG_DISABLE_LOGIN_RENAME', true );`
This immediately deactivates the login renamer and restores access to the default `/wp-login.php`.

= Does Site Checkup Pro store my database password or salts in the audit log? =
No. Our audit logging system features an automated secret scrubber. Edits to `wp-config.php` only log constant names and boolean flags (e.g. `{"DISALLOW_FILE_EDIT": true}`). Salt rotations only log `{"salts_rotated": true}` with zero key material.

= Does this plugin work on WordPress Multisite? =
Version 1.0 is optimized for single-site agency workflows. On Multisite installations, settings and actions are strictly restricted to Super Administrators (`is_super_admin()`).

== Screenshots ==

1. Global Security Check-up dashboard with KPI summary bar and SOP Coverage score.
2. Accessible checklist table with colorblind-friendly SVG status badges and action triggers.
3. Pre-execution diff preview modal before writing to server configuration.
4. Client-facing executive SOP coverage report ready for PDF export with emergency incident response contacts.

== Changelog ==

= 1.0.0 =
* Initial release.
* Added Task Registry with complete SOP checklist items.
* Implemented client-driven sequential batch runner.
* Added .htaccess and wp-config.php marker managers with health checks and diff preview.
* Integrated baseline drift scanner for rogue admins and wp_options.
* Added Nginx server detection and snippet exporter.
* Integrated Patchstack vulnerability database API with match-confidence scoring.
* Added multi-protocol TLS probe (`TLSv1.0` - `TLSv1.3`) and certificate chain depth verification.
* Added strict file permissions auditing (640 for `wp-config.php`, 644 files, 755 directories).
* Added RFC 9116 `/.well-known/security.txt` generator with document root containment.
* Added informational SPF/DKIM/DMARC domain authentication checker.
* Added 12-month audit log retention and 15-day / 90-day / 6-month scheduler.

== Credits ==

Site Checkup Pro is built and maintained by **[SK Sahinur Islam](https://www.genioussonu.me/)**.
* Author Website: [https://www.genioussonu.me/](https://www.genioussonu.me/)
* GitHub: [https://github.com/GeniousSonu/](https://github.com/GeniousSonu/)
* WordPress.org Profile: [https://profiles.wordpress.org/genioussonu/](https://profiles.wordpress.org/genioussonu/)
