#!/usr/bin/env bash
# Hidden verifier for rest-webhook-app-passwords-hmac.
source /tests/wpsb/wpsb.sh
REPO=/wordpress/wp-content/plugins/acme-orders-sync

wpsb_init
wpsb_expect integrity php_lint phpunit_redaction phpunit_signature phpunit_queue phpunit_http phpunit_legacy no_fatals

wpsb_integrity
wpsb_php_lint php_lint "$REPO"
# Grade against the pristine 1.3.2-era site (as if the new version was just deployed).
wpsb_reset_site
wpsb_server_start
# Secrets at rest (incl. the 1.3.2 sync.log), first: nothing else has touched the log yet.
wpsb_phpunit phpunit_redaction /tests/phpunit/RedactionTest.php
# Signatures, duplicates, queue/retries/status (in-process REST dispatch).
wpsb_phpunit phpunit_signature /tests/phpunit/SignatureTest.php
wpsb_phpunit phpunit_queue /tests/phpunit/QueueTest.php
# Real HTTP: application passwords vs cookies, status permissions, rate limits.
wpsb_phpunit phpunit_http /tests/phpunit/HttpAuthTest.php
# Existing behaviour.
wpsb_reset_site
wpsb_server_start
wpsb_phpunit phpunit_legacy /tests/phpunit/LegacyTest.php
wpsb_no_fatals
wpsb_wpcs wpcs "$REPO" --changed
wpsb_finish
