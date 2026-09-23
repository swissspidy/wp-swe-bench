<?php
/**
 * Acme Corporate theme setup.
 *
 * @package AcmeCorporate
 */

add_action(
	'after_setup_theme',
	static function () {
		add_theme_support( 'wp-block-styles' );
		add_theme_support( 'editor-styles' );
		add_theme_support( 'title-tag' );
		add_editor_style( 'style.css' );
	}
);

add_action(
	'wp_enqueue_scripts',
	static function () {
		wp_enqueue_style( 'acme-corporate', get_stylesheet_uri(), array(), wp_get_theme()->get( 'Version' ) );
	}
);
