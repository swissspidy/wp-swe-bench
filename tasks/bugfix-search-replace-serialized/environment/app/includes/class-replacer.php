<?php
/**
 * Replaces strings in stored values.
 *
 * @package Acme\Migrate
 */

namespace Acme\Migrate;

defined( 'ABSPATH' ) || exit;

/**
 * String replacement that understands serialized PHP data.
 *
 * Serialized values are unserialized, the replacement is made in every string inside, and the
 * value is serialized again, so that the string lengths stay correct.
 */
class Replacer {

	/**
	 * Search string.
	 *
	 * @var string
	 */
	protected $search;

	/**
	 * Replacement.
	 *
	 * @var string
	 */
	protected $replace;

	/**
	 * Replacements made so far.
	 *
	 * @var int
	 */
	protected $count = 0;

	/**
	 * Constructor.
	 *
	 * @param string $search  Search string.
	 * @param string $replace Replacement.
	 */
	public function __construct( $search, $replace ) {
		$this->search  = $search;
		$this->replace = $replace;
	}

	/**
	 * Number of replacements made by this instance.
	 *
	 * @return int
	 */
	public function get_count() {
		return $this->count;
	}

	/**
	 * Replace in a stored value.
	 *
	 * @param mixed $data Stored value.
	 * @return mixed
	 */
	public function run( $data ) {
		if ( is_string( $data ) && is_serialized( $data ) ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize
			$unserialized = @unserialize( $data );
			if ( false !== $unserialized || 'b:0;' === $data ) {
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
				return serialize( $this->walk( $unserialized ) );
			}
		}

		return $this->walk( $data );
	}

	/**
	 * Replace recursively.
	 *
	 * @param mixed $data Value.
	 * @return mixed
	 */
	protected function walk( $data ) {
		if ( is_string( $data ) ) {
			return $this->replace_in_string( $data );
		}

		if ( is_array( $data ) ) {
			foreach ( $data as $key => $value ) {
				$data[ $key ] = $this->walk( $value );
			}
			return $data;
		}

		if ( is_object( $data ) ) {
			foreach ( get_object_vars( $data ) as $key => $value ) {
				$data->$key = $this->walk( $value );
			}
			return $data;
		}

		return $data;
	}

	/**
	 * Replace in a plain string.
	 *
	 * @param string $subject String.
	 * @return string
	 */
	protected function replace_in_string( $subject ) {
		$result       = str_replace( $this->search, $this->replace, $subject, $replaced );
		$this->count += $replaced;
		return $result;
	}
}
