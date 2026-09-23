<?php
/**
 * Pre-2.0 options: conversion and read-only back-compat.
 *
 * @package Acme_Social
 */

defined( 'ABSPATH' ) || exit;

/**
 * Knows every option 1.x stored and the formats they had.
 */
class Acme_Social_Legacy {

	/**
	 * Every settings option 1.x stored.
	 */
	const OPTIONS = array(
		'acme_social_share_buttons_enabled',
		'acme_social_networks',
		'acme_share_position',
		'acme_social_post_types',
		'acmesocial_button_style',
		'acme_social_twitter',
		'acme_social_facebook',
		'acme_social_instagram_url',
		'acme_social_linkedin',
		'acme_social_youtube_channel',
		'acme_og_enabled',
		'acme_og_default_image',
		'acme_social_fb_app_id',
		'acme_social_twitter_card',
	);

	/**
	 * Old option names other plugins/themes still read with get_option().
	 */
	public function register() {
		add_filter( 'pre_option_acme_social_twitter', array( $this, 'twitter' ) );
		add_filter( 'pre_option_acme_social_facebook', array( $this, 'facebook' ) );
		add_filter( 'pre_option_acme_social_networks', array( $this, 'networks' ) );
		add_filter( 'pre_option_acme_og_enabled', array( $this, 'og_enabled' ) );
	}

	/**
	 * `acme_social_twitter`: "@handle" or "".
	 *
	 * @return string
	 */
	public function twitter() {
		$handle = (string) Acme_Social_Settings::get( 'twitter' );
		return '' === $handle ? '' : '@' . $handle;
	}

	/**
	 * `acme_social_facebook`: URL or "".
	 *
	 * @return string
	 */
	public function facebook() {
		return (string) Acme_Social_Settings::get( 'facebook' );
	}

	/**
	 * `acme_social_networks`: comma-separated enabled network slugs.
	 *
	 * @return string
	 */
	public function networks() {
		return implode( ',', (array) Acme_Social_Settings::get( 'networks' ) );
	}

	/**
	 * `acme_og_enabled`: "1" or "".
	 *
	 * @return string
	 */
	public function og_enabled() {
		return Acme_Social_Settings::get( 'og_enabled' ) ? '1' : '';
	}

