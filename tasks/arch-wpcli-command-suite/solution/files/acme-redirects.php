<?php
/**
 * Plugin Name:       Acme Redirects
 * Plugin URI:        https://acme.example/plugins/redirects
 * Description:       Manage 301/302/307/308/410 redirects with exact, prefix and regular expression rules, priorities and hit counters.
 * Version:           2.4.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Acme Web Team
 * License:           GPL-2.0-or-later
 * Text Domain:       acme-redirects
 * Domain Path:       /languages
 *
 * @package Acme\Redirects
 */

namespace Acme\Redirects;

defined( 'ABSPATH' ) || exit;

define( 'ACME_REDIRECTS_VERSION', '2.4.0' );
define( 'ACME_REDIRECTS_FILE', __FILE__ );
define( 'ACME_REDIRECTS_DIR', plugin_dir_path( __FILE__ ) );
define( 'ACME_REDIRECTS_URL', plugin_dir_url( __FILE__ ) );

require_once ACME_REDIRECTS_DIR . 'includes/functions.php';
require_once ACME_REDIRECTS_DIR . 'includes/class-rule.php';
require_once ACME_REDIRECTS_DIR . 'includes/class-rule-validator.php';
require_once ACME_REDIRECTS_DIR . 'includes/class-rule-repository.php';
require_once ACME_REDIRECTS_DIR . 'includes/class-installer.php';
require_once ACME_REDIRECTS_DIR . 'includes/class-matcher.php';
require_once ACME_REDIRECTS_DIR . 'includes/class-csv-importer.php';
require_once ACME_REDIRECTS_DIR . 'includes/class-redirector.php';
require_once ACME_REDIRECTS_DIR . 'includes/class-plugin.php';

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once ACME_REDIRECTS_DIR . 'includes/class-cli-command.php';
}

if ( is_admin() ) {
	require_once ACME_REDIRECTS_DIR . 'includes/class-list-table.php';
	require_once ACME_REDIRECTS_DIR . 'includes/class-admin.php';
}

register_activation_hook( __FILE__, array( Installer::class, 'activate' ) );

add_action( 'plugins_loaded', array( Plugin::class, 'instance' ) );
