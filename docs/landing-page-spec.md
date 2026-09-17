# Landing Page Specification: Site Checkup Pro

> **Target URL**: `https://www.genioussonu.me/plugin/site-checkup-pro/`  
> **Author**: SK Sahinur Islam  
> **Source Repository**: `https://github.com/GeniousSonu/site-checkup-pro`

---

## 1. Objectives & Distribution Strategy

The landing page provides an immediate self-hosted distribution portal for **Site Checkup Pro v1.0.0** while WordPress.org directory review is underway, and functions as the ongoing canonical product homepage thereafter.

### Download Strategy: Explicit GitHub Releases Asset
- **Download Button Link**:  
  `https://github.com/GeniousSonu/site-checkup-pro/releases/latest/download/site-checkup-pro.zip`
- **Why Direct GitHub Release Asset?**:
  - Global edge CDN distribution with zero server bandwidth load on `genioussonu.me`.
  - Guarantees 100% SHA-256 binary parity between the tagged git release, self-hosted downloads, and WordPress.org submission.
  - Automatic fallback to previous versions via GitHub Releases archive.
  - No fragile FTP/SSH deploy scripts required to synchronize zips to the web host.

---

## 2. Page Structure & Content (Single Source of Truth)

All feature copy is strictly derived from the audited `readme.txt` description to ensure single-source consistency:

### A. Hero Section
- **Headline**: `Site Checkup Pro – WordPress Security Audit & Site Hardening`
- **Sub-headline**: `Complete security audit, site hardening checklist, and vulnerability scanner for WordPress. One-click safe hardening, login protection, and client reports.`
- **Call to Action**: `Download v1.0.0 (.zip)` linking to the latest release asset.
- **Secondary Action**: `View on GitHub` linking to `https://github.com/GeniousSonu/site-checkup-pro`.
- **Trust Badges**:
  - PHP 7.4 - 8.3 Ready
  - WordPress 5.8 - 6.7 Tested
  - 100% GPLv2 Free & Open Source
  - Zero Upsell Bloat / No Trialware

### B. Core Capabilities (Sourced from readme.txt)
1. **Level A (Safe Automation & Scanners)**:
   - Disable XML-RPC, restrict unauthenticated REST user enumeration, hide PHP headers, enforce HSTS / MIME / X-Frame-Options, disable front-end `WP_DEBUG_DISPLAY`, audit file permissions (strict 640 baseline on `wp-config.php`), probe TLS protocols (`TLSv1.0` - `TLSv1.3`), and inspect certificate chain depth.
2. **Level B (Guided Actions & Bridges)**:
   - Deep integration bridges for mature plugins: WPS Hide Login, Wordfence, Two-Factor Authentication, and backup solutions. RFC 9116 `/.well-known/security.txt` responsible disclosure generation.
3. **Level C (Manual Audits & Recurring Reminders)**:
   - Track 15-day credential rotations, quarterly (90-day) backup restore test rehearsals, and GSC reviews with automated dashboard reminder notices.
4. **Level D (Executive Client Reports)**:
   - Generate print-ready HTML and PDF audit summaries detailing completed hardening, SOP coverage percentage, emergency incident response contacts, and timestamped audit logs for client handoff.

### C. Installation Instructions (Plain English)
1. Download `site-checkup-pro.zip` using the button above.
2. In your WordPress admin dashboard, navigate to **Plugins &rarr; Add New &rarr; Upload Plugin**.
3. Choose the downloaded `.zip` file and click **Install Now**.
4. Click **Activate Plugin**.
5. Open the **Site Checkup** menu in your WordPress admin to begin your audit.

### D. Footer Links
- Documentation: `/plugin/site-checkup-pro/docs/`
- Support: `/plugin/site-checkup-pro/support/`
- Changelog: `/plugin/site-checkup-pro/changelog/`
- Privacy Policy: `/plugin/site-checkup-pro/privacy-policy/`
- Author Homepage: `https://www.genioussonu.me/`
