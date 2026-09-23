#!/usr/bin/env bash
# Hidden verifier for bugfix-wordpress-importer-url-rewriting.
source /tests/wpsb/wpsb.sh
REPO=/wordpress/wp-content/plugins/wordpress-importer
IMPORT=(wp --exec='define( "WP_LOAD_IMPORTERS", true );' eval-file /tests/import.php)

wpsb_init
wpsb_expect integrity php_lint phpunit_rewrite phpunit_verbatim phpunit_admin import_for_editor e2e no_fatals

wpsb_integrity
wpsb_php_lint php_lint "$REPO"

# Every import starts from the fresh (never imported) site.
wpsb_reset_site
wpsb_server_start
wpsb_phpunit phpunit_rewrite /tests/phpunit/ImportRewriteTest.php
wpsb_reset_site
wpsb_phpunit phpunit_verbatim /tests/phpunit/ImportVerbatimTest.php
wpsb_reset_site
wpsb_server_start
wpsb_phpunit phpunit_admin /tests/phpunit/AdminImportTest.php

# Imported content in the block editor.
wpsb_reset_site
wpsb_check import_for_editor required bash -c 'cd /wordpress && "$@"' _ "${IMPORT[@]}" /tests/fixtures/alpine-trails-full.xml 1
wpsb_playwright e2e /tests/e2e
wpsb_no_fatals
wpsb_wpcs wpcs "$REPO" --changed
wpsb_finish
