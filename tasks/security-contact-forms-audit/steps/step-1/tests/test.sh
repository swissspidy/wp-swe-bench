#!/usr/bin/env bash
# Hidden verifier for security-contact-forms-audit (step 1: audit findings F-1 to F-4).
source /tests/wpsb/wpsb.sh
REPO=/wordpress/wp-content/plugins/acme-forms

wpsb_init
wpsb_expect integrity php_lint phpunit no_fatals

wpsb_integrity
wpsb_php_lint php_lint "$REPO"
# Grade against the pristine production data and uploads (as if 2.4.0 was just deployed).
wpsb_reset_site
wpsb_server_start
wpsb_phpunit phpunit /tests/phpunit
wpsb_no_fatals
wpsb_wpcs wpcs "$REPO" --changed
wpsb_finish
