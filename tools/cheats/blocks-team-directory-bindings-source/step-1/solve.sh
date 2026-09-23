#!/usr/bin/env bash
# Cheat (step 1): a binding source that reads the member through the plugin's Member class but
# never checks the member's status and returns raw values (relies on core's kses for escaping).
set -euo pipefail
REPO=/wordpress/wp-content/plugins/acme-team
cp -R "$(dirname "$0")/files/." "$REPO/"
cd "$REPO"
grep -q class-bindings.php acme-team.php || sed -i "s#^require_once ACME_TEAM_DIR . 'includes/class-plugin.php';#require_once ACME_TEAM_DIR . 'includes/class-bindings.php';\n&#" acme-team.php
[ -e node_modules ] || ln -s /opt/wpsb/node/node_modules node_modules
npm run build
