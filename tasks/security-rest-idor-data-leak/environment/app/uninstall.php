<?php
/**
 * Uninstall Acme Support.
 *
 * @package Acme\Support
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'acme_support_version' );
delete_option( 'acme_support_inbox' );

foreach ( array( 'acme_support_customer', 'acme_support_agent', 'acme_support_manager' ) as $role ) {
	remove_role( $role );
}
