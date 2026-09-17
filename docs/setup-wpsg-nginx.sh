#!/usr/bin/env bash
# ==============================================================================
# Site Checkup Pro — Tier 2 Nginx Setup Companion Script
# ==============================================================================
#
# PURPOSE:
# Configures a dedicated, narrowly-scoped directory and sudoers rule allowing
# Site Checkup Pro to safely write Nginx hardening directives and trigger
# syntax-validated configuration reloads (Tier 2 automated apply).
#
# SECURITY BOUNDARY:
# - Run ONCE manually with 'sudo' by the server administrator or developer.
# - This script is distributed solely via the GitHub repository /docs directory.
# - The WordPress plugin NEVER executes this script or arbitrary elevated commands.
# - Sudoers privileges are restricted exclusively to target user 'root' and two
#   exact commands: 'nginx -t' and 'systemctl reload nginx'.
# ==============================================================================

set -euo pipefail

# Require root privileges to run setup
if [[ $EUID -ne 0 ]]; then
   echo "Error: This script must be run as root (e.g. sudo bash setup-wpsg-nginx.sh)" >&2
   exit 1
fi

echo "======================================================="
echo " Site Checkup Pro — Nginx Tier 2 Companion Setup"
echo "======================================================="

# Detect active web server user
WEB_USER=""
if id "www-data" &>/dev/null; then
    WEB_USER="www-data"
elif id "nginx" &>/dev/null; then
    WEB_USER="nginx"
elif id "nobody" &>/dev/null; then
    WEB_USER="nobody"
else
    echo "Warning: Could not automatically identify web server user (www-data, nginx, nobody)."
    read -rp "Please enter the web server username: " WEB_USER
    if ! id "$WEB_USER" &>/dev/null; then
        echo "Error: User '$WEB_USER' does not exist." >&2
        exit 1
    fi
fi

echo "[1/4] Web server user identified as: ${WEB_USER}"

# Define paths
CONF_DIR="/etc/nginx/site-checkup-pro"
STAGING_DIR="${CONF_DIR}/.staging"
MARKER_FILE="${CONF_DIR}/.wpsg-tier2-active"
SUDOERS_FILE="/etc/sudoers.d/wpsg-nginx"

# 1. Create include and staging directories with restricted permissions
echo "[2/4] Setting up include directory: ${CONF_DIR}"
mkdir -p "${STAGING_DIR}"
chown -R "root:${WEB_USER}" "${CONF_DIR}"
chmod 0750 "${CONF_DIR}"
chmod 0770 "${STAGING_DIR}"

# 2. Drop root-owned marker file
echo "[3/4] Creating root-owned verification marker file..."
touch "${MARKER_FILE}"
chown root:root "${MARKER_FILE}"
chmod 0644 "${MARKER_FILE}"

# 3. Create narrowly-scoped sudoers entry
echo "[4/4] Creating scoped sudoers rule in ${SUDOERS_FILE}..."
cat << EOF > "${SUDOERS_FILE}"
# Site Checkup Pro — Restricted Nginx Reload Permissions
# Strictly scoped to root execution of syntax-check and reload ONLY.
${WEB_USER} ALL=(root) NOPASSWD: /usr/sbin/nginx -t, /bin/systemctl reload nginx, /usr/sbin/service nginx reload
EOF

chmod 0440 "${SUDOERS_FILE}"

# Validate sudoers syntax
if ! visudo -c -f "${SUDOERS_FILE}"; then
    echo "Error: Sudoers file syntax validation failed! Removing ${SUDOERS_FILE}." >&2
    rm -f "${SUDOERS_FILE}"
    exit 1
fi

echo ""
echo "======================================================="
echo " Setup Completed Successfully!"
echo "======================================================="
echo "Next Step: Add the following include directive inside your"
echo "Nginx server { ... } block in /etc/nginx/sites-available/:"
echo ""
echo "    include ${CONF_DIR}/*.conf;"
echo ""
echo "Then test and reload your Nginx configuration:"
echo "    sudo nginx -t && sudo systemctl reload nginx"
echo "======================================================="
