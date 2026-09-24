#!/usr/bin/env bash
# Cheat (see ../README.md). Must score 0 on this step.
set -euo pipefail
cp -R "$(dirname "$0")/files/." /wordpress/wp-content/plugins/acme-forms/
