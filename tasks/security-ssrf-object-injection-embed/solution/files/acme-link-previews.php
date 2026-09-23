<?php
/**
 * Plugin Name:       Acme Link Previews
 * Plugin URI:        https://example.org/acme-link-previews
 * Description:       Turns a URL into a rich preview card (title, description, image) by fetching the remote page, and caches the result.
 * Version:           2.2.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Acme Web Team
 * License:           GPL-2.0-or-later
 * Text Domain:       acme-link-previews
 * Domain Path:       /languages
 *
 * @package Acme\LinkPreviews
 */

defined( 'ABSPATH' ) || exit;

define( 'ACME_LP_VERSION', '2.2.0' );
define( 'ACME_LP_FILE', __FILE__ );
define( 'ACME_LP_DIR', plugin_dir_path( __FILE__ ) );
define( 'ACME_LP_URL', plugin_dir_url( __FILE__ ) );

require_once ACME_LP_DIR . 'includes/functions.php';
require_once ACME_LP_DIR . 'includes/class-disk-cache-writer.php';
require_once ACME_LP_DIR . 'includes/class-cache.php';
require_once ACME_LP_DIR . 'includes/class-url-guard.php';
require_once ACME_LP_DIR . 'includes/class-fetcher.php';
require_once ACME_LP_DIR . 'includes/class-card.php';
require_once ACME_LP_DIR . 'includes/class-prefs.php';
require_once ACME_LP_DIR . 'includes/class-porter.php';
require_once ACME_LP_DIR . 'includes/class-shortcode.php';
require_once ACME_LP_DIR . 'includes/class-rest.php';
require_once ACME_LP_DIR . 'includes/class-plugin.php';

Acme\LinkPreviews\Plugin::instance()->boot();
