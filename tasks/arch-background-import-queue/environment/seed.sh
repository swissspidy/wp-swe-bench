#!/usr/bin/env bash
# Native seeding (runs after the blueprint, at image build time).
set -euo pipefail
cd "$(dirname "$0")"
wp user create sam sam@acme.example --role=acme_shop_manager --user_pass=password --display_name="Sam Shopmanager" >/dev/null
wp user create eddie eddie@acme.example --role=editor --user_pass=password --display_name="Eddie Editor" >/dev/null
wp eval-file seed/seed.php
wp rewrite flush >/dev/null
