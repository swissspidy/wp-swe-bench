<?php
/**
 * Product storage.
 *
 * @package Acme\Importer
 */

namespace Acme\Importer;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Creates and updates products by SKU.
 */
class Product_Repository {

	/**
	 * Finds a product by SKU.
	 *
	 * @param string $sku SKU.
	 * @return int Post ID or 0.
	 */
	public function find_by_sku( $sku ) {
		$ids = get_posts(
			array(
				'post_type'        => Product_Type::POST_TYPE,
				'meta_key'         => '_acme_sku', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_key
				'meta_value'       => normalize_sku( $sku ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_value
				'fields'           => 'ids',
				'posts_per_page'   => 1,
				'suppress_filters' => false,
			)
		);
		return $ids ? (int) $ids[0] : 0;
	}

	/**
	 * Creates or updates the product with the SKU in $data.
	 *
	 * @param array $data Product data (see Row_Mapper::map()).
	 * @return array{id: int, created: bool}|WP_Error
	 */
	public function upsert( array $data ) {
		$id      = $this->find_by_sku( $data['sku'] );
		$created = ! $id;

		$postarr = array(
			'post_type' => Product_Type::POST_TYPE,
		);
		if ( $id ) {
			$postarr['ID'] = $id;
		} else {
			$postarr['post_title']  = $data['name'] ?? $data['sku'];
			$postarr['post_status'] = settings()['default_status'];
		}
		if ( null !== ( $data['name'] ?? null ) ) {
			$postarr['post_title'] = $data['name'];
		}
		if ( null !== ( $data['status'] ?? null ) ) {
			$postarr['post_status'] = $data['status'];
		}
		if ( null !== ( $data['description'] ?? null ) ) {
			$postarr['post_content'] = $data['description'];
		}

		$result = $id ? wp_update_post( wp_slash( $postarr ), true ) : wp_insert_post( wp_slash( $postarr ), true );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$id = (int) $result;

		update_post_meta( $id, '_acme_sku', $data['sku'] );
		if ( null !== ( $data['price'] ?? null ) ) {
			update_post_meta( $id, '_acme_price', (int) $data['price'] );
		}
		if ( null !== ( $data['stock'] ?? null ) ) {
			update_post_meta( $id, '_acme_stock', $data['stock'] );
		}
		if ( null !== ( $data['categories'] ?? null ) ) {
			$terms = wp_set_object_terms( $id, $data['categories'], Product_Type::TAXONOMY );
			if ( is_wp_error( $terms ) ) {
				return $terms;
			}
		}

		/**
		 * Fires after an imported product was saved.
		 *
		 * Our search index and the ERP sync listen to this.
		 *
		 * @param int   $id      Product ID.
		 * @param array $data    Product data.
		 * @param bool  $created Whether the product was created.
		 */
		do_action( 'acme_importer_product_saved', $id, $data, $created );

		return array(
			'id'      => $id,
			'created' => $created,
		);
	}
}
