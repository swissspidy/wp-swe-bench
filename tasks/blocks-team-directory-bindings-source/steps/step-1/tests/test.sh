#!/usr/bin/env bash
# Hidden verifier for blocks-team-directory-bindings-source (step 1).
source /tests/wpsb/wpsb.sh
REPO=/wordpress/wp-content/plugins/acme-team

wpsb_init
wpsb_expect integrity build phpunit no_fatals

wpsb_integrity
wpsb_php_lint php_lint "$REPO"
# Rebuild from source: the verifier never trusts committed build output.
wpsb_build build "$REPO"
# Grade against the pristine site content (as if the new version was just deployed).
wpsb_reset_site
wpsb_server_start
wpsb_phpunit phpunit /tests/phpunit
wpsb_reset_site
wpsb_no_fatals
wpsb_wpcs wpcs "$REPO" --changed
wpsb_finish
