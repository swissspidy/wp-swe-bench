<?php
/**
 * Plugin Name:       Acme CRM
 * Plugin URI:        https://acme.example/plugins/crm
 * Description:       Lightweight CRM: contacts, notes, lifecycle stages, a website contact form, CSV export and a REST API for the sales app.
 * Version:           1.5.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Acme Web Team
 * License:           GPL-2.0-or-later
 * Text Domain:       acme-crm
 * Domain Path:       /languages
 *
 * @package Acme\CRM
 */

namespace Acme\CRM;

defined( 'ABSPATH' ) || exit;

const VERSION = '1.5.0';

define( 'ACME_CRM_FILE', __FILE__ );
define( 'ACME_CRM_DIR', plugin_dir_path( __FILE__ ) );

require_once ACME_CRM_DIR . 'includes/class-installer.php';
require_once ACME_CRM_DIR . 'includes/class-schema.php';
require_once ACME_CRM_DIR . 'includes/class-migrations.php';
require_once ACME_CRM_DIR . 'includes/class-migrator.php';
require_once ACME_CRM_DIR . 'includes/class-stages.php';
require_once ACME_CRM_DIR . 'includes/class-contacts.php';
require_once ACME_CRM_DIR . 'includes/class-notes.php';
require_once ACME_CRM_DIR . 'includes/class-rest-controller.php';
require_once ACME_CRM_DIR . 'includes/class-contact-form.php';
require_once ACME_CRM_DIR . 'includes/class-export.php';
require_once ACME_CRM_DIR . 'includes/class-admin.php';
require_once ACME_CRM_DIR . 'includes/class-cli.php';
require_once ACME_CRM_DIR . 'includes/class-plugin.php';
require_once ACME_CRM_DIR . 'includes/functions.php';

register_activation_hook( __FILE__, array( Installer::class, 'activate' ) );

add_action( 'plugins_loaded', array( Plugin::class, 'boot' ) );
