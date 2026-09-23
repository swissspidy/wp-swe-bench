#!/usr/bin/env bash
# Hidden verifier for rest-meta-registration-revisions.
source /tests/wpsb/wpsb.sh
REPO=/wordpress/wp-content/plugins/acme-specs

wpsb_init
wpsb_expect integrity php_lint phpunit no_fatals

wpsb_integrity
wpsb_php_lint php_lint "$REPO"
# Grade against the pristine 2.4.1-era data (as if the new version was just deployed).
wpsb_reset_site
wpsb_server_start
wpsb_phpunit phpunit /tests/phpunit
wpsb_no_fatals
wpsb_wpcs wpcs "$REPO" --changed
wpsb_finish
