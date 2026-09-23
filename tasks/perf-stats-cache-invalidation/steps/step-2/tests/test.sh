#!/usr/bin/env bash
# Hidden verifier for perf-stats-cache-invalidation, step 2 (stampede protection, WP-CLI,
# persistent object cache). Everything runs twice: without and with a persistent object cache.
source /tests/wpsb/wpsb.sh
REPO=/wordpress/wp-content/plugins/acme-dashboard-stats
WPC=/wordpress/wp-content

suite() { # $1 = suffix
  wpsb_phpunit "phpunit_stats$1" /tests/phpunit/StatsTest.php
  wpsb_phpunit "phpunit_cache$1" /tests/phpunit/CacheTest.php
  wpsb_phpunit "phpunit_invalidation$1" /tests/phpunit/InvalidationTest.php
  wpsb_phpunit "phpunit_http$1" /tests/phpunit/HttpCacheTest.php
  wpsb_phpunit "phpunit_stampede$1" /tests/phpunit2/StampedeTest.php
  wpsb_phpunit "phpunit_cli$1" /tests/phpunit2/CliTest.php
}

wpsb_init
wpsb_expect integrity php_lint \
  phpunit_stats phpunit_cache phpunit_invalidation phpunit_http phpunit_stampede phpunit_cli \
  phpunit_stats_objcache phpunit_cache_objcache phpunit_invalidation_objcache phpunit_http_objcache phpunit_stampede_objcache phpunit_cli_objcache \
  no_fatals

wpsb_integrity
wpsb_php_lint php_lint "$REPO"
cp /tests/support/wpsb-stats-probe.php "$WPC/mu-plugins/wpsb-stats-probe.php"

# 1) No persistent object cache (today's production).
wpsb_reset_site
rm -f "$WPC/object-cache.php"; rm -rf "$WPC/wpsb-object-cache.sqlite" "$WPC/wpsb-probe"
wpsb_server_start
suite ""

# 2) With a persistent object cache (the new hosting plan).
wpsb_reset_site
rm -rf "$WPC/wpsb-object-cache.sqlite" "$WPC/wpsb-probe"
cp /tests/support/object-cache.php "$WPC/object-cache.php"
wpsb_server_start
suite "_objcache"
wpsb_server_stop
rm -f "$WPC/object-cache.php" "$WPC/wpsb-object-cache.sqlite"

wpsb_no_fatals
rm -f "$WPC/mu-plugins/wpsb-stats-probe.php"
wpsb_wpcs wpcs "$REPO" --changed
wpsb_finish
