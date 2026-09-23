<?php
/**
 * Server rendering of the Recipe card block.
 *
 * @package Acme\Recipes
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block content.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

$acme_recipe_id = ! empty( $block->context['postId'] ) ? (int) $block->context['postId'] : (int) get_the_ID();
$acme_card      = acme_recipes_render_card( $acme_recipe_id );
if ( '' === $acme_card ) {
	return;
}
$acme_wrapper = get_block_wrapper_attributes();
$acme_tags    = new WP_HTML_Tag_Processor( $acme_card );
if ( $acme_tags->next_tag() && preg_match( '/class="([^"]*)"/', $acme_wrapper, $acme_m ) ) {
	foreach ( preg_split( '/\s+/', $acme_m[1], -1, PREG_SPLIT_NO_EMPTY ) as $acme_class ) {
		$acme_tags->add_class( $acme_class );
	}
	$acme_card = $acme_tags->get_updated_html();
}
echo $acme_card; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the card template.
