#!/usr/bin/env bash
# Reference solution: directory REST API + profile form, visibility applied on every surface.
set -euo pipefail
REPO=/wordpress/wp-content/plugins/acme-members
cp -R /solution/files/. "$REPO/"
