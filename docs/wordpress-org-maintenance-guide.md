# WordPress.org Plugin Directory Optimization (ASO) & Maintenance SOP

> **Site Checkup Pro** — WordPress Plugin Directory Discoverability & Maintenance Manual  
> Author: **SK Sahinur Islam** (`https://www.genioussonu.me/`)

---

## 1. How the WordPress.org Search Algorithm Actually Works

WordPress.org plugin search is open-source (maintained across `meta.trac.wordpress.org`). Independent algorithmic analysis and trac ticket history confirm that the ranking function is an aggregate formula combining the following discrete signals:

### A. Term-by-Term Keyword Relevance
- **Multi-Word Query Splitting**: Searches like `"security checkup"` or `"site hardening"` are parsed and scored term-by-term (e.g. `security` AND `hardening`).
- **High-Weight Real Estate**:
  1. **Plugin Title & Tagline**: Heavily weighted. Having the primary category terms (`Security Audit`, `Site Hardening`) in the title line dramatically boosts score.
  2. **Short Description**: First sentence shown in search cards. Must naturally lead with primary keywords.
  3. **Plugin Tags (Max 5)**: Explicit category indexes matching search terms (`security`, `hardening`, `security audit`, `login security`, `firewall`).
  4. **Long Description Opening**: The first 1-2 paragraphs of `== Description ==` are weighted significantly higher than content buried under headers.

### B. Active Installs (Log-Scaled)
- The algorithm uses a **log-scaled install metric** (`log(active_installs)`).
- This ensures genuine install milestones (e.g., 10+, 100+, 1,000+, 10,000+) improve ranking, but prevents multi-million install incumbents from monopolizing every query.
- *Strict Rule*: Never attempt artificial or bot installs. WordPress.org monitors telemetry patterns, and unnatural spikes trigger manual security review and immediate plugin de-listing.

### C. Ratings & Reviews (Square-Root Weighted)
- Average review score is square-root weighted (`sqrt(reviews)`).
- Steady, authentic 5-star reviews from satisfied developers provide lasting rank momentum.
- Reviews must be collected ethically via non-nagging milestone prompts, never through incentives or paid solicitations.

### D. Compatibility & Recency Signals
- **"Tested up to" Tag**: Plugins not tested with the current major WordPress release are penalized in search results and eventually flagged with compatibility warning notices.
- **Update Frequency**: Regular releases (bug fixes, rule updates, vulnerability database improvements) maintain search freshness scoring.
- **Support Forum Resolution Ratio**: The ratio of support threads marked **"Resolved"** is a scored algorithmic input. Plugins with ignored or abandoned threads suffer rank degradation.

---

## 2. Competitive Tag & Positioning Baseline

Based on live WordPress.org directory analysis of top security solutions:

| Plugin | Active Installs | Core Tags Used on WP.org | Positioning |
| :--- | :--- | :--- | :--- |
| **Wordfence Security** | 5,000,000+ | `security`, `firewall`, `malware`, `scanner`, `2fa` | Heavy endpoint WAF & malware scanner |
| **All-In-One Security (AIOS)** | 1,000,000+ | `security`, `firewall`, `login security`, `two factor authentication` | Broad security score & basic hardening |
| **Sucuri Security** | 700,000+ | `security`, `firewall`, `malware`, `scan`, `spam` | Integrity monitoring & incident response |
| **Solid Security (iThemes)** | 800,000+ | `security`, `brute force protection`, `malware`, `two factor authentication` | Access control, passkeys & user security |
| **Site Checkup Pro** | *New Release* | `security`, `hardening`, `security audit`, `login security`, `firewall` | **SOP-driven security checklist orchestrator**, safe automated hardening, vulnerability intelligence & client reports |

### Naming & Title Strategy
- **Distinctive Brand Name**: `Site Checkup Pro` (fully compliant with WP.org non-generic branding rules).
- **Directory Search Headline** (`readme.txt`): `=== Site Checkup Pro – WordPress Security Audit & Site Hardening ===` (combines brand identity with high-volume search terms `Security Audit` and `Site Hardening`).

---

## 3. Maintenance Habits & Operational SOPs

To maintain high directory ranking and community trust, follow these standing operational habits:

### SOP 1: "Tested Up To" Release Discipline
- **Window**: Within **14 days** of every major WordPress core release (e.g. 6.8, 6.9, 7.0).
- **Action**:
  1. Test plugin against the new WP release in a local environment.
  2. Run `tests/test-suite.php` and `python3 tests/validate_codebase.py`.
  3. Bump `Tested up to: X.X` in `readme.txt`.
  4. Tag and deploy update to WordPress.org SVN.

### SOP 2: Changelog Quality Cadence
- Never publish empty or vague changelog entries (e.g. "bug fixes").
- Document concrete technical improvements (e.g., `* Added strict file permission auditing for 0640 wp-config.php`, `* Updated Patchstack vulnerability intelligence cache TTL`).
- Search engines and WP.org directory indexing weigh detailed changelogs as proof of active maintenance.

### SOP 3: Support Thread Resolution Protocol
- **SLA**: Triage new support threads within 24–48 hours.
- **Resolution Step**: Once a user's question or issue is addressed, **always mark the thread status as "Resolved"** in the forum sidebar.
- Maintaining an **85%+ resolution ratio** guarantees full algorithmic support credit.

### SOP 4: In-Plugin Review Prompt Etiquette
- Site Checkup Pro includes a built-in review banner triggered **only after 3 successful security checks**.
- **Rules Enforced**:
  - Displayed exclusively inside the Site Checkup Pro admin screen (never across the broader WordPress dashboard).
  - Single, permanent dismiss button (`wpsg_review_prompt_dismissed`).
  - No recurring nags, time-delayed re-prompts, or obstructive modals.
  - Transparent direct link to `https://wordpress.org/support/plugin/site-checkup-pro/reviews/#new-post`.

---

## 4. Prohibited Tactics (Do NOT Attempt)

1. **No Keyword Stuffing**: Do not repeat keywords artificially in headings or text blocks.
2. **No Fake Installs**: Do not script automated downloads or dummy installations.
3. **No Review Incentives**: Never offer pro features, discounts, or services in exchange for positive reviews.
4. **No Hidden Telemetry**: All external HTTP connections (Patchstack, WordPress.org API) must remain documented in the `== Third Party Services ==` section of `readme.txt`.
