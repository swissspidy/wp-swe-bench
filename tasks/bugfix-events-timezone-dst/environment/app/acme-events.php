<?php
/**
 * Plugin Name:       Acme Events Calendar
 * Plugin URI:        https://acme.example/plugins/events
 * Description:       Events for the Acme sites: event editor, single event details, upcoming events list, iCal feed and a small REST API for the app.
 * Version:           1.6.2
 * Requires at least: 6.6
 * Requires PHP:      7.4
 * Author:            Acme Web Team
 * License:           GPL-2.0-or-later
 * Text Domain:       acme-events
 *
 * @package Acme\Events
 */

defined( 'ABSPATH' ) || exit;

define( 'ACME_EVENTS_VERSION', '1.6.2' );
define( 'ACME_EVENTS_FILE', __FILE__ );
define( 'ACME_EVENTS_DIR', plugin_dir_path( __FILE__ ) );
define( 'ACME_EVENTS_URL', plugin_dir_url( __FILE__ ) );

require_once ACME_EVENTS_DIR . 'includes/class-clock.php';
require_once ACME_EVENTS_DIR . 'includes/class-dates.php';
require_once ACME_EVENTS_DIR . 'includes/class-event.php';
require_once ACME_EVENTS_DIR . 'includes/class-post-type.php';
require_once ACME_EVENTS_DIR . 'includes/class-upgrader.php';
require_once ACME_EVENTS_DIR . 'includes/class-admin.php';
require_once ACME_EVENTS_DIR . 'includes/class-query.php';
require_once ACME_EVENTS_DIR . 'includes/class-frontend.php';
require_once ACME_EVENTS_DIR . 'includes/class-ical.php';
require_once ACME_EVENTS_DIR . 'includes/class-rest.php';
require_once ACME_EVENTS_DIR . 'includes/class-plugin.php';
require_once ACME_EVENTS_DIR . 'includes/functions.php';

add_action( 'plugins_loaded', array( 'Acme\\Events\\Plugin', 'instance' ) );

register_activation_hook( __FILE__, array( 'Acme\\Events\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Acme\\Events\\Plugin', 'deactivate' ) );
