<?php
/**
 * Uninstall: drop the bookings table and options.
 *
 * @package Acme\Bookings
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}acme_bookings" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
delete_option( 'acme_bookings_db_version' );
delete_option( 'acme_bookings_currency' );
delete_option( 'acme_bookings_office_email' );
remove_role( 'booking_manager' );
