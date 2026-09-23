<?php
/**
 * Registration of the specification meta: REST exposure, schemas,
 * permissions and revision support.
 *
 * @package Acme\Specs
 */

namespace Acme\Specs;

defined( 'ABSPATH' ) || exit;

/**
 * Registers `_acme_specs_*` meta for products.
 */
class Meta {

	/**
	 * Option: Unix timestamp since when product revisions carry specs.
	 */
	const TRACKED_SINCE_OPTION = 'acme_specs_revisions_since';

	/**
	 * Hooks.
	 */
	public function register_hooks() {
		add_action( 'init', array( $this, 'register' ), 20 );
		add_filter( 'rest_pre_insert_' . Post_Type::POST_TYPE, array( $this, 'validate_request' ), 10, 2 );
		add_action( 'wp_restore_post_revision', array( $this, 'before_restore' ), 9, 2 );
		add_action( 'wp_restore_post_revision', array( $this, 'after_restore' ), 11, 2 );

		foreach ( array( 'added_post_meta', 'updated_post_meta', 'deleted_post_meta' ) as $hook ) {
			add_action( $hook, array( $this, 'flush_table_cache' ), 10, 3 );
		}
	}

	/**
	 * The spec meta keys.
	 *
	 * @return string[]
	 */
	public static function keys() {
		return array( Specs::DIMENSIONS_KEY, Specs::MATERIALS_KEY, Specs::CERTIFICATIONS_KEY );
	}

	/**
	 * Register the meta.
	 */
	public function register() {
		$date = array(
			'type'    => 'string',
			'pattern' => '^[0-9]{4}-[0-9]{2}-[0-9]{2}$',
		);

		register_post_meta(
			Post_Type::POST_TYPE,
			Specs::DIMENSIONS_KEY,
			array(
				'type'              => 'object',
				'description'       => __( 'Product dimensions.', 'acme-specs' ),
				'single'            => true,
				'revisions_enabled' => true,
				'sanitize_callback' => array( __CLASS__, 'sanitize_dimensions_meta' ),
				'auth_callback'     => array( __CLASS__, 'can_edit_specs' ),
				'show_in_rest'      => array(
					'schema' => array(
						'type'                 => 'object',
						'properties'           => array(
							'width'  => array(
								'type'             => 'number',
								'minimum'          => 0,
								'exclusiveMinimum' => true,
							),
							'height' => array(
								'type'             => 'number',
								'minimum'          => 0,
								'exclusiveMinimum' => true,
							),
							'depth'  => array(
								'type'             => 'number',
								'minimum'          => 0,
								'exclusiveMinimum' => true,
							),
							'unit'   => array(
								'type' => 'string',
								'enum' => Specs::UNITS,
							),
						),
						'required'             => array( 'width', 'height', 'depth', 'unit' ),
						'additionalProperties' => false,
					),
				),
			)
		);

		register_post_meta(
			Post_Type::POST_TYPE,
			Specs::MATERIALS_KEY,
			array(
				'type'              => 'array',
				'description'       => __( 'Materials.', 'acme-specs' ),
				'single'            => true,
				'revisions_enabled' => true,
				'sanitize_callback' => array( Specs::class, 'sanitize_materials' ),
				'auth_callback'     => array( __CLASS__, 'can_edit_specs' ),
				'show_in_rest'      => array(
					'schema' => array(
						'type'     => 'array',
						'maxItems' => Specs::MAX_MATERIALS,
						'items'    => array(
							'type'      => 'string',
							'minLength' => 1,
							'maxLength' => 100,
						),
					),
				),
			)
		);

		register_post_meta(
			Post_Type::POST_TYPE,
			Specs::CERTIFICATIONS_KEY,
			array(
				'type'              => 'array',
				'description'       => __( 'Certifications.', 'acme-specs' ),
				'single'            => true,
				'revisions_enabled' => true,
				'sanitize_callback' => array( Specs::class, 'sanitize_certifications' ),
				'auth_callback'     => array( __CLASS__, 'can_edit_certifications' ),
				'show_in_rest'      => array(
					'schema' => array(
						'type'  => 'array',
						'items' => array(
							'type'                 => 'object',
							'properties'           => array(
								'code'    => array(
									'type' => 'string',
									'enum' => array_map( 'strval', array_keys( Specs::certification_codes() ) ),
								),
								'issued'  => $date,
								'expires' => $date,
							),
							'required'             => array( 'code', 'issued' ),
							'additionalProperties' => false,
						),
					),
				),
			)
		);
	}

	/**
	 * Sanitize callback for dimensions (keeps "no dimensions" as an empty value).
	 *
	 * @param mixed $value Value.
	 * @return array|string
	 */
	public static function sanitize_dimensions_meta( $value ) {
		$dimensions = Specs::sanitize_dimensions( $value );
		return $dimensions ? $dimensions : '';
	}

	/**
	 * Auth: whoever can edit the product can edit its dimensions and materials.
	 *
	 * @param bool   $allowed  Whether allowed (ignored).
	 * @param string $meta_key Meta key.
	 * @param int    $post_id  Product ID.
	 * @param int    $user_id  User ID.
	 * @return bool
	 */
	public static function can_edit_specs( $allowed, $meta_key, $post_id, $user_id ) {
		return user_can( $user_id, 'edit_post', $post_id );
	}

