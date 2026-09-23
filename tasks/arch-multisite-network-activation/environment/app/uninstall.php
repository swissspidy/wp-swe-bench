<?php
/**
 * Uninstall: remove everything the plugin stored.
 *
 * @package Acme\Directory
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require_once __DIR__ . '/includes/class-schema.php';

Acme\Directory\Schema::init();
Acme\Directory\Schema::drop_tables();

delete_option( 'acme_directory_settings' );
delete_option( 'acme_directory_db_version' );
delete_option( 'acme_directory_installed_at' );
delete_transient( 'acme_directory_counts' );

wp_clear_scheduled_hook( 'acme_directory_daily_cleanup' );
