#!/usr/bin/env bash
# Reference solution: personal data exporters/erasers, retention setting + daily clean-up, policy text.
set -euo pipefail
REPO=/wordpress/wp-content/plugins/acme-loyalty
cp -R /solution/files/. "$REPO/"
