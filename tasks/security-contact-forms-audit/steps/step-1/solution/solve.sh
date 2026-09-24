#!/usr/bin/env bash
# Reference solution, step-1: overlay the files onto the plugin.
set -euo pipefail
REPO=/wordpress/wp-content/plugins/acme-forms
cp -R /solution/files/. "$REPO/"
