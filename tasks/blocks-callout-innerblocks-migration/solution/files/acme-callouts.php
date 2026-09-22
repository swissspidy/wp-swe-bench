<?php
/**
 * Plugin Name:       Acme Callouts
 * Description:       Callout boxes (info, success, warning, danger) as a block, plus the legacy [callout] shortcode.
 * Version:           2.0.0
 * Requires at least: 6.6
 * Requires PHP:      7.4
 * Author:            Acme Web Team
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       acme-callouts
 * Domain Path:       /languages
 *
 * @package Acme\Callouts
 */

defined( 'ABSPATH' ) || exit;

define( 'ACME_CALLOUTS_VERSION', '2.0.0' );
define( 'ACME_CALLOUTS_FILE', __FILE__ );
define( 'ACME_CALLOUTS_DIR', plugin_dir_path( __FILE__ ) );
define( 'ACME_CALLOUTS_URL', plugin_dir_url( __FILE__ ) );

require_once ACME_CALLOUTS_DIR . 'includes/functions.php';
require_once ACME_CALLOUTS_DIR . 'includes/class-settings.php';
require_once ACME_CALLOUTS_DIR . 'includes/class-renderer.php';
require_once ACME_CALLOUTS_DIR . 'includes/class-shortcode.php';
require_once ACME_CALLOUTS_DIR . 'includes/class-block.php';
require_once ACME_CALLOUTS_DIR . 'includes/class-stats.php';
require_once ACME_CALLOUTS_DIR . 'includes/class-plugin.php';

Acme\Callouts\Plugin::instance()->boot();
