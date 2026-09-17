# Architecture Reference: Free vs. Pro Division & Add-on Extensibility

> **Site Checkup Pro** — Future Commercial Architecture Reference  
> Author: **SK Sahinur Islam** (`https://www.genioussonu.me/`)

---

## 1. Guiding Philosophy: Zero Trialware in the Free Core

Per **WordPress.org Guideline 5**, the free plugin must be 100% complete and fully functional on its own:
- **Zero Crippled Features**: No disabled checkboxes with padlock icons saying "Buy Pro to enable".
- **Zero Artificial Limiters**: No "trial period" after which scans stop working.
- **Clean Separation**: Any future commercial features must live in an entirely separate add-on plugin (e.g. `site-checkup-pro-agency` or `site-checkup-pro-pro`), leaving the free plugin completely unencumbered and perpetually open source under GPLv2.

---

## 2. Planned Free vs. Pro Feature Division

| Capability Area | Core Free Plugin (`site-checkup-pro`) | Future Pro Add-on (`site-checkup-pro-agency`) |
| :--- | :--- | :--- |
| **SOP Checklist & Hardening** | All 56 security checkup tasks, safe automated fixes, markers, and rollbacks. | Custom agency checklist builder (create agency-specific tasks with custom documentation). |
| **Vulnerability Intelligence** | On-demand Patchstack vulnerability check with match-confidence scoring. | Automated background vulnerability monitoring with instant email/Slack alerts upon new CVE releases. |
| **Client Audit Reports** | Full SOP coverage report with print/PDF export and manual agency name entry. | **White-labeling**: custom logo branding, custom CSS styling, automated weekly/monthly email PDF delivery to clients. |
| **Multi-Site & Fleet** | Single-site administrative hardening. | Multi-site network dashboard & central management across agency client fleets. |
| **Alerting & Escalations** | Standard webhook dispatch on security events (Slack/Discord payload). | Multi-tier incident escalation: SMS/PagerDuty routing, automated staging branch lockdown. |
| **Licensing Engine** | Powered by `plugin-update-checker` against `genioussonu.me`. | Software licensing verification (EDD Software Licensing / LemonSqueezy / WooCommerce API). |

---

## 3. Extensible Add-on Integration Pattern

The free plugin provides clean hook points so the Pro add-on integrates seamlessly **without modifying core code**:

### Registering Pro Tasks via `wpsg_register_tasks`
```php
/**
 * In site-checkup-pro-agency/class-pro-addon.php:
 */
add_action( 'wpsg_register_tasks', 'wpsg_pro_register_agency_tasks' );

function wpsg_pro_register_agency_tasks( $registry ) {
    $registry->register( new WPSG_Task( array(
        'id'               => 'agency_automated_waf_tuning',
        'section'          => 'hardening',
        'title'            => __( 'Agency Cloudflare WAF Auto-Sync', 'site-checkup-pro-agency' ),
        'description'      => __( 'Synchronizes blocked IPs directly with Cloudflare API.', 'site-checkup-pro-agency' ),
        'automation_level' => 'A',
        'sub_type'         => 'instant',
        'status_callback'  => 'wpsg_pro_check_waf_sync',
        'run_callback'     => 'wpsg_pro_run_waf_sync',
    ) ) );
}
```

### Custom Report Branding via Filters
```php
// White-label report company name and header
add_filter( 'wpsg_report_branding', function( $branding ) {
    $branding['agency_logo'] = get_option( 'wpsg_pro_agency_logo' );
    $branding['white_label'] = true;
    return $branding;
} );
```

This guarantees architectural longevity: the free plugin remains clean, fast, and 100% compliant with WordPress.org guidelines, while the Pro add-on can be plugged in anytime without refactoring.
