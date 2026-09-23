<?php
/**
 * Helpers.
 *
 * @package Acme\Contact
 */

defined( 'ABSPATH' ) || exit;

/**
 * Plugin settings merged with defaults.
 *
 * @return array{recipient:string, success_message:string}
 */
function acme_contact_settings() {
	$saved = get_option( 'acme_contact_settings', array() );
	return array_merge(
		array(
			'recipient'       => get_option( 'admin_email' ),
			'success_message' => __( 'Thank you! Your message has been sent.', 'acme-contact' ),
		),
		is_array( $saved ) ? array_filter( $saved, 'strlen' ) : array()
	);
}

/**
 * IP address of the visitor (as seen by the web server).
 *
 * @return string
 */
function acme_contact_client_ip() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

	/**
	 * Filters the IP address used for rate limiting (e.g. behind a proxy).
	 *
	 * @param string $ip IP address.
	 */
	return (string) apply_filters( 'acme_contact_client_ip', filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0' );
}

/**
 * Parse the `subjects` shortcode attribute ("Sales|Support|Press"; commas work too).
 *
 * @param string $value Attribute value.
 * @return string[]
 */
function acme_contact_parse_subjects( $value ) {
	$parts = preg_split( '/[|,]/', (string) $value );
	return array_values( array_filter( array_map( 'trim', $parts ), 'strlen' ) );
}
