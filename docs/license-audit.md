# Site Checkup Pro — Comprehensive License & Asset Audit

This document fulfills **WordPress.org Plugin Guideline 2** ("Your plugin must be compatible with the GNU General Public License") and records the license audit for every file, asset, and script distributed in Site Checkup Pro.

---

## 1. Core Source Code

| File / Component | License | Author / Copyright | GPLv2+ Compatible | Notes |
| :--- | :--- | :--- | :--- | :--- |
| `site-checkup-pro.php` | GPLv2 or later | SK Sahinur Islam | **YES** | Main plugin bootstrap header |
| `includes/*.php` | GPLv2 or later | SK Sahinur Islam | **YES** | 24 core security scanner & hardening classes |
| `includes/integrations/*.php` | GPLv2 or later | SK Sahinur Islam | **YES** | Non-invasive plugin bridges (2FA, Wordfence, Backups) |
| `admin/*.php`, `admin/views/*.php` | GPLv2 or later | SK Sahinur Islam | **YES** | Dashboard, modal templates, report screens |
| `rest-api/*.php` | GPLv2 or later | SK Sahinur Islam | **YES** | Hardened REST controller with capability checks |
| `db/schema.php` | GPLv2 or later | SK Sahinur Islam | **YES** | Custom table DDL and migration routines |
| `uninstall.php` | GPLv2 or later | SK Sahinur Islam | **YES** | Clean removal routines |

---

## 2. Client-Side Assets (CSS & JavaScript)

| Asset File | License | Author / Source | GPLv2+ Compatible | Notes |
| :--- | :--- | :--- | :--- | :--- |
| `admin/assets/js/admin.js` | GPLv2 or later | SK Sahinur Islam | **YES** | 100% original, unminified, readable JavaScript |
| `admin/assets/js/report.js` | GPLv2 or later | SK Sahinur Islam | **YES** | 100% original, unminified, readable JavaScript |
| `admin/assets/css/admin.css` | GPLv2 or later | SK Sahinur Islam | **YES** | 100% original, unminified stylesheet |

---

## 3. Bundled Libraries & Dependencies Audit

Per **WordPress.org Guideline 13**, plugins must use WordPress core's bundled library copies rather than shipping vendored copies:

| Library | Distribution Method | Core Handle | Shipped in Plugin? | Compliance Status |
| :--- | :--- | :--- | :--- | :--- |
| **jQuery** | WordPress Core | `jquery` | **NO** | Compliant (not vendored; native DOM used) |
| **wp-api-fetch** | WordPress Core | `wp-api-fetch` | **NO** | Compliant (core script handle enqueued) |
| **Dashicons** | WordPress Core | `dashicons` | **NO** | Compliant (core style handle enqueued) |
| **Update Checker** | Self-Hosted Target Only | N/A | **Self-Hosted Only** | **Guideline 8 Compliant:** Stripped completely from WordPress.org SVN release |

---

## 4. Media & Graphic Assets

| Asset File | Description | License | Author / Copyright | GPLv2+ Compatible |
| :--- | :--- | :--- | :--- | :--- |
| `media/badge-sop-verified.svg` | Executive report seal | GPLv2 or later | SK Sahinur Islam | **YES** |
| `media/empty-state.svg` | Zero-issue dashboard art | GPLv2 or later | SK Sahinur Islam | **YES** |
| `media/icon-backup.svg` | Modal action icon | GPLv2 or later | SK Sahinur Islam | **YES** |
| `media/icon-diff.svg` | Diff viewer action icon | GPLv2 or later | SK Sahinur Islam | **YES** |
| `media/icon-safe.svg` | Shield safety icon | GPLv2 or later | SK Sahinur Islam | **YES** |
| `media/icon.svg` | Main plugin icon | GPLv2 or later | SK Sahinur Islam | **YES** |
| `media/logo.svg` | Header brand logo | GPLv2 or later | SK Sahinur Islam | **YES** |
| `media/logo-white.svg` | Dark mode brand logo | GPLv2 or later | SK Sahinur Islam | **YES** |
| `media/menu-icon.svg` | Admin menu sidebar icon | GPLv2 or later | SK Sahinur Islam | **YES** |

*Note: High-resolution raster concept mockups (`media/*.png`) are excluded from release archives via `.distignore` to prevent distribution bloat.*

---

## 5. Typography & Fonts

| Font Family | License | Delivery Method | Compliance Notes |
| :--- | :--- | :--- | :--- |
| **Inter** | SIL Open Font License 1.1 | `@import url(fonts.googleapis.com)` / System Fallback | Permitted under Guideline 8 (font exception). Fallbacks to native OS typography stack. |

---

## 6. External Web Services (Guideline 6 & 7 Compliance)

| Service | Protocol | Data Transmitted | Consent Mechanism | Terms & Policy |
| :--- | :--- | :--- | :--- | :--- |
| **Patchstack Vulnerability API** | HTTPS REST | Plugin & theme slugs, version numbers | **Explicit Opt-In Checkbox in Settings** (Guideline 7) | [Patchstack Terms](https://patchstack.com/terms-of-service/) |
| **Security Webhooks** | HTTPS POST | Alert event name, site URL, timestamp | **Explicit Opt-In Checkbox in Settings** (Guideline 7) | User-defined webhook endpoint (SSRF-protected) |
| **WordPress.org Checksums API** | HTTPS REST | WordPress version, locale | Native Core Service (implicit) | [WordPress.org Privacy](https://wordpress.org/about/privacy/) |

---

## 7. Audit Sign-Off

- **Audit Date**: 2026-03-17
- **Plugin Version**: 1.0.0
- **Auditor**: SK Sahinur Islam (`genioussonu`)
- **Conclusion**: 100% of distributed files, scripts, styles, and assets are fully compliant with the GNU General Public License v2 or later and meet all WordPress.org submission requirements.
