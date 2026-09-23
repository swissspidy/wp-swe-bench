#!/usr/bin/env bash
# Hidden verifier for arch-privacy-export-erase-integration.
source /tests/wpsb/wpsb.sh
REPO=/wordpress/wp-content/plugins/acme-loyalty

wpsb_init
wpsb_expect integrity php_lint phpunit_export phpunit_erase phpunit_retention phpunit_request_flow phpunit_settings phpunit_existing no_fatals

wpsb_integrity
wpsb_php_lint php_lint "$REPO"
# Grade against the pristine shop data (as if 2.4.0 was just deployed).
wpsb_reset_site
wpsb_server_start
# In-process: exporters/erasers driven like core's request screens (erasers + retention roll back).
wpsb_phpunit phpunit_export /tests/phpunit/ExportTest.php
wpsb_phpunit phpunit_erase /tests/phpunit/EraseTest.php
wpsb_phpunit phpunit_retention /tests/phpunit/RetentionTest.php
# The real admin-ajax request flow (commits changes).
wpsb_phpunit phpunit_request_flow /tests/phpunit/http/RequestFlowTest.php
wpsb_reset_site
wpsb_server_start
wpsb_phpunit phpunit_settings /tests/phpunit/http/SettingsPolicyTest.php
wpsb_reset_site
wpsb_server_start
wpsb_phpunit phpunit_existing /tests/phpunit/http/ExistingFeaturesTest.php
wpsb_no_fatals
wpsb_wpcs wpcs "$REPO" --changed
wpsb_finish
