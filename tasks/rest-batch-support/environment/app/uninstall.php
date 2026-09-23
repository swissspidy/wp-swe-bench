<?php
/**
 * Uninstall: drop the tables and delete all lists.
 *
 * @package Acme\Tasks
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;
// phpcs:disable WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}acme_tasks" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}acme_task_activity" );
// phpcs:enable

foreach ( get_posts( array( 'post_type' => 'acme_task_list', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ) ) as $acme_list_id ) {
	wp_delete_post( $acme_list_id, true );
}
delete_option( 'acme_tasks_db_version' );
