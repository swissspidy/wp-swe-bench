<?php
/**
 * Uninstall: remove the plugin's own options.
 *
 * @package Acme\Migrate
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'acme_migrate_history' );
delete_option( 'acme_migrate_settings' );
