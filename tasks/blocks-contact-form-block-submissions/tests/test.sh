#!/usr/bin/env bash
# Hidden verifier for blocks-contact-form-block-submissions.
source /tests/wpsb/wpsb.sh
REPO=/wordpress/wp-content/plugins/acme-contact

wpsb_init
wpsb_expect integrity php_lint build phpunit_shortcode phpunit_submissions phpunit_entries e2e no_fatals

wpsb_integrity
wpsb_php_lint php_lint "$REPO"
# Rebuild from source: the verifier never trusts committed build output.
wpsb_build build "$REPO"
# Lets the tests control the rate limit through the plugin's own filter (default: very high).
cp /tests/phpunit/fixtures/wpsb-contact-limit.php /wordpress/wp-content/mu-plugins/wpsb-contact-limit.php
# Grade against the pristine site (as if 2.0.0 was just deployed).
wpsb_reset_site
wpsb_server_start
wpsb_phpunit phpunit_shortcode /tests/phpunit/ShortcodeTest.php
wpsb_phpunit phpunit_submissions /tests/phpunit/SubmissionTest.php
wpsb_phpunit phpunit_entries /tests/phpunit/EntriesAdminTest.php
wpsb_reset_site
wpsb_playwright e2e /tests/e2e
wpsb_no_fatals
wpsb_wpcs wpcs "$REPO" --changed
wpsb_finish
