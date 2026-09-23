<?php
/**
 * Imports a WXR file like Tools → Import → WordPress does (attachments not downloaded: the site is offline).
 *
 * Usage: wp --exec='define( "WP_LOAD_IMPORTERS", true );' eval-file /tests/import.php <file> <rewrite_urls 0|1>
 */

// phpcs:ignoreFile

list( $file, $rewrite ) = $args;

wp_set_current_user( 1 );
$importer                    = new WP_Import();
$importer->fetch_attachments = false;

ob_start();
$importer->import( $file, array( 'rewrite_urls' => '1' === $rewrite ) );
$output = ob_get_clean();

WP_CLI::log( trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $output ) ) ) );
