#!/usr/bin/env bash
# Reference solution: overlay the 4.0.0 theme files.
set -euo pipefail
REPO=/wordpress/wp-content/themes/acme-corporate
cp -R /solution/files/. "$REPO/"
rm -f "$REPO/inc/class-acme-corporate-menu-walker.php.bak"
