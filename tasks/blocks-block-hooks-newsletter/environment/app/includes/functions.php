<?php
/**
 * Helper functions.
 *
 * @package Acme\Newsletter
 */

namespace Acme\Newsletter;

defined( 'ABSPATH' ) || exit;

/**
 * Option name of the plugin settings.
 */
const OPTION = 'acme_newsletter_settings';

/**
 * Default settings.
 *
 * @return array<string, mixed>
 */
function default_settings() {
	return array(
		'heading'         => __( 'Get our newsletter', 'acme-newsletter' ),
		'description'     => __( 'One email a week. No spam, unsubscribe any time.', 'acme-newsletter' ),
		'button_label'    => __( 'Subscribe', 'acme-newsletter' ),
		'show_name'       => true,
		'consent_text'    => __( 'I agree to receive the newsletter.', 'acme-newsletter' ),
		'success_message' => __( 'Thanks! Please check your inbox to confirm your subscription.', 'acme-newsletter' ),
		'auto_insert'     => true,
	);
}

/**
 * Plugin settings merged with the defaults.
 *
 * @return array<string, mixed>
 */
function get_settings() {
	$saved = get_option( OPTION, array() );
	if ( ! is_array( $saved ) ) {
		$saved = array();
	}
	return array_merge( default_settings(), $saved );
}

/**
 * A single setting.
 *
 * @param string $key Setting key.
 * @return mixed
 */
function get_setting( $key ) {
	$settings = get_settings();
	return isset( $settings[ $key ] ) ? $settings[ $key ] : null;
}

/**
 * Known subscription sources (where a form was displayed). Stored with every
 * subscriber and used in the subscriber reports.
 *
 * @return array<string, string> Source slug => label.
 */
function get_sources() {
	/**
	 * Filters the known subscription sources.
	 *
	 * @param array<string, string> $sources Source slug => label.
	 */
	return (array) apply_filters(
		'acme_newsletter_sources',
		array(
			'content'   => __( 'After post content', 'acme-newsletter' ),
			'block'     => __( 'Signup block', 'acme-newsletter' ),
			'shortcode' => __( 'Shortcode', 'acme-newsletter' ),
			'widget'    => __( 'Widget', 'acme-newsletter' ),
		)
	);
}

/**
 * Whether the given source slug is known.
 *
 * @param string $source Source slug.
 * @return bool
 */
function is_valid_source( $source ) {
	return is_string( $source ) && array_key_exists( $source, get_sources() );
}

/**
 * Render a signup form.
 *
 * @see Form::render()
 *
 * @param array $args Form arguments.
 * @return string
 */
function render_form( array $args = array() ) {
	return Form::render( $args );
}
