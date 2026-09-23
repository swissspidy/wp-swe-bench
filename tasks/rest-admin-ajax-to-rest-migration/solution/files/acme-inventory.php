<?php
/**
 * Plugin Name:       Acme Inventory
 * Plugin URI:        https://example.org/acme-inventory
 * Description:       Stock levels for the Acme shop warehouse: an inventory screen for shop managers, stock adjustments with an audit log, CSV export and low-stock alerts.
 * Version:           4.0.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            Acme Shop Web Team
 * License:           GPL-2.0-or-later
 * Text Domain:       acme-inventory
 * Domain Path:       /languages
 *
 * @package Acme\Inventory
 */

defined( 'ABSPATH' ) || exit;

define( 'ACME_INVENTORY_VERSION', '4.0.0' );
define( 'ACME_INVENTORY_FILE', __FILE__ );
define( 'ACME_INVENTORY_DIR', plugin_dir_path( __FILE__ ) );
define( 'ACME_INVENTORY_URL', plugin_dir_url( __FILE__ ) );

require_once ACME_INVENTORY_DIR . 'includes/functions.php';
require_once ACME_INVENTORY_DIR . 'includes/class-installer.php';
require_once ACME_INVENTORY_DIR . 'includes/class-items.php';
require_once ACME_INVENTORY_DIR . 'includes/class-csv.php';
require_once ACME_INVENTORY_DIR . 'includes/class-alerts.php';
require_once ACME_INVENTORY_DIR . 'includes/class-service.php';
require_once ACME_INVENTORY_DIR . 'includes/class-rest-controller.php';
require_once ACME_INVENTORY_DIR . 'includes/class-ajax.php';
require_once ACME_INVENTORY_DIR . 'includes/class-admin.php';
require_once ACME_INVENTORY_DIR . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( 'Acme\\Inventory\\Installer', 'activate' ) );

add_action( 'plugins_loaded', array( 'Acme\\Inventory\\Plugin', 'instance' ) );
