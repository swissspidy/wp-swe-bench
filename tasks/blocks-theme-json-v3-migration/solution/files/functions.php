<?php
/**
 * Acme Magazine functions.
 *
 * @package Acme_Magazine
 */

defined( 'ABSPATH' ) || exit;

define( 'ACME_MAGAZINE_VERSION', '2.0.0' );

require __DIR__ . '/inc/block-styles.php';

/**
 * Theme setup.
 */
function acme_magazine_setup() {
	load_theme_textdomain( 'acme-magazine', get_template_directory() . '/languages' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'responsive-embeds' );
}
add_action( 'after_setup_theme', 'acme_magazine_setup' );

/**
 * Front-end stylesheet.
 */
function acme_magazine_enqueue() {
	wp_enqueue_style( 'acme-magazine-style', get_stylesheet_uri(), array(), ACME_MAGAZINE_VERSION );
}
add_action( 'wp_enqueue_scripts', 'acme_magazine_enqueue' );

/**
 * Pattern category.
 */
function acme_magazine_pattern_categories() {
	register_block_pattern_category( 'acme-magazine', array( 'label' => __( 'Acme Magazine', 'acme-magazine' ) ) );
}
add_action( 'init', 'acme_magazine_pattern_categories' );
