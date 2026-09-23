#!/usr/bin/env bash
# Native seeding (runs after the blueprint installed Duplicate Post 4.7).
set -euo pipefail
cd "$(dirname "$0")"
PLUGIN=/wordpress/wp-content/plugins/duplicate-post

# The site runs its own maintained copy of the plugin: apply the in-house changes.
[ -f "$PLUGIN/duplicate-post.php" ] || { echo "duplicate-post not installed" >&2; exit 1; }
cp -R seed/inject/. "$PLUGIN/"
rm -rf seed/inject

cp seed/mu-plugins/*.php /wordpress/wp-content/mu-plugins/

# Run the plugin's install routine (capabilities + default options) as the first admin visit would.
wp eval 'require_once DUPLICATE_POST_PATH . "admin-functions.php"; duplicate_post_plugin_upgrade();'
wp option update duplicate_post_types_enabled '["post","page","acme_recipe","acme_release"]' --format=json >/dev/null
wp option update duplicate_post_blacklist '_acme_cache_*' >/dev/null
wp option update duplicate_post_show_notice 0 >/dev/null

wp eval-file seed/seed.php
rm -rf seed/fixtures seed/seed.php

wp rewrite flush >/dev/null
# No leftovers of the downloaded package.
rm -rf /root/.cache/wp-playground /root/.wordpress-playground /tmp/*.zip 2>/dev/null || true
