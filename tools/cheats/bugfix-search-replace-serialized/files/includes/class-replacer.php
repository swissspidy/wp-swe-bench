<?php
/**
 * Replaces strings in stored values (cheat: safe unserialize, objects skipped).
 *
 * @package Acme\Migrate
 */

namespace Acme\Migrate;

defined( 'ABSPATH' ) || exit;

/**
 * String replacement that understands serialized PHP data.
 */
class Replacer {

	protected $pairs = array();
	protected $count = 0;

	public function __construct( $search, $replace ) {
		$this->pairs = array( $search => $replace );
		$json        = str_replace( '/', '\\/', $search );
		if ( $json !== $search ) {
			$this->pairs[ $json ] = str_replace( '/', '\\/', $replace );
		}
		$enc = rawurlencode( $search );
		if ( $enc !== $search ) {
			$this->pairs[ $enc ] = rawurlencode( $replace );
		}
	}

	public function get_pairs() {
		return $this->pairs;
	}

	public function get_count() {
		return $this->count;
	}

	public function run( $data ) {
		if ( is_string( $data ) && is_serialized( $data ) ) {
			// Never instantiate stored classes.
			$unserialized = @unserialize( $data, array( 'allowed_classes' => false ) ); // phpcs:ignore
			if ( false !== $unserialized || 'b:0;' === $data ) {
				return serialize( $this->walk( $unserialized ) ); // phpcs:ignore
			}
		}
		return $this->walk( $data );
	}

	protected function walk( $data ) {
		if ( is_string( $data ) ) {
			if ( is_serialized( $data ) ) {
				return $this->run( $data );
			}
			foreach ( $this->pairs as $from => $to ) {
				$data         = str_replace( $from, $to, $data, $replaced );
				$this->count += $replaced;
			}
			return $data;
		}
		if ( is_array( $data ) ) {
			foreach ( $data as $key => $value ) {
				$data[ $key ] = $this->walk( $value );
			}
			return $data;
		}
		if ( $data instanceof \stdClass ) {
			foreach ( get_object_vars( $data ) as $key => $value ) {
				$data->$key = $this->walk( $value );
			}
		}
		// Other objects (incomplete classes) can't be modified: leave them as they are.
		return $data;
	}
}
