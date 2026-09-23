<?php
/**
 * Document titles.
 *
 * @package Acme\SEO
 */

namespace Acme\SEO;

defined( 'ABSPATH' ) || exit;

/**
 * Title separator, home page title template and per-post title overrides.
 */
class Title {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_filter( 'document_title_separator', array( __CLASS__, 'separator' ) );
		add_filter( 'pre_get_document_title', array( __CLASS__, 'pre_title' ), 20 );
	}

	/**
	 * The configured separator.
	 *
	 * @return string
	 */
	public static function separator() {
		return Options::get( 'title_separator' );
	}

	/**
	 * Replace %%placeholders%% in a title template.
	 *
	 * @param string $template Template.
	 * @return string
	 */
	public static function render_template( $template ) {
		$replacements = array(
			'%%sitename%%' => get_bloginfo( 'name', 'display' ),
			'%%tagline%%'  => get_bloginfo( 'description', 'display' ),
			'%%sep%%'      => self::separator(),
		);
		$title        = strtr( $template, $replacements );
		return trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $title ) ) );
	}

	/**
	 * Home page title and per-post overrides.
	 *
	 * @param string $title Title from earlier filters.
	 * @return string
	 */
	public static function pre_title( $title ) {
		if ( is_front_page() ) {
			return self::render_template( Options::get( 'home_title' ) );
		}
		if ( is_singular() ) {
			$custom = get_post_meta( get_queried_object_id(), Post_Meta::TITLE, true );
			if ( is_string( $custom ) && '' !== trim( $custom ) ) {
				return self::render_template( $custom );
			}
		}
		return $title;
	}
}
