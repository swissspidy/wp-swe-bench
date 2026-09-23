<?php
/**
 * Block style variations.
 *
 * The Card and Inverted group styles are section styles defined in
 * styles/section-card.json and styles/section-inverted.json (registered by
 * WordPress from those files). The Kicker paragraph style is registered here;
 * its styles are in theme.json (styles.blocks.core/paragraph.variations).
 *
 * @package Acme_Magazine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the theme's block styles.
 */
function acme_magazine_register_block_styles() {
	register_block_style(
		'core/paragraph',
		array(
			'name'  => 'kicker',
			'label' => __( 'Kicker', 'acme-magazine' ),
		)
	);
}
add_action( 'init', 'acme_magazine_register_block_styles' );
