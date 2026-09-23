<?php
/**
 * Uninstall: remove the plugin options (events are kept).
 *
 * @package Acme\Events
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'acme_events_settings' );
delete_option( 'acme_events_db_version' );
