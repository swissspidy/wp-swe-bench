<?php
/**
 * Settings → Contact form (option `acme_contact_settings`).
 *
 * @package Acme\Contact
 */

namespace Acme\Contact;

defined( 'ABSPATH' ) || exit;

/**
 * Settings page.
 */
class Settings {

	const OPTION = 'acme_contact_settings';
	const PAGE   = 'acme-contact';

	/**
	 * Hooks.
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'register_setting' ) );
	}

	/**
	 * Menu entry.
	 */
	public function menu() {
		add_options_page( __( 'Contact form', 'acme-contact' ), __( 'Contact form', 'acme-contact' ), 'manage_options', self::PAGE, array( $this, 'render' ) );
	}

	/**
	 * Register the option.
	 */
	public function register_setting() {
		register_setting(
			self::PAGE,
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
			)
		);
		add_settings_section( 'acme_contact_main', '', '__return_false', self::PAGE );
		add_settings_field( 'recipient', __( 'Send messages to', 'acme-contact' ), array( $this, 'field_recipient' ), self::PAGE, 'acme_contact_main', array( 'label_for' => 'acme-contact-recipient' ) );
		add_settings_field( 'success_message', __( 'Success message', 'acme-contact' ), array( $this, 'field_success' ), self::PAGE, 'acme_contact_main', array( 'label_for' => 'acme-contact-success' ) );
	}

	/**
	 * Sanitize.
	 *
	 * @param mixed $input Input.
	 * @return array
	 */
	public function sanitize( $input ) {
		$input     = is_array( $input ) ? $input : array();
		$recipient = isset( $input['recipient'] ) ? sanitize_email( $input['recipient'] ) : '';
		return array(
			'recipient'       => is_email( $recipient ) ? $recipient : '',
			'success_message' => isset( $input['success_message'] ) ? sanitize_text_field( $input['success_message'] ) : '',
		);
	}

	/**
	 * Recipient field.
	 */
	public function field_recipient() {
		$saved = get_option( self::OPTION, array() );
		printf(
			'<input type="email" class="regular-text" id="acme-contact-recipient" name="%1$s[recipient]" value="%2$s" placeholder="%3$s" />',
			esc_attr( self::OPTION ),
			esc_attr( isset( $saved['recipient'] ) ? $saved['recipient'] : '' ),
			esc_attr( get_option( 'admin_email' ) )
		);
	}

	/**
	 * Success message field.
	 */
	public function field_success() {
		$saved = get_option( self::OPTION, array() );
		printf(
			'<input type="text" class="large-text" id="acme-contact-success" name="%1$s[success_message]" value="%2$s" />',
			esc_attr( self::OPTION ),
			esc_attr( isset( $saved['success_message'] ) ? $saved['success_message'] : '' )
		);
	}

	/**
	 * Render the page.
	 */
	public function render() {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Contact form', 'acme-contact' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( self::PAGE );
				do_settings_sections( self::PAGE );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
