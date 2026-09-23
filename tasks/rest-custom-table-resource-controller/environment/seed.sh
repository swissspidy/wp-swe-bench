#!/usr/bin/env bash
# Native seeding (runs after the blueprint, at image build time).
set -euo pipefail
cd "$(dirname "$0")"
wp user create mona mona@acme.example --role=acme_sales_manager --user_pass=password --display_name="Mona Manager" >/dev/null
wp user create rita rita@acme.example --role=acme_sales_rep --user_pass=password --display_name="Rita Rep" >/dev/null
wp user create raj raj@acme.example --role=acme_sales_rep --user_pass=password --display_name="Raj Rep" >/dev/null
wp user create eddie eddie@acme.example --role=editor --user_pass=password --display_name="Eddie Editor" >/dev/null
wp user create sam sam@acme.example --role=subscriber --user_pass=password --display_name="Sam Subscriber" >/dev/null
wp eval-file seed/seed.php
wp post create --post_type=page --post_status=publish --post_name=talk-to-sales --post_title="Talk to sales" --post_content='<!-- wp:shortcode -->[acme_lead_form]<!-- /wp:shortcode -->' >/dev/null
wp eval "print_r( Acme\\Leads\\Repository::count_by_status() );"
wp rewrite flush >/dev/null
