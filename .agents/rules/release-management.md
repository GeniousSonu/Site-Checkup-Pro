# Release Management & Version Bumping Protocol

Follow this protocol strictly before creating any tagged release for Site Checkup Pro:

## 1. Four-Point Version Synchronization
Before creating any git tag, bump and synchronize the version number across all four locations so they match exactly:
1. **Plugin Header `Version:` field** and `WPSG_VERSION` constant in `site-checkup-pro.php`.
2. **`Stable tag:` field** in WordPress.org `readme.txt`.
3. **`version` field** in `update-info.json` (auto-generated via `php bin/generate-update-info.php`).
4. **Git Release Tag** itself (prefixed with `v`, e.g., `v1.0.2`).

## 2. Semantic Versioning (SemVer) Rules
- **PATCH (`x.y.Z`)**: Strictly for bug fixes and security hotfixes.
- **MINOR (`x.Y.z`)**: For new, backward-compatible features.
- **MAJOR (`X.y.z`)**: For breaking changes or major architecture shifts.

## 3. Security Patch Isolation
- **NEVER** bundle a security-relevant fix into the same release as an in-progress feature.
- Always ship security patches alone as their own standalone patch release (e.g., `v1.0.1`), allowing users and site administrators to update immediately for safety without pulling in untested or experimental functionality.

## 4. Release Notes from `readme.txt`
- Pull the GitHub Release description directly from the corresponding version entry (`= X.Y.Z =`) in `readme.txt` under `== Changelog ==` rather than writing separate release notes.

## 5. Pre-Deployment Verification
- Ensure all automated checks pass before any tag push triggers deployment:
  ```bash
  php bin/check-version-consistency.php --tag=vX.Y.Z
  python3 tests/validate_codebase.py
  php tests/test-suite.php
  ```
