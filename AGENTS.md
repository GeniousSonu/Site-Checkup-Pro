# Agent Instructions & Project Guidelines

## Release Management & Version Bumping Protocol

Before every tagged release, strictly adhere to the following rules:

1. **Four-Point Version Synchronization**:
   - Bump and synchronize the version string in four places to match exactly:
     - Plugin header `Version:` and `WPSG_VERSION` constant in `site-checkup-pro.php`.
     - `Stable tag:` field in `readme.txt`.
     - `version` field in `update-info.json` (auto-generated via `php bin/generate-update-info.php`).
     - Git tag itself (prefixed with `v`, e.g., `v1.0.2`).

2. **Semantic Versioning**:
   - **PATCH (`x.y.Z`)**: Strictly for bug/security fixes only.
   - **MINOR (`x.Y.z`)**: For new, backward-compatible features.
   - **MAJOR (`X.y.z`)**: For breaking changes.

3. **Security Patch Isolation**:
   - Never bundle a security-relevant fix into the same release as an in-progress feature.
   - Ship security patches alone, as their own patch-version release, so users can update for safety without pulling in untested functionality.

4. **Release Notes from `readme.txt`**:
   - Pull the GitHub Release description directly from the corresponding `readme.txt` changelog entry (`= X.Y.Z =`) rather than writing separate release notes.

5. **Pre-Tag CI Verification**:
   - Always confirm the version-consistency check passes before pushing the tag:
     ```bash
     php bin/check-version-consistency.php --tag=vX.Y.Z
     ```
