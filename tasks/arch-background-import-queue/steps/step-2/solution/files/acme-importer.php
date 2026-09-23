<?php
/**
 * Plugin Name:       Acme Importer
 * Plugin URI:        https://acme.example/plugins/importer
 * Description:       Product catalog for Acme shops with a CSV importer for supplier price lists.
 * Version:           2.4.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Acme Web Team
 * License:           GPL-2.0-or-later
 * Text Domain:       acme-importer
 * Domain Path:       /languages
 *
 * @package Acme\Importer
 */

namespace Acme\Importer;

defined( 'ABSPATH' ) || exit;

define( 'ACME_IMPORTER_VERSION', '2.4.0' );
define( 'ACME_IMPORTER_FILE', __FILE__ );
define( 'ACME_IMPORTER_DIR', plugin_dir_path( __FILE__ ) );
define( 'ACME_IMPORTER_URL', plugin_dir_url( __FILE__ ) );

require_once ACME_IMPORTER_DIR . 'includes/functions.php';
require_once ACME_IMPORTER_DIR . 'includes/class-installer.php';
require_once ACME_IMPORTER_DIR . 'includes/class-product-type.php';
require_once ACME_IMPORTER_DIR . 'includes/class-csv-reader.php';
require_once ACME_IMPORTER_DIR . 'includes/class-row-mapper.php';
require_once ACME_IMPORTER_DIR . 'includes/class-product-repository.php';
require_once ACME_IMPORTER_DIR . 'includes/class-importer.php';
require_once ACME_IMPORTER_DIR . 'includes/class-job-store.php';
require_once ACME_IMPORTER_DIR . 'includes/class-row-log.php';
require_once ACME_IMPORTER_DIR . 'includes/class-row-validator.php';
require_once ACME_IMPORTER_DIR . 'includes/class-queue.php';
require_once ACME_IMPORTER_DIR . 'includes/class-rest-controller.php';
require_once ACME_IMPORTER_DIR . 'includes/class-plugin.php';

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once ACME_IMPORTER_DIR . 'includes/class-cli-command.php';
}

if ( is_admin() ) {
	require_once ACME_IMPORTER_DIR . 'includes/class-admin.php';
}

register_activation_hook( __FILE__, array( Installer::class, 'activate' ) );

add_action( 'plugins_loaded', array( Plugin::class, 'instance' ) );
