<?php
/**
 * Uninstall.
 *
 * @package Acme\Inventory
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;
// phpcs:disable WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}acme_inventory_items" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}acme_inventory_log" );
// phpcs:enable
delete_option( 'acme_inventory_db_version' );
delete_option( 'acme_inventory_alert_email' );
foreach ( array( 'administrator', 'shop_manager' ) as $role_name ) {
	$role = get_role( $role_name );
	if ( $role ) {
		$role->remove_cap( 'manage_acme_inventory' );
	}
}
