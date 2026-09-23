<?php
/**
 * Specification storage.
 *
 * Since 2.0 a product's specs are stored in three post meta entries:
 *
 *     _acme_specs_dimensions      array( 'width' => 120.0, 'height' => 75.0, 'depth' => 60.0, 'unit' => 'cm' )
 *     _acme_specs_materials       array( 'Oak', 'Steel' )
 *     _acme_specs_certifications  array( array( 'code' => 'CE', 'issued' => '2019-03-01', 'expires' => '2029-03-01' ), ... )
 *
 * `expires` is left out when a certification does not expire.
 *
 * @package Acme\Specs
 */

namespace Acme\Specs;

defined( 'ABSPATH' ) || exit;

/**
 * Read / write product specs.
 */
class Specs {

	const DIMENSIONS_KEY     = '_acme_specs_dimensions';
	const MATERIALS_KEY      = '_acme_specs_materials';
	const CERTIFICATIONS_KEY = '_acme_specs_certifications';

	/**
	 * Supported units.
	 */
	const UNITS = array( 'mm', 'cm', 'in' );

	/**
	 * Max number of materials per product (the catalogue layout breaks beyond that).
	 */
	const MAX_MATERIALS = 12;

	/**
	 * Registered certification codes.
	 *
	 * @return array<string, string> Code => label.
	 */
	public static function certification_codes() {
		$codes = array(
			'CE'   => __( 'CE marking', 'acme-specs' ),
			'UL'   => __( 'UL Listed', 'acme-specs' ),
			'RoHS' => __( 'RoHS compliant', 'acme-specs' ),
			'FCC'  => __( 'FCC', 'acme-specs' ),
			'FSC'  => __( 'FSC certified wood', 'acme-specs' ),
		);

		/**
		 * Filters the certification codes editors can pick.
		 *
		 * @since 1.3.0
		 *
		 * @param array<string, string> $codes Code => label.
		 */
		return (array) apply_filters( 'acme_specs_certification_codes', $codes );
	}

	/**
	 * Empty specs.
	 *
	 * @return array
	 */
	public static function empty_specs() {
		return array(
			'dimensions'     => null,
			'materials'      => array(),
			'certifications' => array(),
		);
	}

	/**
	 * Get a product's specs.
	 *
	 * Reads all of the product's meta in one go (it is primed anyway) and falls
	 * back to the 1.x format for products that were never re-saved.
	 *
	 * @param int $post_id Product ID.
	 * @return array{dimensions: array|null, materials: string[], certifications: array[]}
	 */
	public static function get( $post_id ) {
		$post_id = (int) $post_id;
		$custom  = get_post_custom( $post_id );
		$custom  = is_array( $custom ) ? $custom : array();
		$specs   = self::empty_specs();

		$has_structured = isset( $custom[ self::DIMENSIONS_KEY ] ) || isset( $custom[ self::MATERIALS_KEY ] ) || isset( $custom[ self::CERTIFICATIONS_KEY ] );

		if ( $has_structured ) {
			$dimensions = isset( $custom[ self::DIMENSIONS_KEY ][0] ) ? maybe_unserialize( $custom[ self::DIMENSIONS_KEY ][0] ) : null;
			$materials  = isset( $custom[ self::MATERIALS_KEY ][0] ) ? maybe_unserialize( $custom[ self::MATERIALS_KEY ][0] ) : array();
			$certs      = isset( $custom[ self::CERTIFICATIONS_KEY ][0] ) ? maybe_unserialize( $custom[ self::CERTIFICATIONS_KEY ][0] ) : array();

			$specs['dimensions']     = self::sanitize_dimensions( $dimensions );
			$specs['materials']      = self::sanitize_materials( $materials );
			$specs['certifications'] = self::sanitize_certifications( $certs );
		} elseif ( Legacy::has_legacy( $custom ) ) {
			$specs = Legacy::read( $custom );
		}

		/**
		 * Filters a product's specs.
		 *
		 * @since 2.0.0
		 *
		 * @param array $specs   Specs.
		 * @param int   $post_id Product ID.
		 */
		return apply_filters( 'acme_specs_get', $specs, $post_id );
	}

