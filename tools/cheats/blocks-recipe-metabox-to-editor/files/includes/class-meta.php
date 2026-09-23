<?php
/**
 * Recipe meta registration for the block editor.
 *
 * @package Acme\Recipes
 */

namespace Acme\Recipes;

defined( 'ABSPATH' ) || exit;

/**
 * Exposes the recipe meta in REST so the editor panel can edit it.
 */
class Meta {

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_action( 'init', array( $this, 'register' ) );
	}

	/**
	 * Register meta.
	 */
	public function register() {
		$common = array(
			'single'        => true,
			'auth_callback' => '__return_true',
		);
		register_post_meta(
			Post_Type::POST_TYPE,
			ACME_RECIPES_META_INGREDIENTS,
			$common + array(
				'type'         => 'array',
				'default'      => array(),
				'show_in_rest' => array(
					'schema' => array(
						'type'  => 'array',
						'items' => array(
							'type'       => 'object',
							'properties' => array(
								'amount' => array( 'type' => 'string' ),
								'unit'   => array( 'type' => 'string' ),
								'item'   => array( 'type' => 'string' ),
							),
						),
					),
				),
			)
		);
		foreach ( array( ACME_RECIPES_META_PREP, ACME_RECIPES_META_COOK, ACME_RECIPES_META_SERVINGS ) as $key ) {
			register_post_meta( Post_Type::POST_TYPE, $key, $common + array( 'type' => 'integer', 'default' => 0, 'show_in_rest' => true ) );
		}
		register_post_meta( Post_Type::POST_TYPE, ACME_RECIPES_META_DIFFICULTY, $common + array( 'type' => 'string', 'default' => '', 'show_in_rest' => true ) );
		register_post_meta( Post_Type::POST_TYPE, ACME_RECIPES_META_NOTES, $common + array( 'type' => 'string', 'default' => '', 'show_in_rest' => true ) );
		register_post_meta( Post_Type::POST_TYPE, ACME_RECIPES_META_STAFF_PICK, $common + array( 'type' => 'boolean', 'default' => false, 'show_in_rest' => true ) );
	}
}
