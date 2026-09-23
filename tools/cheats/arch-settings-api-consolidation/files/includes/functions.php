<?php
/**
 * Helper functions: registered networks and accessors for the stored settings.
 *
 * All settings live in the `acme_social_settings` option (see
 * Acme_Social_Settings); these accessors are kept for themes and plugins that
 * call them.
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
 * @return bool
 */
function acme_social_share_enabled() {
	return (bool) Acme_Social_Settings::get( 'share_enabled' );
}

/**
 * Enabled networks, in the configured order (only currently registered ones).
 *
 * @return string[]
 */
function acme_social_enabled_networks() {
	$available = acme_social_available_networks();
	return array_values(
		array_filter(
			(array) Acme_Social_Settings::get( 'networks' ),
			static fn( $slug ) => isset( $available[ $slug ] )
		)
	);
}

/**
 * Where the buttons go.
 *
 * @return string before|after|both
 */
function acme_social_share_position() {
	return (string) Acme_Social_Settings::get( 'position' );
}

/**
 * Post types that show share buttons (only registered, viewable ones).
 *
 * @return string[]
 */
function acme_social_share_post_types() {
	return array_values(
		array_filter(
			(array) Acme_Social_Settings::get( 'post_types' ),
			static fn( $type ) => post_type_exists( $type ) && is_post_type_viewable( $type )
		)
	);
}

/**
 * Button style.
 *
 * @return string
 */
function acme_social_button_style() {
	return (string) Acme_Social_Settings::get( 'button_style' );
}

/**
 * Twitter/X username without "@".
 *
 * @return string Empty string when none.
 */
function acme_social_twitter_handle() {
	return (string) Acme_Social_Settings::get( 'twitter' );
}

/**
 * Profile URLs (without Twitter/X, see acme_social_twitter_handle()).
 *
 * @return array{facebook: string, instagram: string, linkedin: string, youtube: string}
 */
function acme_social_profile_urls() {
	$settings = Acme_Social_Settings::get();
	return array(
		'facebook'  => (string) $settings['facebook'],
		'instagram' => (string) $settings['instagram'],
		'linkedin'  => (string) $settings['linkedin'],
		'youtube'   => (string) $settings['youtube'],
	);
}

/**
 * Whether Open Graph / Twitter card tags are output.
 *
 * @return bool
 */
function acme_social_og_enabled() {
	return (bool) Acme_Social_Settings::get( 'og_enabled' );
}

/**
 * Attachment ID of the fallback share image, 0 if none.
 *
 * @return int
 */
function acme_social_og_default_image_id() {
	$id = (int) Acme_Social_Settings::get( 'og_default_image' );
	return ( $id && wp_attachment_is_image( $id ) ) ? $id : 0;
}

/**
 * Facebook App ID, empty if none.
 *
 * @return string
 */
function acme_social_fb_app_id() {
	return (string) Acme_Social_Settings::get( 'fb_app_id' );
}

/**
 * Twitter card type.
 *
 * @return string
 */
function acme_social_twitter_card() {
	return (string) Acme_Social_Settings::get( 'twitter_card' );
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
