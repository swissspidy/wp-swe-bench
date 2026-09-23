<?php
/**
 * Template tags and helpers.
 *
 * @package Acme\Charts
 */

defined( 'ABSPATH' ) || exit;

/**
 * Default plugin options.
 *
 * @return array
 */
function acme_charts_default_options() {
	return array(
		'palette'        => array( '#3858e9', '#e26f56', '#1a8f5c', '#dba617', '#8a4fb4', '#0f7c8c' ),
		'default_height' => 240,
		'show_values'    => false,
	);
}

/**
 * Get a plugin option (merged with the defaults).
 *
 * @param string $key Option key.
 * @return mixed
 */
function acme_charts_get_option( $key ) {
	$options = get_option( 'acme_charts_options', array() );
	$options = wp_parse_args( is_array( $options ) ? $options : array(), acme_charts_default_options() );
	return isset( $options[ $key ] ) ? $options[ $key ] : null;
}

/**
 * The colour palette used for bars without an explicit colour.
 *
 * Themes customise it with the `acme_charts_palette` filter.
 *
 * @return string[] Hex colours.
 */
function acme_charts_get_palette() {
	$palette = (array) acme_charts_get_option( 'palette' );

	/**
	 * Filters the chart colour palette.
	 *
	 * @param string[] $palette Hex colours, used in order (and repeated) for bars without a colour.
	 */
	$palette = apply_filters( 'acme_charts_palette', $palette );

	$palette = array_values( array_filter( array_map( 'sanitize_hex_color', (array) $palette ) ) );
	return $palette ? $palette : acme_charts_default_options()['palette'];
}

/**
 * Settings shared by the editor and the front-end renderer.
 *
 * Exposed to JavaScript as `window.acmeChartsSettings`.
 *
 * @return array
 */
function acme_charts_script_settings() {
	return array(
		'palette'       => acme_charts_get_palette(),
		'defaultHeight' => (int) acme_charts_get_option( 'default_height' ),
		'showValues'    => (bool) acme_charts_get_option( 'show_values' ),
		'i18n'          => array(
			/* translators: %s: label of a chart bar. */
			'toggle' => __( 'Show or hide %s', 'acme-charts' ),
			'empty'  => __( 'No data', 'acme-charts' ),
		),
	);
}

/**
 * Sanitize a chart series coming from block attributes or the CSV importer.
 *
 * @param mixed $series Raw series.
 * @return array[] List of array( 'label' => string, 'value' => float, 'color' => string ).
 */
function acme_charts_sanitize_series( $series ) {
	$clean = array();
	foreach ( (array) $series as $point ) {
		if ( ! is_array( $point ) || ! isset( $point['label'] ) ) {
			continue;
		}
		$clean[] = array(
			'label' => sanitize_text_field( $point['label'] ),
			'value' => isset( $point['value'] ) ? (float) $point['value'] : 0.0,
			'color' => isset( $point['color'] ) ? (string) sanitize_hex_color( $point['color'] ) : '',
		);
	}
	return $clean;
}
