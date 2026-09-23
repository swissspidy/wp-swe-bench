#!/usr/bin/env bash
# Hidden verifier for perf-autoload-options-bloat-migration.
source /tests/wpsb/wpsb.sh
REPO=/wordpress/wp-content/plugins/acme-activity-log
ALL_LOGS=/tmp/wpsb-debug-all.log
: > "$ALL_LOGS"

# wpsb-reset clears debug.log: keep what was logged so far for the fatals check.
keep_log() { cat /wordpress/wp-content/debug.log >> "$ALL_LOGS" 2>/dev/null || true; }
finish_migration() { (cd /wordpress && wp acme-activity migrate) > "$WPSB_LOGS/migrate-$1.log" 2>&1 || true; }

wpsb_init
wpsb_expect integrity php_lint phpunit_migration phpunit_api phpunit_cli phpunit_uninstall_legacy phpunit_uninstall_migrated no_fatals

wpsb_integrity
wpsb_php_lint php_lint "$REPO"

# 1. The update is deployed on the pristine 2.3.1-era site: migration (batches, kill + resume, parity).
wpsb_reset_site
wpsb_phpunit phpunit_migration /tests/phpunit/migration
finish_migration api

# 2. Public API, admin screens (HTTP), REST, performance, retention job.
wpsb_server_start
wpsb_phpunit phpunit_api /tests/phpunit/api
wpsb_server_stop
keep_log

# 3. WP-CLI prune/list on a freshly migrated site.
wpsb_reset_site
finish_migration cli
wpsb_phpunit phpunit_cli /tests/phpunit/cli/RetentionCliTest.php
keep_log

# 4. Uninstall: before and after the migration.
wpsb_reset_site
wpsb_phpunit phpunit_uninstall_legacy /tests/phpunit/cli/UninstallTest.php
keep_log
wpsb_reset_site
finish_migration uninstall
wpsb_phpunit phpunit_uninstall_migrated /tests/phpunit/cli/UninstallTest.php
keep_log

cat "$ALL_LOGS" > /wordpress/wp-content/debug.log
wpsb_no_fatals
wpsb_wpcs wpcs "$REPO" --changed
wpsb_finish
