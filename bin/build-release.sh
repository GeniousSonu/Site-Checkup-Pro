#!/usr/bin/env bash
# ==============================================================================
# Site Checkup Pro — Dual Distribution Release Builder
#
# Builds two distinct release packages:
# 1. Self-Hosted (genioussonu.me / GitHub Releases):
#    - Includes in-dashboard update checker (class-update-checker.php).
#    - Output: site-checkup-pro.zip & site-checkup-pro-selfhosted.zip
#
# 2. WordPress.org Directory Release:
#    - Complies with Guideline 8: completely strips class-update-checker.php.
#    - Output: site-checkup-pro-wporg.zip & directory build/wporg/site-checkup-pro/
# ==============================================================================

set -e

VERSION="${1:-1.0.0}"
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

echo "======================================================="
echo " Building Site Checkup Pro v${VERSION}"
echo "======================================================="

cd "${ROOT_DIR}"

# 1. Verify version consistency before building
if command -v php >/dev/null 2>&1; then
    php bin/check-version-consistency.php --tag="v${VERSION}"
elif [ -f "/home/dayshift/.config/Local/lightning-services/php-8.2.29+0/bin/linux/bin/php" ]; then
    LD_LIBRARY_PATH=/home/dayshift/.config/Local/lightning-services/php-8.2.29+0/bin/linux/shared-libs /home/dayshift/.config/Local/lightning-services/php-8.2.29+0/bin/linux/bin/php bin/check-version-consistency.php --tag="v${VERSION}"
fi

# Clean previous build artifacts and old root zip variants
rm -rf build/
rm -f site-checkup-pro-selfhosted.zip site-checkup-pro-wporg.zip
mkdir -p build/self-hosted/site-checkup-pro
mkdir -p build/wporg/site-checkup-pro

# 2. Build Target A: Self-Hosted Release (includes Update Checker)
echo "Packaging Target 1: Self-Hosted (with Update Checker)..."
rsync -rc --exclude-from='.distignore' ./ build/self-hosted/site-checkup-pro/

cd build/self-hosted
zip -r ../site-checkup-pro-selfhosted.zip site-checkup-pro -x "*.DS_Store"
cd "${ROOT_DIR}"

# 3. Build Target B: WordPress.org Release (Guideline 8 Compliant: Strips Update Checker)
echo "Packaging Target 2: WordPress.org Compliant (stripped update-checker)..."
rsync -rc --exclude-from='.distignore' ./ build/wporg/site-checkup-pro/

# Strip update checker completely from WP.org build target
rm -f build/wporg/site-checkup-pro/includes/class-update-checker.php

cd build/wporg
zip -r ../site-checkup-pro-wporg.zip site-checkup-pro -x "*.DS_Store"
# Copy the clean, fully compliant WP.org package as the official root release with the actual plugin name
cp ../site-checkup-pro-wporg.zip "${ROOT_DIR}/site-checkup-pro.zip"
cd "${ROOT_DIR}"

echo ""
echo "======================================================="
echo " Build Complete:"
echo " Official Package: site-checkup-pro.zip ($(du -h site-checkup-pro.zip | cut -f1)) [WP.org Compliant, Latest]"
echo " WP.org Build Dir: build/wporg/site-checkup-pro/"
echo " Self-Hosted Zip:  build/site-checkup-pro-selfhosted.zip ($(du -h build/site-checkup-pro-selfhosted.zip | cut -f1))"
echo "======================================================="
