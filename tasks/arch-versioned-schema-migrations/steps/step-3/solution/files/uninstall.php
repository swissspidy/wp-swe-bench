<?php
/**
 * Uninstall: drop the CRM tables and options.
 *
 * @package Acme\CRM
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;
// phpcs:disable WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}acme_crm_notes" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}acme_crm_contacts" );
// phpcs:enable

delete_option( 'acme_crm_version' );
delete_option( 'acme_crm_settings' );
delete_option( 'acme_crm_db_version' );
delete_option( 'acme_crm_migration_log' );
delete_option( 'acme_crm_migration_lock' );
delete_option( 'acme_crm_migration_failure' );
delete_option( 'acme_crm_name_backfill_cursor' );
delete_option( 'acme_crm_migrations_paused' );
