# Standing Cross-Compatibility QA Matrix

> **Site Checkup Pro** — Pre-Release Compatibility Testing Checklist  
> Author: **SK Sahinur Islam** (`https://www.genioussonu.me/`)

Execute this matrix before every major release to verify zero regressions across WordPress themes, page builders, caching solutions, and hosting configurations.

---

## 1. Theme Compatibility Matrix

| Theme | Type | Critical Test Scenario | Expected Outcome | Pass/Fail |
| :--- | :--- | :--- | :--- | :---: |
| **Twenty Twenty-Five** | Block (FSE) | Full Site Editing, Template Parts, Admin Dashboard | Clean dashboard rendering, zero asset conflicts | ✅ |
| **Twenty Twenty-Four** | Block (FSE) | Default core block styling & theme fonts | Clean dashboard rendering, zero typography collision | ✅ |
| **Astra** | Classic / Hybrid | Customizer options, Header/Footer builder | No interference with Astra admin or settings | ✅ |
| **GeneratePress** | Lightweight Classic | Premium modules, Elements engine | Flawless dashboard operation, zero CSS leaks | ✅ |
| **OceanWP** | Feature-rich Classic | Ocean Extra scripts, metaboxes | Modals open cleanly, no z-index stacking conflicts | ✅ |

---

## 2. Page Builder Neutrality

*Architecture Guarantee*: Site Checkup Pro registers zero front-end rendering filters (`the_content`, `template_include`).

| Builder | Test Environment | Verification Step | Pass/Fail |
| :--- | :--- | :--- | :---: |
| **Core Gutenberg / FSE** | WP 6.7 Block Editor | Open page editor; confirm zero JavaScript errors in console | ✅ |
| **Elementor (Free + Pro)** | Visual Editor | Edit page with Elementor; confirm editor canvas loads without delay | ✅ |
| **Divi Builder** | Visual Builder | Launch front-end builder; verify zero script interference | ✅ |
| **Beaver Builder** | Front-end Canvas | Toggle Beaver Builder; confirm seamless module drag-and-drop | ✅ |
| **Bricks** | Vue-based builder | Open Bricks panel; confirm independent operation | ✅ |

---

## 3. Commerce & Performance Layers

| Solution | Category | Verification Scenario | Pass/Fail |
| :--- | :--- | :--- | :---: |
| **WooCommerce** | E-Commerce | Cart, checkout, and admin order screen functioning | ✅ |
| **WP Rocket** | Page Caching | HTML minify, critical CSS, page cache generation | ✅ |
| **W3 Total Cache** | Advanced Caching | Database & Object Cache drop-ins | ✅ |
| **LiteSpeed Cache** | Server Caching | LiteSpeed server tag purging & .htaccess rules | ✅ |

---

## 4. Hosting & Server Environments

| Environment | Architecture | Test Focus | Behavior Verified |
| :--- | :--- | :--- | :--- |
| **Apache 2.4** | `.htaccess` Enabled | Rule insertion, diff preview, markers | Full automated writing with atomic rollback |
| **LiteSpeed** | `.htaccess` Compatible | High-speed rule recognition | Full automated writing with immediate effect |
| **Nginx** | Reverse Proxy / FastCGI | `.htaccess` unsupported detection | Flags tasks as "Not Supported on Nginx"; provides 1-click copyable Nginx snippets |
| **Managed Read-Only** (WP Engine / Kinsta) | Read-only config | File permissions locked down | `WPSG_Compatibility_Guard` triggers graceful degradation with manual snippet notice |

---

## 5. WordPress.org SVN Dry-Run Testing

Before pushing your first production tag to WordPress.org, perform a local dry-run check:

1. **Verify SVN Export Structure**:
   ```bash
   mkdir -p /tmp/svn-test/trunk /tmp/svn-test/tags/1.0.0
   rsync -rc --exclude-from='.distignore' ./ /tmp/svn-test/trunk/
   ```
2. **Inspect File List**:
   Confirm that `/tmp/svn-test/trunk/` contains `site-checkup-pro.php`, `readme.txt`, `includes/`, `admin/`, `db/`, `rest-api/`, `languages/site-checkup-pro.pot`, and `media/` — with zero `.git/`, `.github/`, `tests/`, or `docs/`.
3. **Verify Asset Directory**:
   Confirm `media/` contains valid icons and banners for the WordPress.org directory display.
