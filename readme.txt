=== Site Checkup Pro ===
Contributors: sitecheckuppro
Tags: security, hardening, audit, htaccess, checklist, wp-config
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Turn your manual Security Check-up SOP into an interactive dashboard checklist with one-click safe hardening, guided bridges, and client reports.

== Description ==

**Site Checkup Pro** is an SOP-driven security checklist and hardening orchestrator designed for agencies, WordPress maintenance providers, and web developers.

Rather than trying to replace established firewalls like Wordfence or backup tools like UpdraftPlus, Site Checkup Pro wraps and orchestrates your agency's entire security hardening Standard Operating Procedure (SOP) from end-to-end:

* **Level A (One-Click Safe Hardening):** Disable XML-RPC, restrict unauthenticated REST user enumeration, hide PHP versions, enforce clickjacking protection (X-Frame-Options), MIME sniffing protection, HSTS, and block sensitive webroot files.
* **Level B (Guided Actions & Bridges):** Seamlessly detects and bridges to mature, audited plugins (WPS Hide Login, Wordfence, Two-Factor Authentication, and Backup plugins) with recommended setting checklists.
* **Level C (Manual Audits & Reminders):** Track 15-day credential rotations (cPanel, WordPress admin), staging site HTTP basic authentication, and 6-month Google Search Console URL removal reviews with automated dashboard reminder notices.
* **Level D (Executive Client Reports):** Generate print-ready HTML and PDF audit summaries detailing completed hardening, SOP coverage percentage, and timestamped audit logs for client handoff.

### Built with Enterprise Safeguards

* **Server Environment Aware:** Automatically detects Apache/LiteSpeed vs Nginx. If your site runs on Nginx, .htaccess tasks display copyable Nginx server directives rather than false positive green checkmarks.
* **Zero Secret Leakage:** Audit logs strictly redact passwords, auth keys, and database credentials. Constant changes log only boolean states; salt rotation logs zero key material.
* **Pre-Execution Diff Previews:** Inspect the exact marker-delimited configuration blocks before writing to `.htaccess` or `wp-config.php`.
* **Staging-Safe Health Checks:** Automatic loopback tests detect staging HTTP Basic Auth walls and only roll back upon true 5xx server errors.
* **Safe Plugin Deletion:** Unwanted migration tools are zipped to `wp-content/uploads/wpsg-backups/plugins/` before deletion, making Undo 100% genuine.
* **Fail-Safe Emergency Lockout Recovery:** Custom login renamer contains zero query-string backdoors. Recovery is exclusively controlled via the `WPSG_DISABLE_LOGIN_RENAME` constant in `wp-config.php`.

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
4. Client-facing executive SOP coverage report ready for PDF export.

== Changelog ==

= 1.0.0 =
* Initial release.
* Added Task Registry with 30 SOP checklist items.
* Implemented client-driven sequential batch runner.
* Added .htaccess and wp-config.php marker managers with health checks and diff preview.
* Integrated baseline drift scanner for rogue admins and wp_options.
* Added Nginx server detection and snippet exporter.
* Added 12-month audit log retention and 15-day / 6-month scheduler.
