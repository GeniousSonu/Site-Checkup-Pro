# Site Checkup Pro

[![License: GPL v2](https://img.shields.io/badge/License-GPL%20v2-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-blue)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple)](https://php.net)

**Site Checkup Pro** is an SOP-driven security checklist and hardening orchestrator for WordPress, designed for agencies, maintenance providers, and developers. It translates complex security procedures into an actionable, audited dashboard with automated safe fixes, guided plugin bridges, recurring maintenance tracking, and executive client reports.

Developed and maintained by **[SK Sahinur Islam](https://www.genioussonu.me/)**.

Official Plugin Homepage: [https://www.genioussonu.me/plugin/site-checkup-pro/](https://www.genioussonu.me/plugin/site-checkup-pro/)

---

## ✨ Features

- **Level A (Safe Automation):** One-click fixes for XML-RPC, user enumeration, server headers, sensitive file exposure, `WP_DEBUG_DISPLAY`, and security headers.
- **Level B (Guided Bridges):** Deep integrations and configuration verification with Wordfence, WPS Hide Login, 2FA, and backup solutions.
- **Level C (Manual & Cadence Tracking):** Automated 15-day and 90-day reminders for cPanel/Admin password rotation, quarterly backup restore tests, and GSC reviews.
- **Level D (Executive Client Reports):** Print-ready, executive audit reports detailing SOP compliance, active controls, and incident response contacts.
- **Vulnerability Intelligence:** Integrates with Patchstack database API with confidence scoring (`high`, `medium`, `unverified/low`).
- **Server Environment Scanners:** True multi-protocol TLS probe (`TLSv1.0` through `TLSv1.3`), certificate chain validation, file permissions auditing (strict 640 for `wp-config.php`), PHP restrictions (`disable_functions`), and SPF/DMARC email health checks.
- **RFC 9116 `security.txt`:** One-click generation of `/.well-known/security.txt` with document root confinement.

---

## 🚀 Installation

1. Clone or download this repository into your `/wp-content/plugins/` directory:
   ```bash
   git clone https://github.com/GeniousSonu/site-checkup-pro.git wp-content/plugins/site-checkup-pro
   ```
2. Activate the plugin in **Plugins** within your WordPress admin dashboard.
3. Access **Site Checkup** from the admin navigation.

---

## 🛡️ Security Policy

We treat security with the utmost urgency. If you discover a vulnerability, please do not disclose it publicly. Review our contact information at `/.well-known/security.txt` or report it to:
- **Email:** `security@genioussonu.me`
- **Responsible Disclosure:** [https://www.genioussonu.me/plugin/site-checkup-pro/security/](https://www.genioussonu.me/plugin/site-checkup-pro/security/)

---

## 👤 Author & Attribution

- **Author:** SK Sahinur Islam
- **Website:** [https://www.genioussonu.me/](https://www.genioussonu.me/)
- **GitHub:** [@GeniousSonu](https://github.com/GeniousSonu/)
- **WordPress.org Profile:** [genioussonu](https://profiles.wordpress.org/genioussonu/)

---

## 📄 License

Site Checkup Pro is open-source software licensed under the [GNU General Public License v2 or later](LICENSE).
