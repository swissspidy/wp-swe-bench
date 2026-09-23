<?php
/**
 * Acme Classic theme setup.
 *
 * @package AcmeClassic
 */

add_action(
	'after_setup_theme',
	static function () {
		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script' ) );
	}
);

add_action(
	'widgets_init',
	static function () {
		register_sidebar(
			array(
				'name'          => 'Sidebar',
				'id'            => 'sidebar-1',
				'before_widget' => '<section id="%1$s" class="widget %2$s">',
				'after_widget'  => '</section>',
				'before_title'  => '<h2 class="widget-title">',
				'after_title'   => '</h2>',
			)
		);
		register_sidebar(
			array(
				'name'          => 'Footer',
				'id'            => 'footer-1',
				'before_widget' => '<div id="%1$s" class="footer-widget %2$s">',
				'after_widget'  => '</div>',
				'before_title'  => '<h3 class="footer-widget__title">',
				'after_title'   => '</h3>',
			)
		);
	}
);

add_action(
	'wp_enqueue_scripts',
	static function () {
		wp_enqueue_style( 'acme-classic', get_stylesheet_uri(), array(), '3.1.0' );
	}
);
