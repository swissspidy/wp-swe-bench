<?php
/**
 * Plugin Name:       Acme Call to Action
 * Description:       Call-to-action block (heading + button) with campaign click tracking and a site-wide CTA inventory.
 * Version:           2.3.0
 * Requires at least: 6.7
 * Requires PHP:      7.4
 * Author:            Acme Web Team
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       acme-cta
 * Domain Path:       /languages
 *
 * @package Acme\CTA
 */

defined( 'ABSPATH' ) || exit;

define( 'ACME_CTA_VERSION', '2.3.0' );
define( 'ACME_CTA_FILE', __FILE__ );
define( 'ACME_CTA_DIR', plugin_dir_path( __FILE__ ) );
define( 'ACME_CTA_URL', plugin_dir_url( __FILE__ ) );

require_once ACME_CTA_DIR . 'includes/functions.php';
require_once ACME_CTA_DIR . 'includes/class-settings.php';
require_once ACME_CTA_DIR . 'includes/class-tracking.php';
require_once ACME_CTA_DIR . 'includes/class-block.php';
require_once ACME_CTA_DIR . 'includes/class-inventory.php';
require_once ACME_CTA_DIR . 'includes/class-plugin.php';

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once ACME_CTA_DIR . 'includes/class-cli.php';
}

Acme\CTA\Plugin::instance()->boot();
