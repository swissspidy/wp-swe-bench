#!/usr/bin/env bash
# Native seeding (runs after the blueprint, at image build time).
set -euo pipefail
cd "$(dirname "$0")"

wp user create carol carol@carol-industries.example --role=acme_support_customer --user_pass=password --display_name="Carol Customer" >/dev/null
wp user create dave dave@dave-co.example --role=acme_support_customer --user_pass=password --display_name="Dave Customer" >/dev/null
wp user create amy amy@acme.example --role=acme_support_agent --user_pass=password --display_name="Amy Agent" >/dev/null
wp user create mgr mgr@acme.example --role=acme_support_manager --user_pass=password --display_name="Morgan Manager" >/dev/null
wp user create eddie eddie@acme.example --role=editor --user_pass=password --display_name="Eddie Editor" >/dev/null
wp user create sam sam@example.com --role=subscriber --user_pass=password --display_name="Sam Subscriber" >/dev/null

wp eval-file seed/seed.php

wp rewrite flush >/dev/null
