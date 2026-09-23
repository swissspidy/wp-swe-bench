#!/usr/bin/env bash
# Native seeding (runs after the blueprint, at image build time).
set -euo pipefail
cd "$(dirname "$0")"
wp eval-file seed/seed.php
rm -f seed/seed.php
wp rewrite flush >/dev/null
