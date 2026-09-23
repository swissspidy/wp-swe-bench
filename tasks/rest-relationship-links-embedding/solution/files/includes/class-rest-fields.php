<?php
/**
 * Relations on the core endpoints:
 *
 *  - `authors` field + `acme:author` links on /wp/v2/books,
 *  - `books` field + `acme:book` links on /wp/v2/authors,
 *  - `book_author` filter on the /wp/v2/books collection.
 *
 * Relations are loaded for a whole page of results at once (see prime_results()), so
 * collections and `_embed` don't run queries per item.
 *
 * @package Acme\Library
 */

namespace Acme\Library;

use WP_Error;
use WP_Post;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * REST integration.
 */
class REST_Fields {

	const CURIE      = 'acme';
	const REL_BASE   = 'https://api.acme-publishing.example/rels/';
	const REL_AUTHOR = self::REL_BASE . 'author';
	const REL_BOOK   = self::REL_BASE . 'book';
	const FILTER     = 'book_author';

	/**
	 * Hooks.
	 */
	public function hooks(): void {
		add_action( 'rest_api_init', array( $this, 'register_fields' ) );
		add_filter( 'rest_response_link_curies', array( $this, 'curies' ) );
		add_filter( 'rest_prepare_' . Post_Types::BOOK, array( $this, 'book_links' ), 10, 2 );
		add_filter( 'rest_prepare_' . Post_Types::AUTHOR, array( $this, 'author_links' ), 10, 2 );
		add_filter( 'rest_' . Post_Types::BOOK . '_collection_params', array( $this, 'collection_params' ) );
		add_filter( 'rest_' . Post_Types::BOOK . '_query', array( $this, 'filter_query' ), 10, 2 );
		add_filter( 'the_posts', array( $this, 'prime_results' ), 10, 2 );
	}

	/**
	 * CURIE for our link relations.
	 *
	 * @param array $curies CURIEs.
	 */
	public function curies( array $curies ): array {
		$curies[] = array(
			'name'      => self::CURIE,
			'href'      => self::REL_BASE . '{rel}',
			'templated' => true,
		);
		return $curies;
	}

	/**
	 * Registers `authors` / `books`.
	 */
	public function register_fields(): void {
		register_rest_field(
			Post_Types::BOOK,
			'authors',
			array(
				'get_callback'    => static fn( $data ) => Visibility::book_authors( (int) $data['id'] ),
				'update_callback' => array( $this, 'update_book_authors' ),
				'schema'          => array(
					'description' => __( 'Author posts of the book, in order.', 'acme-library' ),
					'type'        => 'array',
					'items'       => array( 'type' => 'integer' ),
					'context'     => array( 'view', 'edit' ),
					'arg_options' => array(
						'validate_callback' => array( $this, 'validate_author_ids' ),
						'sanitize_callback' => array( $this, 'sanitize_ids' ),
					),
				),
			)
		);

		register_rest_field(
			Post_Types::AUTHOR,
			'books',
			array(
				'get_callback'    => static fn( $data ) => Visibility::author_books( (int) $data['id'] ),
				'update_callback' => array( $this, 'update_author_books' ),
				'schema'          => array(
					'description' => __( 'Books of the author.', 'acme-library' ),
					'type'        => 'array',
					'items'       => array( 'type' => 'integer' ),
					'context'     => array( 'view', 'edit' ),
					'arg_options' => array(
						'validate_callback' => array( $this, 'validate_book_ids' ),
						'sanitize_callback' => array( $this, 'sanitize_ids' ),
					),
				),
			)
		);
	}

	/**
	 * Unique positive integers, order kept.
	 *
	 * @param mixed $value Value.
	 * @return int[]
	 */
	public function sanitize_ids( $value ): array {
		return Relationships::parse_legacy( is_array( $value ) ? $value : wp_parse_id_list( $value ) );
	}

