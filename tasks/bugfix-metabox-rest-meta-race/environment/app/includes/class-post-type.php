<?php
/**
 * Product post type.
 *
 * @package Acme\ProductFields
 */

namespace Acme\ProductFields;

defined( 'ABSPATH' ) || exit;

/**
 * Registers `acme_product`.
 */
class Post_Type {

	const POST_TYPE = 'acme_product';

	/**
	 * Hooks.
	 */
	public function register_hooks() {
		add_action( 'init', array( $this, 'register' ) );
	}

	/**
	 * Register the post type.
	 */
	public function register() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'       => array(
					'name'          => __( 'Products', 'acme-product-fields' ),
					'singular_name' => __( 'Product', 'acme-product-fields' ),
					'add_new_item'  => __( 'Add New Product', 'acme-product-fields' ),
					'edit_item'     => __( 'Edit Product', 'acme-product-fields' ),
					'all_items'     => __( 'All Products', 'acme-product-fields' ),
				),
				'public'       => true,
				'has_archive'  => true,
				'rewrite'      => array( 'slug' => 'product' ),
				'menu_icon'    => 'dashicons-cart',
				'show_in_rest' => true,
				'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields', 'revisions' ),
			)
		);
	}
}