	/**
	 * Auth: certifications are for editors (people who may edit other people's products).
	 *
	 * @param bool   $allowed  Whether allowed (ignored).
	 * @param string $meta_key Meta key.
	 * @param int    $post_id  Product ID.
	 * @param int    $user_id  User ID.
	 * @return bool
	 */
	public static function can_edit_certifications( $allowed, $meta_key, $post_id, $user_id ) {
		return Specs::user_can_edit_certifications( $post_id, $user_id );
	}

	/**
	 * Validation the schema cannot express: real calendar dates, expiry after issue.
	 *
	 * @param \stdClass|\WP_Error $prepared_post Prepared post.
	 * @param \WP_REST_Request    $request       Request.
	 * @return \stdClass|\WP_Error
	 */
	public function validate_request( $prepared_post, $request ) {
		if ( is_wp_error( $prepared_post ) ) {
			return $prepared_post;
		}
		$meta = $request->get_param( 'meta' );
		if ( ! is_array( $meta ) || empty( $meta[ Specs::CERTIFICATIONS_KEY ] ) || ! is_array( $meta[ Specs::CERTIFICATIONS_KEY ] ) ) {
			return $prepared_post;
		}

		foreach ( array_values( $meta[ Specs::CERTIFICATIONS_KEY ] ) as $i => $cert ) {
			$param   = sprintf( 'meta.%s[%d]', Specs::CERTIFICATIONS_KEY, $i );
			$issued  = Legacy::parse_date( $cert['issued'] ?? '' );
			$expires = isset( $cert['expires'] ) ? Legacy::parse_date( $cert['expires'] ) : null;

			if ( null === $issued || ( isset( $cert['expires'] ) && null === $expires ) ) {
				return $this->invalid( $param, __( 'Certification dates must be valid dates (YYYY-MM-DD).', 'acme-specs' ) );
			}
			if ( null !== $expires && $expires < $issued ) {
				return $this->invalid( $param, __( 'A certification cannot expire before it was issued.', 'acme-specs' ) );
			}
		}
		return $prepared_post;
	}

	/**
	 * A 400 error in the REST API's invalid parameter format.
	 *
	 * @param string $param   Parameter.
	 * @param string $message Message.
	 * @return \WP_Error
	 */
	private function invalid( $param, $message ) {
		return new \WP_Error(
			'rest_invalid_param',
			/* translators: %s: parameter list */
			sprintf( __( 'Invalid parameter(s): %s', 'acme-specs' ), 'meta' ),
			array(
				'status' => 400,
				'params' => array( 'meta' => $message ),
				'details' => array( $param => $message ),
			)
		);
	}

	/**
	 * Before a revision is restored: revisions saved before specs were tracked
	 * have no specs at all. Restoring one must not wipe the product's specs.
	 *
	 * @param int $post_id     Product ID.
	 * @param int $revision_id Revision ID.
	 */
	public function before_restore( $post_id, $revision_id ) {
		if ( Post_Type::POST_TYPE !== get_post_type( $post_id ) || ! self::predates_tracking( $revision_id ) ) {
			return;
		}
		add_filter( 'wp_post_revision_meta_keys', array( $this, 'exclude_spec_keys' ) );
	}

	/**
	 * After a revision was restored.
	 *
	 * @param int $post_id     Product ID.
	 * @param int $revision_id Revision ID.
	 */
	public function after_restore( $post_id, $revision_id ) {
		remove_filter( 'wp_post_revision_meta_keys', array( $this, 'exclude_spec_keys' ) );
		Frontend::flush_cache( $post_id );
	}

	/**
	 * Remove our keys from the revisioned keys.
	 *
	 * @param string[] $keys Keys.
	 * @return string[]
	 */
	public function exclude_spec_keys( $keys ) {
		return array_values( array_diff( $keys, self::keys() ) );
	}

	/**
	 * Whether a revision was saved before revisions carried specs.
	 *
	 * @param int $revision_id Revision ID.
	 * @return bool
	 */
	public static function predates_tracking( $revision_id ) {
		foreach ( self::keys() as $key ) {
			if ( metadata_exists( 'post', $revision_id, $key ) ) {
				return false;
			}
		}
		$since    = (int) get_option( self::TRACKED_SINCE_OPTION, 0 );
		$revision = get_post( $revision_id );
		if ( ! $revision || ! $since ) {
			return false;
		}
		return strtotime( $revision->post_date_gmt . ' UTC' ) < $since;
	}

	/**
	 * Drop a product's cached table when its specs change in any way
	 * (REST, revision restore, migration).
	 *
	 * @param int|int[] $meta_ids  Meta ID(s).
	 * @param int       $object_id Object ID.
	 * @param string    $meta_key  Meta key.
	 */
	public function flush_table_cache( $meta_ids, $object_id, $meta_key ) {
		if ( in_array( $meta_key, self::keys(), true ) ) {
			Frontend::flush_cache( $object_id );
		}
	}
}
