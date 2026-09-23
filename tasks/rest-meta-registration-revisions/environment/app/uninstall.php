<?php
/**
 * Uninstall: remove options and cached tables. Product data is kept.
 *
 * @package Acme\Specs
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

delete_option( 'acme_specs_version' );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_acme\_specs\_html\_%' OR option_name LIKE '\_transient\_timeout\_acme\_specs\_html\_%'" );
