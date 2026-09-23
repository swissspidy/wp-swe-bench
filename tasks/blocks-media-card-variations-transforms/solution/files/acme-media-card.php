<?php
/**
 * Plugin Name:       Acme Media Card
 * Description:       A "Media card" block (image, heading, text and a call to action; product, profile and event variations) for the Acme marketing sites.
 * Version:           2.0.0
 * Requires at least: 6.6
 * Requires PHP:      7.4
 * Author:            Acme Web Team
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       acme-media-card
 * Domain Path:       /languages
 *
 * @package Acme\MediaCard
 */

defined( 'ABSPATH' ) || exit;

define( 'ACME_MEDIA_CARD_VERSION', '2.0.0' );
define( 'ACME_MEDIA_CARD_FILE', __FILE__ );
define( 'ACME_MEDIA_CARD_DIR', plugin_dir_path( __FILE__ ) );
define( 'ACME_MEDIA_CARD_URL', plugin_dir_url( __FILE__ ) );

require_once ACME_MEDIA_CARD_DIR . 'includes/class-block.php';
require_once ACME_MEDIA_CARD_DIR . 'includes/class-patterns.php';

add_action(
	'init',
	static function () {
		load_plugin_textdomain( 'acme-media-card', false, dirname( plugin_basename( ACME_MEDIA_CARD_FILE ) ) . '/languages' );
	}
);

( new Acme\MediaCard\Block() )->register_hooks();
( new Acme\MediaCard\Patterns() )->register_hooks();
