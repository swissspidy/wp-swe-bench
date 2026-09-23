<?php
/**
 * Plugin Name:       Acme Contact
 * Description:       Contact form for Acme sites: Contact form block (and the classic [acme_contact] shortcode), stored entries, email notification to the site owner, spam protection.
 * Version:           2.0.0
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

define( 'ACME_CONTACT_VERSION', '2.0.0' );
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
require_once ACME_CONTACT_DIR . 'includes/class-forms.php';
require_once ACME_CONTACT_DIR . 'includes/class-entries.php';
require_once ACME_CONTACT_DIR . 'includes/class-block-submissions.php';
require_once ACME_CONTACT_DIR . 'includes/class-blocks.php';
require_once ACME_CONTACT_DIR . 'includes/class-rest.php';
require_once ACME_CONTACT_DIR . 'includes/class-entries-admin.php';
require_once ACME_CONTACT_DIR . 'includes/class-plugin.php';

Acme\Contact\Plugin::instance()->boot();
