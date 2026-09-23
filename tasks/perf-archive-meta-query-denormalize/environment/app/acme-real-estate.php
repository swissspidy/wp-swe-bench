<?php
/**
 * Plugin Name:       Acme Real Estate
 * Plugin URI:        https://acme.example/plugins/real-estate
 * Description:       Property listings with a search form, a public REST API and WP-CLI import/export.
 * Version:           1.6.2
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            Acme Web Team
 * License:           GPL-2.0-or-later
 * Text Domain:       acme-real-estate
 * Domain Path:       /languages
 *
 * @package Acme\RealEstate
 */

defined( 'ABSPATH' ) || exit;

define( 'ACME_RE_VERSION', '1.6.2' );
define( 'ACME_RE_FILE', __FILE__ );
define( 'ACME_RE_DIR', plugin_dir_path( __FILE__ ) );
define( 'ACME_RE_URL', plugin_dir_url( __FILE__ ) );

require_once ACME_RE_DIR . 'includes/class-features.php';
require_once ACME_RE_DIR . 'includes/class-listing.php';
require_once ACME_RE_DIR . 'includes/class-post-type.php';
require_once ACME_RE_DIR . 'includes/class-search.php';
require_once ACME_RE_DIR . 'includes/class-shortcode.php';
require_once ACME_RE_DIR . 'includes/class-rest.php';
require_once ACME_RE_DIR . 'includes/class-importer.php';
require_once ACME_RE_DIR . 'includes/class-plugin.php';
require_once ACME_RE_DIR . 'includes/functions.php';

if ( is_admin() ) {
	require_once ACME_RE_DIR . 'includes/class-meta-box.php';
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once ACME_RE_DIR . 'includes/class-cli.php';
}

register_activation_hook( __FILE__, array( 'Acme\\RealEstate\\Plugin', 'activate' ) );

add_action( 'plugins_loaded', array( 'Acme\\RealEstate\\Plugin', 'instance' ) );