	/**
	 * Raw values of the 1.x options that exist in the database (bypasses all filters).
	 *
	 * @return array<string, mixed>
	 */
	public static function raw_values() {
		global $wpdb;
		$placeholders = implode( ',', array_fill( 0, count( self::OPTIONS ), '%s' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows   = $wpdb->get_results( $wpdb->prepare( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name IN ($placeholders)", self::OPTIONS ) );
		$values = array();
		foreach ( (array) $rows as $row ) {
			$values[ $row->option_name ] = maybe_unserialize( $row->option_value );
		}
		return $values;
	}

	/**
	 * Converts 1.x values to the 2.0 structure, exactly as 1.6 interpreted them.
	 *
	 * @param array $raw Option name => stored value (missing = never saved).
	 * @return array
	 */
	public static function to_settings( array $raw ) {
		$get = static function ( $name, $fallback ) use ( $raw ) {
			return array_key_exists( $name, $raw ) ? $raw[ $name ] : $fallback;
		};

		$settings = Acme_Social_Settings::defaults();

		// Share buttons on/off: '1'/'0' (1.0), 'yes'/'no' (1.1+).
		$enabled                   = $get( 'acme_social_share_buttons_enabled', 'yes' );
		$settings['share_enabled'] = is_bool( $enabled ) ? $enabled : in_array( strtolower( trim( (string) $enabled ) ), array( 'yes', '1', 'on', 'true' ), true );

		// Networks: array (1.1), comma-separated string (1.2+); "x" = "twitter".
		$networks = $get( 'acme_social_networks', 'facebook,twitter,linkedin' );
		if ( is_array( $networks ) ) {
			$networks = implode( ',', $networks );
		}
		$available            = acme_social_available_networks();
		$settings['networks'] = array();
		foreach ( explode( ',', (string) $networks ) as $slug ) {
			$slug = strtolower( trim( $slug ) );
			if ( 'x' === $slug ) {
				$slug = 'twitter';
			}
			if ( isset( $available[ $slug ] ) && ! in_array( $slug, $settings['networks'], true ) ) {
				$settings['networks'][] = $slug;
			}
		}

		// Position: top/bottom (1.0).
		$position = (string) $get( 'acme_share_position', 'after' );
		$position = array(
			'top'    => 'before',
			'bottom' => 'after',
		)[ $position ] ?? $position;
		$settings['position'] = array_key_exists( $position, acme_social_positions() ) ? $position : 'after';

		// Post types: comma-separated string (1.0), array; only registered viewable ones.
		$types = $get( 'acme_social_post_types', array( 'post' ) );
		if ( ! is_array( $types ) ) {
			$types = explode( ',', (string) $types );
		}
		$settings['post_types'] = array();
		foreach ( $types as $type ) {
			$type = trim( (string) $type );
			if ( '' !== $type && post_type_exists( $type ) && is_post_type_viewable( $type ) && ! in_array( $type, $settings['post_types'], true ) ) {
				$settings['post_types'][] = $type;
			}
		}

		// Button style: "icons+text" (1.3), "both" (1.4 beta).
		$style = (string) $get( 'acmesocial_button_style', 'icons' );
		if ( 'icons+text' === $style || 'both' === $style ) {
			$style = 'icons_text';
		}
		$settings['button_style'] = array_key_exists( $style, acme_social_button_styles() ) ? $style : 'icons';

		// Twitter: "@handle", "handle" or a profile URL.
		$handle = trim( (string) $get( 'acme_social_twitter', '' ) );
		if ( preg_match( '#(?:twitter|x)\.com/@?([A-Za-z0-9_]{1,15})#i', $handle, $m ) ) {
			$handle = $m[1];
		}
		$handle              = ltrim( $handle, '@' );
		$settings['twitter'] = preg_match( '/^[A-Za-z0-9_]{1,15}$/', $handle ) ? $handle : '';

		// Profile URLs (scheme-less values were served as http://).
		$urls = array(
			'facebook'  => 'acme_social_facebook',
			'instagram' => 'acme_social_instagram_url',
			'linkedin'  => 'acme_social_linkedin',
			'youtube'   => 'acme_social_youtube_channel',
		);
		foreach ( $urls as $key => $name ) {
			$url              = trim( (string) $get( $name, '' ) );
			$settings[ $key ] = '' === $url ? '' : esc_url_raw( $url, array( 'http', 'https' ) );
		}

		$settings['og_enabled'] = (bool) $get( 'acme_og_enabled', '1' );

		// Default image: attachment ID, or its URL (1.2).
		$image = $get( 'acme_og_default_image', 0 );
		$id    = is_numeric( $image ) ? absint( $image ) : attachment_url_to_postid( (string) $image );

		$settings['og_default_image'] = ( $id && wp_attachment_is_image( $id ) ) ? $id : 0;

		$app_id                = trim( (string) $get( 'acme_social_fb_app_id', '' ) );
		$settings['fb_app_id'] = preg_match( '/^\d{5,20}$/', $app_id ) ? $app_id : '';

		// Twitter card: "large" (1.1).
		$card = (string) $get( 'acme_social_twitter_card', 'summary_large_image' );
		if ( 'large' === $card ) {
			$card = 'summary_large_image';
		}
		$settings['twitter_card'] = array_key_exists( $card, acme_social_twitter_card_types() ) ? $card : 'summary_large_image';

		return $settings;
	}

	/**
	 * Deletes the 1.x option rows.
	 */
	public static function delete_all() {
		foreach ( self::OPTIONS as $name ) {
			delete_option( $name );
		}
	}
}
