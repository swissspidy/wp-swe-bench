<?php
/**
 * The consolidated plugin settings (`acme_social_settings`).
 *
 * @package Acme_Social
 */

defined( 'ABSPATH' ) || exit;

/**
 * Schema, defaults, access, validation and registration of the settings.
 */
class Acme_Social_Settings {

	const OPTION = 'acme_social_settings';

	/**
	 * Settings screen (option group) => section key.
	 */
	const PAGES = array(
		'acme-social'          => 'sharing',
		'acme-social-profiles' => 'profiles',
		'acme-social-og'       => 'og',
	);

	/**
	 * Field definitions: key => section, type, label.
	 *
	 * @return array<string, array{section: string, type: string, label: string}>
	 */
	public static function fields() {
		return array(
			'share_enabled'    => array(
				'section' => 'sharing',
				'type'    => 'bool',
				'label'   => __( 'Enable share buttons', 'acme-social' ),
			),
			'networks'         => array(
				'section' => 'sharing',
				'type'    => 'networks',
				'label'   => __( 'Networks', 'acme-social' ),
			),
			'position'         => array(
				'section' => 'sharing',
				'type'    => 'position',
				'label'   => __( 'Button position', 'acme-social' ),
			),
			'post_types'       => array(
				'section' => 'sharing',
				'type'    => 'post_types',
				'label'   => __( 'Show on', 'acme-social' ),
			),
			'button_style'     => array(
				'section' => 'sharing',
				'type'    => 'button_style',
				'label'   => __( 'Button style', 'acme-social' ),
			),
			'twitter'          => array(
				'section' => 'profiles',
				'type'    => 'handle',
				'label'   => __( 'Twitter/X username', 'acme-social' ),
			),
			'facebook'         => array(
				'section' => 'profiles',
				'type'    => 'url',
				'label'   => __( 'Facebook page URL', 'acme-social' ),
			),
			'instagram'        => array(
				'section' => 'profiles',
				'type'    => 'url',
				'label'   => __( 'Instagram URL', 'acme-social' ),
			),
			'linkedin'         => array(
				'section' => 'profiles',
				'type'    => 'url',
				'label'   => __( 'LinkedIn URL', 'acme-social' ),
			),
			'youtube'          => array(
				'section' => 'profiles',
				'type'    => 'url',
				'label'   => __( 'YouTube channel URL', 'acme-social' ),
			),
			'og_enabled'       => array(
				'section' => 'og',
				'type'    => 'bool',
				'label'   => __( 'Enable Open Graph tags', 'acme-social' ),
			),
			'og_default_image' => array(
				'section' => 'og',
				'type'    => 'image',
				'label'   => __( 'Default share image (attachment ID)', 'acme-social' ),
			),
			'fb_app_id'        => array(
				'section' => 'og',
				'type'    => 'fb_app_id',
				'label'   => __( 'Facebook App ID', 'acme-social' ),
			),
			'twitter_card'     => array(
				'section' => 'og',
				'type'    => 'twitter_card',
				'label'   => __( 'Twitter card type', 'acme-social' ),
			),
		);
	}

	/**
	 * Defaults (what a fresh 1.x install used).
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'share_enabled'    => true,
			'networks'         => array( 'facebook', 'twitter', 'linkedin' ),
			'position'         => 'after',
			'post_types'       => array( 'post' ),
			'button_style'     => 'icons',
			'twitter'          => '',
			'facebook'         => '',
			'instagram'        => '',
			'linkedin'         => '',
			'youtube'          => '',
			'og_enabled'       => true,
			'og_default_image' => 0,
			'fb_app_id'        => '',
			'twitter_card'     => 'summary_large_image',
		);
	}

	/**
	 * All settings (complete, with defaults) or a single one.
	 *
	 * @param string|null $key Setting key.
	 * @return mixed
	 */
	public static function get( $key = null ) {
		$stored   = get_option( self::OPTION, array() );
		$defaults = self::defaults();
		$settings = is_array( $stored ) ? array_merge( $defaults, array_intersect_key( $stored, $defaults ) ) : $defaults;
		if ( null === $key ) {
			return $settings;
		}
		return array_key_exists( $key, $settings ) ? $settings[ $key ] : null;
	}

