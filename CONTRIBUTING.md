# Contributing to Site Checkup Pro

Thank you for your interest in contributing to Site Checkup Pro!

## Development Guidelines

1. **Adhere to WordPress Coding Standards (WPCS):** Ensure all PHP complies with WordPress core formatting and security rules.
2. **Security-First Architecture:**
   - Every state-changing action must follow the **Validate &rarr; Authorize &rarr; Perform &rarr; Verify &rarr; Log &rarr; Recover** pipeline.
   - Any REST endpoint accepting options must enforce a hardcoded key allowlist with explicit per-key sanitization.
   - All filesystem writes must check path confinement and create pre-flight backups.
3. **Internationalization (i18n):** Wrap all user-visible strings with `__()` or `_e()` using the `'site-checkup-pro'` text domain.
4. **Testing:** Run `python3 tests/validate_codebase.py` and `php tests/test-suite.php` before submitting pull requests.

## Reporting Issues

Please use our GitHub Issue templates to report bugs or submit feature suggestions.
