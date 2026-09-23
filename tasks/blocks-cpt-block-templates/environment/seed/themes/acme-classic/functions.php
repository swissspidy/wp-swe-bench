<?php
/**
 * Acme Classic theme setup.
 *
 * @package Acme_Classic
 */

add_action(
	'after_setup_theme',
	static function () {
		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );
		register_nav_menus( array( 'primary' => 'Primary' ) );
	}
);

add_action(
	'wp_enqueue_scripts',
	static function () {
		wp_enqueue_style( 'acme-classic', get_stylesheet_uri(), array(), '2.3.1' );
	}
);
