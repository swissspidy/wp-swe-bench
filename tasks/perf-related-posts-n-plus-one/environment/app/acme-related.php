<?php
/**
 * Plugin Name:       Acme Related
 * Description:       "Related reading" lists under posts, as a block and in the REST API. Shared tags and categories plus editor picks.
 * Version:           2.3.1
 * Requires at least: 6.6
 * Requires PHP:      7.4
 * Author:            Acme Web Team
 * License:           GPL-2.0-or-later
 * Text Domain:       acme-related
 * Domain Path:       /languages
 *
 * @package Acme\Related
 */

defined( 'ABSPATH' ) || exit;

define( 'ACME_RELATED_VERSION', '2.3.1' );
define( 'ACME_RELATED_DB_VERSION', 2 );
define( 'ACME_RELATED_FILE', __FILE__ );
define( 'ACME_RELATED_DIR', plugin_dir_path( __FILE__ ) );
define( 'ACME_RELATED_URL', plugin_dir_url( __FILE__ ) );

require_once ACME_RELATED_DIR . 'includes/functions.php';
require_once ACME_RELATED_DIR . 'includes/class-settings.php';
require_once ACME_RELATED_DIR . 'includes/class-views.php';
require_once ACME_RELATED_DIR . 'includes/class-engine.php';
require_once ACME_RELATED_DIR . 'includes/class-item.php';
require_once ACME_RELATED_DIR . 'includes/class-renderer.php';
require_once ACME_RELATED_DIR . 'includes/class-content.php';
require_once ACME_RELATED_DIR . 'includes/class-block.php';
require_once ACME_RELATED_DIR . 'includes/class-rest.php';
require_once ACME_RELATED_DIR . 'includes/class-metabox.php';
require_once ACME_RELATED_DIR . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( 'Acme\\Related\\Views', 'install' ) );

add_action( 'plugins_loaded', array( 'Acme\\Related\\Plugin', 'instance' ) );
