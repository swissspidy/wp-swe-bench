<?php
/**
 * Field validation and error messages (shared wording across all forms).
 *
 * @package Acme\Contact
 */

namespace Acme\Contact;

defined( 'ABSPATH' ) || exit;

/**
 * Validation helpers.
 */
class Validator {

	const MAX_TEXT     = 200;
	const MAX_TEXTAREA = 5000;

	/**
	 * Error messages by code.
	 *
	 * @return array<string, string>
	 */
	public static function messages() {
		return array(
			'required'     => __( 'This field is required.', 'acme-contact' ),
			'email'        => __( 'Please enter a valid email address.', 'acme-contact' ),
			'choice'       => __( 'Please choose one of the options.', 'acme-contact' ),
			/* translators: %d: maximum number of characters */
			'too_long'     => __( 'Please shorten this text (maximum %d characters).', 'acme-contact' ),
			'rate_limited' => __( 'Too many submissions. Please try again later.', 'acme-contact' ),
			'expired'      => __( 'The form has expired. Please reload the page and try again.', 'acme-contact' ),
		);
	}

	/**
	 * Message for a code.
	 *
	 * @param string $code Error code.
	 * @param int    $max  For too_long.
	 * @return string
	 */
	public static function message( $code, $max = 0 ) {
		$messages = self::messages();
		if ( ! isset( $messages[ $code ] ) ) {
			return __( 'Something went wrong.', 'acme-contact' );
		}
		return 'too_long' === $code ? sprintf( $messages[ $code ], $max ) : $messages[ $code ];
	}

	/**
	 * Length in characters.
	 *
	 * @param string $value Value.
	 * @return int
	 */
	public static function length( $value ) {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $value, 'UTF-8' ) : strlen( $value );
	}

	/**
	 * Validate a text value.
	 *
	 * @param string $value    Value (unslashed).
	 * @param bool   $required Required?
	 * @param int    $max      Max length.
	 * @return string|null Error code or null.
	 */
	public static function text( $value, $required, $max = self::MAX_TEXT ) {
		if ( '' === trim( $value ) ) {
			return $required ? 'required' : null;
		}
		return self::length( $value ) > $max ? 'too_long' : null;
	}

	/**
	 * Validate an email address.
	 *
	 * @param string $value    Value.
	 * @param bool   $required Required?
	 * @return string|null Error code or null.
	 */
	public static function email( $value, $required ) {
		if ( '' === trim( $value ) ) {
			return $required ? 'required' : null;
		}
		return is_email( trim( $value ) ) ? null : 'email';
	}

	/**
	 * Validate a choice.
	 *
	 * @param string   $value    Value.
	 * @param string[] $options  Allowed values.
	 * @param bool     $required Required?
	 * @return string|null Error code or null.
	 */
	public static function choice( $value, array $options, $required ) {
		if ( '' === $value ) {
			return $required ? 'required' : null;
		}
		return in_array( $value, $options, true ) ? null : 'choice';
	}
}
