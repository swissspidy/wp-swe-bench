<?php
/**
 * Helper functions.
 *
 * @package Acme\Newsroom
 */

defined( 'ABSPATH' ) || exit;

/**
 * Newsroom details (Settings → Newsroom → Press release details).
 *
 * @return array{city:string, boilerplate:string, contact_name:string, contact_email:string, contact_phone:string}
 */
function acme_newsroom_details() {
	$defaults = array(
		'city'          => '',
		'boilerplate'   => '',
		'contact_name'  => '',
		'contact_email' => '',
		'contact_phone' => '',
	);
	$saved    = get_option( 'acme_newsroom_settings', array() );
	return wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );
}

/**
 * The editorial block rules (Settings → Newsroom → Editorial rules).
 *
 * @return array See Acme\Newsroom\Editorial_Rules::sanitize() for the schema.
 */
function acme_newsroom_block_rules() {
	return Acme\Newsroom\Editorial_Rules::get();
}

/**
 * Whether a block name matches a rule entry ("core/paragraph" or "acme/*").
 *
 * @param string $block_name Block name.
 * @param string $pattern    Rule entry.
 * @return bool
 */
function acme_newsroom_block_matches( $block_name, $pattern ) {
	if ( substr( $pattern, -2 ) === '/*' ) {
		return 0 === strpos( $block_name, substr( $pattern, 0, -1 ) );
	}
	return $block_name === $pattern;
}
