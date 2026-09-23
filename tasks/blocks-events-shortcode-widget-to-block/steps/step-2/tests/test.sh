#!/usr/bin/env bash
# Hidden verifier for blocks-events-shortcode-widget-to-block, step 2 (retire widget + shortcode migration).
source /tests/wpsb/wpsb.sh
REPO=/wordpress/wp-content/plugins/acme-events

wpsb_init
wpsb_expect integrity build phpunit e2e no_fatals

wpsb_integrity
wpsb_php_lint php_lint "$REPO"
wpsb_build build "$REPO"
# The production database from before the 3.0 deploy; starting the server requests the site,
# which is the "first request after deploy".
wpsb_reset_site
wpsb_server_start
wpsb_phpunit phpunit /tests/phpunit
wpsb_reset_site
wpsb_playwright e2e /tests/e2e
wpsb_no_fatals
wpsb_wpcs wpcs "$REPO" --changed
wpsb_finish
