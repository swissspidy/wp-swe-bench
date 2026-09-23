<?php
/**
 * Settings → CTA tracking.
 *
 * @package Acme\CTA
 */

namespace Acme\CTA;

defined( 'ABSPATH' ) || exit;

/**
 * Settings screen for the campaign tracking parameters.
 */
class Settings {

	const OPTION = 'acme_cta_tracking';
	const PAGE   = 'acme-cta';

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_action( 'admin_init', array( $this, 'register_setting' ) );
		add_action( 'admin_menu', array( $this, 'add_page' ) );
	}

	/**
	 * Register the option and its fields.
	 */
	public function register_setting() {
		register_setting(
			self::PAGE,
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => array(
					'enabled'    => true,
					'utm_source' => 'acme',
					'utm_medium' => 'cta',
				),
			)
		);

		add_settings_section( 'acme_cta_main', __( 'Campaign tracking', 'acme-cta' ), '__return_false', self::PAGE );

		add_settings_field(
			'acme_cta_enabled',
			__( 'Track CTA clicks', 'acme-cta' ),
			array( $this, 'field_enabled' ),
			self::PAGE,
			'acme_cta_main'
		);
		add_settings_field(
			'acme_cta_utm_source',
			__( 'utm_source', 'acme-cta' ),
			array( $this, 'field_text' ),
			self::PAGE,
			'acme_cta_main',
			array( 'key' => 'utm_source' )
		);
		add_settings_field(
			'acme_cta_utm_medium',
			__( 'utm_medium', 'acme-cta' ),
			array( $this, 'field_text' ),
			self::PAGE,
			'acme_cta_main',
			array( 'key' => 'utm_medium' )
		);
	}

	/**
	 * Sanitize the option.
	 *
	 * @param mixed $value Raw value.
	 * @return array
	 */
	public function sanitize( $value ) {
		$value = is_array( $value ) ? $value : array();
		return array(
			'enabled'    => ! empty( $value['enabled'] ),
			'utm_source' => sanitize_key( $value['utm_source'] ?? '' ) ? sanitize_key( $value['utm_source'] ) : 'acme',
			'utm_medium' => sanitize_key( $value['utm_medium'] ?? '' ) ? sanitize_key( $value['utm_medium'] ) : 'cta',
		);
	}

	/**
	 * Add the settings page.
	 */
	public function add_page() {
		add_options_page(
			__( 'CTA tracking', 'acme-cta' ),
			__( 'CTA tracking', 'acme-cta' ),
			'manage_options',
			self::PAGE,
			array( $this, 'render_page' )
		);
	}

	/**
	 * "Enabled" checkbox.
	 */
	public function field_enabled() {
		printf(
			'<label><input type="checkbox" name="%1$s[enabled]" value="1" %2$s /> %3$s</label>',
			esc_attr( self::OPTION ),
			checked( (bool) acme_cta_get_option( 'enabled' ), true, false ),
			esc_html__( 'Add UTM parameters to outgoing CTA links', 'acme-cta' )
		);
	}

	/**
	 * Text field.
	 *
	 * @param array $args Field args.
	 */
	public function field_text( $args ) {
		printf(
			'<input type="text" class="regular-text" name="%1$s[%2$s]" value="%3$s" />',
			esc_attr( self::OPTION ),
			esc_attr( $args['key'] ),
			esc_attr( (string) acme_cta_get_option( $args['key'] ) )
		);
	}

	/**
	 * Render the settings page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		echo '<div class="wrap"><h1>' . esc_html( get_admin_page_title() ) . '</h1><form method="post" action="options.php">';
		settings_fields( self::PAGE );
		do_settings_sections( self::PAGE );
		submit_button();
		echo '</form></div>';
	}
}
