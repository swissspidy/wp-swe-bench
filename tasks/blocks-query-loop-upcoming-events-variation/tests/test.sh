#!/usr/bin/env bash
# Hidden verifier for blocks-query-loop-upcoming-events-variation.
source /tests/wpsb/wpsb.sh
REPO=/wordpress/wp-content/plugins/acme-events-lite

wpsb_init
wpsb_expect integrity build phpunit e2e no_fatals

wpsb_integrity
wpsb_php_lint php_lint "$REPO"
# Rebuild from source: the verifier never trusts committed build output.
wpsb_build build "$REPO"
# Grading pins the plugin's documented clock (acme_events_now) to the `wpsb_events_now`
# option, so that time-dependent checks are deterministic.
cp /tests/mu-plugins/wpsb-events-clock.php /wordpress/wp-content/mu-plugins/wpsb-events-clock.php
wpsb_reset_site
wpsb_server_start
wpsb_phpunit phpunit /tests/phpunit
wpsb_reset_site
wpsb_playwright e2e /tests/e2e
wpsb_no_fatals
wpsb_wpcs wpcs "$REPO" --changed
wpsb_finish