	/**
	 * Hooks.
	 */
	public function register() {
		add_action( 'admin_init', array( $this, 'register_setting' ) );
		add_action( 'update_option_' . self::OPTION, 'acme_social_flush_og_cache' );
		add_action( 'add_option_' . self::OPTION, 'acme_social_flush_og_cache' );
		foreach ( array_keys( self::PAGES ) as $page ) {
			add_filter( 'option_page_capability_' . $page, array( 'Acme_Social_Settings', 'capability' ) );
		}
	}

	/**
	 * Capability needed to change the settings.
	 *
	 * @return string
	 */
	public static function capability() {
		/**
		 * Filters the capability required to see and change the Acme Social settings.
		 *
		 * @since 1.4.0
		 *
		 * @param string $capability Capability. Default 'manage_options'.
		 */
		return (string) apply_filters( 'acme_social_admin_capability', 'manage_options' );
	}

	/**
	 * Registers the option for each settings screen.
	 */
	public function register_setting() {
		foreach ( array_keys( self::PAGES ) as $group ) {
			register_setting(
				$group,
				self::OPTION,
				array(
					'type'              => 'object',
					'sanitize_callback' => array( $this, 'sanitize' ),
					'default'           => self::defaults(),
					'show_in_rest'      => false,
				)
			);
		}
	}

	/**
	 * Validates submitted settings and merges them into the stored ones.
	 *
	 * When a settings screen is submitted, only that screen's fields are
	 * touched (unchecked checkboxes are missing from the request and mean
	 * "off"/"none"). Otherwise every key that is present is validated.
	 * Invalid values keep their previous value and report an error.
	 *
	 * @param mixed $input Submitted value.
	 * @return array
	 */
	public function sanitize( $input ) {
		$current = self::get();
		if ( ! is_array( $input ) ) {
			return $current;
		}

		$section = null;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- options.php verified the nonce.
		$page = isset( $_POST['option_page'] ) ? sanitize_key( wp_unslash( $_POST['option_page'] ) ) : '';
		if ( isset( self::PAGES[ $page ] ) ) {
			$section = self::PAGES[ $page ];
		}

		$output = $current;
		foreach ( self::fields() as $key => $field ) {
			if ( null !== $section ) {
				if ( $field['section'] !== $section ) {
					continue;
				}
				if ( ! array_key_exists( $key, $input ) ) {
					if ( 'bool' === $field['type'] ) {
						$output[ $key ] = false;
					} elseif ( in_array( $field['type'], array( 'networks', 'post_types' ), true ) ) {
						$output[ $key ] = array();
					}
					continue;
				}
			} elseif ( ! array_key_exists( $key, $input ) ) {
				continue;
			}

			$value = self::validate( $key, $input[ $key ] );
			if ( is_wp_error( $value ) ) {
				add_settings_error( self::OPTION, 'acme_social_' . $key, $value->get_error_message(), 'error' );
				continue;
			}
			$output[ $key ] = $value;
		}
		return $output;
	}

