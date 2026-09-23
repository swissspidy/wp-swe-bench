#!/usr/bin/env bash
# Hidden verifier for rest-bookings-controller-schema (step 2: v2 namespace, v1 compatibility).
source /tests/wpsb/wpsb.sh
REPO=/wordpress/wp-content/plugins/acme-bookings

wpsb_init
wpsb_expect integrity php_lint phpunit phpunit_v2 e2e no_fatals

wpsb_integrity
wpsb_php_lint php_lint "$REPO"
# Pristine production data: the plugin files were replaced, nothing was re-activated.
wpsb_reset_site
wpsb_server_start
# v1 must keep behaving exactly as after step 1.
wpsb_phpunit phpunit /tests/phpunit
wpsb_phpunit phpunit_v2 /tests/phpunit2
wpsb_reset_site
wpsb_playwright e2e /tests/e2e
wpsb_no_fatals
wpsb_wpcs wpcs "$REPO" --changed
wpsb_finish
