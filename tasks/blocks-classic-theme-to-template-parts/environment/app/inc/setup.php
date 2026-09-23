<?php
/**
 * Theme setup: supports, menus, widget areas, assets.
 *
 * @package Acme_Corporate
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers theme supports and menu locations.
 */
function acme_corporate_setup() {
	load_theme_textdomain( 'acme-corporate', ACME_CORPORATE_DIR . '/languages' );

	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'editor-styles' );
	add_theme_support(
		'html5',
		array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' )
	);
	add_theme_support(
		'custom-logo',
		array(
			'height'      => 64,
			'width'       => 240,
			'flex-width'  => true,
			'flex-height' => true,
		)
	);
	add_editor_style( 'assets/css/editor-style.css' );

	register_nav_menus(
		array(
			'primary' => esc_html__( 'Primary', 'acme-corporate' ),
			'footer'  => esc_html__( 'Footer', 'acme-corporate' ),
		)
	);
}
add_action( 'after_setup_theme', 'acme_corporate_setup' );

/**
 * Registers widget areas.
 */
function acme_corporate_widgets_init() {
	register_sidebar(
		array(
			'name'          => esc_html__( 'Sidebar', 'acme-corporate' ),
			'id'            => 'sidebar-1',
			'description'   => esc_html__( 'Shown next to blog posts.', 'acme-corporate' ),
			'before_widget' => '<section id="%1$s" class="widget %2$s">',
			'after_widget'  => '</section>',
			'before_title'  => '<h2 class="widget-title">',
			'after_title'   => '</h2>',
		)
	);
	register_sidebar(
		array(
			'name'          => esc_html__( 'Footer', 'acme-corporate' ),
			'id'            => 'footer-1',
			'description'   => esc_html__( 'Shown in the site footer, e.g. the office address.', 'acme-corporate' ),
			'before_widget' => '<section id="%1$s" class="widget footer-widget %2$s">',
			'after_widget'  => '</section>',
			'before_title'  => '<h2 class="widget-title">',
			'after_title'   => '</h2>',
		)
	);
}
add_action( 'widgets_init', 'acme_corporate_widgets_init' );

/**
 * Front-end assets.
 */
function acme_corporate_scripts() {
	wp_enqueue_style( 'acme-corporate-style', get_stylesheet_uri(), array(), ACME_CORPORATE_VERSION );
	wp_enqueue_script( 'acme-corporate-navigation', ACME_CORPORATE_URI . '/assets/js/navigation.js', array(), ACME_CORPORATE_VERSION, array( 'strategy' => 'defer' ) );

	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}
add_action( 'wp_enqueue_scripts', 'acme_corporate_scripts' );
