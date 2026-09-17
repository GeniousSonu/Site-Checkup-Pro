# WordPress.org Official Submission Guide

> **Site Checkup Pro** — WordPress Plugin Directory Submission Protocol  
> Submitting Account: **`genioussonu`** (`https://profiles.wordpress.org/genioussonu/`)  
> Target Plugin Slug: **`site-checkup-pro`**

---

## 1. Pre-Submission Quality Gate Verification

Before uploading to `wordpress.org/plugins/developers/add/`, verify that every single item below is satisfied:

| Checkpoint | Requirement | Verification Command / File |
| :--- | :--- | :--- |
| **Version Alignment** | Header Version = WPSG_VERSION = Stable tag = 1.0.0 | `php bin/check-version-consistency.php --tag=v1.0.0` |
| **Syntax & Lint** | Zero parse errors, balanced braces across all 48 PHP files | `python3 tests/validate_codebase.py` |
| **Unit Test Suite** | 100% pass rate across all 26 security checks | `php tests/test-suite.php` |
| **Trialware Compliance** | Zero disabled features, zero license locks, 100% free functionality | Audited: `includes/class-task-registry.php` |
| **Third-Party Disclosures** | Documented WordPress.org & Patchstack API connections | Audited: `readme.txt` line 38 |
| **Directory Tags** | Strictly 5 confirmed high-traffic search terms | `security, hardening, security audit, login security, firewall` |
| **Exclusions** | No tests, docs, or dev files in release zip | Verified via `.distignore` |

---

## 2. Step-by-Step Submission Walkthrough

### Step 1: Log in with the Verified Account
1. Open your browser and navigate to `https://login.wordpress.org/`.
2. Sign in using the **`genioussonu`** WordPress.org account.
   *(Confirm the profile matches `https://profiles.wordpress.org/genioussonu/`)*.

### Step 2: Access the Plugin Add Form
1. Navigate directly to:  
   👉 **`https://wordpress.org/plugins/developers/add/`**
2. Read the developer agreement confirming you have the right to distribute under GPLv2 or later.

### Step 3: Upload the Production Zip
1. Generate the distribution package:
   ```bash
   mkdir -p build/site-checkup-pro
   rsync -rc --exclude-from='.distignore' ./ build/site-checkup-pro/
   cd build && zip -r ../site-checkup-pro.zip site-checkup-pro && cd ..
   ```
2. Click **Choose File** on the WordPress.org submission form.
3. Select `site-checkup-pro.zip`.
4. Click **Upload Plugin**.

### Step 4: Submission Confirmation & Review Queue
1. The automated linter will perform an instant pre-check on `readme.txt` and `site-checkup-pro.php`.
2. Upon successful upload, you will receive an on-screen confirmation and an email notification with a ticket link:  
   `https://plugins.trac.wordpress.org/ticket/...`
3. **Review Window**: Manual human review by the WordPress Plugin Review Team typically takes **2 to 7 business days**.

---

## 3. Post-Approval: SVN Setup & Automated Deployment

Once your plugin is approved, you will receive an SVN repository at:  
`https://plugins.svn.wordpress.org/site-checkup-pro/`

### Step 1: Configure GitHub Repository Secrets
Navigate to **GitHub &rarr; Settings &rarr; Secrets and variables &rarr; Actions** and add:
- `SVN_USERNAME`: `genioussonu`
- `SVN_PASSWORD`: Your WordPress.org password (or dedicated SVN app password)

### Step 2: Triggering Automated Releases
Every future release is cut automatically by pushing a git tag:
```bash
git tag v1.0.1
git push origin v1.0.1
```
The GitHub Actions `deploy.yml` workflow will automatically:
1. Verify version consistency.
2. Build the `.distignore`-compliant package.
3. Commit directly to SVN `trunk` and create `/tags/1.0.1/`.
4. Create a matching GitHub Release with the attached zip file.
