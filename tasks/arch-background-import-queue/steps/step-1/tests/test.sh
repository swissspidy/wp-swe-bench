#!/usr/bin/env bash
# Hidden verifier for arch-background-import-queue, step 1 (background queue).
source /tests/wpsb/wpsb.sh
REPO=/wordpress/wp-content/plugins/acme-importer

wpsb_init
wpsb_expect integrity php_lint phpunit_p2p phpunit no_fatals

wpsb_integrity
wpsb_php_lint php_lint "$REPO"
# Test-only hooks (crash simulation, shorter timeouts); inert unless a test enables them.
cp /tests/phpunit/fixtures/wpsb-importer-test-hooks.php /wordpress/wp-content/mu-plugins/
# Pristine shop data, as if the new version was just deployed (no re-activation).
wpsb_reset_site
wpsb_server_start
wpsb_phpunit phpunit_p2p /tests/p2p
wpsb_reset_site
wpsb_server_start
wpsb_phpunit phpunit /tests/phpunit
wpsb_no_fatals
rm -f /wordpress/wp-content/mu-plugins/wpsb-importer-test-hooks.php
wpsb_wpcs wpcs "$REPO" --changed
wpsb_finish
