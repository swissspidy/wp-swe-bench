#!/usr/bin/env bash
# Hidden verifier for blocks-block-hooks-newsletter.
source /tests/wpsb/wpsb.sh
REPO=/wordpress/wp-content/plugins/acme-newsletter

wpsb_init
wpsb_expect integrity build phpunit e2e no_fatals

wpsb_integrity
wpsb_php_lint php_lint "$REPO"
# Rebuild from source: the verifier never trusts committed build output.
wpsb_build build "$REPO"
# Grade against the pristine site (as if the new plugin version was just deployed).
wpsb_reset_site
wpsb_server_start
wpsb_phpunit phpunit /tests/phpunit
wpsb_reset_site
wpsb_playwright e2e /tests/e2e
wpsb_no_fatals
wpsb_wpcs wpcs "$REPO" --changed
wpsb_finish
