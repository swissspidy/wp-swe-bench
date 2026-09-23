#!/usr/bin/env bash
# Hidden verifier for rest-member-directory-visibility (step 1: directory API + visibility everywhere).
source /tests/wpsb/wpsb.sh
REPO=/wordpress/wp-content/plugins/acme-members

wpsb_init
wpsb_expect integrity php_lint phpunit_existing phpunit_rest_read phpunit_rest_update phpunit_surfaces phpunit_form no_fatals

wpsb_integrity
wpsb_php_lint php_lint "$REPO"
# Grade against the pristine community data (as if 2.2.0 was just deployed).
wpsb_reset_site
wpsb_server_start
# Existing behaviour first (anonymous directory before anybody else filled its cache).
wpsb_phpunit phpunit_existing /tests/phpunit/http/ExistingTest.php
wpsb_reset_site
wpsb_server_start
# In-process REST (rolled back).
wpsb_phpunit phpunit_rest_read /tests/phpunit/RestMatrixTest.php
wpsb_phpunit phpunit_rest_update /tests/phpunit/RestUpdateTest.php
# Front-end surfaces over HTTP (directory cache, author archives, feed, sitemaps, cookie auth).
wpsb_phpunit phpunit_surfaces /tests/phpunit/http/SurfacesTest.php
wpsb_reset_site
wpsb_server_start
wpsb_phpunit phpunit_form /tests/phpunit/http/ProfileFormTest.php
wpsb_no_fatals
wpsb_wpcs wpcs "$REPO" --changed
wpsb_finish
