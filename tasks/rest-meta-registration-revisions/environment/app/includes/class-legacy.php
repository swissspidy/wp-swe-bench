<?php
/**
 * Reader for the 1.x ("flat") specification meta.
 *
 * Acme Specs 1.x stored every spec in its own meta key, as typed by the
 * editors, e.g.:
 *
 *     _acme_width      "90cm", "90", "35,5"
 *     _acme_height     (same)
 *     _acme_depth      (same)
 *     _acme_unit       "mm" | "cm" | "in" | "inch" | "inches" (missing = cm)
 *     _acme_materials  "Steel, Powder coating, "
 *     _acme_certs      "CE:2018-01-10|GS:12.05.2020:12.05.2025"
 *                      (code:issued[:expires], dates as Y-m-d or d.m.Y)
 *
 * 2.0 switched to structured meta (see Specs) but never converted old
 * products: Specs::get() falls back to this reader when a product has no
 * structured data. The 1.x keys are still on ~40% of the catalogue.
 *
 * @package Acme\Specs
 */

namespace Acme\Specs;

defined( 'ABSPATH' ) || exit;

/**
 * Parses 1.x meta.
 */
class Legacy {

	/**
	 * The 1.x meta keys.
	 */
	const KEYS = array(
		'_acme_width',
		'_acme_height',
		'_acme_depth',
		'_acme_unit',
		'_acme_materials',
		'_acme_certs',
	);

	/**
	 * Whether a set of meta (as returned by get_post_custom()) contains 1.x data.
	 *
	 * @param array $custom Meta key => list of values.
	 * @return bool
	 */
	public static function has_legacy( array $custom ) {
		foreach ( self::KEYS as $key ) {
			if ( isset( $custom[ $key ] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Convert 1.x meta to the structured format.
	 *
	 * @param array $custom Meta key => list of values (get_post_custom() format).
	 * @return array{dimensions: array|null, materials: string[], certifications: array[]}
	 */
	public static function read( array $custom ) {
		$value = static function ( $key ) use ( $custom ) {
			return isset( $custom[ $key ][0] ) ? trim( (string) maybe_unserialize( $custom[ $key ][0] ) ) : '';
		};

		return array(
			'dimensions'     => self::parse_dimensions( $value( '_acme_width' ), $value( '_acme_height' ), $value( '_acme_depth' ), $value( '_acme_unit' ) ),
			'materials'      => self::parse_materials( $value( '_acme_materials' ) ),
			'certifications' => self::parse_certifications( $value( '_acme_certs' ) ),
		);
	}

	/**
	 * Dimensions: all three must be positive numbers, otherwise there are none.
	 *
	 * @param string $width  Raw width.
	 * @param string $height Raw height.
	 * @param string $depth  Raw depth.
	 * @param string $unit   Raw unit.
	 * @return array|null
	 */
	public static function parse_dimensions( $width, $height, $depth, $unit ) {
		$unit_from_value = '';
		$numbers         = array();
		foreach ( array( $width, $height, $depth ) as $raw ) {
			if ( preg_match( '/^\s*([0-9]+(?:[.,][0-9]+)?)\s*(mm|cm|in|inch|inches|")?\s*$/i', $raw, $m ) ) {
				$number = (float) str_replace( ',', '.', $m[1] );
				if ( $number <= 0 ) {
					return null;
				}
				$numbers[] = $number;
				if ( ! empty( $m[2] ) && '' === $unit_from_value ) {
					$unit_from_value = $m[2];
				}
			} else {
				return null;
			}
		}

		$unit = self::normalize_unit( '' !== $unit ? $unit : $unit_from_value );

		return array(
			'width'  => $numbers[0],
			'height' => $numbers[1],
			'depth'  => $numbers[2],
			'unit'   => $unit,
		);
	}

	/**
	 * Normalize a 1.x unit.
	 *
	 * @param string $unit Raw unit.
	 * @return string mm|cm|in
	 */
	public static function normalize_unit( $unit ) {
		$unit = strtolower( trim( (string) $unit ) );
		switch ( $unit ) {
			case 'mm':
				return 'mm';
			case 'in':
			case 'inch':
			case 'inches':
			case '"':
				return 'in';
			default:
				return 'cm';
		}
	}

	/**
	 * Materials: comma separated.
	 *
	 * @param string $raw Raw value.
	 * @return string[]
	 */
	public static function parse_materials( $raw ) {
		$out = array();
		foreach ( explode( ',', (string) $raw ) as $material ) {
			$material = sanitize_text_field( $material );
			if ( '' !== $material && ! in_array( $material, $out, true ) ) {
				$out[] = $material;
			}
		}
		return $out;
	}

	/**
	 * Certifications: "CODE:issued[:expires]" separated by "|".
	 *
	 * Unknown codes are kept (they may be registered again later); entries
	 * without a parsable issue date are dropped.
	 *
	 * @param string $raw Raw value.
	 * @return array[]
	 */
	public static function parse_certifications( $raw ) {
		$out = array();
		foreach ( explode( '|', (string) $raw ) as $entry ) {
			$parts = array_map( 'trim', explode( ':', $entry ) );
			if ( count( $parts ) < 2 || '' === $parts[0] ) {
				continue;
			}
			$issued = self::parse_date( $parts[1] );
			if ( null === $issued ) {
				continue;
			}
			$cert = array(
				'code'   => $parts[0],
				'issued' => $issued,
			);
			$expires = isset( $parts[2] ) ? self::parse_date( $parts[2] ) : null;
			if ( null !== $expires ) {
				$cert['expires'] = $expires;
			}
			$out[] = $cert;
		}
		return $out;
	}

	/**
	 * Parse a 1.x date (Y-m-d or d.m.Y).
	 *
	 * @param string $raw Raw date.
	 * @return string|null Y-m-d.
	 */
	public static function parse_date( $raw ) {
		$raw = trim( (string) $raw );
		if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $m ) && checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
			return $raw;
		}
		if ( preg_match( '/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $raw, $m ) && checkdate( (int) $m[2], (int) $m[1], (int) $m[3] ) ) {
			return sprintf( '%04d-%02d-%02d', $m[3], $m[2], $m[1] );
		}
		return null;
	}
}
