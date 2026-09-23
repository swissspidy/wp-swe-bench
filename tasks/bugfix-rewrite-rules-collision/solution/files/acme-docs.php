<?php
/**
 * Plugin Name:       Acme Docs
 * Plugin URI:        https://acme.example/plugins/docs
 * Description:       Product documentation: hierarchical docs per product, versioned docs, breadcrumbs, child page navigation and cross-links.
 * Version:           2.0.0
 * Requires at least: 6.6
 * Requires PHP:      7.4
 * Author:            Acme Web Team
 * License:           GPL-2.0-or-later
 * Text Domain:       acme-docs
 *
 * @package Acme\Docs
 */

defined( 'ABSPATH' ) || exit;

define( 'ACME_DOCS_VERSION', '2.0.0' );
define( 'ACME_DOCS_FILE', __FILE__ );
define( 'ACME_DOCS_DIR', plugin_dir_path( __FILE__ ) );
define( 'ACME_DOCS_URL', plugin_dir_url( __FILE__ ) );

require_once ACME_DOCS_DIR . 'includes/class-post-types.php';
require_once ACME_DOCS_DIR . 'includes/class-permalinks.php';
require_once ACME_DOCS_DIR . 'includes/class-router.php';
require_once ACME_DOCS_DIR . 'includes/class-navigation.php';
require_once ACME_DOCS_DIR . 'includes/class-admin.php';
require_once ACME_DOCS_DIR . 'includes/class-plugin.php';
require_once ACME_DOCS_DIR . 'includes/functions.php';

add_action( 'plugins_loaded', array( 'Acme\\Docs\\Plugin', 'instance' ) );

register_activation_hook( __FILE__, array( 'Acme\\Docs\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Acme\\Docs\\Plugin', 'deactivate' ) );
