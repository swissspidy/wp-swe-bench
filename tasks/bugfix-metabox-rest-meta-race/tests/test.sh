#!/usr/bin/env bash
# Hidden verifier for bugfix-metabox-rest-meta-race.
source /tests/wpsb/wpsb.sh
REPO=/wordpress/wp-content/plugins/acme-product-fields

wpsb_init
wpsb_expect integrity build phpunit e2e no_fatals

wpsb_integrity
wpsb_php_lint php_lint "$REPO"
wpsb_build build "$REPO"
wpsb_reset_site
wpsb_server_start
wpsb_phpunit phpunit /tests/phpunit
wpsb_reset_site
wpsb_playwright e2e /tests/e2e
wpsb_no_fatals
wpsb_wpcs wpcs "$REPO" --changed
wpsb_finish
