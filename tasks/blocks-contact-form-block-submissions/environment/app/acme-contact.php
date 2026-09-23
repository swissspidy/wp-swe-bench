<?php
/**
 * Plugin Name:       Acme Contact
 * Description:       Contact form for Acme sites: [acme_contact] shortcode, email notification to the site owner, spam protection.
 * Version:           1.6.2
 * Requires at least: 6.6
 * Requires PHP:      7.4
 * Author:            Acme Web Team
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       acme-contact
 * Domain Path:       /languages
 *
 * @package Acme\Contact
 */

defined( 'ABSPATH' ) || exit;

define( 'ACME_CONTACT_VERSION', '1.6.2' );
define( 'ACME_CONTACT_FILE', __FILE__ );
define( 'ACME_CONTACT_DIR', plugin_dir_path( __FILE__ ) );
define( 'ACME_CONTACT_URL', plugin_dir_url( __FILE__ ) );

require_once ACME_CONTACT_DIR . 'includes/functions.php';
require_once ACME_CONTACT_DIR . 'includes/class-validator.php';
require_once ACME_CONTACT_DIR . 'includes/class-rate-limiter.php';
require_once ACME_CONTACT_DIR . 'includes/class-mailer.php';
require_once ACME_CONTACT_DIR . 'includes/class-shortcode.php';
require_once ACME_CONTACT_DIR . 'includes/class-submission-handler.php';
require_once ACME_CONTACT_DIR . 'includes/class-settings.php';
require_once ACME_CONTACT_DIR . 'includes/class-plugin.php';

Acme\Contact\Plugin::instance()->boot();
