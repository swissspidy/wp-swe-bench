#!/usr/bin/env bash
# Native seeding (runs after the blueprint, at image build time).
set -euo pipefail
cd "$(dirname "$0")"

wp plugin activate acme-forms >/dev/null
wp user create erin erin@example.org --role=editor --user_pass=password --display_name="Erin Editor" >/dev/null
wp user create arthur arthur@example.org --role=author --user_pass=password --display_name="Arthur Author" >/dev/null
wp user create connie connie@example.org --role=contributor --user_pass=password --display_name="Connie Contributor" >/dev/null
wp user create sally sally@example.org --role=subscriber --user_pass=password --display_name="Sally Subscriber" >/dev/null
wp user update admin --display_name="Ada Admin" >/dev/null

# Files uploaded with Acme Forms 2.1/2.2 (stored under their original names).
up=/wordpress/wp-content/uploads/acme-forms
mkdir -p "$up/2025/11" "$up/2026/05"
cp seed/files/cv-anna.pdf seed/files/portfolio.html "$up/2025/11/"
cp seed/files/cv-ben.docx "$up/2026/05/"

wp eval-file seed/seed.php
wp rewrite flush >/dev/null
