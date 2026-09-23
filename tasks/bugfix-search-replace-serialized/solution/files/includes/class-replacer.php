<?php
/**
 * Replaces strings in stored values.
 *
 * @package Acme\Migrate
 */

namespace Acme\Migrate;

defined( 'ABSPATH' ) || exit;

/**
 * String replacement that understands serialized PHP data and encoded URLs.
 *
 * - Serialized values are rewritten token by token: every string inside (at any depth, including
 *   serialized strings stored inside serialized strings) is replaced and its length recalculated.
 *   Nothing is ever unserialized, so no object of any class is instantiated and objects of classes
 *   that are not loaded keep their class name and properties. Array keys and property names are
 *   kept as they are.
 * - Besides the search string itself, its JSON-escaped form (`http:\/\/example.com`) and its
 *   URL-encoded form (`http%3A%2F%2Fexample.com`) are replaced with the equally encoded replacement.
 * - All forms are replaced in a single pass, so a replacement that contains the search string is
 *   never replaced again.
 */
class Replacer {

	/**
	 * Search form => replacement in the same form.
	 *
	 * @var array<string, string>
	 */
	protected $pairs = array();

	/**
	 * Regex matching any search form.
	 *
	 * @var string
	 */
	protected $pattern = '';

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
		$search  = (string) $search;
		$replace = (string) $replace;

		if ( '' === $search ) {
			return;
		}

		$pairs = array( $search => $replace );

		// JSON-encoded strings (wp_json_encode() and most JS encoders) escape slashes.
		$json_search = str_replace( '/', '\\/', $search );
		if ( ! isset( $pairs[ $json_search ] ) ) {
			$pairs[ $json_search ] = str_replace( '/', '\\/', $replace );
		}

		// URL-encoded, e.g. in query strings of share links and redirects.
		$encoded_search = rawurlencode( $search );
		if ( ! isset( $pairs[ $encoded_search ] ) ) {
			$pairs[ $encoded_search ] = rawurlencode( $replace );
		}

		/**
		 * Filters the forms of the search string that are replaced.
		 *
		 * @since 1.5.0
		 *
		 * @param array<string, string> $pairs   Search form => replacement form.
		 * @param string                $search  Search string.
		 * @param string                $replace Replacement.
		 */
		$pairs = (array) apply_filters( 'acme_migrate_replacement_pairs', $pairs, $search, $replace );
		unset( $pairs[''] );

		// Longest first, so that the most specific form wins in the alternation.
		uksort(
			$pairs,
			static function ( $a, $b ) {
				return strlen( (string) $b ) - strlen( (string) $a );
			}
		);

