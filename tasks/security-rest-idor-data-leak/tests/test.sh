#!/usr/bin/env bash
# Hidden verifier for security-rest-idor-data-leak.
source /tests/wpsb/wpsb.sh
REPO=/wordpress/wp-content/plugins/acme-support

wpsb_init
wpsb_expect integrity php_lint phpunit no_fatals

wpsb_integrity
wpsb_php_lint php_lint "$REPO"
# Grade against the pristine seeded helpdesk (as if the new version was just deployed).
wpsb_reset_site
wpsb_server_start
wpsb_phpunit phpunit /tests/phpunit
wpsb_no_fatals
wpsb_wpcs wpcs "$REPO" --changed
wpsb_finish
