<?php
/**
 * Plugin Name:       Acme Social
 * Plugin URI:        https://example.org/acme-social
 * Description:       Share buttons, social profile links and Open Graph / Twitter card tags for Acme sites.
 * Version:           1.6.2
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

define( 'ACME_SOCIAL_VERSION', '1.6.2' );
define( 'ACME_SOCIAL_FILE', __FILE__ );
define( 'ACME_SOCIAL_DIR', plugin_dir_path( __FILE__ ) );
define( 'ACME_SOCIAL_URL', plugin_dir_url( __FILE__ ) );

require_once ACME_SOCIAL_DIR . 'includes/functions.php';
require_once ACME_SOCIAL_DIR . 'includes/class-acme-social-plugin.php';
require_once ACME_SOCIAL_DIR . 'includes/class-acme-social-share-buttons.php';
require_once ACME_SOCIAL_DIR . 'includes/class-acme-social-open-graph.php';
require_once ACME_SOCIAL_DIR . 'includes/class-acme-social-profiles.php';
require_once ACME_SOCIAL_DIR . 'includes/class-acme-social-post-meta.php';

if ( is_admin() ) {
	require_once ACME_SOCIAL_DIR . 'includes/admin/class-acme-social-admin.php';
	require_once ACME_SOCIAL_DIR . 'includes/admin/class-acme-social-sharing-page.php';
	require_once ACME_SOCIAL_DIR . 'includes/admin/class-acme-social-profiles-page.php';
	require_once ACME_SOCIAL_DIR . 'includes/admin/class-acme-social-open-graph-page.php';
}

/**
 * Activation: store the defaults for a fresh install and remember the version.
 *
 * Note: deploys that simply replace the plugin files do not run this.
 */
function acme_social_activate() {
	add_option( 'acme_social_share_buttons_enabled', 'yes' );
	add_option( 'acme_social_networks', 'facebook,twitter,linkedin' );
	add_option( 'acme_share_position', 'after' );
	add_option( 'acme_social_post_types', array( 'post' ) );
	add_option( 'acmesocial_button_style', 'icons' );
	add_option( 'acme_og_enabled', '1' );
	add_option( 'acme_social_twitter_card', 'summary_large_image' );
	update_option( 'acme_social_version', ACME_SOCIAL_VERSION );
}
register_activation_hook( __FILE__, 'acme_social_activate' );

add_action( 'plugins_loaded', array( 'Acme_Social_Plugin', 'instance' ) );
