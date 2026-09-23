<?php
/**
 * Removes secrets from anything we persist (log entries, stored events, order meta).
 *
 * @package Acme\OrdersSync
 */

namespace Acme\OrdersSync;

defined( 'ABSPATH' ) || exit;

/**
 * Redactor.
 */
class Redactor {

	const MASK = '[redacted]';

	/**
	 * Keys whose values are never persisted (payload fields and header names,
	 * compared lower-case with dashes normalized to underscores).
	 */
	const SENSITIVE_KEYS = array(
		'card_number',
		'cvv',
		'password',
		'api_key',
		'secret',
		'token',
		'x_acme_signature',
		'x_acme_token',
		'authorization',
		'cookie',
		'x_wp_nonce',
		'php_auth_pw',
	);

	/**
	 * Known secret values (webhook secrets), replaced wherever they appear in strings.
	 *
	 * @var string[]
	 */
	private $secrets;

	/**
	 * Constructor.
	 *
	 * @param string[] $secrets Secret values.
	 */
	public function __construct( array $secrets = array() ) {
		$this->secrets = array_values(
			array_filter(
				array_map( 'strval', $secrets ),
				static fn( $s ) => strlen( $s ) >= 6
			)
		);
		// Longest first, so that a secret containing another one is fully masked.
		usort( $this->secrets, static fn( $a, $b ) => strlen( $b ) <=> strlen( $a ) );
	}

	/**
	 * Whether a key holds sensitive data.
	 *
	 * @param int|string $key Key.
	 */
	public static function is_sensitive_key( $key ): bool {
		if ( ! is_string( $key ) ) {
			return false;
		}
		$key = str_replace( '-', '_', strtolower( $key ) );
		return in_array( $key, self::SENSITIVE_KEYS, true );
	}

	/**
	 * Redacts a value recursively.
	 *
	 * @param mixed $value Value.
	 * @return mixed
	 */
	public function redact( $value ) {
		if ( is_array( $value ) ) {
			$out = array();
			foreach ( $value as $key => $item ) {
				$out[ $key ] = self::is_sensitive_key( $key ) ? $this->mask( $item ) : $this->redact( $item );
			}
			return $out;
		}
		if ( is_object( $value ) ) {
			return $this->redact( json_decode( (string) wp_json_encode( $value ), true ) );
		}
		if ( is_string( $value ) ) {
			return $this->scrub( $value );
		}
		return $value;
	}

	/**
	 * Masks a sensitive value (headers come as lists of values).
	 *
	 * @param mixed $value Value.
	 * @return mixed
	 */
	private function mask( $value ) {
		if ( is_array( $value ) && array_is_list( $value ) && $value && ! is_array( reset( $value ) ) ) {
			return array_fill( 0, count( $value ), self::MASK );
		}
		return self::MASK;
	}

	/**
	 * Replaces known secret values inside a string.
	 *
	 * @param string $text Text.
	 */
	public function scrub( string $text ): string {
		foreach ( $this->secrets as $secret ) {
			$text = str_replace( $secret, self::MASK, $text );
		}
		return $text;
	}
}
