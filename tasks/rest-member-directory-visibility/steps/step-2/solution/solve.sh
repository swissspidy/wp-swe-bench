#!/usr/bin/env bash
# Reference solution (step 2): connections + "My connections" visibility level, legacy buddy import.
set -euo pipefail
REPO=/wordpress/wp-content/plugins/acme-members
cp -R /solution/files/. "$REPO/"
