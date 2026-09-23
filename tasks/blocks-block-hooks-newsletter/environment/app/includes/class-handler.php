<?php
/**
 * Form submission handler.
 *
 * @package Acme\Newsletter
 */

namespace Acme\Newsletter;

defined( 'ABSPATH' ) || exit;

/**
 * Handles POSTs to admin-post.php?action=acme_newsletter_subscribe.
 */
class Handler {

	/**
	 * Register hooks.
	 */
	public function register() {
		add_action( 'admin_post_nopriv_' . Form::ACTION, array( $this, 'handle' ) );
		add_action( 'admin_post_' . Form::ACTION, array( $this, 'handle' ) );
	}

	/**
	 * Handle a submission and redirect back to the page the form was on.
	 */
	public function handle() {
		$status = $this->process();
		$this->redirect( $status );
	}

	/**
	 * Validate + store. Returns a status slug for the redirect.
	 *
	 * @return string
	 */
	protected function process() {
		$nonce = isset( $_POST[ Form::NONCE_FIELD ] ) ? sanitize_text_field( wp_unslash( $_POST[ Form::NONCE_FIELD ] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, Form::NONCE_ACTION ) ) {
			return 'error';
		}

		// Honeypot: bots fill every field.
		if ( ! empty( $_POST['acme_website'] ) ) {
			return 'subscribed';
		}

		$email   = isset( $_POST['acme_email'] ) ? sanitize_email( wp_unslash( $_POST['acme_email'] ) ) : '';
		$name    = isset( $_POST['acme_name'] ) ? sanitize_text_field( wp_unslash( $_POST['acme_name'] ) ) : '';
		$source  = isset( $_POST['acme_source'] ) ? sanitize_key( wp_unslash( $_POST['acme_source'] ) ) : '';
		$consent = ! empty( $_POST['acme_consent'] );

		if ( ! is_email( $email ) || ! $consent ) {
			return 'invalid';
		}

		/**
		 * Short-circuit a subscription (e.g. for a CRM integration). Return a WP_Error to reject.
		 *
		 * @param true|\WP_Error $allow  Whether to continue.
		 * @param string         $email  Email.
		 * @param string         $source Source slug.
		 */
		$allow = apply_filters( 'acme_newsletter_before_subscribe', true, $email, $source );
		if ( is_wp_error( $allow ) ) {
			return 'error';
		}

		$result = Subscribers::add(
			array(
				'email'  => $email,
				'name'   => $name,
				'source' => $source,
			)
		);
		if ( is_wp_error( $result ) ) {
			return in_array( $result->get_error_code(), array( 'exists', 'invalid' ), true ) ? $result->get_error_code() : 'error';
		}
		return 'subscribed';
	}

	/**
	 * Redirect back to the referring page.
	 *
	 * @param string $status Status slug.
	 */
	protected function redirect( $status ) {
		$back = wp_get_referer();
		if ( ! $back ) {
			$back = home_url( '/' );
		}
		$back = remove_query_arg( 'acme_newsletter', $back );
		wp_safe_redirect( add_query_arg( 'acme_newsletter', $status, $back ) );
		exit;
	}
}
