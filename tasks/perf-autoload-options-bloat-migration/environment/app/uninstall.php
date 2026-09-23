<?php
/**
 * Uninstall: remove our data.
 *
 * @package Acme\ActivityLog
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'acme_activity_log' );
delete_option( 'acme_activity_settings' );
delete_option( 'acme_activity_ui_state' );
delete_option( 'acme_activity_version' );
delete_option( 'acme_activity_last_id' );
// TODO: archive chunks (acme_activity_log_archive_*) and 1.x per-user options are left behind.
