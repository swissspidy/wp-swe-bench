<?php
/**
 * `book` and `author` post types (+ their meta).
 *
 * Both are in the REST API under the core posts controller:
 * /wp/v2/books and /wp/v2/authors. Note that `author` here is a *post type*
 * (the people who wrote the books), not a WordPress user. The `author` field and
 * query parameter of the core endpoints still refer to the WordPress user who
 * created the post.
 *
 * @package Acme\Library
 */

namespace Acme\Library;

defined( 'ABSPATH' ) || exit;

/**
 * Post type registration.
 */
class Post_Types {

	const BOOK   = 'book';
	const AUTHOR = 'author';

	/**
	 * Registers the post types and meta.
	 */
	public static function register(): void {
		register_post_type(
			self::BOOK,
			array(
				'labels'       => array(
					'name'          => __( 'Books', 'acme-library' ),
					'singular_name' => __( 'Book', 'acme-library' ),
					'add_new_item'  => __( 'Add new book', 'acme-library' ),
					'edit_item'     => __( 'Edit book', 'acme-library' ),
					'all_items'     => __( 'All books', 'acme-library' ),
					'search_items'  => __( 'Search books', 'acme-library' ),
					'not_found'     => __( 'No books found.', 'acme-library' ),
				),
				'public'       => true,
				'has_archive'  => 'books',
				'rewrite'      => array( 'slug' => 'books' ),
				'menu_icon'    => 'dashicons-book',
				'show_in_rest' => true,
				'rest_base'    => 'books',
				'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail', 'author', 'custom-fields' ),
			)
		);

		register_post_type(
			self::AUTHOR,
			array(
				'labels'       => array(
					'name'          => __( 'Authors', 'acme-library' ),
					'singular_name' => __( 'Author', 'acme-library' ),
					'add_new_item'  => __( 'Add new author', 'acme-library' ),
					'edit_item'     => __( 'Edit author', 'acme-library' ),
					'all_items'     => __( 'All authors', 'acme-library' ),
					'search_items'  => __( 'Search authors', 'acme-library' ),
					'not_found'     => __( 'No authors found.', 'acme-library' ),
				),
				'public'       => true,
				'has_archive'  => 'authors',
				'rewrite'      => array( 'slug' => 'writers' ),
				'menu_icon'    => 'dashicons-groups',
				'show_in_rest' => true,
				'rest_base'    => 'authors',
				'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail', 'author', 'custom-fields' ),
			)
		);

		register_post_meta(
			self::BOOK,
			'acme_isbn',
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => array( __CLASS__, 'sanitize_isbn' ),
				'auth_callback'     => static fn( $allowed, $key, $post_id ) => current_user_can( 'edit_post', $post_id ),
			)
		);
		register_post_meta(
			self::BOOK,
			'acme_year',
			array(
				'type'          => 'integer',
				'single'        => true,
				'show_in_rest'  => true,
				'auth_callback' => static fn( $allowed, $key, $post_id ) => current_user_can( 'edit_post', $post_id ),
			)
		);
		register_post_meta(
			self::AUTHOR,
			'acme_born',
			array(
				'type'          => 'integer',
				'single'        => true,
				'show_in_rest'  => true,
				'auth_callback' => static fn( $allowed, $key, $post_id ) => current_user_can( 'edit_post', $post_id ),
			)
		);
	}

	/**
	 * Keeps digits and X only.
	 *
	 * @param mixed $value Raw ISBN.
	 */
	public static function sanitize_isbn( $value ): string {
		return strtoupper( (string) preg_replace( '/[^0-9Xx]/', '', (string) $value ) );
	}
}
