<?php
/**
 * Recipe meta registration: types, validation, sanitization, permissions and REST exposure.
 *
 * @package Acme\Recipes
 */

namespace Acme\Recipes;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the recipe details meta for the block editor and the REST API.
 *
 * Storage stays exactly as before (see includes/functions.php). Values stored by 1.x in older
 * formats are returned normalized, the same way the recipe card reads them.
 */
class Meta {

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_action( 'init', array( $this, 'register' ) );
		add_filter( 'rest_request_before_callbacks', array( $this, 'drop_unchanged_staff_pick' ), 10, 3 );
	}

	/**
	 * The block editor sends back every meta value of the recipe when saving, including an unchanged
	 * staff pick flag. Users who may not change the flag must still be able to save their recipe, so an
	 * unchanged value is ignored instead of being rejected (a real change is still rejected).
	 *
	 * @param mixed            $response Response to replace the requested version with.
	 * @param array            $handler  Route handler.
	 * @param \WP_REST_Request $request  Request.
	 * @return mixed
	 */
	public function drop_unchanged_staff_pick( $response, $handler, $request ) {
		if ( ! in_array( $request->get_method(), array( 'POST', 'PUT', 'PATCH' ), true ) || ! preg_match( '#^/wp/v2/recipes/(\d+)(?:/autosaves)?$#', $request->get_route(), $m ) ) {
			return $response;
		}
		$meta = $request->get_param( 'meta' );
		if ( ! is_array( $meta ) || ! array_key_exists( ACME_RECIPES_META_STAFF_PICK, $meta ) || current_user_can( 'edit_others_posts' ) ) {
			return $response;
		}
		$current = (bool) get_post_meta( (int) $m[1], ACME_RECIPES_META_STAFF_PICK, true );
		if ( rest_is_boolean( $meta[ ACME_RECIPES_META_STAFF_PICK ] ) && rest_sanitize_boolean( $meta[ ACME_RECIPES_META_STAFF_PICK ] ) === $current ) {
			unset( $meta[ ACME_RECIPES_META_STAFF_PICK ] );
			$request->set_param( 'meta', $meta );
		}
		return $response;
	}

	/**
	 * May the user edit the recipe (and therefore its details)?
	 *
	 * @param bool   $allowed  Whether allowed so far.
	 * @param string $meta_key Meta key.
	 * @param int    $post_id  Recipe ID.
	 * @param int    $user_id  User ID.
	 * @return bool
	 */
	public static function can_edit( $allowed, $meta_key, $post_id, $user_id ) {
		return user_can( $user_id, 'edit_post', $post_id );
	}

	/**
	 * Only editors (and admins) may pick staff picks.
	 *
	 * @param bool   $allowed  Whether allowed so far.
	 * @param string $meta_key Meta key.
	 * @param int    $post_id  Recipe ID.
	 * @param int    $user_id  User ID.
	 * @return bool
	 */
	public static function can_staff_pick( $allowed, $meta_key, $post_id, $user_id ) {
		return user_can( $user_id, 'edit_post', $post_id ) && user_can( $user_id, 'edit_others_posts' );
	}

	/**
	 * Register all meta keys.
	 */
	public function register() {
		$type = Post_Type::POST_TYPE;
		$edit = array( __CLASS__, 'can_edit' );

		register_post_meta(
			$type,
			ACME_RECIPES_META_INGREDIENTS,
			array(
				'type'              => 'array',
				'single'            => true,
				'default'           => array(),
				'auth_callback'     => $edit,
				'sanitize_callback' => array( __CLASS__, 'sanitize_ingredients' ),
				'show_in_rest'      => array(
					'schema'           => array(
						'type'  => 'array',
						'items' => array(
							'type'                 => 'object',
							'properties'           => array(
								'amount' => array( 'type' => 'string' ),
								'unit'   => array( 'type' => 'string' ),
								'item'   => array(
									'type'      => 'string',
									'minLength' => 1,
								),
							),
							'required'             => array( 'item' ),
							'additionalProperties' => false,
						),
					),
					'prepare_callback' => array( __CLASS__, 'prepare_ingredients' ),
				),
			)
		);

		foreach ( array( ACME_RECIPES_META_PREP, ACME_RECIPES_META_COOK ) as $key ) {
			register_post_meta(
				$type,
				$key,
				array(
					'type'              => 'integer',
					'single'            => true,
					'default'           => 0,
					'auth_callback'     => $edit,
					'sanitize_callback' => 'absint',
					'show_in_rest'      => array(
						'schema'           => array(
							'type'    => 'integer',
							'minimum' => 0,
							'maximum' => 10080,
						),
						'prepare_callback' => static function ( $value ) {
							return acme_recipes_parse_minutes( $value );
						},
					),
				)
			);
		}

		register_post_meta(
			$type,
			ACME_RECIPES_META_SERVINGS,
			array(
				'type'              => 'integer',
				'single'            => true,
				'default'           => 0,
				'auth_callback'     => $edit,
				'sanitize_callback' => 'absint',
				'show_in_rest'      => array(
					// 0 = not specified.
					'schema'           => array(
						'type'    => 'integer',
						'minimum' => 0,
						'maximum' => 100,
					),
					'prepare_callback' => static function ( $value ) {
						return max( 0, (int) $value );
					},
				),
			)
		);

		register_post_meta(
			$type,
			ACME_RECIPES_META_DIFFICULTY,
			array(
				'type'              => 'string',
				'single'            => true,
				'default'           => '',
				'auth_callback'     => $edit,
				'sanitize_callback' => array( __CLASS__, 'sanitize_difficulty' ),
				'show_in_rest'      => array(
					'schema'           => array(
						'type' => 'string',
						'enum' => array_merge( array( '' ), array_keys( acme_recipes_difficulties() ) ),
					),
					'prepare_callback' => static function ( $value ) {
						return self::sanitize_difficulty( $value );
					},
				),
			)
		);

		register_post_meta(
			$type,
			ACME_RECIPES_META_NOTES,
			array(
				'type'              => 'string',
				'single'            => true,
				'default'           => '',
				'auth_callback'     => $edit,
				'sanitize_callback' => 'sanitize_textarea_field',
				'show_in_rest'      => array(
					// Private: only in "edit" context responses, which require permission to edit the recipe.
					'schema' => array(
						'type'    => 'string',
						'context' => array( 'edit' ),
					),
				),
			)
		);

		register_post_meta(
			$type,
			ACME_RECIPES_META_STAFF_PICK,
			array(
				'type'              => 'boolean',
				'single'            => true,
				'default'           => false,
				'auth_callback'     => array( __CLASS__, 'can_staff_pick' ),
				'sanitize_callback' => 'rest_sanitize_boolean',
				'show_in_rest'      => true,
			)
		);
	}

	/**
	 * Sanitize ingredients to the stored format.
	 *
	 * @param mixed $value Ingredients.
	 * @return array
	 */
	public static function sanitize_ingredients( $value ) {
		$out = array();
		foreach ( is_array( $value ) ? $value : array() as $raw ) {
			if ( ! is_array( $raw ) ) {
				continue;
			}
			$ingredient = acme_recipes_normalize_ingredient(
				array(
					'amount' => sanitize_text_field( (string) ( $raw['amount'] ?? '' ) ),
					'unit'   => sanitize_text_field( (string) ( $raw['unit'] ?? '' ) ),
					'item'   => sanitize_text_field( (string) ( $raw['item'] ?? '' ) ),
				)
			);
			if ( $ingredient ) {
				$out[] = $ingredient;
			}
		}
		return $out;
	}

	/**
	 * Stored ingredients (any format) as objects for the REST API.
	 *
	 * @param mixed $value Stored value.
	 * @return array
	 */
	public static function prepare_ingredients( $value ) {
		if ( is_string( $value ) && '' !== $value ) {
			$value = preg_split( '/\r\n|\r|\n/', $value );
		}
		$out = array();
		foreach ( (array) $value as $raw ) {
			$ingredient = acme_recipes_normalize_ingredient( $raw );
			if ( $ingredient ) {
				$out[] = $ingredient;
			}
		}
		return $out;
	}

	/**
	 * Difficulty slug or ''.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	public static function sanitize_difficulty( $value ) {
		$value = strtolower( trim( (string) $value ) );
		return array_key_exists( $value, acme_recipes_difficulties() ) ? $value : '';
	}
}
