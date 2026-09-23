<?php
/**
 * Plugin Name:       Acme Events
 * Description:       Event listings for the Acme sites: an "Event" post type, the [acme_events] shortcode, an "Upcoming events" widget and the "Upcoming Events" and "Event details" blocks.
 * Version:           2.4.0
 * Requires at least: 6.6
 * Requires PHP:      7.4
 * Author:            Acme Web Team
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       acme-events
 * Domain Path:       /languages
 *
 * @package Acme\Events
 */

defined( 'ABSPATH' ) || exit;

define( 'ACME_EVENTS_VERSION', '2.4.0' );
define( 'ACME_EVENTS_FILE', __FILE__ );
define( 'ACME_EVENTS_DIR', plugin_dir_path( __FILE__ ) );
define( 'ACME_EVENTS_URL', plugin_dir_url( __FILE__ ) );

require_once ACME_EVENTS_DIR . 'includes/functions.php';
require_once ACME_EVENTS_DIR . 'includes/class-post-type.php';
require_once ACME_EVENTS_DIR . 'includes/class-admin.php';
require_once ACME_EVENTS_DIR . 'includes/class-listing.php';
require_once ACME_EVENTS_DIR . 'includes/class-shortcode.php';
require_once ACME_EVENTS_DIR . 'includes/class-widget.php';
require_once ACME_EVENTS_DIR . 'includes/class-blocks.php';
require_once ACME_EVENTS_DIR . 'includes/class-plugin.php';

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once ACME_EVENTS_DIR . 'includes/class-cli.php';
}

Acme\Events\Plugin::instance()->boot();
