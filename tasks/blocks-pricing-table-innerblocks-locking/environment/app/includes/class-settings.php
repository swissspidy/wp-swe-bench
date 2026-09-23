<?php
/**
 * Settings screen (Settings → Pricing).
 *
 * @package Acme\Pricing
 */

namespace Acme\Pricing;

defined( 'ABSPATH' ) || exit;

/**
 * Settings API integration for the `acme_pricing_options` option.
 */
class Settings {

	const OPTION = 'acme_pricing_options';
	const PAGE   = 'acme-pricing';

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Add the options page.
	 */
	public function add_page() {
		add_options_page(
			__( 'Pricing tables', 'acme-pricing' ),
			__( 'Pricing', 'acme-pricing' ),
			'manage_options',
			self::PAGE,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Register setting, section and fields.
	 */
	public function register_settings() {
		register_setting(
			self::PAGE,
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => acme_pricing_default_options(),
			)
		);

		add_settings_section( 'acme_pricing_main', __( 'Pricing tables', 'acme-pricing' ), '__return_false', self::PAGE );

		add_settings_field(
			'default_currency',
			__( 'Default currency', 'acme-pricing' ),
			array( $this, 'field_default_currency' ),
			self::PAGE,
			'acme_pricing_main',
			array( 'label_for' => 'acme-pricing-default-currency' )
		);

		add_settings_field(
			'schema',
			__( 'Structured data', 'acme-pricing' ),
			array( $this, 'field_schema' ),
			self::PAGE,
			'acme_pricing_main',
			array( 'label_for' => 'acme-pricing-schema' )
		);
	}

	/**
	 * Sanitize the option array.
	 *
	 * @param mixed $input Raw input.
	 * @return array
	 */
	public function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();

		return array(
			'default_currency' => Currency::sanitize_code( isset( $input['default_currency'] ) ? $input['default_currency'] : 'USD' ),
			'schema'           => ! empty( $input['schema'] ),
		);
	}

	/**
	 * Default currency <select>.
	 */
	public function field_default_currency() {
		$current = acme_pricing_get_option( 'default_currency' );
		echo '<select id="acme-pricing-default-currency" name="' . esc_attr( self::OPTION ) . '[default_currency]">';
		foreach ( acme_pricing_currencies() as $code => $rules ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $code ), selected( $current, $code, false ), esc_html( $code . ' – ' . $rules['label'] ) );
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Currency of newly inserted pricing tables.', 'acme-pricing' ) . '</p>';
	}

	/**
	 * Structured data checkbox.
	 */
	public function field_schema() {
		printf(
			'<label><input type="checkbox" id="acme-pricing-schema" name="%s[schema]" value="1"%s /> %s</label>',
			esc_attr( self::OPTION ),
			checked( acme_pricing_get_option( 'schema' ), true, false ),
			esc_html__( 'Output product/offer structured data (JSON-LD) for pages with pricing tables', 'acme-pricing' )
		);
	}

	/**
	 * Render the page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		echo '<div class="wrap"><h1>' . esc_html( get_admin_page_title() ) . '</h1><form action="options.php" method="post">';
		settings_fields( self::PAGE );
		do_settings_sections( self::PAGE );
		submit_button();
		echo '</form></div>';
	}
}
