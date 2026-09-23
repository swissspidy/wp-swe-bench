<?php
/**
 * Plugin Name:       Acme Newsroom
 * Description:       Press releases, newsroom blocks (dateline, boilerplate, media contact, breaking banner) and editorial rules for the Acme newsroom.
 * Version:           4.0.0
 * Requires at least: 6.6
 * Requires PHP:      7.4
 * Author:            Acme Digital
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       acme-newsroom
 * Domain Path:       /languages
 *
 * @package Acme\Newsroom
 */

defined( 'ABSPATH' ) || exit;

define( 'ACME_NEWSROOM_VERSION', '4.0.0' );
define( 'ACME_NEWSROOM_FILE', __FILE__ );
define( 'ACME_NEWSROOM_DIR', plugin_dir_path( __FILE__ ) );
define( 'ACME_NEWSROOM_URL', plugin_dir_url( __FILE__ ) );

require_once ACME_NEWSROOM_DIR . 'includes/functions.php';
require_once ACME_NEWSROOM_DIR . 'includes/class-editorial-rules.php';
require_once ACME_NEWSROOM_DIR . 'includes/class-press-releases.php';
require_once ACME_NEWSROOM_DIR . 'includes/class-blocks.php';
require_once ACME_NEWSROOM_DIR . 'includes/class-settings.php';
require_once ACME_NEWSROOM_DIR . 'includes/class-block-restrictions.php';
require_once ACME_NEWSROOM_DIR . 'includes/class-plugin.php';

Acme\Newsroom\Plugin::instance()->boot();
