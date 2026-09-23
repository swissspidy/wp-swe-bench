<?php
/**
 * Plugin Name:       Acme Leads
 * Plugin URI:        https://example.org/acme-leads
 * Description:       Collects sales leads from the website and lets the sales team work through them.
 * Version:           1.9.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Acme Web Team
 * License:           GPL-2.0-or-later
 * Text Domain:       acme-leads
 * Domain Path:       /languages
 *
 * @package Acme\Leads
 */

defined( 'ABSPATH' ) || exit;

define( 'ACME_LEADS_VERSION', '1.9.0' );
define( 'ACME_LEADS_FILE', __FILE__ );
define( 'ACME_LEADS_DIR', plugin_dir_path( __FILE__ ) );
define( 'ACME_LEADS_URL', plugin_dir_url( __FILE__ ) );

require_once ACME_LEADS_DIR . 'includes/functions.php';
require_once ACME_LEADS_DIR . 'includes/class-installer.php';
require_once ACME_LEADS_DIR . 'includes/class-repository.php';
require_once ACME_LEADS_DIR . 'includes/class-cursor.php';
require_once ACME_LEADS_DIR . 'includes/class-leads-controller.php';
require_once ACME_LEADS_DIR . 'includes/class-rest.php';
require_once ACME_LEADS_DIR . 'includes/class-form.php';
require_once ACME_LEADS_DIR . 'includes/class-notifications.php';
require_once ACME_LEADS_DIR . 'includes/class-admin.php';
require_once ACME_LEADS_DIR . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( 'Acme\Leads\Installer', 'activate' ) );

Acme\Leads\Plugin::instance()->boot();
