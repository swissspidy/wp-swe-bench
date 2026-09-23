<?php
/**
 * Plugin Name:       Acme Loyalty
 * Description:       Loyalty points, member tiers, the store newsletter and order sync for the Acme Coffee Roasters shop.
 * Version:           2.4.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Acme Web Team
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       acme-loyalty
 * Domain Path:       /languages
 *
 * @package Acme\Loyalty
 */

defined( 'ABSPATH' ) || exit;

define( 'ACME_LOYALTY_VERSION', '2.4.0' );
define( 'ACME_LOYALTY_DB_VERSION', 4 );
define( 'ACME_LOYALTY_FILE', __FILE__ );
define( 'ACME_LOYALTY_DIR', plugin_dir_path( __FILE__ ) );
define( 'ACME_LOYALTY_URL', plugin_dir_url( __FILE__ ) );

require_once ACME_LOYALTY_DIR . 'includes/functions.php';
require_once ACME_LOYALTY_DIR . 'includes/class-installer.php';
require_once ACME_LOYALTY_DIR . 'includes/class-ledger.php';
require_once ACME_LOYALTY_DIR . 'includes/class-members.php';
require_once ACME_LOYALTY_DIR . 'includes/class-newsletter.php';
require_once ACME_LOYALTY_DIR . 'includes/class-orders.php';
require_once ACME_LOYALTY_DIR . 'includes/class-account.php';
require_once ACME_LOYALTY_DIR . 'includes/class-settings.php';
require_once ACME_LOYALTY_DIR . 'includes/class-user-profile.php';
require_once ACME_LOYALTY_DIR . 'includes/class-privacy.php';
require_once ACME_LOYALTY_DIR . 'includes/class-retention.php';
require_once ACME_LOYALTY_DIR . 'includes/class-plugin.php';

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once ACME_LOYALTY_DIR . 'includes/class-cli.php';
}

register_activation_hook( __FILE__, array( 'Acme\\Loyalty\\Installer', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Acme\\Loyalty\\Installer', 'deactivate' ) );

Acme\Loyalty\Plugin::instance()->boot();
