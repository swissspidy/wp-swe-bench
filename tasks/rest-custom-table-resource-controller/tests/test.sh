#!/usr/bin/env bash
# Hidden verifier for rest-custom-table-resource-controller.
source /tests/wpsb/wpsb.sh
REPO=/wordpress/wp-content/plugins/acme-leads

wpsb_init
wpsb_expect integrity php_lint phpunit no_fatals

wpsb_integrity
wpsb_php_lint php_lint "$REPO"
# Grade against the pristine 1.8.0-era leads table.
wpsb_reset_site
wpsb_server_start
wpsb_phpunit phpunit /tests/phpunit
wpsb_no_fatals
wpsb_wpcs wpcs "$REPO" --changed
wpsb_finish
