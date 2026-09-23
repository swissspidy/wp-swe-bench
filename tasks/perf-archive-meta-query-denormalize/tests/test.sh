#!/usr/bin/env bash
# Hidden verifier for perf-archive-meta-query-denormalize.
source /tests/wpsb/wpsb.sh
REPO=/wordpress/wp-content/plugins/acme-real-estate

wpsb_init
wpsb_expect integrity php_lint phpunit_parity phpunit_performance phpunit_sync phpunit_cli_http no_fatals

wpsb_integrity
wpsb_php_lint php_lint "$REPO"
# The new version is deployed on the pristine 1.6.2-era site (no re-saving of listings).
wpsb_reset_site
wpsb_server_start
wpsb_phpunit phpunit_parity /tests/phpunit/ParityTest.php
wpsb_phpunit phpunit_performance /tests/phpunit/QueryShapeTest.php
wpsb_phpunit phpunit_sync /tests/phpunit/SyncTest.php
wpsb_phpunit phpunit_cli_http /tests/phpunit/CliHttpTest.php
wpsb_no_fatals
wpsb_wpcs wpcs "$REPO" --changed
wpsb_finish
