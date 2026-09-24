#!/usr/bin/env bash
# Hidden verifier for security-contact-forms-audit (step 2: retest findings + audit log).
source /tests/wpsb/wpsb.sh
REPO=/wordpress/wp-content/plugins/acme-forms

wpsb_init
wpsb_expect integrity php_lint phpunit phpunit_step2 no_fatals

wpsb_integrity
wpsb_php_lint php_lint "$REPO"
# Pristine production data: the plugin files were replaced, nothing was re-activated.
wpsb_reset_site
wpsb_server_start
# Everything fixed in step 1 must stay fixed.
wpsb_phpunit phpunit /tests/phpunit
wpsb_reset_site
wpsb_server_start
wpsb_phpunit phpunit_step2 /tests/phpunit2
wpsb_no_fatals
wpsb_wpcs wpcs "$REPO" --changed
wpsb_finish
