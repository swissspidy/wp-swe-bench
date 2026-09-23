<?php
/**
 * Plugin Name:       Acme Migrate
 * Description:       Site migration helpers for the Acme agency: search & replace across the database (Tools → Acme Migrate and WP-CLI), with run history.
 * Version:           1.4.2
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Acme Agency
 * License:           GPL-2.0-or-later
 * Text Domain:       acme-migrate
 *
 * @package Acme\Migrate
 */

defined( 'ABSPATH' ) || exit;

define( 'ACME_MIGRATE_VERSION', '1.4.2' );
define( 'ACME_MIGRATE_FILE', __FILE__ );
define( 'ACME_MIGRATE_DIR', plugin_dir_path( __FILE__ ) );
define( 'ACME_MIGRATE_URL', plugin_dir_url( __FILE__ ) );

require_once ACME_MIGRATE_DIR . 'includes/functions.php';
require_once ACME_MIGRATE_DIR . 'includes/class-replacer.php';
require_once ACME_MIGRATE_DIR . 'includes/class-table-map.php';
require_once ACME_MIGRATE_DIR . 'includes/class-report.php';
require_once ACME_MIGRATE_DIR . 'includes/class-history.php';
require_once ACME_MIGRATE_DIR . 'includes/class-runner.php';
require_once ACME_MIGRATE_DIR . 'includes/class-admin-page.php';
require_once ACME_MIGRATE_DIR . 'includes/class-plugin.php';

add_action( 'plugins_loaded', array( 'Acme\\Migrate\\Plugin', 'instance' ) );

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once ACME_MIGRATE_DIR . 'includes/class-cli-command.php';
	WP_CLI::add_command( 'acme-migrate', 'Acme\\Migrate\\CLI_Command' );
}
