#!/usr/bin/env bash
# Hidden verifier for security-ssrf-object-injection-embed.
source /tests/wpsb/wpsb.sh
REPO=/wordpress/wp-content/plugins/acme-link-previews

wpsb_init
wpsb_expect integrity php_lint phpunit no_fatals

wpsb_integrity
wpsb_php_lint php_lint "$REPO"
wpsb_reset_site
wpsb_server_start
wpsb_phpunit phpunit /tests/phpunit
wpsb_no_fatals
wpsb_wpcs wpcs "$REPO" --changed
wpsb_finish
