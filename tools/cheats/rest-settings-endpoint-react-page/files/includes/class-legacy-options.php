<?php
/**
 * Readers for the settings as stored by Acme SEO 1.x.
 *
 * Up to 1.9 every setting lived in its own option (see MAP). Over the years the stored
 * formats changed several times and the settings screen never sanitized consistently, so
 * every value had to be normalized when it was read. Since 2.0 these readers are only used
 * to migrate the old options into the `acme_seo_settings` option (see Upgrader).
 *
 * @package Acme\SEO
 */

namespace Acme\SEO;

defined( 'ABSPATH' ) || exit;

/**
 * Legacy settings reader.
 */
class Legacy_Options {

	/**
	 * Setting key => option name.
	 */
	const MAP = array(
		'title_separator'    => 'acme_seo_title_separator',
		'home_title'         => 'acme_seo_home_title',
		'home_description'   => 'acme_seo_home_description',
		'noindex_post_types' => 'acme_seo_noindex_post_types',
		'noindex_archives'   => 'acme_seo_noindex_archives',
		'og_enabled'         => 'acme_seo_og_enabled',
		'og_default_image'   => 'acme_seo_og_default_image',
		'twitter_handle'     => 'acme_seo_twitter_handle',
		'social_profiles'    => 'acme_seo_social_profiles',
		'sitemap_enabled'    => 'acme_seo_sitemap_enabled',
		'sitemap_exclude'    => 'acme_seo_sitemap_exclude',
		'verification'       => 'acme_seo_verification',
	);

	/**
	 * Allowed title separators.
	 */
	const SEPARATORS = array( '-', '–', '|', '·', '»' );

	/**
	 * Social networks we link to.
	 */
	const NETWORKS = array( 'facebook', 'instagram', 'linkedin', 'youtube' );

	const DEFAULT_HOME_TITLE = '%%sitename%% %%sep%% %%tagline%%';

	/**
	 * Read and normalize one legacy option.
	 *
	 * @param string $key Setting key (see MAP).
	 * @return mixed Null for unknown keys.
	 */
	public static function read( $key ) {
		if ( ! isset( self::MAP[ $key ] ) ) {
			return null;
		}
		$method = 'read_' . $key;
		return self::$method( self::raw( self::MAP[ $key ] ) );
	}

	/**
	 * Stored value of an option, bypassing `pre_option_*` filters (the 2.0 compatibility
	 * layer answers for some of the old names once they are gone).
	 *
	 * @param string $option Option name.
	 * @return mixed Null when the option does not exist.
	 */
	public static function raw( $option ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1", $option ) );
		return $row ? maybe_unserialize( $row->option_value ) : null;
	}

