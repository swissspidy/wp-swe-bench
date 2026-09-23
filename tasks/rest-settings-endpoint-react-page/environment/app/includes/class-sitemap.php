<?php
/**
 * XML sitemap tweaks (WordPress' built-in sitemaps).
 *
 * @package Acme\SEO
 */

namespace Acme\SEO;

defined( 'ABSPATH' ) || exit;

/**
 * Turns the sitemap on/off, removes noindex post types and excluded posts.
 */
class Sitemap {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_filter( 'wp_sitemaps_enabled', array( __CLASS__, 'enabled' ) );
		add_filter( 'wp_sitemaps_post_types', array( __CLASS__, 'post_types' ) );
		add_filter( 'wp_sitemaps_posts_query_args', array( __CLASS__, 'query_args' ) );
	}

	/**
	 * Sitemap on/off.
	 *
	 * @param bool $enabled Enabled.
	 * @return bool
	 */
	public static function enabled( $enabled ) {
		return $enabled && Options::get( 'sitemap_enabled' );
	}

	/**
	 * Noindexed post types don't belong into the sitemap.
	 *
	 * @param \WP_Post_Type[] $post_types Post types.
	 * @return \WP_Post_Type[]
	 */
	public static function post_types( $post_types ) {
		foreach ( Options::get( 'noindex_post_types' ) as $type ) {
			unset( $post_types[ $type ] );
		}
		return $post_types;
	}

	/**
	 * Exclude posts.
	 *
	 * @param array $args WP_Query args.
	 * @return array
	 */
	public static function query_args( $args ) {
		$exclude = Options::get( 'sitemap_exclude' );
		if ( $exclude ) {
			$args['post__not_in'] = array_merge( isset( $args['post__not_in'] ) ? (array) $args['post__not_in'] : array(), $exclude );
		}
		return $args;
	}
}
