<?php
/**
 * Plugin Name:       Acme Product Fields
 * Description:       Products for the Acme shop: price, SKU, badge, stock status, featured flag and internal notes. Block editor sidebar, classic meta box, Quick Edit and Bulk Edit.
 * Version:           2.3.2
 * Requires at least: 6.6
 * Requires PHP:      7.4
 * Author:            Acme Web Team
 * License:           GPL-2.0-or-later
 * Text Domain:       acme-product-fields
 *
 * @package Acme\ProductFields
 */

defined( 'ABSPATH' ) || exit;

define( 'ACME_PF_VERSION', '2.3.2' );
define( 'ACME_PF_FILE', __FILE__ );
define( 'ACME_PF_DIR', plugin_dir_path( __FILE__ ) );
define( 'ACME_PF_URL', plugin_dir_url( __FILE__ ) );

require_once ACME_PF_DIR . 'includes/class-fields.php';
require_once ACME_PF_DIR . 'includes/functions.php';
require_once ACME_PF_DIR . 'includes/class-post-type.php';
require_once ACME_PF_DIR . 'includes/class-meta.php';
require_once ACME_PF_DIR . 'includes/class-settings.php';
require_once ACME_PF_DIR . 'includes/class-metabox.php';
require_once ACME_PF_DIR . 'includes/class-list-table.php';
require_once ACME_PF_DIR . 'includes/class-sidebar.php';
require_once ACME_PF_DIR . 'includes/class-frontend.php';
require_once ACME_PF_DIR . 'includes/class-plugin.php';

add_action( 'plugins_loaded', array( 'Acme\\ProductFields\\Plugin', 'instance' ) );
register_activation_hook( __FILE__, array( 'Acme\\ProductFields\\Plugin', 'activate' ) );
