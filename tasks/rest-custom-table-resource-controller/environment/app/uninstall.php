<?php
/**
 * Uninstall: drop the table, roles and options.
 *
 * @package Acme\Leads
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}acme_leads" );
remove_role( 'acme_sales_rep' );
remove_role( 'acme_sales_manager' );
$admin = get_role( 'administrator' );
if ( $admin ) {
	$admin->remove_cap( 'acme_view_leads' );
	$admin->remove_cap( 'acme_manage_leads' );
}
delete_option( 'acme_leads_db_version' );
delete_option( 'acme_leads_inbox' );
