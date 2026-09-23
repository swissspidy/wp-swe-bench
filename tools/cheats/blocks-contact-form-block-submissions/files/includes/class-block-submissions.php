<?php
/**
 * Processing of Contact form block submissions (shared by the no-JS form post and the REST API).
 *
 * @package Acme\Contact
 */

namespace Acme\Contact;

defined( 'ABSPATH' ) || exit;

/**
 * Validates, stores and mails block form submissions.
 */
class Block_Submissions {

	const STATUS_SENT         = 'sent';
	const STATUS_SPAM         = 'spam';
	const STATUS_INVALID      = 'invalid';
	const STATUS_RATE_LIMITED = 'rate_limited';
	const STATUS_NOT_FOUND    = 'not_found';

	/**
	 * Process a submission.
	 *
	 * @param int    $post_id   Post the form is on.
	 * @param string $form_id   Form ID.
	 * @param mixed  $raw       Submitted values (field name => value), unslashed.
	 * @param string $honeypot  Value of the honeypot field.
	 * @return array{status:string, errors:array<string,string>, values:array<string,string>, message:string, form:?array, entry_id:int}
	 */
	public static function process( $post_id, $form_id, $raw, $honeypot = '' ) {
		$result = array(
			'status'   => self::STATUS_NOT_FOUND,
			'errors'   => array(),
			'values'   => array(),
			'message'  => __( 'This form does not exist.', 'acme-contact' ),
			'form'     => null,
			'entry_id' => 0,
		);

		$form = Forms::accepts_submissions( $post_id ) ? Forms::find( $post_id, $form_id ) : null;
		if ( ! $form || ! $form['fields'] ) {
			return $result;
		}
		$result['form']    = $form;
		$result['message'] = self::success_message( $form );

		// Honeypot: bots fill every field. Pretend everything went fine.
		if ( '' !== trim( (string) $honeypot ) ) {
			$result['status'] = self::STATUS_SPAM;
			return $result;
		}

		$raw = is_array( $raw ) ? $raw : array();
		list( $values, $errors ) = self::validate( $form, $raw );
		$result['values']        = $values;


		if ( $errors ) {
			$result['status']  = self::STATUS_INVALID;
			$result['errors']  = $errors;
			$result['message'] = __( 'Please correct the errors below.', 'acme-contact' );
			return $result;
		}


		$email    = '';
		$name     = '';
		$fields   = array();
		$by_name  = array();
		foreach ( $form['fields'] as $field ) {
			$value = $values[ $field['name'] ];
			if ( '' === $email && 'email' === $field['type'] ) {
				$email = $value;
			}
			if ( '' === $name && 'text' === $field['type'] ) {
				$name = $value;
			}
			$fields[]                  = array(
				'label' => $field['label'],
				'value' => $value,
			);
			$by_name[ $field['name'] ] = $value;
		}
		if ( isset( $by_name['name'] ) ) {
			$name = $by_name['name'];
		}

		$result['entry_id'] = Entries::insert( $form['post_id'], $form['form_id'], $email, $values );

		Mailer::send(
			array(
				'fields'     => $fields,
				'reply_to'   => $email,
				'reply_name' => $name,
				'subject'    => isset( $by_name['subject'] ) ? $by_name['subject'] : '',
				'post_id'    => $form['post_id'],
				'form_id'    => $form['form_id'],
				'entry_id'   => $result['entry_id'],
			)
		);

		/** This action is documented in includes/class-submission-handler.php */
		do_action( 'acme_contact_submitted', $values, $form['post_id'] );

		$result['status'] = self::STATUS_SENT;
		return $result;
	}

	/**
	 * Success message of a form (falls back to the site setting).
	 *
	 * @param array $form Form definition.
	 * @return string
	 */
	public static function success_message( array $form ) {
		return '' !== trim( $form['success_message'] ) ? $form['success_message'] : acme_contact_settings()['success_message'];
	}

	/**
	 * Validate and sanitize the submitted values against the form definition. Values for fields
	 * the form doesn't have are ignored.
	 *
	 * @param array $form Form definition.
	 * @param array $raw  Raw values.
	 * @return array{0: array<string,string>, 1: array<string,string>} Values, errors (field => message).
	 */
	public static function validate( array $form, array $raw ) {
		$values = array();
		$errors = array();
		foreach ( $form['fields'] as $field ) {
			$name  = $field['name'];
			$input = isset( $raw[ $name ] ) && is_scalar( $raw[ $name ] ) ? (string) $raw[ $name ] : '';
			$code  = null;
			$max   = 0;
			switch ( $field['type'] ) {
				case 'email':
					$value = trim( sanitize_text_field( $input ) );
					$code  = Validator::email( $value, $field['required'] );
					break;
				case 'textarea':
					$value = sanitize_textarea_field( $input );
					$max   = Validator::MAX_TEXTAREA;
					$code  = Validator::text( $value, $field['required'], $max );
					break;
				case 'select':
					$value = trim( $input );
					$code  = Validator::choice( $value, $field['options'], $field['required'] );
					if ( $code ) {
						$value = '';
					}
					break;
				case 'checkbox':
					$checked = in_array( strtolower( trim( $input ) ), array( '1', 'on', 'yes', 'true' ), true );
					$value   = $checked ? __( 'Yes', 'acme-contact' ) : __( 'No', 'acme-contact' );
					$code    = ( $field['required'] && ! $checked ) ? 'required' : null;
					break;
				default:
					$value = sanitize_text_field( $input );
					$max   = Validator::MAX_TEXT;
					$code  = Validator::text( $value, $field['required'], $max );
			}
			$values[ $name ] = $value;
			if ( $code ) {
				$errors[ $name ] = Validator::message( $code, $max );
			}
		}
		return array( $values, $errors );
	}
}
