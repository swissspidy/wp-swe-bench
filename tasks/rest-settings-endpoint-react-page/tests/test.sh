#!/usr/bin/env bash
# Hidden verifier for rest-settings-endpoint-react-page.
source /tests/wpsb/wpsb.sh
REPO=/wordpress/wp-content/plugins/acme-seo

wpsb_init
wpsb_expect integrity php_lint build build_output phpunit e2e no_fatals

wpsb_integrity
wpsb_php_lint php_lint "$REPO"
# Rebuild from source: the verifier never trusts committed build output.
rm -rf "$REPO/build"
wpsb_build build "$REPO"
wpsb_check build_output required test -s "$REPO/build/editor/index.js"
# Grade against the pristine 1.9.2 data, as if 2.0 was just deployed.
wpsb_reset_site
wpsb_server_start
wpsb_phpunit phpunit /tests/phpunit
wpsb_reset_site
wpsb_playwright e2e /tests/e2e
wpsb_no_fatals
wpsb_wpcs wpcs "$REPO" --changed
wpsb_finish
