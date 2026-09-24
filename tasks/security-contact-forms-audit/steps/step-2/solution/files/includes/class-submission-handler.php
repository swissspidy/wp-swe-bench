<?php
/**
 * Handles public form submissions.
 *
 * @package Acme\Forms
 */

namespace Acme\Forms;

defined( 'ABSPATH' ) || exit;

/**
 * Validates and stores a submission, stores uploaded files (see Uploads for what a file field
 * accepts) and redirects back to the form.
 *
 * Forms are posted to `admin-post.php` with `action=acme_forms_submit`. Pages with forms are
 * served from the page cache, so the public form deliberately carries no per-user token; the
 * honeypot field keeps most bots out.
 */
class Submission_Handler {

	/**
	 * Maximum stored length of one value.
	 */
	const MAX_LENGTH = 5000;

	/**
	 * Hooks.
	 */
	public function register_hooks() {
		add_action( 'admin_post_nopriv_acme_forms_submit', array( $this, 'handle' ) );
		add_action( 'admin_post_acme_forms_submit', array( $this, 'handle' ) );
	}

	/**
	 * Handle a POSTed form.
	 */
	public function handle() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- public form, see class docblock.
		$form_id = isset( $_POST['acme_form_id'] ) ? absint( $_POST['acme_form_id'] ) : 0;
		$form    = get_post( $form_id );
		$return  = isset( $_POST['acme_return'] ) ? esc_url_raw( wp_unslash( $_POST['acme_return'] ) ) : '';
		$return  = wp_validate_redirect( $return, home_url( '/' ) );

		if ( ! $form || Forms::POST_TYPE !== $form->post_type || 'publish' !== $form->post_status ) {
			wp_die( esc_html__( 'This form does not exist.', 'acme-forms' ), '', array( 'response' => 404 ) );
		}

		// Honeypot: pretend everything went fine.
		if ( ! empty( $_POST['acme_hp'] ) ) {
			$this->redirect( $return, array( 'acme_form' => 'sent' ) );
		}

		$fields = Forms::get_fields( $form_id );
		$input  = isset( $_POST['acme_fields'] ) && is_array( $_POST['acme_fields'] ) ? wp_unslash( $_POST['acme_fields'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated per field below.
		// phpcs:enable

		$data   = array();
		$errors = array();
		foreach ( $fields as $field ) {
			if ( 'file' === $field['type'] ) {
				continue;
			}
			$value = isset( $input[ $field['name'] ] ) ? $this->clean( $input[ $field['name'] ] ) : '';
			$error = $this->validate( $field, $value );
			if ( $error ) {
				$errors[] = $field['name'];
				continue;
			}
			$data[ $field['name'] ] = $value;
		}

		$uploads = array();
		foreach ( $fields as $field ) {
			if ( 'file' !== $field['type'] ) {
				continue;
			}
			$upload = $this->check_upload( $field );
			if ( is_wp_error( $upload ) ) {
				$errors[] = $field['name'];
			} elseif ( $upload ) {
				$uploads[ $field['name'] ] = $upload;
			}
		}

		if ( $errors ) {
			$this->redirect(
				$return,
				array(
					'acme_form'   => 'invalid',
					'acme_errors' => implode( ',', $errors ),
				)
			);
		}

		$files = array();
		foreach ( $uploads as $name => $upload ) {
			$stored = $this->store_upload( $upload );
			if ( $stored ) {
				$files[ $name ] = $stored;
			}
		}

		$id = Submissions::insert(
			array(
				'form_id' => $form_id,
				'email'   => Submissions::find_email( $fields, $data ),
				'ip'      => acme_forms_get_setting( 'store_ip' ) ? $this->client_ip() : '',
				'data'    => $data,
				'files'   => $files,
			)
		);

		if ( $id ) {
			/**
			 * Fires after a submission was stored.
			 *
			 * @param int   $id      Submission ID.
			 * @param int   $form_id Form ID.
			 * @param array $data    Field values.
			 */
			do_action( 'acme_forms_submission_created', $id, $form_id, $data );
		}

		$this->redirect( $return, array( 'acme_form' => 'sent' ) );
	}

	/**
	 * Normalize a submitted value (we keep what the visitor typed).
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	protected function clean( $value ) {
		if ( is_array( $value ) ) {
			$value = implode( ', ', array_map( 'strval', $value ) );
		}
		$value = trim( (string) $value );
		if ( function_exists( 'mb_substr' ) ) {
			$value = mb_substr( $value, 0, self::MAX_LENGTH );
		}
		return $value;
	}

	/**
	 * Validate a value.
	 *
	 * @param array  $field Field.
	 * @param string $value Value.
	 * @return string Error code or ''.
	 */
	protected function validate( array $field, $value ) {
		if ( '' === $value ) {
			return $field['required'] ? 'required' : '';
		}
		if ( 'email' === $field['type'] && ! is_email( $value ) ) {
			return 'email';
		}
		if ( 'select' === $field['type'] && ! in_array( $value, $field['options'], true ) ) {
			return 'option';
		}
		return '';
	}

	/**
	 * Check an uploaded file (upload errors, size, allowed type and matching content).
	 *
	 * @param array $field File field.
	 * @return array|null|\WP_Error The $_FILES entry, null if nothing was uploaded.
	 */
	protected function check_upload( array $field ) {
		$key = 'acme_file_' . $field['name'];
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$file = isset( $_FILES[ $key ] ) && is_array( $_FILES[ $key ] ) ? $_FILES[ $key ] : null;

		if ( ! $file || ! isset( $file['error'] ) || UPLOAD_ERR_NO_FILE === (int) $file['error'] ) {
			return $field['required'] ? new \WP_Error( 'required' ) : null;
		}
		if ( UPLOAD_ERR_OK !== (int) $file['error'] || ! is_uploaded_file( $file['tmp_name'] ) ) {
			return new \WP_Error( 'upload' );
		}
		$max = (float) acme_forms_get_setting( 'max_upload_mb', 5 ) * MB_IN_BYTES;
		if ( $max > 0 && (int) $file['size'] > $max ) {
			return new \WP_Error( 'size' );
		}
		$ext = Uploads::check( $file, $field );
		if ( is_wp_error( $ext ) ) {
			return $ext;
		}
		$file['acme_ext'] = $ext;
		return $file;
	}

	/**
	 * Store a checked upload (see Uploads::store()).
	 *
	 * @param array $file $_FILES entry, with the checked extension in `acme_ext`.
	 * @return array|null File info.
	 */
	protected function store_upload( array $file ) {
		return Uploads::store( $file, $file['acme_ext'] );
	}

	/**
	 * Visitor IP.
	 *
	 * @return string
	 */
	protected function client_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return (string) filter_var( $ip, FILTER_VALIDATE_IP );
	}

	/**
	 * Redirect back to the form and stop.
	 *
	 * @param string $url  Target.
	 * @param array  $args Query args.
	 */
	protected function redirect( $url, array $args ) {
		wp_safe_redirect( add_query_arg( $args, $url ) );
		exit;
	}
}
