<?php
/**
 * Acme Classic theme.
 */

add_action(
	'after_setup_theme',
	static function () {
		add_theme_support( 'title-tag' );
		add_theme_support( 'widgets-block-editor' );
	}
);

add_action(
	'widgets_init',
	static function () {
		register_sidebar(
			array(
				'id'            => 'sidebar-1',
				'name'          => __( 'Sidebar', 'acme-classic' ),
				'before_widget' => '<section id="%1$s" class="widget %2$s">',
				'after_widget'  => '</section>',
			)
		);
	}
);

add_action(
	'wp_enqueue_scripts',
	static function () {
		wp_enqueue_style( 'acme-classic', get_stylesheet_uri(), array(), '1.4.0' );
	}
);
