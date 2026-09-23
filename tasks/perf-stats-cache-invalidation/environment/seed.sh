#!/usr/bin/env bash
# Native seeding (runs after the blueprint, at image build time).
set -euo pipefail
cd "$(dirname "$0")"
wp post delete 1 2 3 --force >/dev/null 2>&1 || true
wp eval-file seed/seed.php
wp rewrite flush >/dev/null
