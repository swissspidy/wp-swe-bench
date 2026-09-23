<?php
/**
 * Plugin Name:       Acme Social
 * Plugin URI:        https://example.org/acme-social
 * Description:       Share buttons, social profile links and Open Graph / Twitter card tags for Acme sites.
 * Version:           2.0.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            Acme Web Team
 * License:           GPL-2.0-or-later
 * Text Domain:       acme-social
 * Domain Path:       /languages
 *
 * @package Acme_Social
 */

defined( 'ABSPATH' ) || exit;

define( 'ACME_SOCIAL_VERSION', '2.0.0' );
define( 'ACME_SOCIAL_FILE', __FILE__ );
define( 'ACME_SOCIAL_DIR', plugin_dir_path( __FILE__ ) );
define( 'ACME_SOCIAL_URL', plugin_dir_url( __FILE__ ) );

require_once ACME_SOCIAL_DIR . 'includes/functions.php';
require_once ACME_SOCIAL_DIR . 'includes/class-acme-social-settings.php';
require_once ACME_SOCIAL_DIR . 'includes/class-acme-social-legacy.php';
require_once ACME_SOCIAL_DIR . 'includes/class-acme-social-upgrader.php';
require_once ACME_SOCIAL_DIR . 'includes/class-acme-social-plugin.php';
require_once ACME_SOCIAL_DIR . 'includes/class-acme-social-share-buttons.php';
require_once ACME_SOCIAL_DIR . 'includes/class-acme-social-open-graph.php';
require_once ACME_SOCIAL_DIR . 'includes/class-acme-social-profiles.php';
require_once ACME_SOCIAL_DIR . 'includes/class-acme-social-post-meta.php';

if ( is_admin() ) {
	require_once ACME_SOCIAL_DIR . 'includes/admin/class-acme-social-admin.php';
	require_once ACME_SOCIAL_DIR . 'includes/admin/class-acme-social-settings-page.php';
	require_once ACME_SOCIAL_DIR . 'includes/admin/class-acme-social-sharing-page.php';
	require_once ACME_SOCIAL_DIR . 'includes/admin/class-acme-social-profiles-page.php';
	require_once ACME_SOCIAL_DIR . 'includes/admin/class-acme-social-open-graph-page.php';
}

/**
 * Activation: a fresh install starts with the defaults; an older install is
 * upgraded (deploys that replace the files are upgraded on the next request).
 */
function acme_social_activate() {
	if ( false === get_option( Acme_Social_Upgrader::VERSION_OPTION, false ) && false === get_option( Acme_Social_Settings::OPTION, false ) && ! Acme_Social_Legacy::raw_values() ) {
		add_option( Acme_Social_Settings::OPTION, Acme_Social_Settings::defaults() );
		update_option( Acme_Social_Upgrader::VERSION_OPTION, ACME_SOCIAL_VERSION );
		return;
	}
	( new Acme_Social_Upgrader() )->maybe_upgrade();
}
register_activation_hook( __FILE__, 'acme_social_activate' );

add_action( 'plugins_loaded', array( 'Acme_Social_Plugin', 'instance' ) );
