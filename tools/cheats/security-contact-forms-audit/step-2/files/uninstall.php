<?php
/**
 * Uninstall: remove the table, options and capabilities. Uploaded files are kept on purpose.
 *
 * @package Acme\Forms
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}acme_form_submissions" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}acme_forms_audit_log" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange

delete_option( 'acme_forms_settings' );
delete_option( 'acme_forms_db_version' );
delete_option( 'acme_forms_version' );

foreach ( array( 'administrator', 'editor' ) as $acme_role_name ) {
	$acme_role = get_role( $acme_role_name );
	if ( $acme_role ) {
		$acme_role->remove_cap( 'acme_forms_manage' );
	}
}
