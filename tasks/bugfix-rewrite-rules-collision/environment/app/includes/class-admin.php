<?php
/**
 * Admin tweaks.
 *
 * @package Acme\Docs
 */

namespace Acme\Docs;

defined( 'ABSPATH' ) || exit;

/**
 * Docs list filters and the parent dropdown.
 */
class Admin {

	/**
	 * Hooks.
	 */
	public function register() {
		add_action( 'restrict_manage_posts', array( $this, 'product_filter' ) );
		add_filter( 'page_attributes_dropdown_pages_args', array( $this, 'parent_dropdown_args' ), 10, 2 );
		add_filter( 'quick_edit_dropdown_pages_args', array( $this, 'parent_dropdown_args' ), 10, 2 );
		add_filter( 'display_post_states', array( $this, 'post_states' ), 10, 2 );
	}

	/**
	 * "All products" dropdown above the docs list.
	 *
	 * @param string $post_type Post type.
	 */
	public function product_filter( $post_type ) {
		if ( Post_Types::DOC !== $post_type ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- List filter.
		$current = isset( $_GET[ Post_Types::PRODUCT ] ) ? sanitize_title( wp_unslash( $_GET[ Post_Types::PRODUCT ] ) ) : '';
		wp_dropdown_categories(
			array(
				'taxonomy'        => Post_Types::PRODUCT,
				'name'            => Post_Types::PRODUCT,
				'value_field'     => 'slug',
				'selected'        => $current,
				'show_option_all' => __( 'All products', 'acme-docs' ),
				'hide_empty'      => false,
			)
		);
	}

	/**
	 * Only offer parents of the same product in the "Parent" dropdown.
	 *
	 * @param array    $args Dropdown args.
	 * @param \WP_Post $post Post being edited.
	 * @return array
	 */
	public function parent_dropdown_args( $args, $post ) {
		if ( ! $post || Post_Types::DOC !== $post->post_type ) {
			return $args;
		}
		$product = Permalinks::get_product( $post );
		if ( $product ) {
			$others = get_posts(
				array(
					'post_type'      => Post_Types::DOC,
					'post_status'    => 'any',
					'fields'         => 'ids',
					'posts_per_page' => -1,
					'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
						array(
							'taxonomy' => Post_Types::PRODUCT,
							'terms'    => array( $product->term_id ),
							'operator' => 'NOT IN',
						),
					),
				)
			);
			$args['exclude'] = implode( ',', array_map( 'intval', $others ) );
		}
		return $args;
	}

	/**
	 * Shows the version next to versioned docs in the list.
	 *
	 * @param string[] $states States.
	 * @param \WP_Post $post   Post.
	 * @return string[]
	 */
	public function post_states( $states, $post ) {
		if ( Post_Types::DOC === $post->post_type ) {
			$version = Permalinks::get_version( $post );
			if ( $version ) {
				$states['acme_docs_version'] = $version;
			}
		}
		return $states;
	}
}
