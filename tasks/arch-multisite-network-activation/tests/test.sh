#!/usr/bin/env bash
# Hidden verifier for arch-multisite-network-activation.
source /tests/wpsb/wpsb.sh
REPO=/wordpress/wp-content/plugins/acme-directory

wpsb_init
wpsb_expect integrity php_lint phpunit no_fatals

wpsb_integrity
wpsb_php_lint php_lint "$REPO"
# The production network as it was before the 2.5.0 deploy (every test restores it again).
wpsb_reset_site
wpsb_server_start
wpsb_phpunit phpunit /tests/phpunit
wpsb_no_fatals
wpsb_wpcs wpcs "$REPO" --changed
wpsb_finish
