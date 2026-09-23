#!/usr/bin/env bash
# Hidden verifier for bugfix-events-timezone-dst.
source /tests/wpsb/wpsb.sh
REPO=/wordpress/wp-content/plugins/acme-events

wpsb_init
wpsb_expect integrity php_lint phpunit phpunit_http no_fatals

wpsb_integrity
wpsb_php_lint php_lint "$REPO"
# Grade against the production data (1.0 and 1.6 events), as if the new version was just deployed.
wpsb_reset_site
wpsb_server_start
wpsb_phpunit phpunit /tests/phpunit
wpsb_reset_site
wpsb_server_start
wpsb_phpunit phpunit_http /tests/phpunit-http
wpsb_no_fatals
wpsb_wpcs wpcs "$REPO" --changed
wpsb_finish
