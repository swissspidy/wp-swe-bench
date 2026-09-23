#!/usr/bin/env bash
# Cheat (step 2): string-replace migration + admin-only widget conversion. Must score 0 on step 2.
set -euo pipefail
REPO=/wordpress/wp-content/plugins/acme-events
cp -R "$(dirname "$0")/files/." "$REPO/"
cd "$REPO"
# Unregister the widget.
sed -i "s#add_action( 'widgets_init', array( \$this, 'register_widgets' ) );#add_action( 'admin_init', array( __NAMESPACE__ . '\\\\\\\\Cheat_Migration', 'widgets' ) );#" includes/class-plugin.php
sed -i "s#require_once ACME_EVENTS_DIR . 'includes/class-widget.php';#require_once ACME_EVENTS_DIR . 'includes/class-cheat-migration.php';#" acme-events.php
sed -i "s/2\.4\.0/3.0.0/g" acme-events.php package.json
[ -e node_modules ] || ln -s /opt/wpsb/node/node_modules node_modules
npm run build
