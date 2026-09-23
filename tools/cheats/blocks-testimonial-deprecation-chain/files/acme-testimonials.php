<?php
/**
 * Plugin Name:       Acme Testimonials
 * Description:       Customer testimonial block with ratings, author avatars, review structured data and a CSV importer.
 * Version:           4.0.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Acme Web Team
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       acme-testimonials
 * Domain Path:       /languages
 *
 * @package Acme\Testimonials
 */

defined( 'ABSPATH' ) || exit;

define( 'ACME_TESTIMONIALS_VERSION', '4.0.0' );
define( 'ACME_TESTIMONIALS_FILE', __FILE__ );
define( 'ACME_TESTIMONIALS_DIR', plugin_dir_path( __FILE__ ) );

require_once ACME_TESTIMONIALS_DIR . 'includes/functions.php';
require_once ACME_TESTIMONIALS_DIR . 'includes/class-markup.php';
require_once ACME_TESTIMONIALS_DIR . 'includes/class-block.php';
require_once ACME_TESTIMONIALS_DIR . 'includes/class-schema.php';
require_once ACME_TESTIMONIALS_DIR . 'includes/class-plugin.php';

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once ACME_TESTIMONIALS_DIR . 'includes/class-cli.php';
}

Acme\Testimonials\Plugin::instance()->boot();
