<?php
/**
 * Helper functions (public API of the plugin, used by themes).
 *
 * @package Acme\CTA
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registered CTA variants (visual styles), slug => label.
 *
 * Themes may add their own variants; the theme must then ship the CSS for
 * `.wp-block-acme-cta.is-variant-{slug}`.
 *
 * @return array<string, string>
 */
function acme_cta_get_variants() {
	$variants = array(
		'primary'   => __( 'Primary', 'acme-cta' ),
		'secondary' => __( 'Secondary', 'acme-cta' ),
		'dark'      => __( 'Dark', 'acme-cta' ),
	);

	/**
	 * Filters the available CTA variants.
	 *
	 * @param array<string, string> $variants Variant slug => label.
	 */
	$variants = apply_filters( 'acme_cta_variants', $variants );

	$clean = array();
	foreach ( (array) $variants as $slug => $label ) {
		$slug = sanitize_key( $slug );
		if ( '' !== $slug ) {
			$clean[ $slug ] = (string) $label;
		}
	}
	return $clean;
}

/**
 * Normalize a variant slug: unknown variants fall back to "primary".
 *
 * @param mixed $variant Raw variant.
 * @return string
 */
function acme_cta_sanitize_variant( $variant ) {
	$variant = is_string( $variant ) ? sanitize_key( $variant ) : '';
	return array_key_exists( $variant, acme_cta_get_variants() ) ? $variant : 'primary';
}

/**
 * Map the 1.x colour names to 2.x variants.
 *
 * @param string $color 1.x `color` attribute.
 * @return string
 */
function acme_cta_variant_from_legacy_color( $color ) {
	$map = array(
		'blue'  => 'primary',
		'green' => 'secondary',
		'black' => 'dark',
	);
	return $map[ $color ] ?? 'primary';
}

/**
 * Normalize the attributes of an acme/cta block, whatever version saved it.
 *
 * Blocks saved by 1.x used `title`, `label`, `url` and `color`; 2.x uses
 * `heading`, `buttonText`, `buttonUrl` and `variant`.
 *
 * @param array $attrs Raw block attributes (from the block comment).
 * @return array{heading:string, headingLevel:int, buttonText:string, buttonUrl:string, variant:string, opensInNewTab:bool, campaign:string}
 */
function acme_cta_normalize_attributes( array $attrs ) {
	if ( ! isset( $attrs['heading'] ) && isset( $attrs['title'] ) ) {
		$attrs['heading'] = $attrs['title'];
	}
	if ( ! isset( $attrs['buttonText'] ) && isset( $attrs['label'] ) ) {
		$attrs['buttonText'] = $attrs['label'];
	}
	if ( ! isset( $attrs['buttonUrl'] ) && isset( $attrs['url'] ) ) {
		$attrs['buttonUrl'] = $attrs['url'];
	}
	if ( ! isset( $attrs['variant'] ) && isset( $attrs['color'] ) ) {
		$attrs['variant'] = acme_cta_variant_from_legacy_color( (string) $attrs['color'] );
	}

	$level = isset( $attrs['headingLevel'] ) ? (int) $attrs['headingLevel'] : 2;

	return array(
		'heading'       => isset( $attrs['heading'] ) ? (string) $attrs['heading'] : '',
		'headingLevel'  => min( 4, max( 2, $level ) ),
		'buttonText'    => isset( $attrs['buttonText'] ) ? (string) $attrs['buttonText'] : '',
		'buttonUrl'     => isset( $attrs['buttonUrl'] ) ? (string) $attrs['buttonUrl'] : '',
		'variant'       => acme_cta_sanitize_variant( $attrs['variant'] ?? 'primary' ),
		'opensInNewTab' => ! empty( $attrs['opensInNewTab'] ),
		'campaign'      => isset( $attrs['campaign'] ) ? sanitize_key( $attrs['campaign'] ) : '',
	);
}

/**
 * Find all acme/cta blocks in a list of parsed blocks (recursively).
 *
 * @param array $blocks Parsed blocks.
 * @return array[] Parsed acme/cta blocks.
 */
function acme_cta_find_blocks( array $blocks ) {
	$found = array();
	foreach ( $blocks as $block ) {
		if ( 'acme/cta' === ( $block['blockName'] ?? '' ) ) {
			$found[] = $block;
		}
		if ( ! empty( $block['innerBlocks'] ) ) {
			$found = array_merge( $found, acme_cta_find_blocks( $block['innerBlocks'] ) );
		}
	}
	return $found;
}

/**
 * Get a plugin option.
 *
 * @param string $key Option key inside `acme_cta_tracking`.
 * @return mixed
 */
function acme_cta_get_option( $key ) {
	$defaults = array(
		'enabled'    => true,
		'utm_source' => 'acme',
		'utm_medium' => 'cta',
	);
	$options  = wp_parse_args( (array) get_option( 'acme_cta_tracking', array() ), $defaults );
	return $options[ $key ] ?? null;
}