	/**
	 * Validates one value.
	 *
	 * @param string $key   Setting key.
	 * @param mixed  $value Raw value.
	 * @return mixed|WP_Error Normalized value or error.
	 */
	public static function validate( $key, $value ) {
		$fields = self::fields();
		if ( ! isset( $fields[ $key ] ) ) {
			return new WP_Error( 'acme_social_unknown', __( 'Unknown setting.', 'acme-social' ) );
		}
		$label = $fields[ $key ]['label'];

		switch ( $fields[ $key ]['type'] ) {
			case 'bool':
				return is_string( $value ) ? ! in_array( strtolower( trim( $value ) ), array( '', '0', 'no', 'off', 'false' ), true ) : (bool) $value;

			case 'networks':
				$available = acme_social_available_networks();
				return self::unique_slugs( $value, static fn( $slug ) => isset( $available[ $slug ] ) );

			case 'post_types':
				return self::unique_slugs( $value, static fn( $slug ) => post_type_exists( $slug ) && is_post_type_viewable( $slug ) );

			case 'position':
			case 'button_style':
			case 'twitter_card':
				$choices = array(
					'position'     => acme_social_positions(),
					'button_style' => acme_social_button_styles(),
					'twitter_card' => acme_social_twitter_card_types(),
				);
				$value   = is_scalar( $value ) ? (string) $value : '';
				if ( ! array_key_exists( $value, $choices[ $fields[ $key ]['type'] ] ) ) {
					/* translators: %s: field label. */
					return new WP_Error( 'acme_social_invalid', sprintf( __( '%s: please choose one of the available options.', 'acme-social' ), $label ) );
				}
				return $value;

			case 'handle':
				$value = is_scalar( $value ) ? trim( (string) $value ) : '';
				$value = ltrim( $value, '@' );
				if ( '' !== $value && ! preg_match( '/^[A-Za-z0-9_]{1,15}$/', $value ) ) {
					/* translators: %s: field label. */
					return new WP_Error( 'acme_social_invalid', sprintf( __( '%s: use 1 to 15 letters, numbers or underscores.', 'acme-social' ), $label ) );
				}
				return $value;

			case 'url':
				$value = is_scalar( $value ) ? trim( (string) $value ) : '';
				if ( '' === $value ) {
					return '';
				}
				$scheme = strtolower( (string) wp_parse_url( $value, PHP_URL_SCHEME ) );
				$host   = (string) wp_parse_url( $value, PHP_URL_HOST );
				if ( ! in_array( $scheme, array( 'http', 'https' ), true ) || '' === $host || ! filter_var( $value, FILTER_VALIDATE_URL ) ) {
					/* translators: %s: field label. */
					return new WP_Error( 'acme_social_invalid', sprintf( __( '%s: enter a complete http:// or https:// URL.', 'acme-social' ), $label ) );
				}
				return esc_url_raw( $value, array( 'http', 'https' ) );

			case 'image':
				$value = is_scalar( $value ) ? trim( (string) $value ) : '';
				if ( '' === $value || '0' === $value ) {
					return 0;
				}
				if ( ! ctype_digit( $value ) || ! wp_attachment_is_image( (int) $value ) ) {
					/* translators: %s: field label. */
					return new WP_Error( 'acme_social_invalid', sprintf( __( '%s: this is not the ID of an image in the media library.', 'acme-social' ), $label ) );
				}
				return (int) $value;

			case 'fb_app_id':
				$value = is_scalar( $value ) ? trim( (string) $value ) : '';
				if ( '' !== $value && ! preg_match( '/^\d{5,20}$/', $value ) ) {
					/* translators: %s: field label. */
					return new WP_Error( 'acme_social_invalid', sprintf( __( '%s: must be a number (5 to 20 digits).', 'acme-social' ), $label ) );
				}
				return $value;
		}
		return new WP_Error( 'acme_social_unknown', __( 'Unknown setting.', 'acme-social' ) );
	}

	/**
	 * Unique list of slugs that pass a check (order kept, others dropped).
	 *
	 * @param mixed    $value List.
	 * @param callable $check Check.
	 * @return string[]
	 */
	private static function unique_slugs( $value, $check ) {
		if ( is_string( $value ) ) {
			$value = explode( ',', $value );
		}
		$out = array();
		foreach ( (array) $value as $slug ) {
			if ( ! is_scalar( $slug ) ) {
				continue;
			}
			$slug = strtolower( trim( (string) $slug ) );
			if ( '' !== $slug && $check( $slug ) && ! in_array( $slug, $out, true ) ) {
				$out[] = $slug;
			}
		}
		return $out;
	}
}
