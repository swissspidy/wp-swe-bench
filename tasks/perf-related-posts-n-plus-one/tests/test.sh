#!/usr/bin/env bash
# Hidden verifier for perf-related-posts-n-plus-one.
source /tests/wpsb/wpsb.sh
REPO=/wordpress/wp-content/plugins/acme-related

wpsb_init
wpsb_expect integrity php_lint phpunit_parity phpunit_pages phpunit_queries phpunit_invalidation phpunit_http_invalidation no_fatals

wpsb_integrity
wpsb_php_lint php_lint "$REPO"
# Grade against the pristine production data (as if the new version was just deployed).
wpsb_reset_site
# Grading-only mu-plugin: reports the number of DB queries of a request when asked to.
cp /tests/support/wpsb-query-counter.php /wordpress/wp-content/mu-plugins/wpsb-query-counter.php
wpsb_server_start
# Pass-to-pass: identical output to 2.3.1 (fixtures recorded from it).
wpsb_phpunit phpunit_parity /tests/phpunit/ParityTest.php
wpsb_phpunit phpunit_pages /tests/phpunit/PagesParityTest.php
# Fail-to-pass: query budgets.
wpsb_phpunit phpunit_queries /tests/phpunit/QueryCountTest.php
# Lists stay correct when content changes.
wpsb_phpunit phpunit_invalidation /tests/phpunit/InvalidationTest.php
wpsb_reset_site
wpsb_server_start
wpsb_phpunit phpunit_http_invalidation /tests/phpunit/HttpInvalidationTest.php
wpsb_no_fatals
rm -f /wordpress/wp-content/mu-plugins/wpsb-query-counter.php
wpsb_wpcs wpcs "$REPO" --changed
wpsb_finish
