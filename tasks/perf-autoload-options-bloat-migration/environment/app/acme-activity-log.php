<?php
/**
 * Plugin Name:       Acme Activity Log
 * Plugin URI:        https://acme.example/plugins/activity-log
 * Description:       Keeps a searchable log of what happens on the site: logins, publishing, user and plugin changes.
 * Version:           2.3.1
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            Acme Web Team
 * License:           GPL-2.0-or-later
 * Text Domain:       acme-activity-log
 * Domain Path:       /languages
 *
 * @package Acme\ActivityLog
 */

defined( 'ABSPATH' ) || exit;

define( 'ACME_ACTIVITY_VERSION', '2.3.1' );
define( 'ACME_ACTIVITY_FILE', __FILE__ );
define( 'ACME_ACTIVITY_DIR', plugin_dir_path( __FILE__ ) );
define( 'ACME_ACTIVITY_URL', plugin_dir_url( __FILE__ ) );

require_once ACME_ACTIVITY_DIR . 'includes/class-log-store.php';
require_once ACME_ACTIVITY_DIR . 'includes/class-user-state.php';
require_once ACME_ACTIVITY_DIR . 'includes/class-settings.php';
require_once ACME_ACTIVITY_DIR . 'includes/class-tracker.php';
require_once ACME_ACTIVITY_DIR . 'includes/class-rest.php';
require_once ACME_ACTIVITY_DIR . 'includes/class-plugin.php';
require_once ACME_ACTIVITY_DIR . 'includes/functions.php';

if ( is_admin() ) {
	require_once ACME_ACTIVITY_DIR . 'includes/class-admin-page.php';
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once ACME_ACTIVITY_DIR . 'includes/class-cli.php';
}

register_activation_hook( __FILE__, array( 'Acme\\ActivityLog\\Plugin', 'activate' ) );

add_action( 'plugins_loaded', array( 'Acme\\ActivityLog\\Plugin', 'instance' ) );
