<?php
/**
 * Product lookups used by the grid.
 *
 * @package Acme\Catalog
 */

namespace Acme\Catalog;

defined( 'ABSPATH' ) || exit;

/**
 * Loads products and product categories as plain arrays.
 */
class Products {

	/**
	 * Published products, optionally limited to some categories.
	 *
	 * @param array $args {
	 *     @type string[] $categories Category slugs (empty: all products).
	 *     @type string   $order_by   'title', 'price' or 'date'.
	 * }
	 * @return array<int, array{id:int, title:string, url:string, sku:string, price:float, price_html:string, categories:string[]}>
	 */
	public static function query( array $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'categories' => array(),
				'order_by'   => 'title',
			)
		);

		$query_args = array(
			'post_type'              => Post_Types::POST_TYPE,
			'post_status'            => 'publish',
			'posts_per_page'         => 200,
			'no_found_rows'          => true,
			'update_post_term_cache' => true,
			'orderby'                => 'title',
			'order'                  => 'ASC',
		);
		if ( 'date' === $args['order_by'] ) {
			$query_args['orderby'] = 'date';
			$query_args['order']   = 'DESC';
		}
		if ( $args['categories'] ) {
			$query_args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => Post_Types::TAXONOMY,
					'field'    => 'slug',
					'terms'    => $args['categories'],
				),
			);
		}

		/**
		 * Filters the WP_Query arguments used to load the products of a grid.
		 *
		 * @since 1.2.0
		 *
		 * @param array $query_args WP_Query arguments.
		 * @param array $args       Grid arguments.
		 */
		$query_args = apply_filters( 'acme_catalog_product_query_args', $query_args, $args );

		$products = array();
		foreach ( get_posts( $query_args ) as $post ) {
			$products[] = self::to_array( $post );
		}

		if ( 'price' === $args['order_by'] ) {
			usort(
				$products,
				static function ( $a, $b ) {
					return $a['price'] <=> $b['price'] ?: strcasecmp( $a['title'], $b['title'] );
				}
			);
		}
		return $products;
	}

	/**
	 * A product as an array.
	 *
	 * @param \WP_Post $post Product post.
	 * @return array
	 */
	public static function to_array( \WP_Post $post ) {
		$terms = get_the_terms( $post, Post_Types::TAXONOMY );
		$price = (float) get_post_meta( $post->ID, '_acme_price', true );
		$data  = array(
			'id'         => $post->ID,
			'title'      => get_the_title( $post ),
			'url'        => get_permalink( $post ),
			'sku'        => (string) get_post_meta( $post->ID, '_acme_sku', true ),
			'price'      => $price,
			'price_html' => format_price( $price ),
			'categories' => is_array( $terms ) ? wp_list_pluck( $terms, 'slug' ) : array(),
		);

		/**
		 * Filters the data of a product shown in a grid.
		 *
		 * @since 1.0.0
		 *
		 * @param array    $data Product data.
		 * @param \WP_Post $post Product.
		 */
		return apply_filters( 'acme_catalog_product_data', $data, $post );
	}

	/**
	 * Categories to show as filters.
	 *
	 * @param string[] $slugs Limit to these slugs (in this order); empty: all non-empty categories by name.
	 * @return array<int, array{slug:string, name:string}>
	 */
	public static function categories( array $slugs = array() ) {
		if ( $slugs ) {
			$out = array();
			foreach ( $slugs as $slug ) {
				$term = get_term_by( 'slug', $slug, Post_Types::TAXONOMY );
				if ( $term ) {
					$out[] = array(
						'slug' => $term->slug,
						'name' => $term->name,
					);
				}
			}
			return $out;
		}
		$terms = get_terms(
			array(
				'taxonomy'   => Post_Types::TAXONOMY,
				'hide_empty' => true,
				'orderby'    => 'name',
			)
		);
		if ( is_wp_error( $terms ) ) {
			return array();
		}
		return array_map(
			static function ( $term ) {
				return array(
					'slug' => $term->slug,
					'name' => $term->name,
				);
			},
			$terms
		);
	}
}
