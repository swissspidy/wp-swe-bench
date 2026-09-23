#!/usr/bin/env bash
# Native seeding (runs after the blueprint installed WordPress Importer 0.9.3).
set -euo pipefail
cd "$(dirname "$0")"
PLUGIN=/wordpress/wp-content/plugins/wordpress-importer
grep -q "Version:           0.9.3" "$PLUGIN/wordpress-importer.php" || { echo "wordpress-importer 0.9.3 not installed" >&2; exit 1; }

# The export file attached to the bug report.
cp seed/alpine-trails-export.xml /root/alpine-trails-export.xml

# The site plugin that provides the custom blocks used on the old site is not installed here yet.
wp rewrite flush >/dev/null
rm -rf /root/.cache/wp-playground /root/.wordpress-playground /tmp/*.zip 2>/dev/null || true
