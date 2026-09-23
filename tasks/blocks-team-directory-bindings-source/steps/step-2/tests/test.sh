#!/usr/bin/env bash
# Hidden verifier for blocks-team-directory-bindings-source (step 2).
source /tests/wpsb/wpsb.sh
REPO=/wordpress/wp-content/plugins/acme-team

wpsb_init
wpsb_expect integrity build phpunit phpunit_step2 e2e no_fatals

wpsb_integrity
wpsb_php_lint php_lint "$REPO"
# Rebuild from source: the verifier never trusts committed build output.
wpsb_build build "$REPO"
wpsb_reset_site
wpsb_server_start
# Step 1 behaviour must keep working.
wpsb_phpunit phpunit /tests/phpunit
wpsb_phpunit phpunit_step2 /tests/phpunit2
wpsb_reset_site
wpsb_playwright e2e /tests/e2e
wpsb_no_fatals
wpsb_wpcs wpcs "$REPO" --changed
wpsb_finish
