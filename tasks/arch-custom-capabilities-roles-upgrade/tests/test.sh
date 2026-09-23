#!/usr/bin/env bash
# Hidden verifier for arch-custom-capabilities-roles-upgrade.
source /tests/wpsb/wpsb.sh
REPO=/wordpress/wp-content/plugins/acme-newsroom

wpsb_init
wpsb_expect integrity php_lint phpunit no_fatals

wpsb_integrity
wpsb_php_lint php_lint "$REPO"

# The 3.0 code is deployed on the pristine 2.3.0 site (roles/users as they are in production).
wpsb_reset_site
wpsb_server_start
wpsb_phpunit phpunit /tests/phpunit
wpsb_server_stop

wpsb_no_fatals
wpsb_wpcs wpcs "$REPO" --changed
wpsb_finish
