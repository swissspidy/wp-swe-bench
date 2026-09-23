<?php
/**
 * Plugin Name:       Acme Pricing Tables
 * Description:       Pricing table block with plans, prices in several currencies and structured data for search engines.
 * Version:           1.6.0
 * Requires at least: 6.6
 * Requires PHP:      7.4
 * Author:            Acme Web Team
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       acme-pricing
 * Domain Path:       /languages
 *
 * @package Acme\Pricing
 */

defined( 'ABSPATH' ) || exit;

define( 'ACME_PRICING_VERSION', '1.6.0' );
define( 'ACME_PRICING_FILE', __FILE__ );
define( 'ACME_PRICING_DIR', plugin_dir_path( __FILE__ ) );
define( 'ACME_PRICING_URL', plugin_dir_url( __FILE__ ) );

require_once ACME_PRICING_DIR . 'includes/functions.php';
require_once ACME_PRICING_DIR . 'includes/class-currency.php';
require_once ACME_PRICING_DIR . 'includes/class-settings.php';
require_once ACME_PRICING_DIR . 'includes/class-block.php';
require_once ACME_PRICING_DIR . 'includes/class-schema.php';
require_once ACME_PRICING_DIR . 'includes/class-shortcode.php';
require_once ACME_PRICING_DIR . 'includes/class-plugin.php';

Acme\Pricing\Plugin::instance()->boot();
