<?php
/**
 * Public helper functions.
 *
 * @package Acme\Callouts
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registered callout types, as slug => translated label.
 *
 * Themes and other plugins add their own types through the
 * `acme_callouts_types` filter, e.g. `$types['tip'] = __( 'Tip', 'my-theme' );`.
 *
 * @return array<string, string>
 */
function acme_callouts_get_types() {
	$types = array(
		'info'    => __( 'Info', 'acme-callouts' ),
		'success' => __( 'Success', 'acme-callouts' ),
		'warning' => __( 'Warning', 'acme-callouts' ),
		'danger'  => __( 'Danger', 'acme-callouts' ),
	);

	/**
	 * Filters the available callout types.
	 *
	 * @param array<string, string> $types Slug => label.
	 */
	$types = apply_filters( 'acme_callouts_types', $types );

	$clean = array();
	foreach ( (array) $types as $slug => $label ) {
		$slug = sanitize_key( $slug );
		if ( '' !== $slug ) {
			$clean[ $slug ] = (string) $label;
		}
	}
	return $clean;
}

/**
 * Plugin options merged with defaults.
 *
 * @return array{default_type: string, enable_shortcode: bool}
 */
function acme_callouts_get_options() {
	$defaults = array(
		'default_type'     => 'info',
		'enable_shortcode' => true,
	);
	$options  = get_option( 'acme_callouts_options', array() );
	$options  = is_array( $options ) ? $options : array();
	$options  = array_merge( $defaults, $options );

	$options['enable_shortcode'] = (bool) $options['enable_shortcode'];
	if ( ! array_key_exists( $options['default_type'], acme_callouts_get_types() ) ) {
		$options['default_type'] = 'info';
	}
	return $options;
}

/**
 * Get a single plugin option.
 *
 * @param string $key Option key.
 * @return mixed
 */
function acme_callouts_get_option( $key ) {
	$options = acme_callouts_get_options();
	return isset( $options[ $key ] ) ? $options[ $key ] : null;
}
