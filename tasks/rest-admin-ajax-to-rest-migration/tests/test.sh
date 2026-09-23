#!/usr/bin/env bash
# Hidden verifier for rest-admin-ajax-to-rest-migration.
source /tests/wpsb/wpsb.sh
REPO=/wordpress/wp-content/plugins/acme-inventory

wpsb_init
wpsb_expect integrity php_lint phpunit e2e no_fatals

wpsb_integrity
wpsb_php_lint php_lint "$REPO"
# Grade against the pristine warehouse data (as if 4.0 was just deployed).
wpsb_reset_site
wpsb_server_start
wpsb_phpunit phpunit /tests/phpunit
wpsb_reset_site
wpsb_playwright e2e /tests/e2e
wpsb_no_fatals
wpsb_wpcs wpcs "$REPO" --changed
wpsb_finish
