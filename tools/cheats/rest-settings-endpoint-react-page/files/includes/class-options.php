<?php
/**
 * Reading the plugin settings by their 1.x keys.
 *
 * Since 2.0 all settings live in the `acme_seo_settings` option (see Settings). This
 * class keeps the flat 1.x keys working for the plugin itself and for the public
 * acme_seo_get_option() wrapper that themes and other plugins use.
 *
 * @package Acme\SEO
 */

namespace Acme\SEO;

defined( 'ABSPATH' ) || exit;

/**
 * Settings reader.
 */
class Options {

	/**
	 * 1.x key => [ section, field ] in the settings.
	 */
	const PATHS = array(
		'title_separator'    => array( 'titles', 'separator' ),
		'home_title'         => array( 'titles', 'home_title' ),
		'home_description'   => array( 'titles', 'home_description' ),
		'noindex_post_types' => array( 'indexing', 'noindex_post_types' ),
		'og_enabled'         => array( 'social', 'og_enabled' ),
		'og_default_image'   => array( 'social', 'default_image' ),
		'twitter_handle'     => array( 'social', 'twitter_handle' ),
		'social_profiles'    => array( 'social', 'profiles' ),
		'sitemap_enabled'    => array( 'sitemap', 'enabled' ),
		'sitemap_exclude'    => array( 'sitemap', 'exclude' ),
	);

	/**
	 * Allowed title separators.
	 */
	const SEPARATORS = Legacy_Options::SEPARATORS;

	/**
	 * Get a setting by its 1.x key.
	 *
	 * @param string $key Setting key.
	 * @return mixed Null for unknown keys.
	 */
	public static function get( $key ) {
		$s = Settings::get();
		switch ( $key ) {
			case 'home_title':
				return '' === trim( $s['titles']['home_title'] ) ? Legacy_Options::DEFAULT_HOME_TITLE : $s['titles']['home_title'];
			case 'noindex_post_types':
				// Post types can disappear (plugin deactivated).
				return array_values( array_intersect( $s['indexing']['noindex_post_types'], Settings::post_types() ) );
			case 'noindex_archives':
				return array(
					'author' => $s['indexing']['noindex_author_archives'],
					'date'   => $s['indexing']['noindex_date_archives'],
					'tag'    => $s['indexing']['noindex_tag_archives'],
				);
			case 'verification':
				return $s['verification'];
		}
		if ( ! isset( self::PATHS[ $key ] ) ) {
			return null;
		}
		list( $section, $field ) = self::PATHS[ $key ];
		return $s[ $section ][ $field ];
	}

	/**
	 * All settings by 1.x key.
	 *
	 * @return array
	 */
	public static function all() {
		$all = array();
		foreach ( array_keys( Legacy_Options::MAP ) as $key ) {
			$all[ $key ] = self::get( $key );
		}
		return $all;
	}

	/**
	 * Forget cached values.
	 */
	public static function flush() {
		Settings::flush();
	}
}
