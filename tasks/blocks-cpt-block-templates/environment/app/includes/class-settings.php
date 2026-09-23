<?php
/**
 * Settings → Courses screen.
 *
 * @package Acme\Courses
 */

namespace Acme\Courses;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin settings stored in the `acme_courses_settings` option.
 */
class Settings {

	const OPTION = 'acme_courses_settings';
	const PAGE   = 'acme-courses';

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'currency'          => 'USD',
			'currency_position' => 'before',
			'enroll_base_url'   => '',
			'show_summary'      => true,
		);
	}

	/**
	 * Supported currencies and their symbols.
	 *
	 * @return array<string, string>
	 */
	public static function currencies() {
		return array(
			'USD' => '$',
			'EUR' => '€',
			'GBP' => '£',
			'CHF' => 'CHF ',
		);
	}

	/**
	 * Returns one setting (or all of them).
	 *
	 * @param string|null $key Setting key.
	 * @return mixed
	 */
	public static function get( $key = null ) {
		$settings = get_option( self::OPTION, array() );
		$settings = wp_parse_args( is_array( $settings ) ? $settings : array(), self::defaults() );
		if ( null === $key ) {
			return $settings;
		}
		return $settings[ $key ] ?? null;
	}

	/**
	 * Hooks.
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'register_setting' ) );
	}

	/**
	 * Adds the settings page under Settings.
	 */
	public function menu() {
		add_options_page(
			__( 'Courses', 'acme-courses' ),
			__( 'Courses', 'acme-courses' ),
			'manage_options',
			self::PAGE,
			array( $this, 'render' )
		);
	}

	/**
	 * Registers the option and fields.
	 */
	public function register_setting() {
		register_setting(
			self::PAGE,
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);

		add_settings_section( 'acme_courses_main', '', '__return_false', self::PAGE );

		add_settings_field( 'currency', __( 'Currency', 'acme-courses' ), array( $this, 'field_currency' ), self::PAGE, 'acme_courses_main' );
		add_settings_field( 'enroll_base_url', __( 'Enrollment page', 'acme-courses' ), array( $this, 'field_enroll' ), self::PAGE, 'acme_courses_main' );
		add_settings_field( 'show_summary', __( 'Course summary box', 'acme-courses' ), array( $this, 'field_summary' ), self::PAGE, 'acme_courses_main' );
	}

	/**
	 * Sanitizes the settings.
	 *
	 * @param mixed $input Raw input.
	 * @return array
	 */
	public function sanitize( $input ) {
		$input    = is_array( $input ) ? $input : array();
		$defaults = self::defaults();
		$currency = isset( $input['currency'] ) ? strtoupper( sanitize_key( $input['currency'] ) ) : $defaults['currency'];

		return array(
			'currency'          => isset( self::currencies()[ $currency ] ) ? $currency : $defaults['currency'],
			'currency_position' => ( isset( $input['currency_position'] ) && 'after' === $input['currency_position'] ) ? 'after' : 'before',
			'enroll_base_url'   => isset( $input['enroll_base_url'] ) ? esc_url_raw( trim( (string) $input['enroll_base_url'] ) ) : '',
			'show_summary'      => ! empty( $input['show_summary'] ),
		);
	}

	/**
	 * Currency field.
	 */
	public function field_currency() {
		$settings = self::get();
		echo '<select name="' . esc_attr( self::OPTION ) . '[currency]">';
		foreach ( self::currencies() as $code => $symbol ) {
			printf( '<option value="%1$s" %2$s>%1$s (%3$s)</option>', esc_attr( $code ), selected( $settings['currency'], $code, false ), esc_html( trim( $symbol ) ) );
		}
		echo '</select> ';
		echo '<select name="' . esc_attr( self::OPTION ) . '[currency_position]">';
		printf( '<option value="before" %s>%s</option>', selected( $settings['currency_position'], 'before', false ), esc_html__( 'Symbol before the amount', 'acme-courses' ) );
		printf( '<option value="after" %s>%s</option>', selected( $settings['currency_position'], 'after', false ), esc_html__( 'Symbol after the amount', 'acme-courses' ) );
		echo '</select>';
	}

	/**
	 * Enrollment URL field.
	 */
	public function field_enroll() {
		printf(
			'<input type="url" class="regular-text" name="%s[enroll_base_url]" value="%s" /><p class="description">%s</p>',
			esc_attr( self::OPTION ),
			esc_attr( self::get( 'enroll_base_url' ) ),
			esc_html__( 'Courses without their own enrollment link send visitors here, with ?course=<slug> appended.', 'acme-courses' )
		);
	}

	/**
	 * Summary box toggle.
	 */
	public function field_summary() {
		printf(
			'<label><input type="checkbox" name="%s[show_summary]" value="1" %s /> %s</label>',
			esc_attr( self::OPTION ),
			checked( (bool) self::get( 'show_summary' ), true, false ),
			esc_html__( 'Show price, duration and the enroll button above the course description', 'acme-courses' )
		);
	}

	/**
	 * Renders the page.
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		echo '<div class="wrap"><h1>' . esc_html__( 'Courses', 'acme-courses' ) . '</h1><form method="post" action="options.php">';
		settings_fields( self::PAGE );
		do_settings_sections( self::PAGE );
		submit_button();
		echo '</form></div>';
	}
}
