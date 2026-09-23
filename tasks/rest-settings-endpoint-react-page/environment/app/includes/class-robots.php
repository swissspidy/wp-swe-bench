<?php
/**
 * Robots meta.
 *
 * @package Acme\SEO
 */

namespace Acme\SEO;

defined( 'ABSPATH' ) || exit;

/**
 * Adds noindex for excluded post types, archives and single posts.
 */
class Robots {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_filter( 'wp_robots', array( __CLASS__, 'robots' ) );
	}

	/**
	 * Should the current view be kept out of search engines?
	 *
	 * @return bool
	 */
	public static function is_noindex() {
		if ( is_singular() ) {
			$post = get_queried_object();
			if ( in_array( $post->post_type, Options::get( 'noindex_post_types' ), true ) ) {
				return true;
			}
			return (bool) get_post_meta( $post->ID, Post_Meta::NOINDEX, true );
		}
		$archives = Options::get( 'noindex_archives' );
		if ( is_author() ) {
			return $archives['author'];
		}
		if ( is_date() ) {
			return $archives['date'];
		}
		if ( is_tag() ) {
			return $archives['tag'];
		}
		return false;
	}

	/**
	 * Filter wp_robots.
	 *
	 * @param array $robots Directives.
	 * @return array
	 */
	public static function robots( $robots ) {
		if ( self::is_noindex() ) {
			$robots['noindex'] = true;
			$robots['follow']  = true;
		}
		return $robots;
	}
}
