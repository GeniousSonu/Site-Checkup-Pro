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

All 47 tests must pass with zero failures.

---

## 3. Version Consistency Checks

Before tagging any release, ensure strict three-point version synchronization passes:

```bash
php bin/check-version-consistency.php --tag=vX.Y.Z
```
