#!/usr/bin/env bash
# Native seeding (runs after the blueprint, at image build time).
set -euo pipefail
cd "$(dirname "$0")"
wp plugin activate acme-inventory >/dev/null
wp user create sam sam@example.org --role=shop_manager --user_pass=password --display_name="Sam Shopmanager" >/dev/null
wp user create wendy wendy@example.org --role=subscriber --user_pass=password --display_name="Wendy Warehouse" >/dev/null
wp user add-cap wendy manage_acme_inventory >/dev/null
wp user create eddie eddie@example.org --role=editor --user_pass=password --display_name="Eddie Editor" >/dev/null
wp user create alex alex@example.org --role=author --user_pass=password --display_name="Alex Author" >/dev/null
wp user create sue sue@example.org --role=subscriber --user_pass=password --display_name="Sue Subscriber" >/dev/null
wp eval-file seed/seed.php