		$this->pairs   = $pairs;
		$this->pattern = '/' . implode(
			'|',
			array_map(
				static function ( $form ) {
					return preg_quote( (string) $form, '/' );
				},
				array_keys( $pairs )
			)
		) . '/';
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
	 * Whether a raw value contains any form of the search string (cheap pre-check).
	 *
	 * @param string $value Raw value.
	 * @return bool
	 */
	public function might_match( $value ) {
		if ( ! $this->pairs || ! is_string( $value ) || '' === $value ) {
			return false;
		}
		foreach ( $this->pairs as $form => $unused ) {
			if ( false !== strpos( $value, (string) $form ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Replace in a stored value.
	 *
	 * Strings are treated as stored database values (possibly serialized). Arrays are walked
	 * recursively for backwards compatibility; any other type is returned unchanged.
	 *
	 * @param mixed $data Stored value.
	 * @return mixed
	 */
	public function run( $data ) {
		if ( is_string( $data ) ) {
			return $this->replace_value( $data );
		}

		if ( is_array( $data ) ) {
			foreach ( $data as $key => $value ) {
				$data[ $key ] = $this->run( $value );
			}
		}

		return $data;
	}

	/**
	 * Replace in a string that may hold serialized data.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	protected function replace_value( $value ) {
		if ( ! $this->might_match( $value ) ) {
			return $value;
		}

		if ( is_serialized( $value ) ) {
			$before    = $this->count;
			$rewritten = $this->rewrite_serialized( $value );
			if ( null !== $rewritten ) {
				return $rewritten;
			}
			// Broken serialized data: leave it alone rather than corrupting it further.
			$this->count = $before;
			return $value;
		}

		return $this->replace_plain( $value );
	}

	/**
	 * Replace in a plain string, all forms in one pass.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	protected function replace_plain( $value ) {
		$pairs  = $this->pairs;
		$result = preg_replace_callback(
			$this->pattern,
			static function ( $matches ) use ( $pairs ) {
				return $pairs[ $matches[0] ];
			},
			$value,
			-1,
			$replaced
		);

		if ( null === $result ) {
			return $value;
		}

		$this->count += $replaced;
		return $result;
	}

	/**
	 * Rewrite a serialized value.
	 *
	 * @param string $serialized Serialized data.
	 * @return string|null Rewritten data, or null if the data is not valid serialized data.
	 */
	protected function rewrite_serialized( $serialized ) {
		// Keep surrounding whitespace (is_serialized() tolerates it) exactly as it is.
		$data     = trim( $serialized );
		$position = strpos( $serialized, $data );
		$leading  = (string) substr( $serialized, 0, (int) $position );
		$trailing = (string) substr( $serialized, (int) $position + strlen( $data ) );
		$offset   = 0;
		try {
			$out = $this->rewrite_token( $data, $offset, true );
		} catch ( \UnexpectedValueException $e ) {
			return null;
		}
		if ( strlen( $data ) !== $offset ) {
			return null;
		}
		return $leading . $out . $trailing;
	}

	/**
	 * Rewrite the serialized token at $offset and advance $offset past it.
	 *
	 * @param string $data    Serialized data.
	 * @param int    $offset  Current position (updated).
	 * @param bool   $replace Whether strings in this token are replaced (false for keys).
	 * @return string
	 *
	 * @throws \UnexpectedValueException On malformed data.
	 */
	protected function rewrite_token( $data, &$offset, $replace ) {
		$type = isset( $data[ $offset ] ) ? $data[ $offset ] : '';

		switch ( $type ) {
			case 'N':
				return $this->expect( $data, $offset, '/\GN;/' );

			case 'b':
				return $this->expect( $data, $offset, '/\Gb:[01];/' );

			case 'i':
				return $this->expect( $data, $offset, '/\Gi:[+-]?\d+;/' );

			case 'd':
				return $this->expect( $data, $offset, '/\Gd:(?:[+-]?(?:\d+(?:\.\d*)?|\.\d+)(?:[eE][+-]?\d+)?|NAN|-?INF);/' );

			case 'r':
			case 'R':
				return $this->expect( $data, $offset, '/\G[rR]:\d+;/' );

			case 's':
				$string = $this->read_string( $data, $offset, 's' );
				if ( ';' !== ( $data[ $offset ] ?? '' ) ) {
					throw new \UnexpectedValueException( 'Missing ;' );
				}
				++$offset;
				if ( $replace ) {
					$string = $this->replace_value( $string );
				}
				return 's:' . strlen( $string ) . ':"' . $string . '";';

			case 'E':
				// Enum case: copied verbatim.
				$start = $offset;
				$this->read_string( $data, $offset, 'E' );
				if ( ';' !== ( $data[ $offset ] ?? '' ) ) {
					throw new \UnexpectedValueException( 'Missing ;' );
				}
				++$offset;
				return substr( $data, $start, $offset - $start );

			case 'a':
				if ( ! preg_match( '/\Ga:(\d+):\{/', $data, $m, 0, $offset ) ) {
					throw new \UnexpectedValueException( 'Malformed array' );
				}
				$offset += strlen( $m[0] );
				return $m[0] . $this->rewrite_members( $data, $offset, (int) $m[1] );

			case 'O':
				// Objects are never instantiated: class name and property names are copied, values rewritten.
				$class = $this->read_string( $data, $offset, 'O' );
				if ( ! preg_match( '/\G:(\d+):\{/', $data, $m, 0, $offset ) ) {
					throw new \UnexpectedValueException( 'Malformed object' );
				}
				$offset += strlen( $m[0] );
				return 'O:' . strlen( $class ) . ':"' . $class . '"' . $m[0] . $this->rewrite_members( $data, $offset, (int) $m[1] );

			case 'C':
				// Custom serialization (Serializable): the payload format is up to the class, copy it verbatim.
				$start = $offset;
				$this->read_string( $data, $offset, 'C' );
				if ( ! preg_match( '/\G:(\d+):\{/', $data, $m, 0, $offset ) ) {
					throw new \UnexpectedValueException( 'Malformed custom object' );
				}
				$offset += strlen( $m[0] ) + (int) $m[1];
				if ( '}' !== ( $data[ $offset ] ?? '' ) ) {
					throw new \UnexpectedValueException( 'Malformed custom object' );
				}
				++$offset;
				return substr( $data, $start, $offset - $start );
		}

		throw new \UnexpectedValueException( 'Unknown token' );
	}

	/**
	 * Rewrite $count key/value pairs followed by "}".
	 *
	 * @param string $data   Serialized data.
	 * @param int    $offset Position (updated).
	 * @param int    $count  Number of members.
	 * @return string
	 *
	 * @throws \UnexpectedValueException On malformed data.
	 */
	protected function rewrite_members( $data, &$offset, $count ) {
		$out = '';
		for ( $i = 0; $i < $count; $i++ ) {
			$key_type = $data[ $offset ] ?? '';
			if ( 'i' !== $key_type && 's' !== $key_type ) {
				throw new \UnexpectedValueException( 'Invalid key' );
			}
			$out .= $this->rewrite_token( $data, $offset, false );
			$out .= $this->rewrite_token( $data, $offset, true );
		}
		if ( '}' !== ( $data[ $offset ] ?? '' ) ) {
			throw new \UnexpectedValueException( 'Missing }' );
		}
		++$offset;
		return $out . '}';
	}

	/**
	 * Read `<type>:<length>:"<bytes>"` and return the bytes.
	 *
	 * @param string $data   Serialized data.
	 * @param int    $offset Position (updated to just after the closing quote).
	 * @param string $type   Type letter.
	 * @return string
	 *
	 * @throws \UnexpectedValueException On malformed data.
	 */
	protected function read_string( $data, &$offset, $type ) {
		if ( ! preg_match( '/\G' . $type . ':(\d+):"/', $data, $m, 0, $offset ) ) {
			throw new \UnexpectedValueException( 'Malformed string' );
		}
		$length = (int) $m[1];
		$start  = $offset + strlen( $m[0] );
		if ( $start + $length + 1 > strlen( $data ) || '"' !== $data[ $start + $length ] ) {
			throw new \UnexpectedValueException( 'String length mismatch' );
		}
		$offset = $start + $length + 1;
		return (string) substr( $data, $start, $length );
	}

	/**
	 * Copy a token matching $regex at $offset.
	 *
	 * @param string $data   Serialized data.
	 * @param int    $offset Position (updated).
	 * @param string $regex  Anchored regex.
	 * @return string
	 *
	 * @throws \UnexpectedValueException On malformed data.
	 */
	protected function expect( $data, &$offset, $regex ) {
		if ( ! preg_match( $regex, $data, $m, 0, $offset ) ) {
			throw new \UnexpectedValueException( 'Malformed scalar' );
		}
		$offset += strlen( $m[0] );
		return $m[0];
	}
}
