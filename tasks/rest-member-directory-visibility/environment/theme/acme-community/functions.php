<?php
/**
 * Acme Community theme setup.
 *
 * @package Acme\Community
 */

add_action(
	'after_setup_theme',
	static function () {
		add_theme_support( 'title-tag' );
		add_theme_support( 'automatic-feed-links' );
	}
);

add_action(
	'wp_enqueue_scripts',
	static function () {
		wp_enqueue_style( 'acme-community', get_stylesheet_uri(), array(), '1.4.0' );
	}
);
