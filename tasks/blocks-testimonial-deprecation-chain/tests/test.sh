#!/usr/bin/env bash
# Hidden verifier for blocks-testimonial-deprecation-chain.
source /tests/wpsb/wpsb.sh
REPO=/wordpress/wp-content/plugins/acme-testimonials

wpsb_init
wpsb_expect integrity build phpunit e2e no_fatals

wpsb_integrity
wpsb_php_lint php_lint "$REPO"
# Rebuild from source: the verifier never trusts committed build output.
wpsb_build build "$REPO"
# Grade against the pristine content (as if the 4.0 update was just deployed).
wpsb_reset_site
wpsb_server_start
wpsb_phpunit phpunit /tests/phpunit
wpsb_reset_site
wpsb_playwright e2e /tests/e2e
wpsb_no_fatals
wpsb_wpcs wpcs "$REPO" --changed
wpsb_finish
