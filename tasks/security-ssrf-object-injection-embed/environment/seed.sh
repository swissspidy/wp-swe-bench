#!/usr/bin/env bash
# Native seeding (runs after the blueprint, at image build time).
set -euo pipefail
cd "$(dirname "$0")"

wp user create eddie eddie@acme.example --role=editor --user_pass=password --display_name="Eddie Editor" >/dev/null
wp user create sam sam@example.com --role=subscriber --user_pass=password --display_name="Sam Subscriber" >/dev/null

wp eval-file seed/seed.php

wp rewrite flush >/dev/null
