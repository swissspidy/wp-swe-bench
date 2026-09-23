<?php
/**
 * Plugin Name:       Acme Content Blocks
 * Description:       Notice boxes and statistics for the Acme sites.
 * Version:           1.6.0
 * Requires at least: 6.6
 * Requires PHP:      7.4
 * Author:            Acme Web Team
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       acme-content-blocks
 * Domain Path:       /languages
 *
 * @package Acme\ContentBlocks
 */

defined( 'ABSPATH' ) || exit;

define( 'ACME_CONTENT_BLOCKS_VERSION', '1.6.0' );
define( 'ACME_CONTENT_BLOCKS_FILE', __FILE__ );
define( 'ACME_CONTENT_BLOCKS_DIR', plugin_dir_path( __FILE__ ) );

require_once ACME_CONTENT_BLOCKS_DIR . 'includes/functions.php';
require_once ACME_CONTENT_BLOCKS_DIR . 'includes/class-colors.php';
require_once ACME_CONTENT_BLOCKS_DIR . 'includes/class-blocks.php';
require_once ACME_CONTENT_BLOCKS_DIR . 'includes/class-notices-api.php';
require_once ACME_CONTENT_BLOCKS_DIR . 'includes/class-plugin.php';

Acme\ContentBlocks\Plugin::instance()->boot();
