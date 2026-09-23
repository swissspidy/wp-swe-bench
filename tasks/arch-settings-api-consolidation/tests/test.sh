#!/usr/bin/env bash
# Hidden verifier for arch-settings-api-consolidation.
source /tests/wpsb/wpsb.sh
REPO=/wordpress/wp-content/plugins/acme-social

wpsb_init
wpsb_expect integrity php_lint phpunit phpunit_uninstall no_fatals

wpsb_integrity
wpsb_php_lint php_lint "$REPO"

# 1. The update is deployed on the pristine 1.6.2 site: migration, screens, security, front end.
wpsb_reset_site
wpsb_server_start
wpsb_phpunit phpunit /tests/phpunit/main
wpsb_server_stop

# 2. Uninstall (after the update, and of a site deactivated before the update).
wpsb_reset_site
wpsb_phpunit phpunit_uninstall /tests/phpunit/uninstall

wpsb_no_fatals
wpsb_wpcs wpcs "$REPO" --changed
wpsb_finish
