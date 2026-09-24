<?php
/**
 * Settings screen.
 *
 * @package Acme\Forms
 */

namespace Acme\Forms\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Acme Forms → Settings. Values are stored in the `acme_forms_settings` option (an array; see
 * acme_forms_default_settings() for the keys). Add-ons read that option directly.
 */
class Settings_Page {

	const SLUG   = 'acme-forms-settings';
	const OPTION = 'acme_forms_settings';

	/**
	 * Hooks.
	 */
	public function register_hooks() {
		add_action( 'admin_post_acme_forms_save_settings', array( $this, 'save' ) );
	}

	/**
	 * Render the screen.
	 */
	public function render() {
		$settings = acme_forms_get_settings();
		echo '<div class="wrap acme-forms-settings">';
		echo '<h1>' . esc_html__( 'Acme Forms Settings', 'acme-forms' ) . '</h1>';

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['updated'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'acme-forms' ) . '</p></div>';
		}

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="acme_forms_save_settings" />';
		echo '<table class="form-table" role="presentation"><tbody>';

		$this->row(
			'notify_email',
			__( 'Send notifications to', 'acme-forms' ),
			'<input type="email" class="regular-text" id="acme-notify_email" name="' . esc_attr( self::OPTION ) . '[notify_email]" value="' . esc_attr( $settings['notify_email'] ) . '" placeholder="' . esc_attr( get_option( 'admin_email' ) ) . '" />',
			__( 'Leave empty to use the site admin e-mail address.', 'acme-forms' )
		);
		$this->row(
			'subject_prefix',
			__( 'E-mail subject prefix', 'acme-forms' ),
			'<input type="text" class="regular-text" id="acme-subject_prefix" name="' . esc_attr( self::OPTION ) . '[subject_prefix]" value="' . esc_attr( $settings['subject_prefix'] ) . '" />'
		);
		$this->row(
			'store_ip',
			__( 'Visitor IP addresses', 'acme-forms' ),
			'<label><input type="checkbox" id="acme-store_ip" name="' . esc_attr( self::OPTION ) . '[store_ip]" value="1" ' . checked( ! empty( $settings['store_ip'] ), true, false ) . ' /> ' . esc_html__( 'Store the IP address with each submission', 'acme-forms' ) . '</label>'
		);
		$this->row(
			'max_upload_mb',
			__( 'Maximum upload size (MB)', 'acme-forms' ),
			'<input type="number" min="1" max="64" class="small-text" id="acme-max_upload_mb" name="' . esc_attr( self::OPTION ) . '[max_upload_mb]" value="' . esc_attr( $settings['max_upload_mb'] ) . '" />'
		);
		$this->row(
			'per_page',
			__( 'Submissions per page', 'acme-forms' ),
			'<input type="number" min="5" max="200" class="small-text" id="acme-per_page" name="' . esc_attr( self::OPTION ) . '[per_page]" value="' . esc_attr( $settings['per_page'] ) . '" />'
		);

		echo '</tbody></table>';
		submit_button();
		echo '</form></div>';
	}

	/**
	 * One settings row.
	 *
	 * @param string $key         Key.
	 * @param string $label       Label.
	 * @param string $control     Control HTML (escaped by the caller).
	 * @param string $description Help text.
	 */
	protected function row( $key, $label, $control, $description = '' ) {
		echo '<tr><th scope="row"><label for="acme-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td>';
		echo $control; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		if ( $description ) {
			echo '<p class="description">' . esc_html( $description ) . '</p>';
		}
		echo '</td></tr>';
	}

	/**
	 * Sanitize submitted settings.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$input    = is_array( $input ) ? $input : array();
		$defaults = acme_forms_default_settings();
		$email    = isset( $input['notify_email'] ) ? sanitize_email( $input['notify_email'] ) : '';

		return array(
			'notify_email'   => is_email( $email ) ? $email : '',
			'subject_prefix' => isset( $input['subject_prefix'] ) ? sanitize_text_field( $input['subject_prefix'] ) : $defaults['subject_prefix'],
			'store_ip'       => empty( $input['store_ip'] ) ? 0 : 1,
			'max_upload_mb'  => isset( $input['max_upload_mb'] ) ? min( 64, max( 1, absint( $input['max_upload_mb'] ) ) ) : $defaults['max_upload_mb'],
			'per_page'       => isset( $input['per_page'] ) ? min( 200, max( 5, absint( $input['per_page'] ) ) ) : $defaults['per_page'],
		);
	}

	/**
	 * Save handler (admin-post.php?action=acme_forms_save_settings).
	 */
	public function save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to manage these settings.', 'acme-forms' ), '', array( 'response' => 403 ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized in sanitize().
		$input = isset( $_POST[ self::OPTION ] ) ? wp_unslash( $_POST[ self::OPTION ] ) : array();
		update_option( self::OPTION, self::sanitize( $input ) );

		/**
		 * Fires after the settings were saved.
		 *
		 * @param array $settings New settings.
		 */
		do_action( 'acme_forms_settings_saved', acme_forms_get_settings() );

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG . '&updated=1' ) );
		exit;
	}
}
