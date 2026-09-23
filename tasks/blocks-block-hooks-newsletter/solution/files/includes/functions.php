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
		'placements'      => array( 'after_content', 'footer' ),
	);
}

/**
 * Automatic placements: slug => label.
 *
 * - after_content: after the post content of single posts (block themes: in the
 *   Single templates; classic themes: appended to the content).
 * - footer: at the end of the site footer (block themes only).
 *
 * @return array<string, string>
 */
function get_placement_choices() {
	return array(
		'after_content' => __( 'After the content of single posts', 'acme-newsletter' ),
		'footer'        => __( 'At the end of the site footer (block themes)', 'acme-newsletter' ),
	);
}

/**
 * Normalize a list of placements (drops unknown values and duplicates).
 *
 * @param mixed $placements Raw value.
 * @return string[]
 */
function sanitize_placements( $placements ) {
	if ( ! is_array( $placements ) ) {
		return array();
	}
	$valid = array_keys( get_placement_choices() );
	$out   = array();
	foreach ( $placements as $placement ) {
		if ( is_string( $placement ) && in_array( $placement, $valid, true ) && ! in_array( $placement, $out, true ) ) {
			$out[] = $placement;
		}
	}
	return $out;
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

	/*
	 * Before 2.4.0 the only automatic placement was the "auto_insert" checkbox (after
	 * the content of single posts). Sites that haven't saved the settings since keep
	 * that choice; the footer placement is on for them.
	 */
	if ( ! array_key_exists( 'placements', $saved ) ) {
		$legacy              = array_key_exists( 'auto_insert', $saved ) ? (bool) $saved['auto_insert'] : true;
		$saved['placements'] = $legacy ? array( 'after_content', 'footer' ) : array( 'footer' );
	}
	$saved['placements'] = sanitize_placements( $saved['placements'] );
	unset( $saved['auto_insert'] );

	return array_merge( default_settings(), $saved );
}

/**
 * Whether an automatic placement is enabled.
 *
 * @param string $placement Placement slug (see get_placement_choices()).
 * @return bool
 */
function is_placement_enabled( $placement ) {
	return in_array( $placement, (array) get_setting( 'placements' ), true );
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
			'footer'    => __( 'Site footer', 'acme-newsletter' ),
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
