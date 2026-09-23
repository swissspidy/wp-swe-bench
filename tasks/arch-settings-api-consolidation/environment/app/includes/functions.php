<?php
/**
 * Helper functions: registered networks and accessors for the stored settings.
 *
 * The accessors normalize every format older versions have stored, so the rest
 * of the plugin never has to care about legacy values.
 *
 * @package Acme_Social
 */

defined( 'ABSPATH' ) || exit;

/**
 * Networks share buttons can be shown for.
 *
 * Each network has a label and a share URL template (%1$s = URL, %2$s = title,
 * both already URL-encoded). Other plugins add or remove networks through the
 * `acme_social_networks` filter.
 *
 * @return array<string, array{label: string, share_url: string}>
 */
function acme_social_available_networks() {
	$networks = array(
		'facebook'  => array(
			'label'     => __( 'Facebook', 'acme-social' ),
			'share_url' => 'https://www.facebook.com/sharer/sharer.php?u=%1$s',
		),
		'twitter'   => array(
			'label'     => __( 'X (Twitter)', 'acme-social' ),
			'share_url' => 'https://twitter.com/intent/tweet?url=%1$s&text=%2$s',
		),
		'linkedin'  => array(
			'label'     => __( 'LinkedIn', 'acme-social' ),
			'share_url' => 'https://www.linkedin.com/sharing/share-offsite/?url=%1$s',
		),
		'pinterest' => array(
			'label'     => __( 'Pinterest', 'acme-social' ),
			'share_url' => 'https://pinterest.com/pin/create/button/?url=%1$s&description=%2$s',
		),
		'whatsapp'  => array(
			'label'     => __( 'WhatsApp', 'acme-social' ),
			'share_url' => 'https://api.whatsapp.com/send?text=%2$s%%20%1$s',
		),
		'email'     => array(
			'label'     => __( 'Email', 'acme-social' ),
			'share_url' => 'mailto:?subject=%2$s&body=%1$s',
		),
	);

	/**
	 * Filters the networks share buttons can be shown for.
	 *
	 * @since 1.0.0
	 *
	 * @param array $networks Network slug => array( 'label' => ..., 'share_url' => ... ).
	 */
	return (array) apply_filters( 'acme_social_networks', $networks );
}

/**
 * Button styles.
 *
 * @return array<string, string>
 */
function acme_social_button_styles() {
	return array(
		'icons'      => __( 'Icons only', 'acme-social' ),
		'text'       => __( 'Text only', 'acme-social' ),
		'icons_text' => __( 'Icons and text', 'acme-social' ),
	);
}

/**
 * Button positions.
 *
 * @return array<string, string>
 */
function acme_social_positions() {
	return array(
		'before' => __( 'Before the content', 'acme-social' ),
		'after'  => __( 'After the content', 'acme-social' ),
		'both'   => __( 'Before and after the content', 'acme-social' ),
	);
}

/**
 * Twitter card types.
 *
 * @return array<string, string>
 */
function acme_social_twitter_card_types() {
	return array(
		'summary'             => __( 'Summary', 'acme-social' ),
		'summary_large_image' => __( 'Summary with large image', 'acme-social' ),
	);
}

/**
 * Whether share buttons are enabled at all.
 *
 * 1.0 stored '1'/'0', 1.1+ stores 'yes'/'no'.
 *
 * @return bool
 */
function acme_social_share_enabled() {
	$value = get_option( 'acme_social_share_buttons_enabled', 'yes' );
	if ( is_bool( $value ) ) {
		return $value;
	}
	return in_array( strtolower( trim( (string) $value ) ), array( 'yes', '1', 'on', 'true' ), true );
}

/**
 * Enabled networks, in the configured order.
 *
 * Stored as a comma-separated list since 1.2 (1.1 stored an array). Unknown
 * networks are skipped, "x" is an alias of "twitter".
 *
 * @return string[]
 */
function acme_social_enabled_networks() {
	$raw = get_option( 'acme_social_networks', 'facebook,twitter,linkedin' );
	if ( is_array( $raw ) ) {
		$raw = implode( ',', $raw );
	}
	$available = acme_social_available_networks();
	$enabled   = array();
	foreach ( explode( ',', (string) $raw ) as $slug ) {
		$slug = strtolower( trim( $slug ) );
		if ( 'x' === $slug ) {
			$slug = 'twitter';
		}
		if ( isset( $available[ $slug ] ) && ! in_array( $slug, $enabled, true ) ) {
			$enabled[] = $slug;
		}
	}
	return $enabled;
}

/**
 * Where the buttons go. 1.0 used "top"/"bottom".
 *
 * @return string before|after|both
 */
