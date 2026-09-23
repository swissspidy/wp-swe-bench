<?php
/**
 * Plugin Name:       Acme Bookings
 * Plugin URI:        https://example.org/acme-bookings
 * Description:       Room bookings for Acme guest houses: rooms, booking requests, an office screen and an availability widget.
 * Version:           2.0.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Acme Hospitality Web Team
 * License:           GPL-2.0-or-later
 * Text Domain:       acme-bookings
 * Domain Path:       /languages
 *
 * @package Acme\Bookings
 */

defined( 'ABSPATH' ) || exit;

define( 'ACME_BOOKINGS_VERSION', '2.0.0' );
define( 'ACME_BOOKINGS_FILE', __FILE__ );
define( 'ACME_BOOKINGS_DIR', plugin_dir_path( __FILE__ ) );
define( 'ACME_BOOKINGS_URL', plugin_dir_url( __FILE__ ) );

require_once ACME_BOOKINGS_DIR . 'includes/functions.php';
require_once ACME_BOOKINGS_DIR . 'includes/class-installer.php';
require_once ACME_BOOKINGS_DIR . 'includes/class-rooms.php';
require_once ACME_BOOKINGS_DIR . 'includes/class-pricing.php';
require_once ACME_BOOKINGS_DIR . 'includes/class-repository.php';
require_once ACME_BOOKINGS_DIR . 'includes/class-notifications.php';
require_once ACME_BOOKINGS_DIR . 'includes/class-bookings-controller.php';
require_once ACME_BOOKINGS_DIR . 'includes/class-rest.php';
require_once ACME_BOOKINGS_DIR . 'includes/class-admin.php';
require_once ACME_BOOKINGS_DIR . 'includes/class-widget.php';
require_once ACME_BOOKINGS_DIR . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( 'Acme\\Bookings\\Installer', 'activate' ) );

add_action( 'plugins_loaded', array( 'Acme\\Bookings\\Plugin', 'instance' ) );