	/**
	 * Save a product's specs (structured format). Empty parts are deleted.
	 *
	 * @param int   $post_id Product ID.
	 * @param array $specs   Specs (see get()).
	 */
	public static function save( $post_id, array $specs ) {
		$dimensions = self::sanitize_dimensions( $specs['dimensions'] ?? null );
		$materials  = self::sanitize_materials( $specs['materials'] ?? array() );
		$certs      = self::sanitize_certifications( $specs['certifications'] ?? array() );

		if ( $dimensions ) {
			update_post_meta( $post_id, self::DIMENSIONS_KEY, $dimensions );
		} else {
			delete_post_meta( $post_id, self::DIMENSIONS_KEY );
		}

		if ( $materials ) {
			update_post_meta( $post_id, self::MATERIALS_KEY, $materials );
		} else {
			delete_post_meta( $post_id, self::MATERIALS_KEY );
		}

		if ( $certs ) {
			update_post_meta( $post_id, self::CERTIFICATIONS_KEY, $certs );
		} else {
			delete_post_meta( $post_id, self::CERTIFICATIONS_KEY );
		}

		/**
		 * Fires after a product's specs were saved.
		 *
		 * @since 2.0.0
		 *
		 * @param int   $post_id Product ID.
		 * @param array $specs   The saved specs.
		 */
		do_action(
			'acme_specs_saved',
			$post_id,
			array(
				'dimensions'     => $dimensions,
				'materials'      => $materials,
				'certifications' => $certs,
			)
		);
	}

	/**
	 * Sanitize dimensions. Returns null unless width, height and depth are positive numbers.
	 *
	 * @param mixed $dimensions Raw value.
	 * @return array|null
	 */
	public static function sanitize_dimensions( $dimensions ) {
		if ( ! is_array( $dimensions ) ) {
			return null;
		}
		$out = array();
		foreach ( array( 'width', 'height', 'depth' ) as $axis ) {
			$value = $dimensions[ $axis ] ?? null;
			if ( is_string( $value ) ) {
				$value = str_replace( ',', '.', trim( $value ) );
			}
			if ( ! is_numeric( $value ) || (float) $value <= 0 ) {
				return null;
			}
			$out[ $axis ] = round( (float) $value, 2 );
		}
		$unit        = isset( $dimensions['unit'] ) ? (string) $dimensions['unit'] : 'cm';
		$out['unit'] = in_array( $unit, self::UNITS, true ) ? $unit : 'cm';
		return $out;
	}

	/**
	 * Sanitize materials.
	 *
	 * @param mixed $materials Raw value (list of strings).
	 * @return string[]
	 */
	public static function sanitize_materials( $materials ) {
		if ( ! is_array( $materials ) ) {
			return array();
		}
		$out = array();
		foreach ( $materials as $material ) {
			if ( ! is_scalar( $material ) ) {
				continue;
			}
			$material = sanitize_text_field( (string) $material );
			if ( '' !== $material && ! in_array( $material, $out, true ) ) {
				$out[] = $material;
			}
		}
		return array_slice( $out, 0, self::MAX_MATERIALS );
	}

	/**
	 * Sanitize certifications. Rows without a code or a valid issue date are dropped.
	 *
	 * @param mixed $certs Raw value.
	 * @return array[]
	 */
	public static function sanitize_certifications( $certs ) {
		if ( ! is_array( $certs ) ) {
			return array();
		}
		$out = array();
		foreach ( $certs as $cert ) {
			if ( ! is_array( $cert ) || empty( $cert['code'] ) ) {
				continue;
			}
			$issued = Legacy::parse_date( $cert['issued'] ?? '' );
			if ( null === $issued ) {
				continue;
			}
			$row     = array(
				'code'   => sanitize_text_field( (string) $cert['code'] ),
				'issued' => $issued,
			);
			$expires = Legacy::parse_date( $cert['expires'] ?? '' );
			if ( null !== $expires ) {
				$row['expires'] = $expires;
			}
			$out[] = $row;
		}
		return $out;
	}
}