	/**
	 * Does any legacy option still exist?
	 *
	 * @return bool
	 */
	public static function exist() {
		foreach ( self::MAP as $option ) {
			if ( null !== self::raw( $option ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * All settings, normalized.
	 *
	 * @return array
	 */
	public static function all() {
		$all = array();
		foreach ( array_keys( self::MAP ) as $key ) {
			$all[ $key ] = self::read( $key );
		}
		return $all;
	}

	/**
	 * Separator. 1.0 stored HTML entities (&ndash; &raquo; &middot;).
	 *
	 * @param mixed $raw Stored value.
	 * @return string
	 */
	protected static function read_title_separator( $raw ) {
		$sep = is_string( $raw ) ? trim( html_entity_decode( $raw, ENT_QUOTES, 'UTF-8' ) ) : '';
		return in_array( $sep, self::SEPARATORS, true ) ? $sep : '-';
	}

	/**
	 * Home title template. 1.0 used {site}, {tagline} and {sep} placeholders.
	 *
	 * @param mixed $raw Stored value.
	 * @return string
	 */
	protected static function read_home_title( $raw ) {
		if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
			return self::DEFAULT_HOME_TITLE;
		}
		return str_replace( array( '{site}', '{tagline}', '{sep}' ), array( '%%sitename%%', '%%tagline%%', '%%sep%%' ), trim( $raw ) );
	}

	/**
	 * Home meta description.
	 *
	 * @param mixed $raw Stored value.
	 * @return string
	 */
	protected static function read_home_description( $raw ) {
		return is_string( $raw ) ? trim( $raw ) : '';
	}

	/**
	 * Post types excluded from search engines. 1.0 stored a comma separated string.
	 * Post types that are not (or no longer) registered as public are ignored.
	 *
	 * @param mixed $raw Stored value.
	 * @return string[]
	 */
	protected static function read_noindex_post_types( $raw ) {
		if ( is_string( $raw ) ) {
			$raw = explode( ',', $raw );
		}
		$public = get_post_types( array( 'public' => true ) );
		$types  = array();
		foreach ( (array) $raw as $type ) {
			$type = is_string( $type ) ? sanitize_key( $type ) : '';
			if ( '' !== $type && isset( $public[ $type ] ) && ! in_array( $type, $types, true ) ) {
				$types[] = $type;
			}
		}
		return $types;
	}

	/**
	 * Archive types excluded from search engines: [ author, date, tag ] => bool.
	 * Missing keys fall back to the defaults (date archives are noindex by default).
	 *
	 * @param mixed $raw Stored value.
	 * @return array<string, bool>
	 */
	protected static function read_noindex_archives( $raw ) {
		$defaults = array(
			'author' => false,
			'date'   => true,
			'tag'    => false,
		);
		$raw      = is_array( $raw ) ? $raw : array();
		$out      = array();
		foreach ( $defaults as $archive => $default ) {
			$out[ $archive ] = array_key_exists( $archive, $raw ) ? self::truthy( $raw[ $archive ] ) : $default;
		}
		return $out;
	}

	/**
	 * Open Graph on/off. Stored as 'yes'/'no' since 1.2, '1'/'0' before.
	 *
	 * @param mixed $raw Stored value.
	 * @return bool
	 */
	protected static function read_og_enabled( $raw ) {
		return null === $raw ? true : self::truthy( $raw );
	}

	/**
	 * Default share image as attachment ID. Before 1.5 the image URL was stored; URLs
	 * that don't belong to an attachment of this site can't be used (0).
	 *
	 * @param mixed $raw Stored value.
	 * @return int
	 */
	protected static function read_og_default_image( $raw ) {
		if ( is_int( $raw ) || ( is_string( $raw ) && ctype_digit( $raw ) ) ) {
			return (int) $raw;
		}
		if ( is_string( $raw ) && preg_match( '#^https?://#i', $raw ) ) {
			return (int) attachment_url_to_postid( $raw );
		}
		return 0;
	}

	/**
	 * Twitter/X username without "@". 1.0 stored the profile URL.
	 *
	 * @param mixed $raw Stored value.
	 * @return string
	 */
	protected static function read_twitter_handle( $raw ) {
		if ( ! is_string( $raw ) ) {
			return '';
		}
		$handle = trim( $raw );
		if ( preg_match( '#^https?://(?:www\.)?(?:twitter|x)\.com/@?([^/?\#]+)#i', $handle, $m ) ) {
			$handle = $m[1];
		}
		$handle = ltrim( $handle, '@' );
		return preg_match( '/^[A-Za-z0-9_]{1,15}$/', $handle ) ? $handle : '';
	}

	/**
	 * Social profile URLs keyed by network. Anything that isn't an http(s) URL is dropped.
	 *
	 * @param mixed $raw Stored value.
	 * @return array<string, string>
	 */
	protected static function read_social_profiles( $raw ) {
		$raw = is_array( $raw ) ? $raw : array();
		$out = array();
		foreach ( self::NETWORKS as $network ) {
			$url             = isset( $raw[ $network ] ) && is_string( $raw[ $network ] ) ? trim( $raw[ $network ] ) : '';
			$out[ $network ] = self::is_http_url( $url ) ? $url : '';
		}
		return $out;
	}

	/**
	 * XML sitemap on/off. Only an explicit "0" disables it.
	 *
	 * @param mixed $raw Stored value.
	 * @return bool
	 */
	protected static function read_sitemap_enabled( $raw ) {
		return null === $raw || '0' !== (string) $raw;
	}

	/**
	 * Post IDs excluded from the sitemap. Stored as a comma separated string by the
	 * settings screen; imports wrote arrays.
	 *
	 * @param mixed $raw Stored value.
	 * @return int[]
	 */
	protected static function read_sitemap_exclude( $raw ) {
		if ( is_string( $raw ) ) {
			$raw = explode( ',', $raw );
		}
		$ids = array();
		foreach ( (array) $raw as $id ) {
			$id = is_string( $id ) ? trim( $id ) : $id;
			if ( ( is_int( $id ) || ( is_string( $id ) && ctype_digit( $id ) ) ) && (int) $id > 0 && ! in_array( (int) $id, $ids, true ) ) {
				$ids[] = (int) $id;
			}
		}
		return $ids;
	}

	/**
	 * Verification codes. 1.0 asked for the whole meta tag, so extract its content.
	 *
	 * @param mixed $raw Stored value.
	 * @return array{google:string, bing:string}
	 */
	protected static function read_verification( $raw ) {
		$raw      = is_array( $raw ) ? $raw : array();
		$patterns = array(
			'google' => '/^[A-Za-z0-9_-]{20,64}$/',
			'bing'   => '/^[A-Fa-f0-9]{32}$/',
		);
		$out      = array();
		foreach ( $patterns as $engine => $pattern ) {
			$code = isset( $raw[ $engine ] ) && is_string( $raw[ $engine ] ) ? trim( $raw[ $engine ] ) : '';
			if ( false !== stripos( $code, '<meta' ) && preg_match( '/content=["\']([^"\']*)["\']/i', $code, $m ) ) {
				$code = trim( $m[1] );
			}
			$out[ $engine ] = preg_match( $pattern, $code ) ? $code : '';
		}
		return $out;
	}

	/**
	 * Truthiness of legacy checkbox values ('1', 'yes', 'on', true, 1 ...).
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	public static function truthy( $value ) {
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( is_int( $value ) ) {
			return 0 !== $value;
		}
		return is_string( $value ) && in_array( strtolower( trim( $value ) ), array( '1', 'yes', 'on', 'true' ), true );
	}

	/**
	 * Is this an absolute http(s) URL?
	 *
	 * @param string $url URL.
	 * @return bool
	 */
	public static function is_http_url( $url ) {
		return is_string( $url ) && (bool) preg_match( '#^https?://[a-z0-9-]+(\.[a-z0-9-]+)+(:\d+)?(/[^\s"<>]*)?$#i', $url );
	}
}
