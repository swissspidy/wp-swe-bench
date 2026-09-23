<?php
/**
 * Website contact form: [acme_crm_form].
 *
 * @package Acme\CRM
 */

namespace Acme\CRM;

defined( 'ABSPATH' ) || exit;

/**
 * Creates (or updates) a lead from the website contact form and stores the message as a note.
 */
class Contact_Form {

	const ACTION = 'acme_crm_form';

	/**
	 * Register the shortcode and handlers.
	 */
	public static function register() {
		add_shortcode( 'acme_crm_form', array( __CLASS__, 'render' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION, array( __CLASS__, 'handle' ) );
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle' ) );
	}

	/**
	 * Render the form.
	 *
	 * @return string
	 */
	public static function render() {
		$status = '';
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['acme_crm_sent'] ) ) {
			$status = '<p class="acme-crm-form__sent">' . esc_html__( 'Thanks! We will get back to you shortly.', 'acme-crm' ) . '</p>';
		} elseif ( isset( $_GET['acme_crm_error'] ) ) {
			$status = '<p class="acme-crm-form__error">' . esc_html__( 'Sorry, your message could not be sent. Please try again.', 'acme-crm' ) . '</p>';
		}
		// phpcs:enable
		ob_start();
		?>
		<form class="acme-crm-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php echo $status; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />
			<?php wp_nonce_field( self::ACTION, '_acme_crm_nonce' ); ?>
			<p><label><?php esc_html_e( 'Your name', 'acme-crm' ); ?> <input type="text" name="name" required /></label></p>
			<p><label><?php esc_html_e( 'Email', 'acme-crm' ); ?> <input type="email" name="email" required /></label></p>
			<p><label><?php esc_html_e( 'Company', 'acme-crm' ); ?> <input type="text" name="company" /></label></p>
			<p><label><?php esc_html_e( 'Message', 'acme-crm' ); ?> <textarea name="message"></textarea></label></p>
			<p><button type="submit"><?php esc_html_e( 'Send', 'acme-crm' ); ?></button></p>
		</form>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Handle a submission.
	 */
	public static function handle() {
		$nonce = isset( $_POST['_acme_crm_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_acme_crm_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, self::ACTION ) ) {
			wp_die( esc_html__( 'The form has expired. Please reload the page.', 'acme-crm' ), 403 );
		}
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized by the repository.
		$name    = isset( $_POST['name'] ) ? wp_unslash( $_POST['name'] ) : '';
		$email   = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$company = isset( $_POST['company'] ) ? wp_unslash( $_POST['company'] ) : '';
		$message = isset( $_POST['message'] ) ? wp_unslash( $_POST['message'] ) : '';
		// phpcs:enable

		$back = wp_get_referer() ? wp_get_referer() : home_url( '/' );
		if ( ! is_email( $email ) ) {
			wp_safe_redirect( add_query_arg( 'acme_crm_error', 1, $back ) );
			exit;
		}

		$existing = Contacts::find_by_email( $email );
		if ( $existing ) {
			$id     = (int) $existing->id;
			$result = true;
		} else {
			$settings = get_option( Installer::SETTINGS_OPTION, array() );
			$result   = Contacts::create(
				array(
					'full_name' => $name,
					'email'     => $email,
					'company'   => $company,
					'stage'     => isset( $settings['form_stage'] ) ? $settings['form_stage'] : 'lead',
					'owner_id'  => 0,
					'source'    => 'website',
				)
			);
			$id       = is_wp_error( $result ) ? 0 : (int) $result;
		}

		if ( is_wp_error( $result ) || ! $id ) {
			wp_safe_redirect( add_query_arg( 'acme_crm_error', 1, $back ) );
			exit;
		}
		if ( '' !== trim( $message ) ) {
			Notes::add( $id, $message );
		}

		$settings = get_option( Installer::SETTINGS_OPTION, array() );
		if ( ! empty( $settings['notify_email'] ) ) {
			/* translators: %s: sender email */
			wp_mail( $settings['notify_email'], sprintf( __( 'New enquiry from %s', 'acme-crm' ), $email ), $message );
		}

		wp_safe_redirect( add_query_arg( 'acme_crm_sent', 1, $back ) );
		exit;
	}
}
