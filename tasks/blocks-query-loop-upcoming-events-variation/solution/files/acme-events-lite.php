<?php
/**
 * Plugin Name:       Acme Events Lite
 * Plugin URI:        https://example.org/acme-events-lite
 * Description:       Lightweight events: an Event post type with dates, status and venue, an upcoming events shortcode and REST data for the mobile app.
 * Version:           1.7.0
 * Requires at least: 6.6
 * Requires PHP:      7.4
 * Author:            Acme Digital
 * License:           GPL-2.0-or-later
 * Text Domain:       acme-events-lite
 * Domain Path:       /languages
 *
 * @package Acme\Events
 */

namespace Acme\Events;

defined( 'ABSPATH' ) || exit;

define( 'ACME_EVENTS_VERSION', '1.7.0' );
define( 'ACME_EVENTS_FILE', __FILE__ );
define( 'ACME_EVENTS_DIR', plugin_dir_path( __FILE__ ) );
define( 'ACME_EVENTS_URL', plugin_dir_url( __FILE__ ) );

require_once ACME_EVENTS_DIR . 'includes/functions.php';
require_once ACME_EVENTS_DIR . 'includes/class-event.php';
require_once ACME_EVENTS_DIR . 'includes/class-post-type.php';
require_once ACME_EVENTS_DIR . 'includes/class-meta.php';
require_once ACME_EVENTS_DIR . 'includes/class-upcoming.php';
require_once ACME_EVENTS_DIR . 'includes/class-shortcode.php';
require_once ACME_EVENTS_DIR . 'includes/class-query-loop.php';
require_once ACME_EVENTS_DIR . 'includes/class-blocks.php';
require_once ACME_EVENTS_DIR . 'includes/class-rest.php';
require_once ACME_EVENTS_DIR . 'includes/class-admin.php';
require_once ACME_EVENTS_DIR . 'includes/class-editor.php';
require_once ACME_EVENTS_DIR . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( Plugin::class, 'activate' ) );

Plugin::instance()->boot();
