<?php
/**
 * Uninstall: remove everything the plugin stored, on every site of every network.
 *
 * @package Acme\Directory
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

if ( ! defined( 'ACME_DIRECTORY_FILE' ) ) {
	define( 'ACME_DIRECTORY_FILE', __DIR__ . '/acme-directory.php' );
}

require_once __DIR__ . '/includes/class-schema.php';
require_once __DIR__ . '/includes/class-settings.php';
require_once __DIR__ . '/includes/class-categories.php';
require_once __DIR__ . '/includes/class-installer.php';
require_once __DIR__ . '/includes/class-network.php';

if ( ! is_multisite() ) {
	Acme\Directory\Installer::uninstall_current_site();
	return;
}

// Every site of every network, however many there are (and whether or not the plugin
// was ever used there: dropping missing tables and deleting missing options is a no-op).
Acme\Directory\Network::each_site(
	static function () {
		Acme\Directory\Installer::uninstall_current_site();
		wp_clear_scheduled_hook( Acme\Directory\Network::SETUP_HOOK );
	}
);

foreach ( get_networks(
	array(
		'fields' => 'ids',
		'number' => 0,
	)
) as $acme_directory_network_id ) {
	delete_network_option( (int) $acme_directory_network_id, Acme\Directory\Network::STATE_OPTION );
}