	/**
	 * Shape check shared by both fields.
	 *
	 * @param mixed  $value Value.
	 * @param string $param Param name.
	 * @return true|WP_Error
	 */
	private function validate_shape( $value, string $param ) {
		if ( ! is_array( $value ) || ! array_is_list( $value ) ) {
			/* translators: %s: parameter name. */
			return new WP_Error( 'rest_invalid_type', sprintf( __( '%s must be a list of post IDs.', 'acme-library' ), $param ) );
		}
		foreach ( $value as $id ) {
			if ( ! is_int( $id ) && ! ( is_string( $id ) && ctype_digit( $id ) ) ) {
				/* translators: %s: parameter name. */
				return new WP_Error( 'rest_invalid_type', sprintf( __( '%s must be a list of post IDs.', 'acme-library' ), $param ) );
			}
		}
		return true;
	}

	/**
	 * `authors`: existing author posts the user can see.
	 *
	 * @param mixed           $value   Value.
	 * @param WP_REST_Request $request Request.
	 * @param string          $param   Param.
	 * @return true|WP_Error
	 */
	public function validate_author_ids( $value, $request, $param ) {
		$valid = $this->validate_shape( $value, $param );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}
		return self::check_author_ids( array_map( 'intval', $value ) );
	}

	/**
	 * Every ID must be an author the current user can see. Also used by the relation routes.
	 *
	 * @param int[] $ids IDs.
	 * @return true|WP_Error
	 */
	public static function check_author_ids( array $ids ) {
		foreach ( $ids as $id ) {
			if ( ! Visibility::can_see( $id, Post_Types::AUTHOR ) ) {
				return new WP_Error(
					'acme_library_invalid_author',
					/* translators: %d: post ID. */
					sprintf( __( '%d is not an author.', 'acme-library' ), $id ),
					array( 'status' => 400 )
				);
			}
		}
		return true;
	}

	/**
	 * `books`: existing books the user can edit (their author list changes).
	 *
	 * @param mixed           $value   Value.
	 * @param WP_REST_Request $request Request.
	 * @param string          $param   Param.
	 * @return true|WP_Error
	 */
	public function validate_book_ids( $value, $request, $param ) {
		$valid = $this->validate_shape( $value, $param );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}
		foreach ( array_map( 'intval', $value ) as $id ) {
			$book = get_post( $id );
			if ( ! $book || Post_Types::BOOK !== $book->post_type || 'trash' === $book->post_status || ! current_user_can( 'edit_post', $id ) ) {
				return new WP_Error(
					'acme_library_invalid_book',
					/* translators: %d: post ID. */
					sprintf( __( '%d is not a book you can edit.', 'acme-library' ), $id )
				);
			}
		}
		return true;
	}

	/**
	 * Saves `authors`.
	 *
	 * @param int[]   $ids  Author IDs.
	 * @param WP_Post $post Book.
	 * @return true|WP_Error
	 */
	public function update_book_authors( $ids, $post ) {
		$ids = $this->sanitize_ids( $ids );
		if ( Relationships::get_author_ids( $post->ID ) !== $ids ) {
			Relationships::set_author_ids( $post->ID, $ids );
		}
		return true;
	}

	/**
	 * Saves `books`: adds/removes this author on each changed book.
	 *
	 * @param int[]   $ids  Book IDs.
	 * @param WP_Post $post Author.
	 * @return true|WP_Error
	 */
	public function update_author_books( $ids, $post ) {
		$ids     = $this->sanitize_ids( $ids );
		$current = Relationships::get_book_ids( $post->ID );

		foreach ( array_diff( $ids, $current ) as $book_id ) {
			$authors   = Relationships::get_author_ids( $book_id );
			$authors[] = $post->ID;
			Relationships::set_author_ids( $book_id, $authors );
		}
		foreach ( array_diff( $current, $ids ) as $book_id ) {
			if ( ! current_user_can( 'edit_post', $book_id ) ) {
				continue; // Not ours to change (e.g. someone else's draft).
			}
			Relationships::set_author_ids( $book_id, array_values( array_diff( Relationships::get_author_ids( $book_id ), array( $post->ID ) ) ) );
		}
		return true;
	}

	/**
	 * `acme:author` links.
	 *
	 * @param WP_REST_Response $response Response.
	 * @param WP_Post          $post     Book.
	 */
	public function book_links( $response, $post ) {
		foreach ( Visibility::book_authors( $post->ID ) as $author_id ) {
			$response->add_link( self::REL_AUTHOR, rest_url( 'wp/v2/authors/' . $author_id ), array( 'embeddable' => true ) );
		}
		return $response;
	}

	/**
	 * `acme:book` links.
	 *
	 * @param WP_REST_Response $response Response.
	 * @param WP_Post          $post     Author.
	 */
	public function author_links( $response, $post ) {
		foreach ( Visibility::author_books( $post->ID ) as $book_id ) {
			$response->add_link( self::REL_BOOK, rest_url( 'wp/v2/books/' . $book_id ), array( 'embeddable' => true ) );
		}
		return $response;
	}

	/**
	 * `book_author` collection parameter.
	 *
	 * @param array $params Params.
	 */
	public function collection_params( array $params ): array {
		$params[ self::FILTER ] = array(
			'description' => __( 'Limit the result to books by any of these author posts (IDs of `author` posts, not users).', 'acme-library' ),
			'type'        => 'array',
			'items'       => array( 'type' => 'integer' ),
			'default'     => array(),
		);
		return $params;
	}

	/**
	 * Applies `book_author`.
	 *
	 * @param array           $args    WP_Query args.
	 * @param WP_REST_Request $request Request.
	 */
	public function filter_query( array $args, $request ): array {
		$authors = array_filter( array_map( 'intval', (array) $request[ self::FILTER ] ) );
		if ( ! $authors ) {
			return $args;
		}
		$authors = Visibility::filter( array_values( array_unique( $authors ) ), Post_Types::AUTHOR );
		$books   = $authors ? Relationships::get_book_ids_for_authors( $authors ) : array();
		if ( ! empty( $args['post__in'] ) ) {
			$books = array_values( array_intersect( $books, array_map( 'intval', $args['post__in'] ) ) );
		}
		$args['post__in'] = $books ? $books : array( 0 );
		return $args;
	}

	/**
	 * Loads relations (and the related posts) for a whole page of books/authors.
	 *
	 * @param WP_Post[] $posts Posts.
	 * @param \WP_Query $query Query.
	 */
	public function prime_results( $posts, $query ) {
		if ( ! $posts || ! is_array( $posts ) ) {
			return $posts;
		}
		$books   = array();
		$authors = array();
		foreach ( $posts as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}
			if ( Post_Types::BOOK === $post->post_type ) {
				$books[] = $post->ID;
			} elseif ( Post_Types::AUTHOR === $post->post_type ) {
				$authors[] = $post->ID;
			}
		}
		if ( ! $books && ! $authors ) {
			return $posts;
		}

		// Relations of this page, and of the related posts (their links are built when embedded).
		Relationships::prime_books( $books );
		$related_authors = $authors;
		foreach ( $books as $book_id ) {
			$related_authors = array_merge( $related_authors, Relationships::get_author_ids( $book_id ) );
		}
		Relationships::prime_authors( $related_authors );
		$related_books = $books;
		foreach ( array_unique( $related_authors ) as $author_id ) {
			$related_books = array_merge( $related_books, Relationships::get_book_ids( $author_id ) );
		}
		Relationships::prime_books( $related_books );

		$ids = array_diff( array_unique( array_merge( $related_authors, $related_books ) ), $books, $authors );
		if ( $ids ) {
			_prime_post_caches( array_values( $ids ), false, true );
		}
		return $posts;
	}
}
