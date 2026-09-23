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
	 * Every key is read on its own (and never from a bulk read of all meta) so
	 * that previews of unsaved changes show the autosaved specs. Products that
	 * still carry 1.x data (e.g. imported after the 3.0 upgrade) are read from
	 * their old fields until they are migrated.
	 *
	 * @param int $post_id Product ID.
	 * @return array{dimensions: array|null, materials: string[], certifications: array[]}
	 */
	public static function get( $post_id ) {
		$post_id = (int) $post_id;
		$specs   = self::empty_specs();
		$source  = self::source_id( $post_id );
		if ( $source !== $post_id && ! self::has_structured( $source ) ) {
			$source = $post_id;
		}

		if ( self::has_structured( $source ) ) {
			$specs['dimensions']     = self::sanitize_dimensions( get_post_meta( $source, self::DIMENSIONS_KEY, true ) );
			$specs['materials']      = self::sanitize_materials( get_post_meta( $source, self::MATERIALS_KEY, true ) );
			$specs['certifications'] = self::sanitize_certifications( get_post_meta( $source, self::CERTIFICATIONS_KEY, true ) );
		} else {
			$custom = self::legacy_meta( $post_id );
			if ( $custom ) {
				$specs = Legacy::read( $custom );
			}
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
	 * Which post to read the specs from: the product itself, or, while the
	 * product is being previewed, its autosave (the unsaved changes).
	 *
	 * The autosave is read directly: WordPress' own preview handling of
	 * revisioned meta only works for scalar values.
	 *
	 * @param int $post_id Product ID.
	 * @return int
	 */
	public static function source_id( $post_id ) {
		if ( ! is_preview() ) {
			return $post_id;
		}
		$current = get_post();
		if ( ! $current || (int) $current->ID !== $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			return $post_id;
		}
		$autosave = wp_get_post_autosave( $post_id, get_current_user_id() );
		if ( ! $autosave ) {
			$autosave = wp_get_post_autosave( $post_id );
		}
		return $autosave ? (int) $autosave->ID : $post_id;
	}

	/**
	 * Whether a product has structured (2.x+) specs.
	 *
	 * @param int $post_id Product ID.
	 * @return bool
	 */
	public static function has_structured( $post_id ) {
		foreach ( array( self::DIMENSIONS_KEY, self::MATERIALS_KEY, self::CERTIFICATIONS_KEY ) as $key ) {
			if ( metadata_exists( 'post', $post_id, $key ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * A product's 1.x meta in get_post_custom() format (empty when there is none).
	 *
	 * @param int $post_id Product ID.
	 * @return array
	 */
	public static function legacy_meta( $post_id ) {
		$custom = array();
		foreach ( Legacy::KEYS as $key ) {
			$values = get_post_meta( $post_id, $key, false );
			if ( $values ) {
				$custom[ $key ] = $values;
			}
		}
		return $custom;
	}

	/**
	 * Whether a user may change a product's certifications (Editors and up).
	 *
	 * @param int      $post_id Product ID.
	 * @param int|null $user_id User ID (default: current user).
	 * @return bool
	 */
	public static function user_can_edit_certifications( $post_id, $user_id = null ) {
		$user_id = null === $user_id ? get_current_user_id() : (int) $user_id;
		$type    = get_post_type_object( Post_Type::POST_TYPE );
		if ( ! $user_id || ! $type ) {
			return false;
		}
		return user_can( $user_id, 'edit_post', $post_id ) && user_can( $user_id, $type->cap->edit_others_posts );
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
