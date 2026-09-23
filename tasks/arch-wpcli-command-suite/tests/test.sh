#!/usr/bin/env bash
# Hidden verifier for arch-wpcli-command-suite.
source /tests/wpsb/wpsb.sh
REPO=/wordpress/wp-content/plugins/acme-redirects

wpsb_init
wpsb_expect integrity php_lint phpunit_p2p phpunit no_fatals

wpsb_integrity
wpsb_php_lint php_lint "$REPO"
# Grade against the pristine production data (as if the new version was just deployed).
wpsb_reset_site
wpsb_server_start
# Existing behaviour: admin screen, export, front-end redirects, hit counters.
wpsb_phpunit phpunit_p2p /tests/p2p
wpsb_reset_site
wpsb_server_start
wpsb_phpunit phpunit /tests/phpunit
wpsb_no_fatals
wpsb_wpcs wpcs "$REPO" --changed
wpsb_finish
