<?php
/**
 * Plugin Name:       Acme Support
 * Plugin URI:        https://example.org/acme-support
 * Description:       A lightweight helpdesk: customers open support tickets, agents reply and keep private internal notes.
 * Version:           1.4.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Acme Web Team
 * License:           GPL-2.0-or-later
 * Text Domain:       acme-support
 * Domain Path:       /languages
 *
 * @package Acme\Support
 */

defined( 'ABSPATH' ) || exit;

define( 'ACME_SUPPORT_VERSION', '1.4.0' );
define( 'ACME_SUPPORT_FILE', __FILE__ );
define( 'ACME_SUPPORT_DIR', plugin_dir_path( __FILE__ ) );
define( 'ACME_SUPPORT_URL', plugin_dir_url( __FILE__ ) );

require_once ACME_SUPPORT_DIR . 'includes/functions.php';
require_once ACME_SUPPORT_DIR . 'includes/class-installer.php';
require_once ACME_SUPPORT_DIR . 'includes/class-post-types.php';
require_once ACME_SUPPORT_DIR . 'includes/class-tickets.php';
require_once ACME_SUPPORT_DIR . 'includes/class-replies.php';
require_once ACME_SUPPORT_DIR . 'includes/class-attachments.php';
require_once ACME_SUPPORT_DIR . 'includes/class-rest.php';
require_once ACME_SUPPORT_DIR . 'includes/class-notifications.php';
require_once ACME_SUPPORT_DIR . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( 'Acme\Support\Installer', 'activate' ) );

Acme\Support\Plugin::instance()->boot();
