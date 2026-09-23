<?php
/**
 * Handles form posts to admin-post.php (action `acme_contact_submit`).
 *
 * @package Acme\Contact
 */

namespace Acme\Contact;

defined( 'ABSPATH' ) || exit;

/**
 * Submission handler for the shortcode form and the Contact form block (without JavaScript).
 */
class Submission_Handler {

	/**
	 * Hooks.
	 */
	public function register() {
		add_action( 'admin_post_acme_contact_submit', array( $this, 'handle' ) );
		add_action( 'admin_post_nopriv_acme_contact_submit', array( $this, 'handle' ) );
	}

	/**
	 * Where to send the visitor back to.
	 *
	 * @param int $post_id Post with the form.
	 * @return string
	 */
	private function back_url( $post_id ) {
		$url = $post_id ? get_permalink( $post_id ) : '';
		if ( ! $url ) {
			$url = wp_get_referer() ? wp_get_referer() : home_url( '/' );
		}
		return remove_query_arg( array( 'acme_contact', 'acme_state', 'acme_form' ), $url );
	}

	/**
	 * Redirect back with an error state (errors + entered values, kept for 10 minutes).
	 *
	 * @param int   $post_id Post.
	 * @param array $errors  Field => error code.
	 * @param array $values  Field => entered value.
	 */
	private function fail( $post_id, array $errors, array $values ) {
		$token = strtolower( wp_generate_password( 20, false ) );
		set_transient(
			'acme_contact_state_' . $token,
			array(
				'errors' => $errors,
				'values' => $values,
			),
			10 * MINUTE_IN_SECONDS
		);
		$url = add_query_arg(
			array(
				'acme_contact' => 'error',
				'acme_state'   => $token,
			),
			$this->back_url( $post_id )
		);
		wp_safe_redirect( $url . '#acme-contact', 303 );
		exit;
	}

	/**
	 * Redirect back to the success message.
	 *
	 * @param int $post_id Post.
	 */
	private function succeed( $post_id ) {
		wp_safe_redirect( add_query_arg( 'acme_contact', 'sent', $this->back_url( $post_id ) ) . '#acme-contact', 303 );
		exit;
	}

	/**
	 * Contact form block posted without JavaScript: same processing as the REST endpoint, then
	 * back to the page (success message, or errors + entered values).
	 */
	private function handle_block() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- public form (cached pages): honeypot + rate limit instead.
		$post_id  = isset( $_POST['acme_post_id'] ) ? absint( $_POST['acme_post_id'] ) : 0;
		$form_id  = isset( $_POST['acme_form_id'] ) ? sanitize_text_field( wp_unslash( $_POST['acme_form_id'] ) ) : '';
		$raw      = isset( $_POST['acme_fields'] ) && is_array( $_POST['acme_fields'] ) ? wp_unslash( $_POST['acme_fields'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated per field.
		$honeypot = isset( $_POST['acme_website'] ) ? sanitize_text_field( wp_unslash( $_POST['acme_website'] ) ) : '';
		// phpcs:enable

		$result = Block_Submissions::process( $post_id, $form_id, $raw, $honeypot );
		if ( Block_Submissions::STATUS_NOT_FOUND === $result['status'] ) {
			wp_die( esc_html( $result['message'] ), '', array( 'response' => 404 ) );
		}

		$back   = $this->back_url( $post_id );
		$anchor = '#acme-contact-' . sanitize_html_class( $form_id );
		if ( in_array( $result['status'], array( Block_Submissions::STATUS_SENT, Block_Submissions::STATUS_SPAM ), true ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'acme_contact' => 'sent',
						'acme_form'    => rawurlencode( $form_id ),
					),
					$back
				) . $anchor,
				303
			);
			exit;
		}

		$token = strtolower( wp_generate_password( 20, false ) );
		set_transient(
			'acme_contact_state_' . $token,
			array(
				'form_id' => $form_id,
				'errors'  => $result['errors'],
				'values'  => $result['values'],
				'message' => $result['message'],
			),
			10 * MINUTE_IN_SECONDS
		);
		wp_safe_redirect(
			add_query_arg(
				array(
					'acme_contact' => 'error',
					'acme_state'   => $token,
				),
				$back
			) . $anchor,
			303
		);
		exit;
	}

	/**
	 * Handle a submission.
	 */
	public function handle() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- block forms are public, see handle_block().
		if ( isset( $_POST['acme_form_id'] ) ) {
			$this->handle_block();
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified below.
		$post_id = isset( $_POST['acme_post_id'] ) ? absint( $_POST['acme_post_id'] ) : 0;
		$values  = array(
			'name'    => isset( $_POST['acme_name'] ) ? sanitize_text_field( wp_unslash( $_POST['acme_name'] ) ) : '',
			'email'   => isset( $_POST['acme_email'] ) ? sanitize_text_field( wp_unslash( $_POST['acme_email'] ) ) : '',
			'subject' => isset( $_POST['acme_subject'] ) ? sanitize_text_field( wp_unslash( $_POST['acme_subject'] ) ) : '',
			'message' => isset( $_POST['acme_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['acme_message'] ) ) : '',
		);

		if ( ! isset( $_POST['_acme_contact_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_acme_contact_nonce'] ) ), 'acme_contact_submit' ) ) {
			$this->fail( $post_id, array( 'form' => 'expired' ), $values );
		}

		// Honeypot: bots fill every field. Pretend everything went fine.
		if ( ! empty( $_POST['acme_website'] ) ) {
			$this->succeed( $post_id );
		}
		// phpcs:enable

		$ip = acme_contact_client_ip();
		if ( Rate_Limiter::is_limited( $ip ) ) {
			$this->fail( $post_id, array( 'form' => 'rate_limited' ), $values );
		}

		$atts = Shortcode::find_in_post( $post_id );
		if ( null === $atts || 'publish' !== get_post_status( $post_id ) ) {
			wp_die( esc_html__( 'This form does not exist.', 'acme-contact' ), '', array( 'response' => 404 ) );
		}
		$subjects = acme_contact_parse_subjects( $atts['subjects'] );

		$errors = array_filter(
			array(
				'name'    => Validator::text( $values['name'], true ),
				'email'   => Validator::email( $values['email'], true ),
				'subject' => $subjects ? Validator::choice( $values['subject'], $subjects, true ) : null,
				'message' => Validator::text( $values['message'], true, Validator::MAX_TEXTAREA ),
			)
		);
		if ( $errors ) {
			$this->fail( $post_id, $errors, $values );
		}

		Rate_Limiter::hit( $ip );

		$fields = array(
			array(
				'label' => __( 'Name', 'acme-contact' ),
				'value' => $values['name'],
			),
			array(
				'label' => __( 'Email', 'acme-contact' ),
				'value' => $values['email'],
			),
		);
		if ( $subjects ) {
			$fields[] = array(
				'label' => __( 'Subject', 'acme-contact' ),
				'value' => $values['subject'],
			);
		}
		$fields[] = array(
			'label' => __( 'Message', 'acme-contact' ),
			'value' => $values['message'],
		);

		Mailer::send(
			array(
				'fields'     => $fields,
				'reply_to'   => $values['email'],
				'reply_name' => $values['name'],
				'subject'    => $values['subject'],
				'post_id'    => $post_id,
			)
		);

		/**
		 * Fires after a contact form was submitted successfully.
		 *
		 * @param array $values  Submitted values.
		 * @param int   $post_id Post with the form.
		 */
		do_action( 'acme_contact_submitted', $values, $post_id );

		$this->succeed( $post_id );
	}
}
