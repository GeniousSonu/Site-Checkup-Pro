# Local Development & Testing Guide

## 1. Local WordPress Synchronization & Symlink Constraint

### ⚠️ Critical Constraint: Do NOT Use Symlinks Across Directory Trees
In LocalWP and containerized WordPress environments, the web server (Apache/Nginx) and PHP-FPM process run inside isolated mount/container namespaces. 
**Symbolic links from `wp-content/plugins/site-checkup-pro` to an external path (e.g. `~/Pictures/Site Checkup Pro`) are broken and invisible to the web server's PHP execution context.** 
While host-level CLI tools may resolve the symlink, real HTTP requests fail with `file_exists() === false`, causing WordPress to report *"Plugin file does not exist"* and automatically deactivate the plugin.

### Proper Local Synchronization (`bin/sync-local.sh`)
To keep the local testing site synchronized with this Git repository without manual copying or unreliable symlinks, use the automated sync tool:

```bash
./bin/sync-local.sh
```

This script:
1. Detects local test sites (e.g., `Local Sites/test111`, `Local Sites/test`).
2. Removes any broken or legacy symlinks.
3. Performs a clean mirror sync of repository files directly into `wp-content/plugins/site-checkup-pro/` while respecting `.distignore` rules.

---

## 2. Automated Test Suite

Before committing any changes, always run the automated PHP test suite:

```bash
php tests/test-suite.php
```

All 59 tests must pass with zero failures.

---

## 5. Architecture Roadmap & Feature Batch Notes

### v1.2.0 Developer Toolkit
- **REST API Security Auditor** (`class-rest-auditor.php`): Enumerates registered REST routes, analyzes callbacks, and detects risks.
- **Environment Badge** (`class-environment-badge.php`): Top admin bar badge (Production/Staging/Dev) with safety heuristics.
- **Diagnostic Snapshot** (`class-diagnostic-snapshot.php`): Sanitized Markdown export with secret/credential redaction.
- **WP-Cron Health Auditor** (`class-cron-auditor.php`): Flags overdue events (>600s late) and duplicate hooks.
- **Database Health Scanner** (`class-db-health-scanner.php`): Audits bloat; cleanup is gated by re-auth and backup validation.
- **Migration Serialization Readiness** (`class-migration-readiness.php`): Bounded scan for serialized URLs with WP-CLI guidance.
- **Weekly Changelog Digest** (`class-changelog-digest.php`): Aggregates update notices and schedules digest alerts.

### Phase 5 Roadmap
- **Multi-site Agency Dashboard**: Architecture explicitly deferred to Phase 5. Site Checkup Pro currently focuses on single-site and standard network node execution. Network-wide aggregated dashboards and cross-site management will be implemented as a dedicated Phase 5 module.

---

## 3. Version Consistency Checks

Before tagging any release, ensure strict Four-Point Version Synchronization passes:

```bash
php bin/check-version-consistency.php --tag=vX.Y.Z
```

This verifies that the version matches across `site-checkup-pro.php` (Header & `WPSG_VERSION`), `readme.txt` (`Stable tag`), `update-info.json` (`version`), and the Git release tag.

---

## 4. Workflows: Fast Local Dev Sync vs. Real Update-Flow Testing

It is critical to distinguish between the two distinct development workflows:

### Workflow A: Fast Local Dev Sync (Day-to-day Feature & Bugfix Iteration)
- **Purpose**: Rapidly develop and test code changes directly in the local WordPress environment without dealing with version bumps, zip packaging, or update notifications.
- **Mechanism**: Run `bin/sync-local.sh`.
- **Characteristics**:
  - Direct file-copy (`rsync`) from the repo directly into `wp-content/plugins/site-checkup-pro/`.
  - Unversioned, immediate feedback.
  - Zero involvement of the update checker.
  - Safe for active debugging.

### Workflow B: Real Update-Flow Testing (End-to-End User Update UX Verification)
- **Purpose**: Test the authentic self-hosted update experience exactly as an end-user admin experiences it in `wp-admin/plugins.php` and `wp-admin/update-core.php`.
- **Mechanism**: Uses `YahnisElsts/plugin-update-checker` reading from the remote metadata JSON file (`update-info.json`).
- **Step-by-Step Procedure**:
  1. **Install an Older Version**:
     In your test WordPress site, ensure the installed version of `site-checkup-pro` is older than the version declared in `update-info.json` (e.g., set `Version: 1.0.0` in `wp-content/plugins/site-checkup-pro/site-checkup-pro.php` and define `'WPSG_VERSION', '1.0.0'`).
  2. **Force WordPress Update Check**:
     In WordPress admin, go to **Dashboard &rarr; Updates** (`wp-admin/update-core.php`) and click **"Check Again"**, or run via WP-CLI:
     ```bash
     wp transient delete --all
     wp core check-update
     ```
  3. **Verify "Update Available" Notice**:
     Navigate to **Plugins &rarr; Installed Plugins** (`wp-admin/plugins.php`).
     Confirm the notice appears beneath Site Checkup Pro:
     > *"There is a new version of Site Checkup Pro available. View version X.X.X details or update now."*
  4. **Verify "View Version Details" Thickbox Modal**:
     Click **"View version X.X.X details"**. Confirm the Thickbox modal opens cleanly, populated with:
     - Plugin Name, Author, Active Version, and Latest Version.
     - **Description** tab with human-readable plugin summary.
     - **Changelog** tab showing human-readable bullet points parsed directly from `readme.txt`.
     - Official plugin banner and icon graphics.
  5. **Perform 1-Click Update**:
     Click **"Update Now"**. Verify that WordPress downloads the update package, unpacks it into the plugin folder, and confirms:
     > *"Plugin updated successfully."*
  6. **Security & Capability Gating**:
     Only users with the `update_plugins` capability can view update notices, open the update details modal, or execute plugin updates. Updating code on disk is a privileged action and strictly follows the same security and capability standards as other administrative operations.

