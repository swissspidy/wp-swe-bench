#!/usr/bin/env bash
# Hidden verifier for bugfix-duplicate-post-meta-slashes.
source /tests/wpsb/wpsb.sh
REPO=/wordpress/wp-content/plugins/duplicate-post

wpsb_init
wpsb_expect integrity php_lint phpunit_clone phpunit_rewrite no_fatals

wpsb_integrity
wpsb_php_lint php_lint "$REPO"
# The site's content model is not part of the fix: grade against the production mu-plugin.
cp /tests/fixtures/acme-site.php /wordpress/wp-content/mu-plugins/acme-site.php
wpsb_reset_site
wpsb_server_start
wpsb_phpunit phpunit_clone /tests/phpunit/CloneTest.php
wpsb_phpunit phpunit_rewrite /tests/phpunit/RewriteRepublishTest.php
wpsb_no_fatals
wpsb_wpcs wpcs "$REPO" --changed
wpsb_finish
