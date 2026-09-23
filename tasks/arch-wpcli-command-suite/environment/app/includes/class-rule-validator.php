<?php
/**
 * Validation and sanitization of rule input.
 *
 * @package Acme\Redirects
 */

namespace Acme\Redirects;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Validates rule data coming from the admin form (and anything else that creates rules).
 */
class Rule_Validator {

	/**
	 * Repository (duplicate checks).
	 *
	 * @var Rule_Repository
	 */
	private $rules;

	/**
	 * Constructor.
	 *
	 * @param Rule_Repository $rules Repository.
	 */
	public function __construct( Rule_Repository $rules ) {
		$this->rules = $rules;
	}

	/**
	 * Validates and sanitizes rule input.
	 *
	 * Missing keys get their defaults (match_type exact, status 301, priority 10, enabled).
	 *
	 * @param array $input Raw input (source, target, match_type, status, priority, enabled, note).
	 * @param array $args  {
	 *     @type int  $id              ID of the rule being edited (0 for a new rule).
	 *     @type bool $check_duplicate Whether to reject a source that another rule already uses.
	 * }
	 * @return array|WP_Error Sanitized data or all validation errors.
	 */
	public function validate( array $input, array $args = array() ) {
		$args   = wp_parse_args(
			$args,
			array(
				'id'              => 0,
				'check_duplicate' => true,
			)
		);
		$errors = new WP_Error();

		$match_type = isset( $input['match_type'] ) && '' !== $input['match_type'] ? strtolower( trim( (string) $input['match_type'] ) ) : 'exact';
		if ( ! array_key_exists( $match_type, match_types() ) ) {
			$errors->add(
				'acme_redirects_invalid_match_type',
				/* translators: %s: match type */
				sprintf( __( 'Unknown match type "%s". Use exact, prefix or regex.', 'acme-redirects' ), $match_type )
			);
		}

		$source = trim( (string) ( $input['source'] ?? '' ) );
		if ( '' === $source ) {
			$errors->add( 'acme_redirects_invalid_source', __( 'The source is required.', 'acme-redirects' ) );
		} elseif ( 'regex' === $match_type ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- we only want to know whether it compiles.
			if ( false === @preg_match( regex_for( $source ), '' ) ) {
				$errors->add( 'acme_redirects_invalid_source', __( 'The source is not a valid regular expression.', 'acme-redirects' ) );
			}
		} elseif ( preg_match( '#^[a-z][a-z0-9+.-]*://#i', $source ) ) {
			$errors->add( 'acme_redirects_invalid_source', __( 'The source must be a path on this site (e.g. /old-page/), not a full URL.', 'acme-redirects' ) );
		} elseif ( '/' !== $source[0] ) {
			$errors->add( 'acme_redirects_invalid_source', __( 'The source must start with a slash.', 'acme-redirects' ) );
		} elseif ( preg_match( '/\s/', $source ) ) {
			$errors->add( 'acme_redirects_invalid_source', __( 'The source must not contain spaces.', 'acme-redirects' ) );
		}

		$status = isset( $input['status'] ) && '' !== $input['status'] ? $input['status'] : 301;
		if ( ! is_numeric( $status ) || ! array_key_exists( (int) $status, status_codes() ) ) {
			$errors->add(
				'acme_redirects_invalid_status',
				/* translators: %s: status code */
				sprintf( __( 'Unsupported status code "%s". Use 301, 302, 307, 308 or 410.', 'acme-redirects' ), is_scalar( $status ) ? $status : '' )
			);
		}
		$status = (int) $status;

		$target = trim( (string) ( $input['target'] ?? '' ) );
		if ( 410 === $status ) {
			$target = '';
		} elseif ( '' === $target ) {
			$errors->add( 'acme_redirects_invalid_target', __( 'The target is required (except for 410 rules).', 'acme-redirects' ) );
		} elseif ( '/' === $target[0] && ( ! isset( $target[1] ) || '/' !== $target[1] ) ) {
			$target = sanitize_url( $target );
		} elseif ( preg_match( '#^https?://[^/\s]+#i', $target ) ) {
			$target = sanitize_url( $target, array( 'http', 'https' ) );
			if ( '' === $target ) {
				$errors->add( 'acme_redirects_invalid_target', __( 'The target is not a valid URL.', 'acme-redirects' ) );
			}
		} else {
			$errors->add( 'acme_redirects_invalid_target', __( 'The target must be a path starting with a slash or an http(s) URL.', 'acme-redirects' ) );
		}

		if ( 'exact' === $match_type && '' !== $target && '/' === $target[0] && '' !== $source
			&& normalize_path( wp_parse_url( $target, PHP_URL_PATH ) ) === normalize_path( wp_parse_url( $source, PHP_URL_PATH ) ) ) {
			$errors->add( 'acme_redirects_loop', __( 'The rule would redirect to itself.', 'acme-redirects' ) );
		}

		$priority = isset( $input['priority'] ) && '' !== $input['priority'] ? $input['priority'] : Rule::DEFAULT_PRIORITY;
		if ( ! is_numeric( $priority ) || (string) (int) $priority !== (string) $priority || (int) $priority < 0 || (int) $priority > 100 ) {
			$errors->add( 'acme_redirects_invalid_priority', __( 'The priority must be a whole number between 0 and 100.', 'acme-redirects' ) );
		}

		$enabled = $input['enabled'] ?? true;
		if ( is_string( $enabled ) ) {
			$flag = strtolower( trim( $enabled ) );
			if ( in_array( $flag, array( '1', 'yes', 'true', 'on', '' ), true ) ) {
				$enabled = true;
			} elseif ( in_array( $flag, array( '0', 'no', 'false', 'off' ), true ) ) {
				$enabled = false;
			} else {
				$errors->add( 'acme_redirects_invalid_enabled', __( 'Enabled must be yes or no.', 'acme-redirects' ) );
			}
		}

		$note = sanitize_text_field( (string) ( $input['note'] ?? '' ) );
		if ( strlen( $note ) > 255 ) {
			$note = substr( $note, 0, 255 );
		}

		if ( ! $errors->has_errors() && $args['check_duplicate'] ) {
			$existing = $this->rules->find_by_source( $source, $match_type );
			if ( $existing && (int) $existing->id !== (int) $args['id'] ) {
				$errors->add(
					'acme_redirects_duplicate',
					/* translators: %d: rule ID */
					sprintf( __( 'Another rule (#%d) already uses this source.', 'acme-redirects' ), $existing->id ),
					array( 'existing' => $existing->id )
				);
			}
		}

		/**
		 * Filters the validation result of a rule.
		 *
		 * @param WP_Error $errors Errors (add to it to reject the rule).
		 * @param array    $input  Raw input.
		 * @param array    $args   Validation args.
		 */
		$errors = apply_filters( 'acme_redirects_validate_rule', $errors, $input, $args );

		if ( $errors->has_errors() ) {
			return $errors;
		}

		return array(
			'id'         => (int) $args['id'],
			'source'     => $source,
			'target'     => $target,
			'match_type' => $match_type,
			'status'     => $status,
			'priority'   => (int) $priority,
			'enabled'    => (bool) $enabled,
			'note'       => $note,
		);
	}
}
