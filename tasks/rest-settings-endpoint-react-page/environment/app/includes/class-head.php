<?php
/**
 * Tags printed in <head>.
 *
 * @package Acme\SEO
 */

namespace Acme\SEO;

defined( 'ABSPATH' ) || exit;

/**
 * Meta description, Open Graph, Twitter card, verification and Organization JSON-LD.
 */
class Head {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'wp_head', array( __CLASS__, 'output' ), 1 );
	}

	/**
	 * Description for the current view.
	 *
	 * @return string
	 */
	public static function description() {
		if ( is_front_page() ) {
			$description = Options::get( 'home_description' );
		} elseif ( is_singular() ) {
			$post        = get_queried_object();
			$description = get_post_meta( $post->ID, Post_Meta::DESCRIPTION, true );
			if ( '' === trim( (string) $description ) ) {
				$description = has_excerpt( $post ) ? $post->post_excerpt : wp_trim_words( strip_shortcodes( $post->post_content ), 30, '…' );
			}
		} else {
			$description = '';
		}

		/**
		 * Filters the meta description.
		 *
		 * @since 1.0.0
		 *
		 * @param string $description Description (may contain HTML, it is stripped).
		 */
		$description = apply_filters( 'acme_seo_description', $description );
		return trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $description ) ) );
	}

	/**
	 * Print everything.
	 */
	public static function output() {
		echo "\n<!-- Acme SEO -->\n";

		$description = self::description();
		if ( '' !== $description ) {
			printf( '<meta name="description" content="%s" />' . "\n", esc_attr( $description ) );
		}

		self::verification();

		if ( Options::get( 'og_enabled' ) ) {
			self::open_graph( $description );
		}

		$handle = Options::get( 'twitter_handle' );
		if ( '' !== $handle ) {
			echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
			printf( '<meta name="twitter:site" content="@%s" />' . "\n", esc_attr( $handle ) );
		}

		if ( is_front_page() ) {
			self::organization();
		}
		echo "<!-- / Acme SEO -->\n";
	}

	/**
	 * Search engine verification tags.
	 */
	protected static function verification() {
		$codes = Options::get( 'verification' );
		if ( '' !== $codes['google'] ) {
			printf( '<meta name="google-site-verification" content="%s" />' . "\n", esc_attr( $codes['google'] ) );
		}
		if ( '' !== $codes['bing'] ) {
			printf( '<meta name="msvalidate.01" content="%s" />' . "\n", esc_attr( $codes['bing'] ) );
		}
	}

	/**
	 * Open Graph tags.
	 *
	 * @param string $description Description.
	 */
	protected static function open_graph( $description ) {
		$tags = array(
			'og:site_name' => get_bloginfo( 'name', 'display' ),
			'og:title'     => wp_get_document_title(),
			'og:type'      => is_singular( 'post' ) ? 'article' : 'website',
			'og:url'       => is_singular() ? get_permalink() : home_url( add_query_arg( array() ) ),
		);
		if ( '' !== $description ) {
			$tags['og:description'] = $description;
		}

		$image = '';
		if ( is_singular() && has_post_thumbnail() ) {
			$image = get_the_post_thumbnail_url( null, 'large' );
		}
		if ( ! $image && Options::get( 'og_default_image' ) ) {
			$image = wp_get_attachment_image_url( Options::get( 'og_default_image' ), 'large' );
		}
		if ( $image ) {
			$tags['og:image'] = $image;
		}

		/**
		 * Filters the Open Graph tags.
		 *
		 * @since 1.2.0
		 *
		 * @param array<string, string> $tags Property => content.
		 */
		$tags = apply_filters( 'acme_seo_open_graph_tags', $tags );
		foreach ( $tags as $property => $content ) {
			printf( '<meta property="%s" content="%s" />' . "\n", esc_attr( $property ), esc_attr( $content ) );
		}
	}

	/**
	 * Organization JSON-LD with the social profiles.
	 */
	protected static function organization() {
		$data = array(
			'@context' => 'https://schema.org',
			'@type'    => 'Organization',
			'name'     => get_bloginfo( 'name' ),
			'url'      => home_url( '/' ),
		);
		$same = array_values( array_filter( Options::get( 'social_profiles' ) ) );
		if ( $same ) {
			$data['sameAs'] = $same;
		}
		echo '<script type="application/ld+json" class="acme-seo-schema">' . wp_json_encode( $data, JSON_UNESCAPED_SLASHES ) . "</script>\n";
	}
}
