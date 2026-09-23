#!/usr/bin/env bash
# Hidden verifier for blocks-theme-json-v3-migration.
source /tests/wpsb/wpsb.sh
REPO=/wordpress/wp-content/themes/acme-magazine

wpsb_init
wpsb_expect integrity php_lint phpunit e2e no_fatals

wpsb_integrity
wpsb_php_lint php_lint "$REPO"
wpsb_reset_site
wpsb_server_start
wpsb_phpunit phpunit /tests/phpunit
wpsb_reset_site
wpsb_playwright e2e /tests/e2e
wpsb_no_fatals
wpsb_wpcs wpcs "$REPO" --changed
wpsb_finish
