<?php
/**
 * Product post type, product categories and product meta.
 *
 * @package Acme\Catalog
 */

namespace Acme\Catalog;

defined( 'ABSPATH' ) || exit;

/**
 * Registers content types.
 */
class Post_Types {

	const POST_TYPE = 'acme_product';
	const TAXONOMY  = 'acme_product_cat';

	/**
	 * Register everything.
	 */
	public function register() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'       => array(
					'name'          => __( 'Products', 'acme-catalog' ),
					'singular_name' => __( 'Product', 'acme-catalog' ),
					'add_new_item'  => __( 'Add new product', 'acme-catalog' ),
					'edit_item'     => __( 'Edit product', 'acme-catalog' ),
				),
				'public'       => true,
				'has_archive'  => 'products',
				'rewrite'      => array( 'slug' => 'product' ),
				'menu_icon'    => 'dashicons-cart',
				'show_in_rest' => true,
				'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields' ),
			)
		);

		register_taxonomy(
			self::TAXONOMY,
			self::POST_TYPE,
			array(
				'labels'            => array(
					'name'          => __( 'Product categories', 'acme-catalog' ),
					'singular_name' => __( 'Product category', 'acme-catalog' ),
				),
				'hierarchical'      => true,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'rewrite'           => array( 'slug' => 'product-category' ),
			)
		);

		register_post_meta(
			self::POST_TYPE,
			'_acme_price',
			array(
				'type'              => 'number',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => static function ( $value ) {
					return max( 0, round( (float) $value, 2 ) );
				},
				'auth_callback'     => static function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);
		register_post_meta(
			self::POST_TYPE,
			'_acme_sku',
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => static function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);
	}
}
