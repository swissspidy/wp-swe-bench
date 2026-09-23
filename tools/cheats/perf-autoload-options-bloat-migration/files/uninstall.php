<?php
/**
 * Uninstall: remove everything the plugin stored.
 *
 * @package Acme\ActivityLog
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

// The log table.
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}acme_activity_log" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

// All options, including pre-3.0 leftovers (log chunks, 1.x/2.x screen state).
$acme_activity_options = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( 'acme_activity_' ) . '%' )
);
foreach ( $acme_activity_options as $acme_activity_option ) {
	delete_option( $acme_activity_option );
}

// Per-user screen state.
delete_metadata( 'user', 0, 'acme_activity_ui', '', true );

// Background jobs.
wp_clear_scheduled_hook( 'acme_activity_migrate' );
wp_clear_scheduled_hook( 'acme_activity_prune' );
