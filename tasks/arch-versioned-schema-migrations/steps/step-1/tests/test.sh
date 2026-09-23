#!/usr/bin/env bash
# Hidden verifier for arch-versioned-schema-migrations, step 1 (migration system + migration 2).
source /tests/wpsb/wpsb.sh
REPO=/wordpress/wp-content/plugins/acme-crm
export WPSB_CRM_LATEST=2

wpsb_init
wpsb_expect integrity php_lint phpunit phpunit_step no_fatals

wpsb_integrity
wpsb_php_lint php_lint "$REPO"
# The customer database from before the deploy (every test restores it again).
wpsb_reset_site
wpsb_server_start
wpsb_phpunit phpunit /tests/phpunit
wpsb_phpunit phpunit_step /tests/phpunit_step
wpsb_no_fatals
wpsb_wpcs wpcs "$REPO" --changed
wpsb_finish
