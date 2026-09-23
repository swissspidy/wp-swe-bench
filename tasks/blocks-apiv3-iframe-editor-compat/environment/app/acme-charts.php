<?php
/**
 * Plugin Name:       Acme Charts
 * Description:       Simple bar charts and chart legends for the block editor.
 * Version:           1.6.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Acme Web Team
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       acme-charts
 * Domain Path:       /languages
 *
 * @package Acme\Charts
 */

defined( 'ABSPATH' ) || exit;

define( 'ACME_CHARTS_VERSION', '1.6.0' );
define( 'ACME_CHARTS_FILE', __FILE__ );
define( 'ACME_CHARTS_DIR', plugin_dir_path( __FILE__ ) );
define( 'ACME_CHARTS_URL', plugin_dir_url( __FILE__ ) );

require_once ACME_CHARTS_DIR . 'includes/functions.php';
require_once ACME_CHARTS_DIR . 'includes/class-settings.php';
require_once ACME_CHARTS_DIR . 'includes/class-assets.php';
require_once ACME_CHARTS_DIR . 'includes/class-blocks.php';
require_once ACME_CHARTS_DIR . 'includes/class-plugin.php';

Acme\Charts\Plugin::instance()->boot();
