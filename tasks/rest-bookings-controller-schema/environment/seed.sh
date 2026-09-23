#!/usr/bin/env bash
# Native seeding (runs after the blueprint, at image build time).
set -euo pipefail
cd "$(dirname "$0")"
wp plugin activate acme-bookings >/dev/null
wp user create mira mira@example.org --role=booking_manager --user_pass=password --display_name="Mira Office" >/dev/null
wp user create alice alice@example.org --role=subscriber --user_pass=password --display_name="Alice Traveller" >/dev/null
wp user create bob bob@example.org --role=subscriber --user_pass=password --display_name="Bob Backpacker" >/dev/null
wp user create carol carol@example.org --role=subscriber --user_pass=password --display_name="Carol Newguest" >/dev/null
wp user create eddie eddie@example.org --role=editor --user_pass=password --display_name="Eddie Editor" >/dev/null
wp eval-file seed/seed.php
wp rewrite flush >/dev/null
