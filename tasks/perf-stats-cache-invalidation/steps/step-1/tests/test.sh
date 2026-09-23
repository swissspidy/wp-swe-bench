#!/usr/bin/env bash
# Hidden verifier for perf-stats-cache-invalidation, step 1 (cache + exact invalidation).
source /tests/wpsb/wpsb.sh
REPO=/wordpress/wp-content/plugins/acme-dashboard-stats

wpsb_init
wpsb_expect integrity php_lint phpunit_stats phpunit_cache phpunit_invalidation phpunit_http no_fatals

wpsb_integrity
wpsb_php_lint php_lint "$REPO"
# Pristine newsroom data (the new version was just deployed, not re-activated).
wpsb_reset_site
rm -f /wordpress/wp-content/object-cache.php
rm -rf /wordpress/wp-content/wpsb-object-cache.sqlite /wordpress/wp-content/wpsb-probe
# Grading-only mu-plugin: logs every computation (acme_stats_before_compute) across processes.
cp /tests/support/wpsb-stats-probe.php /wordpress/wp-content/mu-plugins/wpsb-stats-probe.php
wpsb_server_start
wpsb_phpunit phpunit_stats /tests/phpunit/StatsTest.php
wpsb_phpunit phpunit_cache /tests/phpunit/CacheTest.php
wpsb_phpunit phpunit_invalidation /tests/phpunit/InvalidationTest.php
wpsb_phpunit phpunit_http /tests/phpunit/HttpCacheTest.php
wpsb_no_fatals
rm -f /wordpress/wp-content/mu-plugins/wpsb-stats-probe.php
wpsb_wpcs wpcs "$REPO" --changed
wpsb_finish
