#!/usr/bin/env bash
# ==============================================================================
# GeniousSonu Site Checkup — Dual Distribution Release Builder
#
# Builds two distinct release packages:
# 1. Self-Hosted (genioussonu.me / GitHub Releases):
#    - Includes in-dashboard update checker (class-update-checker.php).
#    - Output: genioussonu-site-checkup.zip & genioussonu-site-checkup-selfhosted.zip
#
# 2. WordPress.org Directory Release:
#    - Complies with Guideline 8: completely strips class-update-checker.php.
#    - Output: genioussonu-site-checkup-wporg.zip & directory build/wporg/genioussonu-site-checkup/
# ==============================================================================

set -e

VERSION="${1:-1.0.0}"
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

echo "======================================================="
echo " Building GeniousSonu Site Checkup v${VERSION}"
echo "======================================================="

cd "${ROOT_DIR}"

# 1. Auto-generate update-info.json and verify version consistency before building
if command -v php >/dev/null 2>&1; then
    php bin/generate-update-info.php
    php bin/check-version-consistency.php --tag="v${VERSION}"
elif [ -f "/home/dayshift/.config/Local/lightning-services/php-8.2.29+0/bin/linux/bin/php" ]; then
    LD_LIBRARY_PATH=/home/dayshift/.config/Local/lightning-services/php-8.2.29+0/bin/linux/shared-libs /home/dayshift/.config/Local/lightning-services/php-8.2.29+0/bin/linux/bin/php bin/generate-update-info.php
    LD_LIBRARY_PATH=/home/dayshift/.config/Local/lightning-services/php-8.2.29+0/bin/linux/shared-libs /home/dayshift/.config/Local/lightning-services/php-8.2.29+0/bin/linux/bin/php bin/check-version-consistency.php --tag="v${VERSION}"
fi

# Clean previous build artifacts and old root zip variants
rm -rf build/
rm -f genioussonu-site-checkup-selfhosted.zip genioussonu-site-checkup-wporg.zip
mkdir -p build/self-hosted/genioussonu-site-checkup
mkdir -p build/wporg/genioussonu-site-checkup

# 2. Build Target A: Self-Hosted Release (includes Update Checker)
echo "Packaging Target 1: Self-Hosted (with Update Checker)..."
rsync -rc --exclude-from='.distignore' ./ build/self-hosted/genioussonu-site-checkup/

cd build/self-hosted
zip -r ../genioussonu-site-checkup-selfhosted.zip genioussonu-site-checkup -x "*.DS_Store"
cd "${ROOT_DIR}"

# 3. Build Target B: WordPress.org Release (Guideline 8 Compliant: Strips Update Checker)
echo "Packaging Target 2: WordPress.org Compliant (stripped update-checker)..."
rsync -rc --exclude-from='.distignore' ./ build/wporg/genioussonu-site-checkup/

# Strip update checker completely from WP.org build target
rm -f build/wporg/genioussonu-site-checkup/includes/class-update-checker.php
rm -rf build/wporg/genioussonu-site-checkup/includes/plugin-update-checker/

# Strip Tier 2 Nginx companion completely from WP.org build target
rm -f build/wporg/genioussonu-site-checkup/includes/class-nginx-tier2.php

# Strip internal dev and agent files completely from release builds
rm -rf build/wporg/genioussonu-site-checkup/.agents/
rm -f build/wporg/genioussonu-site-checkup/AGENTS.md
rm -f build/wporg/genioussonu-site-checkup/DEVELOPMENT.md
rm -f build/wporg/genioussonu-site-checkup/CONTRIBUTING.md
rm -f build/wporg/genioussonu-site-checkup/README.md
rm -rf build/self-hosted/genioussonu-site-checkup/.agents/
rm -f build/self-hosted/genioussonu-site-checkup/AGENTS.md
rm -f build/self-hosted/genioussonu-site-checkup/DEVELOPMENT.md
rm -f build/self-hosted/genioussonu-site-checkup/CONTRIBUTING.md
rm -f build/self-hosted/genioussonu-site-checkup/README.md

cd build/wporg
zip -r ../genioussonu-site-checkup-wporg.zip genioussonu-site-checkup -x "*.DS_Store"
# Copy the clean, fully compliant WP.org package as the official root release with the actual plugin name
cp ../genioussonu-site-checkup-wporg.zip "${ROOT_DIR}/genioussonu-site-checkup.zip"
cd "${ROOT_DIR}"

echo ""
echo "======================================================="
echo " Build Complete:"
echo " Official Package: genioussonu-site-checkup.zip ($(du -h genioussonu-site-checkup.zip | cut -f1)) [WP.org Compliant, Latest]"
echo " WP.org Build Dir: build/wporg/genioussonu-site-checkup/"
echo " Self-Hosted Zip:  build/genioussonu-site-checkup-selfhosted.zip ($(du -h build/genioussonu-site-checkup-selfhosted.zip | cut -f1))"
echo "======================================================="
