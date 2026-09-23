#!/usr/bin/env bash
# Hidden verifier for rest-member-directory-visibility (step 2: connections + "connections" level).
source /tests/wpsb/wpsb.sh
REPO=/wordpress/wp-content/plugins/acme-members

wpsb_init
wpsb_expect integrity php_lint phpunit_existing phpunit_rest_read phpunit_rest_update phpunit_surfaces phpunit_form phpunit_connections phpunit_connections_http no_fatals

wpsb_integrity
wpsb_php_lint php_lint "$REPO"
# Pristine data (buddy lists never imported): the plugin files were replaced, nothing re-activated.
wpsb_reset_site
wpsb_server_start
# Everything from step 1 must keep working.
wpsb_phpunit phpunit_existing /tests/phpunit/http/ExistingTest.php
wpsb_reset_site
wpsb_server_start
wpsb_phpunit phpunit_rest_read /tests/phpunit/RestMatrixTest.php
wpsb_phpunit phpunit_rest_update /tests/phpunit/RestUpdateTest.php
wpsb_phpunit phpunit_surfaces /tests/phpunit/http/SurfacesTest.php
wpsb_reset_site
wpsb_server_start
wpsb_phpunit phpunit_form /tests/phpunit/http/ProfileFormTest.php
# Step 2.
wpsb_reset_site
wpsb_server_start
wpsb_phpunit phpunit_connections /tests/phpunit2/ConnectionsTest.php
wpsb_reset_site
wpsb_server_start
wpsb_phpunit phpunit_connections_http /tests/phpunit2/http/ConnectionsSurfacesTest.php
wpsb_no_fatals
wpsb_wpcs wpcs "$REPO" --changed
wpsb_finish
