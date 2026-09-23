<?php
/**
 * Plugin Name:       Acme Directory
 * Plugin URI:        https://acme.example/plugins/directory
 * Description:       Local business directory: listings, categories, a submission form, moderation and a public REST API.
 * Version:           2.4.1
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Acme Web Team
 * License:           GPL-2.0-or-later
 * Text Domain:       acme-directory
 * Domain Path:       /languages
 *
 * @package Acme\Directory
 */

namespace Acme\Directory;

defined( 'ABSPATH' ) || exit;

const VERSION = '2.4.1';

/**
 * Database schema version. Bump when Schema::sql() changes; Installer::maybe_upgrade()
 * re-runs the installer when the stored version is older.
 */
const DB_VERSION = 3;

define( 'ACME_DIRECTORY_FILE', __FILE__ );
define( 'ACME_DIRECTORY_DIR', plugin_dir_path( __FILE__ ) );

require_once ACME_DIRECTORY_DIR . 'includes/class-schema.php';
require_once ACME_DIRECTORY_DIR . 'includes/class-settings.php';
require_once ACME_DIRECTORY_DIR . 'includes/class-categories.php';
require_once ACME_DIRECTORY_DIR . 'includes/class-listings.php';
require_once ACME_DIRECTORY_DIR . 'includes/class-installer.php';
require_once ACME_DIRECTORY_DIR . 'includes/class-cleanup.php';
require_once ACME_DIRECTORY_DIR . 'includes/class-rest-controller.php';
require_once ACME_DIRECTORY_DIR . 'includes/class-shortcodes.php';
require_once ACME_DIRECTORY_DIR . 'includes/class-admin.php';
require_once ACME_DIRECTORY_DIR . 'includes/class-plugin.php';
require_once ACME_DIRECTORY_DIR . 'includes/functions.php';

// Resolve our table names once, up front.
Schema::init();

register_activation_hook( __FILE__, array( Installer::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( Installer::class, 'deactivate' ) );

add_action( 'plugins_loaded', array( Plugin::class, 'boot' ) );
