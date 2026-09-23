<?php
/**
 * Plugin settings (Settings → Directory).
 *
 * @package Acme\Directory
 */

namespace Acme\Directory;

defined( 'ABSPATH' ) || exit;

/**
 * Per-site settings stored in the `acme_directory_settings` option.
 */
class Settings {

	const OPTION = 'acme_directory_settings';

	/**
	 * Default settings. Filterable so the network mu-plugin can change them.
	 *
	 * @return array
	 */
	public static function defaults() {
		/**
		 * Filters the default directory settings used when a site is set up.
		 *
		 * @param array $defaults Default settings.
		 */
		return apply_filters(
			'acme_directory_default_settings',
			array(
				'per_page'     => 20,
				'moderation'   => true,
				'expire_days'  => 365,
				'purge_days'   => 30,
				'notify_email' => '',
			)
		);
	}

	/**
	 * All settings merged with defaults.
	 *
	 * @return array
	 */
	public static function all() {
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		return array_merge( self::defaults(), $stored );
	}

	/**
	 * A single setting.
	 *
	 * @param string $key Setting key.
	 * @return mixed
	 */
	public static function get( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/**
	 * Register the settings screen.
	 */
	public static function register() {
		register_setting(
			'acme_directory',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);

		add_settings_section( 'acme_directory_main', __( 'Directory', 'acme-directory' ), '__return_false', 'acme-directory-settings' );

		$fields = array(
			'per_page'     => __( 'Listings per page', 'acme-directory' ),
			'moderation'   => __( 'Hold new submissions for moderation', 'acme-directory' ),
			'expire_days'  => __( 'Listings expire after (days)', 'acme-directory' ),
			'purge_days'   => __( 'Delete unmoderated submissions after (days)', 'acme-directory' ),
			'notify_email' => __( 'Notify this address about new submissions', 'acme-directory' ),
		);
		foreach ( $fields as $key => $label ) {
			add_settings_field(
				'acme_directory_' . $key,
				$label,
				array( __CLASS__, 'render_field' ),
				'acme-directory-settings',
				'acme_directory_main',
				array(
					'key'       => $key,
					'label_for' => 'acme_directory_' . $key,
				)
			);
		}
	}

	/**
	 * Render one settings field.
	 *
	 * @param array $args Field args.
	 */
	public static function render_field( $args ) {
		$key   = $args['key'];
		$value = self::get( $key );
		$name  = self::OPTION . '[' . $key . ']';
		if ( 'moderation' === $key ) {
			printf(
				'<input type="checkbox" id="%1$s" name="%2$s" value="1" %3$s />',
				esc_attr( $args['label_for'] ),
				esc_attr( $name ),
				checked( (bool) $value, true, false )
			);
			return;
		}
		printf(
			'<input type="%1$s" id="%2$s" name="%3$s" value="%4$s" class="regular-text" />',
			'notify_email' === $key ? 'email' : 'number',
			esc_attr( $args['label_for'] ),
			esc_attr( $name ),
			esc_attr( (string) $value )
		);
	}

	/**
	 * Sanitize the settings array.
	 *
	 * @param mixed $input Raw input.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();
		$out   = self::defaults();

		$out['per_page']     = max( 1, min( 100, absint( isset( $input['per_page'] ) ? $input['per_page'] : $out['per_page'] ) ) );
		$out['moderation']   = ! empty( $input['moderation'] );
		$out['expire_days']  = max( 1, absint( isset( $input['expire_days'] ) ? $input['expire_days'] : $out['expire_days'] ) );
		$out['purge_days']   = max( 1, absint( isset( $input['purge_days'] ) ? $input['purge_days'] : $out['purge_days'] ) );
		$out['notify_email'] = isset( $input['notify_email'] ) ? sanitize_email( $input['notify_email'] ) : '';

		return $out;
	}
}
