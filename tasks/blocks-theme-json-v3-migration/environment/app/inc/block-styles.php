<?php
/**
 * Block style variations. Their CSS lives in style.css.
 *
 * @package Acme_Magazine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the theme's block styles.
 */
function acme_magazine_register_block_styles() {
	register_block_style(
		'core/group',
		array(
			'name'  => 'card',
			'label' => __( 'Card', 'acme-magazine' ),
		)
	);
	register_block_style(
		'core/group',
		array(
			'name'  => 'inverted',
			'label' => __( 'Inverted', 'acme-magazine' ),
		)
	);
	register_block_style(
		'core/paragraph',
		array(
			'name'  => 'kicker',
			'label' => __( 'Kicker', 'acme-magazine' ),
		)
	);
}
add_action( 'init', 'acme_magazine_register_block_styles' );
