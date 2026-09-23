<?php
/**
 * Content types.
 *
 * @package Acme\Docs
 */

namespace Acme\Docs;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the `doc` post type and the `product` and `doc_version` taxonomies.
 *
 * - Every doc belongs to one product (`product` taxonomy).
 * - Docs are hierarchical: a doc's URL contains the slugs of its parents.
 * - Docs of an older/newer major version have a `doc_version` term (`v1`, `v2`, …). Docs without a
 *   version are the current docs.
 */
class Post_Types {

	const DOC     = 'doc';
	const PRODUCT = 'product';
	const VERSION = 'doc_version';

	/**
	 * Registers everything.
	 */
	public static function register() {
		register_post_type(
			self::DOC,
			array(
				'labels'       => array(
					'name'          => __( 'Docs', 'acme-docs' ),
					'singular_name' => __( 'Doc', 'acme-docs' ),
					'add_new_item'  => __( 'Add New Doc', 'acme-docs' ),
					'edit_item'     => __( 'Edit Doc', 'acme-docs' ),
					'all_items'     => __( 'All Docs', 'acme-docs' ),
					'parent_item'   => __( 'Parent Doc', 'acme-docs' ),
				),
				'public'       => true,
				'hierarchical' => true,
				'show_in_rest' => true,
				'menu_icon'    => 'dashicons-book-alt',
				'supports'     => array( 'title', 'editor', 'excerpt', 'page-attributes', 'revisions', 'custom-fields' ),
				'has_archive'  => 'docs',
				// URLs are routed by Router (the generic rules WordPress would generate for
				// `docs/%product%/%doc%` swallow pages, product pagination and versions).
				'rewrite'      => false,
				'query_var'    => 'doc',
			)
		);

		register_taxonomy(
			self::PRODUCT,
			self::DOC,
			array(
				'labels'            => array(
					'name'          => __( 'Products', 'acme-docs' ),
					'singular_name' => __( 'Product', 'acme-docs' ),
				),
				'public'            => true,
				'hierarchical'      => false,
				'show_in_rest'      => true,
				'show_admin_column' => true,
				'rewrite'           => false,
				'query_var'         => self::PRODUCT,
			)
		);

		register_taxonomy(
			self::VERSION,
			self::DOC,
			array(
				'labels'            => array(
					'name'          => __( 'Versions', 'acme-docs' ),
					'singular_name' => __( 'Version', 'acme-docs' ),
				),
				'public'            => false,
				'show_ui'           => true,
				'show_in_rest'      => true,
				'show_admin_column' => true,
				'hierarchical'      => false,
				'rewrite'           => false,
			)
		);
	}
}
