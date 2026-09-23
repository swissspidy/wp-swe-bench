<?php
/**
 * The `acme_seo_settings` option: schema, defaults, sanitizing and REST exposure.
 *
 * @package Acme\SEO
 */

namespace Acme\SEO;

defined( 'ABSPATH' ) || exit;

/**
 * All settings live in one option, exposed as `acme_seo_settings` on /wp/v2/settings.
 */
class Settings {

	const OPTION = 'acme_seo_settings';
	const GROUP  = 'acme-seo';

	const PATTERN_TWITTER = '^[A-Za-z0-9_]{0,15}$';
	const PATTERN_URL     = '^(https?://[A-Za-z0-9-]+(\.[A-Za-z0-9-]+)+(:[0-9]+)?(/[^\s"<>]*)?)?$';
	const PATTERN_GOOGLE  = '^([A-Za-z0-9_-]{20,64})?$';
	const PATTERN_BING    = '^([A-Fa-f0-9]{32})?$';

	/**
	 * Per-request cache of the merged settings.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * Hooks.
	 */
	public static function init() {
		// After custom post types were registered (they are part of the schema).
		add_action( 'init', array( __CLASS__, 'register' ), 20 );
		add_action( 'rest_api_init', array( __CLASS__, 'register' ) );
		add_action( 'update_option_' . self::OPTION, array( __CLASS__, 'updated' ) );
		add_action( 'add_option_' . self::OPTION, array( __CLASS__, 'updated' ) );
		add_action( 'delete_option_' . self::OPTION, array( __CLASS__, 'flush' ) );
	}

	/**
	 * Register the setting.
	 */
	public static function register() {
		register_setting(
			self::GROUP,
			self::OPTION,
			array(
				'type'              => 'object',
				'description'       => __( 'Acme SEO settings.', 'acme-seo' ),
				'default'           => self::defaults(),
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'show_in_rest'      => array(
					'name'   => self::OPTION,
					'schema' => self::schema(),
				),
			)
		);
	}

	/**
	 * Default values.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'titles'       => array(
				'separator'        => '-',
				'home_title'       => Legacy_Options::DEFAULT_HOME_TITLE,
				'home_description' => '',
			),
			'indexing'     => array(
				'noindex_post_types'      => array(),
				'noindex_author_archives' => false,
				'noindex_date_archives'   => true,
				'noindex_tag_archives'    => false,
			),
			'social'       => array(
				'og_enabled'     => true,
				'default_image'  => 0,
				'twitter_handle' => '',
				'profiles'       => array_fill_keys( Legacy_Options::NETWORKS, '' ),
			),
			'sitemap'      => array(
				'enabled' => true,
				'exclude' => array(),
			),
			'verification' => array(
				'google' => '',
				'bing'   => '',
			),
		);
	}

	/**
	 * Public post types that can be excluded from search engines.
	 *
	 * @return string[]
	 */
	public static function post_types() {
		return array_values( get_post_types( array( 'public' => true ) ) );
	}

	/**
	 * JSON schema of the setting.
	 *
	 * @return array
	 */
	public static function schema() {
		$object = static function ( array $properties ) {
			return array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'properties'           => $properties,
			);
		};
		$bool   = array( 'type' => 'boolean' );
		$url    = array(
			'type'    => 'string',
			'pattern' => self::PATTERN_URL,
		);

