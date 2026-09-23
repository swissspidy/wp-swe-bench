<?php
/**
 * Uninstall: removes everything Acme Social stored (2.x and 1.x data).
 *
 * Runs without the plugin being loaded, so it must not rely on its classes.
 *
 * @package Acme_Social
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$acme_social_options = array(
	'acme_social_settings',
	'acme_social_version',
	// 1.x options (sites that were never upgraded).
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
foreach ( $acme_social_options as $acme_social_option ) {
	delete_option( $acme_social_option );
}

global $wpdb;
// Cached Open Graph tags.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_acme_social_og_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_acme_social_og_' ) . '%'
	)
);

delete_post_meta_by_key( '_acme_social_hide_buttons' );
wp_cache_flush();
