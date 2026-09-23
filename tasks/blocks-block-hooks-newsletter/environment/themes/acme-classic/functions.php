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
		add_theme_support( 'html5', array( 'search-form', 'comment-form', 'gallery', 'caption', 'style', 'script' ) );
		register_nav_menus( array( 'primary' => __( 'Primary', 'acme-classic' ) ) );
	}
);

add_action(
	'widgets_init',
	static function () {
		register_sidebar(
			array(
				'name'          => __( 'Footer', 'acme-classic' ),
				'id'            => 'footer-1',
				'before_widget' => '<section id="%1$s" class="widget %2$s">',
				'after_widget'  => '</section>',
				'before_title'  => '<h2 class="widget-title">',
				'after_title'   => '</h2>',
			)
		);
	}
);

add_action(
	'wp_enqueue_scripts',
	static function () {
		wp_enqueue_style( 'acme-classic', get_stylesheet_uri(), array(), '1.8.0' );
	}
);
