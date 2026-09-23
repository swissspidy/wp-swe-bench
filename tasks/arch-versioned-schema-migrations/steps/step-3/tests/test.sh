#!/usr/bin/env bash
# Hidden verifier for arch-versioned-schema-migrations, step 3 (wp acme-crm migrate status|run|rollback).
source /tests/wpsb/wpsb.sh
REPO=/wordpress/wp-content/plugins/acme-crm
export WPSB_CRM_LATEST=5

wpsb_init
wpsb_expect integrity php_lint phpunit phpunit_names phpunit_cli no_fatals

wpsb_integrity
wpsb_php_lint php_lint "$REPO"
# The customer database from before the deploy (schema version 1; every test restores it again).
wpsb_reset_site
wpsb_server_start
# Steps 1 and 2 must keep working.
wpsb_phpunit phpunit /tests/phpunit
wpsb_phpunit phpunit_names /tests/phpunit_names
wpsb_phpunit phpunit_cli /tests/phpunit_cli
wpsb_no_fatals
wpsb_wpcs wpcs "$REPO" --changed
wpsb_finish
