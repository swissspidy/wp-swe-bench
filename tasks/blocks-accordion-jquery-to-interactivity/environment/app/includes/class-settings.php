<?php
/**
 * Settings → FAQ.
 *
 * @package Acme\Faq
 */

namespace Acme\Faq;

defined( 'ABSPATH' ) || exit;

/**
 * Settings screen.
 */
class Settings {

	const PAGE   = 'acme-faq';
	const OPTION = 'acme_faq_options';

	/**
	 * Menu entry.
	 */
	public function add_page() {
		add_options_page( __( 'FAQ', 'acme-faq' ), __( 'FAQ', 'acme-faq' ), 'manage_options', self::PAGE, array( $this, 'render_page' ) );
	}

	/**
	 * Setting + field.
	 */
	public function register() {
		register_setting(
			self::PAGE,
			self::OPTION,
			array(
				'type'              => 'object',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => array( 'structured_data' => true ),
			)
		);
		add_settings_section( 'acme_faq_main', '', '__return_false', self::PAGE );
		add_settings_field( 'structured_data', __( 'Structured data', 'acme-faq' ), array( $this, 'field' ), self::PAGE, 'acme_faq_main' );
	}

	/**
	 * Sanitize.
	 *
	 * @param mixed $value Raw.
	 * @return array
	 */
	public function sanitize( $value ) {
		return array( 'structured_data' => is_array( $value ) && ! empty( $value['structured_data'] ) );
	}

	/**
	 * Checkbox.
	 */
	public function field() {
		$options = get_options();
		printf(
			'<label><input type="checkbox" name="%1$s[structured_data]" value="1" %2$s /> %3$s</label>',
			esc_attr( self::OPTION ),
			checked( ! empty( $options['structured_data'] ), true, false ),
			esc_html__( 'Add FAQPage structured data (JSON-LD) to posts with FAQ blocks', 'acme-faq' )
		);
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
