#!/usr/bin/env bash
# Hidden verifier for bugfix-search-replace-serialized.
source /tests/wpsb/wpsb.sh
REPO=/wordpress/wp-content/plugins/acme-migrate

wpsb_init
wpsb_expect integrity php_lint phpunit_api phpunit_cli phpunit_admin phpunit_basics no_fatals

wpsb_integrity
wpsb_php_lint php_lint "$REPO"

# Every suite starts from the pristine (just migrated from the old host) database.
wpsb_reset_site
wpsb_phpunit phpunit_api /tests/phpunit/ReplaceValueTest.php
wpsb_reset_site
wpsb_phpunit phpunit_cli /tests/phpunit/CliMigrationTest.php
wpsb_reset_site
wpsb_server_start
wpsb_phpunit phpunit_admin /tests/phpunit/AdminMigrationTest.php
wpsb_reset_site
wpsb_phpunit phpunit_basics /tests/phpunit/CliBasicsTest.php
wpsb_no_fatals
wpsb_wpcs wpcs "$REPO" --changed
wpsb_finish
