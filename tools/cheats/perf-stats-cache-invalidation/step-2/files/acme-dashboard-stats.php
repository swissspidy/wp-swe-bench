<?php
/**
 * Plugin Name:       Acme Dashboard Stats
 * Description:       Newsroom numbers on the dashboard: posts per author, category and month, word counts and comment activity. Also as a shortcode and a REST endpoint.
 * Version:           1.7.0
 * Requires at least: 6.6
 * Requires PHP:      7.4
 * Author:            Acme Web Team
 * License:           GPL-2.0-or-later
 * Text Domain:       acme-dashboard-stats
 * Domain Path:       /languages
 *
 * @package Acme\Stats
 */

defined( 'ABSPATH' ) || exit;

define( 'ACME_STATS_VERSION', '1.7.0' );
define( 'ACME_STATS_FILE', __FILE__ );
define( 'ACME_STATS_DIR', plugin_dir_path( __FILE__ ) );
define( 'ACME_STATS_URL', plugin_dir_url( __FILE__ ) );

require_once ACME_STATS_DIR . 'includes/functions.php';
require_once ACME_STATS_DIR . 'includes/class-scope.php';
require_once ACME_STATS_DIR . 'includes/class-calculator.php';
require_once ACME_STATS_DIR . 'includes/class-cache.php';
require_once ACME_STATS_DIR . 'includes/class-invalidator.php';
require_once ACME_STATS_DIR . 'includes/class-lock.php';
require_once ACME_STATS_DIR . 'includes/class-stats.php';
require_once ACME_STATS_DIR . 'includes/class-format.php';
require_once ACME_STATS_DIR . 'includes/class-dashboard-widget.php';
require_once ACME_STATS_DIR . 'includes/class-admin-page.php';
require_once ACME_STATS_DIR . 'includes/class-shortcode.php';
require_once ACME_STATS_DIR . 'includes/class-rest.php';
require_once ACME_STATS_DIR . 'includes/class-plugin.php';

add_action( 'plugins_loaded', array( 'Acme\\Stats\\Plugin', 'instance' ) );

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once ACME_STATS_DIR . 'includes/class-cli.php';
	WP_CLI::add_command( 'acme-stats', 'Acme\\Stats\\CLI' );
}