function acme_social_share_position() {
	$position = (string) get_option( 'acme_share_position', 'after' );
	$legacy   = array(
		'top'    => 'before',
		'bottom' => 'after',
	);
	if ( isset( $legacy[ $position ] ) ) {
		$position = $legacy[ $position ];
	}
	return array_key_exists( $position, acme_social_positions() ) ? $position : 'after';
}

/**
 * Post types that show share buttons (only registered, viewable ones).
 *
 * 1.0 stored a comma-separated string.
 *
 * @return string[]
 */
function acme_social_share_post_types() {
	$types = get_option( 'acme_social_post_types', array( 'post' ) );
	if ( ! is_array( $types ) ) {
		$types = explode( ',', (string) $types );
	}
	$out = array();
	foreach ( $types as $type ) {
		$type = trim( (string) $type );
		if ( '' !== $type && post_type_exists( $type ) && is_post_type_viewable( $type ) && ! in_array( $type, $out, true ) ) {
			$out[] = $type;
		}
	}
	return $out;
}

/**
 * Button style. 1.3 stored "icons+text", 1.4 briefly "both".
 *
 * @return string
 */
function acme_social_button_style() {
	$style = (string) get_option( 'acmesocial_button_style', 'icons' );
	if ( 'icons+text' === $style || 'both' === $style ) {
		$style = 'icons_text';
	}
	return array_key_exists( $style, acme_social_button_styles() ) ? $style : 'icons';
}

/**
 * Twitter/X username without "@".
 *
 * The option has held "@handle", "handle" and full profile URLs over the years.
 *
 * @return string Empty string when none/invalid.
 */
function acme_social_twitter_handle() {
	$handle = trim( (string) get_option( 'acme_social_twitter', '' ) );
	if ( preg_match( '#(?:twitter|x)\.com/@?([A-Za-z0-9_]{1,15})#i', $handle, $m ) ) {
		$handle = $m[1];
	}
	$handle = ltrim( $handle, '@' );
	return preg_match( '/^[A-Za-z0-9_]{1,15}$/', $handle ) ? $handle : '';
}

/**
 * Profile URLs (without Twitter/X, see acme_social_twitter_handle()).
 *
 * @return array{facebook: string, instagram: string, linkedin: string, youtube: string}
 */
function acme_social_profile_urls() {
	$urls = array(
		'facebook'  => get_option( 'acme_social_facebook', '' ),
		'instagram' => get_option( 'acme_social_instagram_url', '' ),
		'linkedin'  => get_option( 'acme_social_linkedin', '' ),
		'youtube'   => get_option( 'acme_social_youtube_channel', '' ),
	);
	foreach ( $urls as $network => $url ) {
		$url              = trim( (string) $url );
		$urls[ $network ] = '' === $url ? '' : esc_url_raw( $url, array( 'http', 'https' ) );
	}
	return $urls;
}

/**
 * Whether Open Graph / Twitter card tags are output.
 *
 * @return bool
 */
function acme_social_og_enabled() {
	return (bool) get_option( 'acme_og_enabled', '1' );
}

/**
 * Attachment ID of the fallback share image, 0 if none.
 *
 * 1.2 stored the image URL instead of the ID.
 *
 * @return int
 */
function acme_social_og_default_image_id() {
	$value = get_option( 'acme_og_default_image', 0 );
	if ( is_numeric( $value ) ) {
		$id = absint( $value );
	} else {
		$id = attachment_url_to_postid( (string) $value );
	}
	return ( $id && wp_attachment_is_image( $id ) ) ? $id : 0;
}

/**
 * Facebook App ID (digits only), empty if none/invalid.
 *
 * @return string
 */
function acme_social_fb_app_id() {
	$id = trim( (string) get_option( 'acme_social_fb_app_id', '' ) );
	return preg_match( '/^\d{5,20}$/', $id ) ? $id : '';
}

/**
 * Twitter card type. 1.1 stored "large".
 *
 * @return string
 */
function acme_social_twitter_card() {
	$card = (string) get_option( 'acme_social_twitter_card', 'summary_large_image' );
	if ( 'large' === $card ) {
		$card = 'summary_large_image';
	}
	return array_key_exists( $card, acme_social_twitter_card_types() ) ? $card : 'summary_large_image';
}

/**
 * Deletes the cached Open Graph tags of all posts.
 *
 * Must be called whenever settings that affect the tags change.
 */
function acme_social_flush_og_cache() {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( '_transient_acme_social_og_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_acme_social_og_' ) . '%'
		)
	);
	wp_cache_flush_group( 'options' );
	wp_cache_delete( 'alloptions', 'options' );
}
