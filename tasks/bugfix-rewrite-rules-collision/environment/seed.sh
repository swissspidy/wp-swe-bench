#!/usr/bin/env bash
# Native seeding: products, versions, docs (with duplicate slugs across products/versions), pages.
set -euo pipefail
cd "$(dirname "$0")"
wp eval-file seed/content.php
wp rewrite flush >/dev/null
