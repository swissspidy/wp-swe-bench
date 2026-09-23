#!/usr/bin/env bash
# Cheat: the "obvious" multisite support. Network activation loops over get_sites() and sets
# every site up synchronously; network deactivation/uninstall loop over get_sites() too.
set -euo pipefail
REPO=/wordpress/wp-content/plugins/acme-directory
cp -R /solution/files/. "$REPO/"
