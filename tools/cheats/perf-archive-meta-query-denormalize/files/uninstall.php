<?php
/**
 * Uninstall: drop the search index (listings and their meta are content and stay).
 *
 * @package Acme\RealEstate
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}acme_listing_index" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}acme_listing_features" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
delete_option( 'acme_re_index_db_version' );
delete_option( 'acme_re_version' );
