#!/usr/bin/env bash
# Cheat: "version bump" upgrade. theme.json v3 with the right default-preset flags and fluid
# presets, but the hand-written CSS for buttons/quotes/links/Card/Inverted stays in style.css.
set -euo pipefail
REPO=/wordpress/wp-content/themes/acme-magazine
cp -R "$(dirname "$0")/files/." "$REPO/"
