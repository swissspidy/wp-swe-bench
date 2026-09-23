<?php
/**
 * Handles form posts to admin-post.php (action `acme_contact_submit`).
 *
 * @package Acme\Contact
 */

namespace Acme\Contact;

defined( 'ABSPATH' ) || exit;

/**
 * Submission handler for the shortcode form.
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
		return remove_query_arg( array( 'acme_contact', 'acme_state' ), $url );
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
	 * Handle a submission.
	 */
	public function handle() {
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
