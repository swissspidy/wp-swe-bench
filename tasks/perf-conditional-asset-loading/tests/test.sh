#!/usr/bin/env bash
# Hidden verifier for perf-conditional-asset-loading.
source /tests/wpsb/wpsb.sh
REPO=/wordpress/wp-content/plugins/acme-ui-kit

wpsb_init
wpsb_expect integrity php_lint phpunit_front phpunit_admin e2e phpunit_classic e2e_classic no_fatals

wpsb_integrity
wpsb_php_lint php_lint "$REPO"

# Block theme (Twenty Twenty-Five), pristine content.
wpsb_reset_site
wpsb_server_start
wpsb_phpunit phpunit_front /tests/phpunit/FrontEndAssetsTest.php
wpsb_phpunit phpunit_admin /tests/phpunit/AdminAssetsTest.php
wpsb_reset_site
wpsb_playwright e2e /tests/e2e

# Classic theme with block widgets (the shop sub-site).
wpsb_reset_site
wp eval-file /tests/support/classic-setup.php > "$WPSB_LOGS/classic-setup.log" 2>&1
wpsb_server_start
wpsb_phpunit phpunit_classic /tests/phpunit-classic/ClassicThemeTest.php
wpsb_playwright e2e_classic /tests/e2e-classic

wpsb_no_fatals
wpsb_wpcs wpcs "$REPO" --changed
wpsb_finish
