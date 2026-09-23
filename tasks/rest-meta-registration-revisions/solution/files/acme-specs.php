<?php
/**
 * Plugin Name:       Acme Specs
 * Plugin URI:        https://example.org/acme-specs
 * Description:       Product catalogue with technical specifications (dimensions, materials, certifications).
 * Version:           3.0.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Acme Furniture Web Team
 * License:           GPL-2.0-or-later
 * Text Domain:       acme-specs
 * Domain Path:       /languages
 *
 * @package Acme\Specs
 */

defined( 'ABSPATH' ) || exit;

define( 'ACME_SPECS_VERSION', '3.0.0' );
define( 'ACME_SPECS_FILE', __FILE__ );
define( 'ACME_SPECS_DIR', plugin_dir_path( __FILE__ ) );
define( 'ACME_SPECS_URL', plugin_dir_url( __FILE__ ) );

require_once ACME_SPECS_DIR . 'includes/functions.php';
require_once ACME_SPECS_DIR . 'includes/class-legacy.php';
require_once ACME_SPECS_DIR . 'includes/class-specs.php';
require_once ACME_SPECS_DIR . 'includes/class-post-type.php';
require_once ACME_SPECS_DIR . 'includes/class-meta.php';
require_once ACME_SPECS_DIR . 'includes/class-migration.php';
require_once ACME_SPECS_DIR . 'includes/class-metabox.php';
require_once ACME_SPECS_DIR . 'includes/class-frontend.php';
require_once ACME_SPECS_DIR . 'includes/class-admin-columns.php';
require_once ACME_SPECS_DIR . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( 'Acme\Specs\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Acme\Specs\Plugin', 'deactivate' ) );

Acme\Specs\Plugin::instance()->boot();

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once ACME_SPECS_DIR . 'includes/class-cli.php';
	WP_CLI::add_command( 'acme-specs', 'Acme\Specs\CLI' );
}
