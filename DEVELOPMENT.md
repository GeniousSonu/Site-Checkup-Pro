# Local Development & Testing Guide

## 1. Local WordPress Plugin Symlink Requirement

When testing Site Checkup Pro on a local development server (such as LocalWP, Docker, or native LAMP/LEMP stacks), the plugin folder inside `wp-content/plugins/` **must always be a symbolic link** to this git repository, never a manually copied or synced folder.

### Why this is critical
Manually copying files or keeping a static clone inside `wp-content/plugins/` causes silent divergence: code edits committed to the git repository will not be executed by the local server, leading to testing stale code and false bug reports.

### Setup Instructions

1. Remove any existing physical directory in the WordPress plugins folder:
   ```bash
   rm -rf "/path/to/local-site/app/public/wp-content/plugins/site-checkup-pro"
   ```

2. Create a symbolic link pointing to the repository root:
   ```bash
   ln -s "/path/to/repo/Site Checkup Pro" "/path/to/local-site/app/public/wp-content/plugins/site-checkup-pro"
   ```

3. Confirm the symlink resolves correctly:
   ```bash
   ls -la "/path/to/local-site/app/public/wp-content/plugins/site-checkup-pro"
   ```

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
