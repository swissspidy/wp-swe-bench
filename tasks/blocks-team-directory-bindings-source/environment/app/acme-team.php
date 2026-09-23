<?php
/**
 * Plugin Name:       Acme Team
 * Description:       Team member profiles (custom post type) with a [team_member] card shortcode and a [team_directory] listing.
 * Version:           2.2.0
 * Requires at least: 6.6
 * Requires PHP:      7.4
 * Author:            Acme Web Team
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       acme-team
 * Domain Path:       /languages
 *
 * @package Acme\Team
 */

defined( 'ABSPATH' ) || exit;

define( 'ACME_TEAM_VERSION', '2.2.0' );
define( 'ACME_TEAM_FILE', __FILE__ );
define( 'ACME_TEAM_DIR', plugin_dir_path( __FILE__ ) );
define( 'ACME_TEAM_URL', plugin_dir_url( __FILE__ ) );

require_once ACME_TEAM_DIR . 'includes/functions.php';
require_once ACME_TEAM_DIR . 'includes/class-member.php';
require_once ACME_TEAM_DIR . 'includes/class-post-type.php';
require_once ACME_TEAM_DIR . 'includes/class-meta-box.php';
require_once ACME_TEAM_DIR . 'includes/class-shortcodes.php';
require_once ACME_TEAM_DIR . 'includes/class-plugin.php';

Acme\Team\Plugin::instance()->boot();
