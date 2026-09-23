<?php
/**
 * Block template parts (header / footer) for this classic theme.
 *
 * The header and footer live in parts/header.html and parts/footer.html so they
 * can be edited in Appearance → Editor → Patterns → Template Parts. header.php
 * and footer.php render them, so every PHP template keeps working.
 *
 * Navigation: the Navigation blocks in the parts point at the classic menu
 * locations (`__unstableLocation`). Until a site admin picks or builds a
 * navigation menu for them in the Site Editor (which stores a `ref`), they show
 * the menu assigned in Appearance → Menus, live. Without an assigned menu the
 * header lists the site's pages (like 3.x did) and the footer navigation is
 * omitted.
 *
 * @package Acme_Corporate
 */

defined( 'ABSPATH' ) || exit;

/**
 * Enables the template part editor for this (classic) theme.
 */
function acme_corporate_block_template_parts_setup() {
	add_theme_support( 'block-template-parts' );
}
add_action( 'after_setup_theme', 'acme_corporate_block_template_parts_setup' );

/**
 * Fills location-bound Navigation blocks with the classic menu at that location.
 *
 * @param array $parsed_block Parsed block.
 * @return array
 */
function acme_corporate_navigation_from_menu_location( $parsed_block ) {
	if (
		'core/navigation' !== $parsed_block['blockName'] ||
		empty( $parsed_block['attrs']['__unstableLocation'] ) ||
		! empty( $parsed_block['attrs']['ref'] ) ||
		! empty( $parsed_block['innerBlocks'] )
	) {
		return $parsed_block;
	}

	$location = (string) $parsed_block['attrs']['__unstableLocation'];
	$blocks   = acme_corporate_menu_location_blocks( $location );

	if ( null === $blocks ) {
		// No menu assigned: the header falls back to the list of pages.
		$blocks = 'primary' === $location ? parse_blocks( '<!-- wp:page-list {"isNested":false} /-->' ) : array();
		if ( ! $blocks ) {
			$parsed_block['attrs']['acmeCorporateEmpty'] = true;
		}
	}

	$parsed_block['innerBlocks']  = $blocks;
	$parsed_block['innerContent'] = array_fill( 0, count( $blocks ), null );
	return $parsed_block;
}
add_filter( 'render_block_data', 'acme_corporate_navigation_from_menu_location' );

/**
 * Blocks for the classic menu assigned to a location, or null when there is none.
 *
 * @param string $location Menu location.
 * @return array[]|null Parsed blocks.
 */
function acme_corporate_menu_location_blocks( $location ) {
	$locations = get_nav_menu_locations();
	if ( empty( $locations[ $location ] ) ) {
		return null;
	}
	$menu = wp_get_nav_menu_object( $locations[ $location ] );
	if ( ! $menu ) {
		return null;
	}
	$markup = WP_Classic_To_Block_Menu_Converter::convert( $menu );
	if ( is_wp_error( $markup ) || '' === trim( (string) $markup ) ) {
		return null;
	}
	$blocks = array_values(
		array_filter(
			parse_blocks( $markup ),
			static function ( $block ) {
				return ! empty( $block['blockName'] );
			}
		)
	);
	return $blocks ? $blocks : null;
}

/**
 * Location-bound navigation without any items (footer without a menu) renders nothing,
 * instead of the Navigation block's generic fallback.
 *
 * @param string $content Rendered block.
 * @param array  $block   Parsed block.
 * @return string
 */
function acme_corporate_hide_empty_location_navigation( $content, $block ) {
	if ( ! empty( $block['attrs']['acmeCorporateEmpty'] ) ) {
		return '';
	}
	return $content;
}
add_filter( 'render_block_core/navigation', 'acme_corporate_hide_empty_location_navigation', 10, 2 );
