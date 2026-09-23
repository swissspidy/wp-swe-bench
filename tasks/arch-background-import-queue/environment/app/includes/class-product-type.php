<?php
/**
 * The product post type.
 *
 * @package Acme\Importer
 */

namespace Acme\Importer;

defined( 'ABSPATH' ) || exit;

/**
 * Registers `acme_product`, `acme_product_cat` and the product meta.
 *
 * Meta:
 * - `_acme_sku`   SKU (unique; upper-case since 2.0, as typed before)
 * - `_acme_price` price in cents (int), '' when unknown
 * - `_acme_stock` stock level (int), '' when stock is not tracked
 */
class Product_Type {

	const POST_TYPE = 'acme_product';
	const TAXONOMY  = 'acme_product_cat';

	/**
	 * Hooks.
	 */
	public function register() {
		add_action( 'init', array( $this, 'register_types' ) );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'column' ), 10, 2 );
	}

	/**
	 * Post type, taxonomy, meta.
	 */
	public function register_types() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'       => array(
					'name'          => __( 'Products', 'acme-importer' ),
					'singular_name' => __( 'Product', 'acme-importer' ),
					'add_new_item'  => __( 'Add New Product', 'acme-importer' ),
					'edit_item'     => __( 'Edit Product', 'acme-importer' ),
				),
				'public'       => true,
				'has_archive'  => true,
				'rewrite'      => array( 'slug' => 'products' ),
				'menu_icon'    => 'dashicons-cart',
				'supports'     => array( 'title', 'editor', 'thumbnail', 'custom-fields' ),
				'show_in_rest' => true,
			)
		);

		register_taxonomy(
			self::TAXONOMY,
			self::POST_TYPE,
			array(
				'labels'            => array(
					'name'          => __( 'Product categories', 'acme-importer' ),
					'singular_name' => __( 'Product category', 'acme-importer' ),
				),
				'hierarchical'      => true,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'rewrite'           => array( 'slug' => 'product-category' ),
			)
		);

		foreach ( array( '_acme_sku' => 'string', '_acme_price' => 'integer', '_acme_stock' => 'integer' ) as $key => $type ) {
			register_post_meta(
				self::POST_TYPE,
				$key,
				array(
					'type'          => $type,
					'single'        => true,
					'show_in_rest'  => false,
					'auth_callback' => static function () {
						return current_user_can( 'edit_posts' );
					},
				)
			);
		}
	}

	/**
	 * List table columns.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public function columns( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['acme_sku']   = __( 'SKU', 'acme-importer' );
				$new['acme_price'] = __( 'Price', 'acme-importer' );
				$new['acme_stock'] = __( 'Stock', 'acme-importer' );
			}
		}
		return $new;
	}

	/**
	 * Column values.
	 *
	 * @param string $column  Column.
	 * @param int    $post_id Post ID.
	 */
	public function column( $column, $post_id ) {
		switch ( $column ) {
			case 'acme_sku':
				echo '<code>' . esc_html( get_post_meta( $post_id, '_acme_sku', true ) ) . '</code>';
				break;
			case 'acme_price':
				echo esc_html( format_price( get_post_meta( $post_id, '_acme_price', true ) ) );
				break;
			case 'acme_stock':
				$stock = get_post_meta( $post_id, '_acme_stock', true );
				echo '' === $stock ? '—' : esc_html( number_format_i18n( (int) $stock ) );
				break;
		}
	}
}
