#!/usr/bin/env bash
# Hidden verifier for arch-versioned-schema-migrations, step 2 (first/last names, batched backfill).
source /tests/wpsb/wpsb.sh
REPO=/wordpress/wp-content/plugins/acme-crm
export WPSB_CRM_LATEST=5

wpsb_init
wpsb_expect integrity php_lint phpunit phpunit_names no_fatals

wpsb_integrity
wpsb_php_lint php_lint "$REPO"
# The customer database from before the deploy (schema version 1; every test restores it again).
wpsb_reset_site
wpsb_server_start
# The migration system from step 1 must keep working (now up to version 5).
wpsb_phpunit phpunit /tests/phpunit
wpsb_phpunit phpunit_names /tests/phpunit_names
wpsb_no_fatals
wpsb_wpcs wpcs "$REPO" --changed
wpsb_finish
