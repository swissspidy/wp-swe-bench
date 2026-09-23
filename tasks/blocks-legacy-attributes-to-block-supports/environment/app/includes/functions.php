<?php
/**
 * Public helper functions.
 *
 * @package Acme\ContentBlocks
 */

defined( 'ABSPATH' ) || exit;

/**
 * Notice tones, slug => label.
 *
 * @return array<string, string>
 */
function acme_content_blocks_tones() {
	$tones = array(
		'info'    => __( 'Info', 'acme-content-blocks' ),
		'success' => __( 'Success', 'acme-content-blocks' ),
		'warning' => __( 'Warning', 'acme-content-blocks' ),
		'error'   => __( 'Error', 'acme-content-blocks' ),
	);

	/**
	 * Filters the notice tones.
	 *
	 * @param array<string, string> $tones Slug => label.
	 */
	return (array) apply_filters( 'acme_content_blocks_tones', $tones );
}

/**
 * Find blocks of a type in parsed blocks (recursively).
 *
 * @param array  $blocks Parsed blocks.
 * @param string $name   Block name.
 * @return array[]
 */
function acme_content_blocks_find( array $blocks, $name ) {
	$found = array();
	foreach ( $blocks as $block ) {
		if ( ( $block['blockName'] ?? '' ) === $name ) {
			$found[] = $block;
		}
		if ( ! empty( $block['innerBlocks'] ) ) {
			$found = array_merge( $found, acme_content_blocks_find( $block['innerBlocks'], $name ) );
		}
	}
	return $found;
}