		return $object(
			array(
				'titles'       => $object(
					array(
						'separator'        => array(
							'type' => 'string',
							'enum' => Legacy_Options::SEPARATORS,
						),
						'home_title'       => array(
							'type'      => 'string',
							'maxLength' => 200,
						),
						'home_description' => array(
							'type'      => 'string',
							'maxLength' => 320,
						),
					)
				),
				'indexing'     => $object(
					array(
						'noindex_post_types'      => array(
							'type'        => 'array',
							'uniqueItems' => true,
							'items'       => array(
								'type' => 'string',
								'enum' => self::post_types(),
							),
						),
						'noindex_author_archives' => $bool,
						'noindex_date_archives'   => $bool,
						'noindex_tag_archives'    => $bool,
					)
				),
				'social'       => $object(
					array(
						'og_enabled'     => $bool,
						'default_image'  => array(
							'type'    => 'integer',
							'minimum' => 0,
						),
						'twitter_handle' => array(
							'type'    => 'string',
							'pattern' => self::PATTERN_TWITTER,
						),
						'profiles'       => $object( array_fill_keys( Legacy_Options::NETWORKS, $url ) ),
					)
				),
				'sitemap'      => $object(
					array(
						'enabled' => $bool,
						'exclude' => array(
							'type'  => 'array',
							'items' => array(
								'type'    => 'integer',
								'minimum' => 1,
							),
						),
					)
				),
				'verification' => $object(
					array(
						'google' => array(
							'type'    => 'string',
							'pattern' => self::PATTERN_GOOGLE,
						),
						'bing'   => array(
							'type'    => 'string',
							'pattern' => self::PATTERN_BING,
						),
					)
				),
			)
		);
	}

	/**
	 * Current settings, complete (defaults for anything missing).
	 *
	 * @return array
	 */
	public static function get() {
		if ( null === self::$cache ) {
			$stored      = get_option( self::OPTION, array() );
			self::$cache = self::merge( self::defaults(), is_array( $stored ) ? $stored : array() );
		}
		return self::$cache;
	}

	/**
	 * Sanitize a new value: merged into the current settings (clients may send only the
	 * parts they change), then every field normalized so the stored option always matches
	 * the schema.
	 *
	 * @param mixed $value New value.
	 * @return array
	 */
	public static function sanitize( $value ) {
		self::flush();
		$current = self::get();
		if ( ! is_array( $value ) ) {
			return $current;
		}
		return self::normalize( self::merge( $current, $value ) );
	}

	/**
	 * Normalize a complete settings array.
	 *
	 * @param array $s Settings.
	 * @return array
	 */
	public static function normalize( array $s ) {
		$d   = self::defaults();
		$out = $d;

		$text = static function ( $value, $max ) {
			$value = is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
			return mb_substr( $value, 0, $max );
		};

		$sep                                  = $s['titles']['separator'] ?? '-';
		$out['titles']['separator']           = in_array( $sep, Legacy_Options::SEPARATORS, true ) ? $sep : $d['titles']['separator'];
		$out['titles']['home_title']          = $text( $s['titles']['home_title'] ?? '', 200 );
		$out['titles']['home_description']    = $text( $s['titles']['home_description'] ?? '', 320 );
		$public                               = self::post_types();
		$types                                = array_values( array_unique( array_filter( (array) ( $s['indexing']['noindex_post_types'] ?? array() ), 'is_string' ) ) );
		$out['indexing']['noindex_post_types'] = array_values( array_intersect( $types, $public ) );
		foreach ( array( 'noindex_author_archives', 'noindex_date_archives', 'noindex_tag_archives' ) as $key ) {
			$out['indexing'][ $key ] = (bool) ( $s['indexing'][ $key ] ?? $d['indexing'][ $key ] );
		}

		$out['social']['og_enabled']    = (bool) ( $s['social']['og_enabled'] ?? true );
		$out['social']['default_image'] = absint( $s['social']['default_image'] ?? 0 );
		$handle                         = ltrim( (string) ( $s['social']['twitter_handle'] ?? '' ), '@' );
		$out['social']['twitter_handle'] = preg_match( '/' . self::PATTERN_TWITTER . '/', $handle ) ? $handle : '';
		foreach ( Legacy_Options::NETWORKS as $network ) {
			$url                                     = trim( (string) ( $s['social']['profiles'][ $network ] ?? '' ) );
			$out['social']['profiles'][ $network ] = preg_match( '#' . self::PATTERN_URL . '#', $url ) ? $url : '';
		}

		$out['sitemap']['enabled'] = (bool) ( $s['sitemap']['enabled'] ?? true );
		$ids                       = array();
		foreach ( (array) ( $s['sitemap']['exclude'] ?? array() ) as $id ) {
			if ( is_numeric( $id ) && (int) $id > 0 && ! in_array( (int) $id, $ids, true ) ) {
				$ids[] = (int) $id;
			}
		}
		$out['sitemap']['exclude'] = $ids;

		$google                          = trim( (string) ( $s['verification']['google'] ?? '' ) );
		$bing                            = trim( (string) ( $s['verification']['bing'] ?? '' ) );
		$out['verification']['google'] = preg_match( '/' . self::PATTERN_GOOGLE . '/', $google ) ? $google : '';
		$out['verification']['bing']   = preg_match( '/' . self::PATTERN_BING . '/', $bing ) ? $bing : '';

		return $out;
	}

	/**
	 * Build the settings from the normalized 1.x values (see Legacy_Options::all()).
	 *
	 * @param array $legacy Legacy values keyed like Legacy_Options::MAP.
	 * @return array
	 */
	public static function from_legacy( array $legacy ) {
		return self::normalize(
			array(
				'titles'       => array(
					'separator'        => $legacy['title_separator'],
					'home_title'       => $legacy['home_title'],
					'home_description' => $legacy['home_description'],
				),
				'indexing'     => array(
					'noindex_post_types'      => $legacy['noindex_post_types'],
					'noindex_author_archives' => $legacy['noindex_archives']['author'],
					'noindex_date_archives'   => $legacy['noindex_archives']['date'],
					'noindex_tag_archives'    => $legacy['noindex_archives']['tag'],
				),
				'social'       => array(
					'og_enabled'     => $legacy['og_enabled'],
					'default_image'  => $legacy['og_default_image'],
					'twitter_handle' => $legacy['twitter_handle'],
					'profiles'       => $legacy['social_profiles'],
				),
				'sitemap'      => array(
					'enabled' => $legacy['sitemap_enabled'],
					'exclude' => $legacy['sitemap_exclude'],
				),
				'verification' => $legacy['verification'],
			)
		);
	}

	/**
	 * Recursive merge: associative arrays are merged key by key, lists and scalars replaced.
	 *
	 * @param array $base  Base.
	 * @param array $patch Changes.
	 * @return array
	 */
	public static function merge( array $base, array $patch ) {
		foreach ( $patch as $key => $value ) {
			if ( is_array( $value ) && isset( $base[ $key ] ) && is_array( $base[ $key ] ) && self::is_assoc( $base[ $key ] ) && ! array_is_list( $value ) ) {
				$base[ $key ] = self::merge( $base[ $key ], $value );
			} elseif ( is_array( $value ) && isset( $base[ $key ] ) && is_array( $base[ $key ] ) && self::is_assoc( $base[ $key ] ) && array() === $value ) {
				continue;
			} else {
				$base[ $key ] = $value;
			}
		}
		return $base;
	}

	/**
	 * Is this an associative (object-like) array?
	 *
	 * @param array $value Array.
	 * @return bool
	 */
	private static function is_assoc( array $value ) {
		return array() !== $value && ! array_is_list( $value );
	}

	/**
	 * Option changed: forget caches and keep the 1.x "saved" action firing.
	 */
	public static function updated() {
		self::flush();

		/** This action is documented in the 1.x settings screen (since 1.0.0). */
		do_action( 'acme_seo_settings_saved' );
	}

	/**
	 * Forget the per-request cache.
	 */
	public static function flush() {
		self::$cache = null;
	}
}
