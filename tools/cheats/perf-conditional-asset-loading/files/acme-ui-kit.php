<?php
/**
 * Plugin Name:       Acme UI Kit
 * Description:       Tabs, accordions and carousels for the Acme sites: blocks, a legacy shortcode and a small JS runtime other plugins build on.
 * Version:           2.9.0
 * Requires at least: 6.6
 * Requires PHP:      7.4
 * Author:            Acme Web Team
 * License:           GPL-2.0-or-later
 * Text Domain:       acme-ui-kit
 * Domain Path:       /languages
 *
 * @package Acme\UI
 */

defined( 'ABSPATH' ) || exit;

define( 'ACME_UI_VERSION', '2.9.0' );
define( 'ACME_UI_FILE', __FILE__ );
define( 'ACME_UI_DIR', plugin_dir_path( __FILE__ ) );
define( 'ACME_UI_URL', plugin_dir_url( __FILE__ ) );

require_once ACME_UI_DIR . 'includes/class-settings.php';
require_once ACME_UI_DIR . 'includes/class-assets.php';
require_once ACME_UI_DIR . 'includes/class-renderer.php';
require_once ACME_UI_DIR . 'includes/class-blocks.php';
require_once ACME_UI_DIR . 'includes/class-shortcodes.php';
require_once ACME_UI_DIR . 'includes/class-plugin.php';
require_once ACME_UI_DIR . 'includes/functions.php';

add_action( 'plugins_loaded', array( 'Acme\\UI\\Plugin', 'instance' ) );
