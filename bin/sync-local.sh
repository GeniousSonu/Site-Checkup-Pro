#!/usr/bin/env bash
# ==============================================================================
# Site Checkup Pro — Local Environment Synchronizer
#
# Copies files from this Git repository into LocalWP site directories.
# NOTE: Do NOT use symlinks to external directories because LocalWP's web server
# / container environment cannot traverse paths outside the site root.
# ==============================================================================

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"

TARGETS=(
  "/home/dayshift/Local Sites/test111/app/public/wp-content/plugins/site-checkup-pro"
  "/home/dayshift/Local Sites/test111/app/public/site2/wp-content/plugins/site-checkup-pro"
  "/home/dayshift/Local Sites/test/app/public/wp-content/plugins/site-checkup-pro"
)

SYNCED=0
cd "${REPO_DIR}"

for TARGET in "${TARGETS[@]}"; do
  PARENT_DIR="$(dirname "${TARGET}")"
  if [ -d "${PARENT_DIR}" ]; then
    # If it is currently a symlink, remove it
    if [ -L "${TARGET}" ]; then
      echo "Removing broken symlink at: ${TARGET}"
      rm -f "${TARGET}"
    fi

    mkdir -p "${TARGET}"
    echo "Syncing plugin files to: ${TARGET}..."
    rsync -rc --delete \
      --exclude-from='.distignore' \
      ./ "${TARGET}/"
    echo "✓ Synced successfully to: ${TARGET}"
    SYNCED=$((SYNCED + 1))
  fi
done

if [ ${SYNCED} -eq 0 ]; then
  echo "Warning: No LocalWP target plugin directories found."
  exit 1
fi

echo "======================================================="
echo " Local sync complete (${SYNCED} site(s) updated)."
echo "======================================================="
