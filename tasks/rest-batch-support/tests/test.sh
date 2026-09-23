#!/usr/bin/env bash
# Hidden verifier for rest-batch-support.
source /tests/wpsb/wpsb.sh
REPO=/wordpress/wp-content/plugins/acme-tasks

wpsb_init
wpsb_expect integrity php_lint phpunit_direct phpunit_batch e2e no_fatals

wpsb_integrity
wpsb_php_lint php_lint "$REPO"
# Grade against the pristine site (as if the new plugin version was just deployed).
wpsb_reset_site
wpsb_server_start
# Existing single-request clients (mostly pass-to-pass) + position consistency.
wpsb_phpunit phpunit_direct /tests/phpunit/DirectApiTest.php
wpsb_phpunit phpunit_positions /tests/phpunit/PositionsTest.php
wpsb_expect phpunit_positions
wpsb_reset_site
wpsb_server_start
# Batch requests (fail-to-pass).
wpsb_phpunit phpunit_batch /tests/phpunit/BatchTest.php
wpsb_reset_site
# wp-admin Tasks screen (pass-to-pass).
wpsb_playwright e2e /tests/e2e
wpsb_no_fatals
wpsb_wpcs wpcs "$REPO" --changed
wpsb_finish
