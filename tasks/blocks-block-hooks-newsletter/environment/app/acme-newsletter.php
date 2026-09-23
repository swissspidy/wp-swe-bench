<?php
/**
 * Plugin Name:       Acme Newsletter
 * Plugin URI:        https://example.org/acme-newsletter
 * Description:       Newsletter signup form (block, shortcode, widget and automatic placement) that stores subscribers locally.
 * Version:           2.3.1
 * Requires at least: 6.6
 * Requires PHP:      7.4
 * Author:            Acme Digital
 * License:           GPL-2.0-or-later
 * Text Domain:       acme-newsletter
 * Domain Path:       /languages
 *
 * @package Acme\Newsletter
 */

namespace Acme\Newsletter;

defined( 'ABSPATH' ) || exit;

define( 'ACME_NEWSLETTER_VERSION', '2.3.1' );
define( 'ACME_NEWSLETTER_FILE', __FILE__ );
define( 'ACME_NEWSLETTER_DIR', plugin_dir_path( __FILE__ ) );
define( 'ACME_NEWSLETTER_URL', plugin_dir_url( __FILE__ ) );

require_once ACME_NEWSLETTER_DIR . 'includes/functions.php';
require_once ACME_NEWSLETTER_DIR . 'includes/class-installer.php';
require_once ACME_NEWSLETTER_DIR . 'includes/class-subscribers.php';
require_once ACME_NEWSLETTER_DIR . 'includes/class-form.php';
require_once ACME_NEWSLETTER_DIR . 'includes/class-handler.php';
require_once ACME_NEWSLETTER_DIR . 'includes/class-content.php';
require_once ACME_NEWSLETTER_DIR . 'includes/class-shortcode.php';
require_once ACME_NEWSLETTER_DIR . 'includes/class-widget.php';
require_once ACME_NEWSLETTER_DIR . 'includes/class-block.php';
require_once ACME_NEWSLETTER_DIR . 'includes/class-settings.php';
require_once ACME_NEWSLETTER_DIR . 'includes/class-admin-subscribers.php';
require_once ACME_NEWSLETTER_DIR . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( Installer::class, 'activate' ) );

Plugin::instance()->boot();
