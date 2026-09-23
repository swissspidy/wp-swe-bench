<?php
/**
 * Plugin Name: Acme Social Share
 * Description: Share bar under blog posts (maintained by the marketing agency; reads the Acme SEO options).
 * Version:     2.3.1
 */

add_action(
	'wp_footer',
	static function () {
		if ( ! is_singular( 'post' ) ) {
			return;
		}
		$handle   = ltrim( (string) get_option( 'acme_seo_twitter_handle', '' ), '@' );
		$image_id = (int) get_option( 'acme_seo_og_default_image', 0 );
		$profiles = get_option( 'acme_seo_social_profiles', array() );
		$sep      = function_exists( 'acme_seo_get_option' ) ? acme_seo_get_option( 'title_separator', '-' ) : '-';

		echo '<aside class="acme-share" data-twitter="' . esc_attr( '' !== $handle ? '@' . $handle : '' ) . '">';
		if ( $image_id ) {
			echo '<img class="acme-share-image" src="' . esc_url( (string) wp_get_attachment_image_url( $image_id, 'thumbnail' ) ) . '" alt="" />';
		}
		echo '<span class="acme-share-title">' . esc_html( get_the_title() . ' ' . $sep . ' ' . get_bloginfo( 'name' ) ) . '</span>';
		foreach ( (array) $profiles as $network => $url ) {
			if ( is_string( $url ) && '' !== $url ) {
				echo '<a class="acme-follow acme-follow-' . esc_attr( $network ) . '" href="' . esc_url( $url ) . '">' . esc_html( ucfirst( $network ) ) . '</a>';
			}
		}
		echo '</aside>';
	}
);
