#!/usr/bin/env bash
# Hidden verifier for arch-background-import-queue, step 2 (retries, validation, error report, WP-CLI).
source /tests/wpsb/wpsb.sh
REPO=/wordpress/wp-content/plugins/acme-importer

wpsb_init
wpsb_expect integrity php_lint phpunit_p2p phpunit phpunit_step2 no_fatals

wpsb_integrity
wpsb_php_lint php_lint "$REPO"
# Test-only hooks (crash simulation, failing saves, shorter delays); inert unless a test enables them.
cp /tests/phpunit/fixtures/wpsb-importer-test-hooks.php /wordpress/wp-content/mu-plugins/
wpsb_reset_site
wpsb_server_start
wpsb_phpunit phpunit_p2p /tests/p2p
wpsb_reset_site
wpsb_server_start
# Everything from step 1 must keep working.
wpsb_phpunit phpunit /tests/phpunit
wpsb_reset_site
wpsb_server_start
wpsb_phpunit phpunit_step2 /tests/phpunit2
wpsb_no_fatals
rm -f /wordpress/wp-content/mu-plugins/wpsb-importer-test-hooks.php
wpsb_wpcs wpcs "$REPO" --changed
wpsb_finish
