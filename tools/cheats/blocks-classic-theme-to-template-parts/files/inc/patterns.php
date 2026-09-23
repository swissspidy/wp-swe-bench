<?php
/**
 * Block patterns: the pattern category, and the synced "Contact card" pattern.
 *
 * The patterns themselves live in /patterns (registered automatically by WordPress).
 * Their slugs must not change: page content embeds them by slug.
 *
 * @package Acme_Corporate
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the theme's pattern category.
 */
function acme_corporate_register_pattern_categories() {
	register_block_pattern_category(
		'acme',
		array( 'label' => __( 'Acme', 'acme-corporate' ) )
	);
}
add_action( 'init', 'acme_corporate_register_pattern_categories', 9 );
