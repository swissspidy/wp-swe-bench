<?php
/**
 * Settings → Catalog.
 *
 * @package Acme\Catalog
 */

namespace Acme\Catalog;

defined( 'ABSPATH' ) || exit;

/**
 * Currency settings.
 */
class Settings {

	const PAGE   = 'acme-catalog';
	const OPTION = 'acme_catalog_options';

	/**
	 * Menu entry.
	 */
	public function add_page() {
		add_options_page( __( 'Catalog', 'acme-catalog' ), __( 'Catalog', 'acme-catalog' ), 'manage_options', self::PAGE, array( $this, 'render_page' ) );
	}

	/**
	 * Setting and fields.
	 */
	public function register() {
		register_setting(
			self::PAGE,
			self::OPTION,
			array(
				'type'              => 'object',
				'sanitize_callback' => array( $this, 'sanitize' ),
			)
		);
		add_settings_section( 'acme_catalog_main', '', '__return_false', self::PAGE );
		add_settings_field( 'currency', __( 'Currency symbol', 'acme-catalog' ), array( $this, 'field_currency' ), self::PAGE, 'acme_catalog_main', array( 'label_for' => 'acme-catalog-currency' ) );
		add_settings_field( 'currency_position', __( 'Currency position', 'acme-catalog' ), array( $this, 'field_position' ), self::PAGE, 'acme_catalog_main', array( 'label_for' => 'acme-catalog-position' ) );
	}

	/**
	 * Sanitize.
	 *
	 * @param mixed $value Raw.
	 * @return array
	 */
	public function sanitize( $value ) {
		$value = is_array( $value ) ? $value : array();
		return array(
			'currency'          => mb_substr( sanitize_text_field( $value['currency'] ?? '$' ), 0, 5 ),
			'currency_position' => ( $value['currency_position'] ?? '' ) === 'after' ? 'after' : 'before',
		);
	}

	/**
	 * Currency field.
	 */
	public function field_currency() {
		$options = get_options();
		printf( '<input id="acme-catalog-currency" name="%s[currency]" value="%s" class="small-text" />', esc_attr( self::OPTION ), esc_attr( $options['currency'] ) );
	}

	/**
	 * Position field.
	 */
	public function field_position() {
		$options = get_options();
		printf( '<select id="acme-catalog-position" name="%s[currency_position]">', esc_attr( self::OPTION ) );
		foreach ( array( 'before' => __( 'Before the amount', 'acme-catalog' ), 'after' => __( 'After the amount', 'acme-catalog' ) ) as $value => $label ) {
			printf( '<option value="%s" %s>%s</option>', esc_attr( $value ), selected( $options['currency_position'], $value, false ), esc_html( $label ) );
		}
		echo '</select>';
	}

	/**
	 * Page.
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
