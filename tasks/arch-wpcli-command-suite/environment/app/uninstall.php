<?php
/**
 * Uninstall: drop the table and options.
 *
 * @package Acme\Redirects
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}acme_redirects" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
delete_option( 'acme_redirects_db_version' );
delete_transient( 'acme_redirects_rules' );
