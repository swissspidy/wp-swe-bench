<?php
/**
 * Uninstall: remove settings. Orders are kept (accounting needs them).
 *
 * @package Acme\OrdersSync
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'acme_orders_sync_settings' );
delete_option( 'acme_orders_sync_version' );
delete_option( 'acme_orders_webhook_secret' );

global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}acme_orders_deliveries" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
wp_clear_scheduled_hook( 'acme_orders_process_queue' );
