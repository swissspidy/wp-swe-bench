#!/usr/bin/env bash
# Native seeding (build time). Production history: the site ran Acme CRM 1.2 (tables created
# then); the 1.4.0 files were deployed over it without re-activating the plugin.
set -euo pipefail
cd "$(dirname "$0")"

wp user create sally sally@acme.example --role=editor --user_pass=password --display_name="Sally Sales" >/dev/null
wp user create sam sam@acme.example --role=author --user_pass=password >/dev/null

wp eval-file seed/v1-data.php
# The plugin was active before the file update: mark it active without running 1.4.0's activation.
wp option update active_plugins '["acme-crm/acme-crm.php"]' --format=json >/dev/null

wp post create --post_type=page --post_status=publish --post_name=contact --post_title="Contact us" --post_content='[acme_crm_form]' >/dev/null
