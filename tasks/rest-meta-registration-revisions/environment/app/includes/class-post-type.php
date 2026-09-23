<?php
/**
 * The product post type.
 *
 * @package Acme\Specs
 */

namespace Acme\Specs;

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
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
	}

	/**
	 * Register the post type.
	 *
	 * The REST base is `products`; the block editor and our headless catalogue
	 * (catalog.acme.example) both use /wp/v2/products.
	 */
	public static function register_post_type() {
		$labels = array(
			'name'               => _x( 'Products', 'post type general name', 'acme-specs' ),
			'singular_name'      => _x( 'Product', 'post type singular name', 'acme-specs' ),
			'add_new_item'       => __( 'Add New Product', 'acme-specs' ),
			'edit_item'          => __( 'Edit Product', 'acme-specs' ),
			'new_item'           => __( 'New Product', 'acme-specs' ),
			'view_item'          => __( 'View Product', 'acme-specs' ),
			'search_items'       => __( 'Search Products', 'acme-specs' ),
			'not_found'          => __( 'No products found.', 'acme-specs' ),
			'not_found_in_trash' => __( 'No products found in Trash.', 'acme-specs' ),
			'all_items'          => __( 'All Products', 'acme-specs' ),
			'menu_name'          => __( 'Products', 'acme-specs' ),
		);

		register_post_type(
			self::POST_TYPE,
			array(
				'labels'          => $labels,
				'public'          => true,
				'has_archive'     => 'products',
				'rewrite'         => array( 'slug' => 'product' ),
				'menu_icon'       => 'dashicons-archive',
				'show_in_rest'    => true,
				'rest_base'       => 'products',
				'capability_type' => 'post',
				'map_meta_cap'    => true,
				'supports'        => array( 'title', 'editor', 'excerpt', 'thumbnail', 'author', 'revisions' ),
			)
		);
	}
}
