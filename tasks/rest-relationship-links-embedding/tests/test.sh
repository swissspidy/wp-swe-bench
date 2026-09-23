#!/usr/bin/env bash
# Hidden verifier for rest-relationship-links-embedding.
source /tests/wpsb/wpsb.sh
REPO=/wordpress/wp-content/plugins/acme-library

wpsb_init
wpsb_expect integrity php_lint phpunit_read phpunit_filter phpunit_performance phpunit_writes phpunit_routes no_fatals

wpsb_integrity
wpsb_php_lint php_lint "$REPO"
# Grade against the pristine staging data (as if 2.4.0 was just deployed).
wpsb_reset_site
wpsb_server_start
# Read-only, in-process (each suite in its own PHP process: cold caches).
wpsb_phpunit phpunit_read /tests/phpunit/RelationsReadTest.php
wpsb_phpunit phpunit_filter /tests/phpunit/FilterTest.php
wpsb_phpunit phpunit_performance /tests/phpunit/PerformanceTest.php
# Old routes, byline, counters (HTTP).
wpsb_phpunit phpunit_routes /tests/phpunit/LegacyRoutesTest.php
# Writes (HTTP, committed).
wpsb_phpunit phpunit_writes /tests/phpunit/WritesTest.php
wpsb_no_fatals
wpsb_wpcs wpcs "$REPO" --changed
wpsb_finish
