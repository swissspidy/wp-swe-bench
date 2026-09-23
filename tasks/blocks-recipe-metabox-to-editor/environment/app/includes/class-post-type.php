<?php
/**
 * Recipe post type and cuisine taxonomy.
 *
 * @package Acme\Recipes
 */

namespace Acme\Recipes;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the `acme_recipe` post type and the `acme_cuisine` taxonomy.
 */
class Post_Type {

	const POST_TYPE = 'acme_recipe';
	const TAXONOMY  = 'acme_cuisine';

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_action( 'init', array( $this, 'register' ) );
	}

	/**
	 * Register post type and taxonomy.
	 */
	public function register() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'          => array(
					'name'          => __( 'Recipes', 'acme-recipes' ),
					'singular_name' => __( 'Recipe', 'acme-recipes' ),
					'add_new_item'  => __( 'Add New Recipe', 'acme-recipes' ),
					'edit_item'     => __( 'Edit Recipe', 'acme-recipes' ),
					'all_items'     => __( 'All Recipes', 'acme-recipes' ),
					'menu_name'     => __( 'Recipes', 'acme-recipes' ),
				),
				'public'          => true,
				'has_archive'     => 'recipes',
				'rewrite'         => array( 'slug' => 'recipe' ),
				'menu_icon'       => 'dashicons-carrot',
				'capability_type' => 'post',
				'map_meta_cap'    => true,
				// Recipes are still edited in the classic editor: the details meta box predates the block editor.
				'show_in_rest'    => false,
				'supports'        => array( 'title', 'editor', 'excerpt', 'thumbnail', 'author', 'revisions' ),
			)
		);

		register_taxonomy(
			self::TAXONOMY,
			self::POST_TYPE,
			array(
				'labels'            => array(
					'name'          => __( 'Cuisines', 'acme-recipes' ),
					'singular_name' => __( 'Cuisine', 'acme-recipes' ),
				),
				'hierarchical'      => true,
				'show_admin_column' => true,
				'rewrite'           => array( 'slug' => 'cuisine' ),
			)
		);
	}
}
