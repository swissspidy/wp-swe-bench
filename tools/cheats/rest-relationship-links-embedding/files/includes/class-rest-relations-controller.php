<?php
/**
 * The original relation endpoints (used by the mobile catalogue app and the
 * bookshop widget, which we can't update quickly):
 *
 *  - GET  /acme-library/v1/books/<id>/authors
 *  - POST /acme-library/v1/books/<id>/authors   {"authors": [12, 15]}
 *  - GET  /acme-library/v1/authors/<id>/books
 *
 * @package Acme\Library
 */

namespace Acme\Library;

use WP_Error;
use WP_Post;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Relation routes.
 */
class REST_Relations_Controller {

	/**
	 * Registers the routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			REST_NAMESPACE,
			'/books/(?P<id>\d+)/authors',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_book_authors' ),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'set_book_authors' ),
					'permission_callback' => array( $this, 'can_edit_book' ),
					'args'                => array(
						'authors' => array(
							'type'     => 'array',
							'items'    => array( 'type' => 'integer' ),
							'required' => true,
						),
					),
				),
			)
		);

		register_rest_route(
			REST_NAMESPACE,
			'/authors/(?P<id>\d+)/books',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_author_books' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Post of the given type, or an error.
	 *
	 * @param int    $id   Post ID.
	 * @param string $type Post type.
	 * @return WP_Post|WP_Error
	 */
	private function get_typed_post( int $id, string $type ) {
		$post = get_post( $id );
		if ( ! $post || $type !== $post->post_type ) {
			return new WP_Error( 'acme_library_not_found', __( 'Not found.', 'acme-library' ), array( 'status' => 404 ) );
		}
		return $post;
	}

	/**
	 * Permission to change a book's authors.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function can_edit_book( WP_REST_Request $request ) {
		$book = $this->get_typed_post( (int) $request['id'], Post_Types::BOOK );
		if ( is_wp_error( $book ) ) {
			return $book;
		}
		if ( ! current_user_can( 'edit_post', $book->ID ) ) {
			return new WP_Error( 'rest_forbidden', __( 'Sorry, you are not allowed to edit this book.', 'acme-library' ), array( 'status' => rest_authorization_required_code() ) );
		}
		return true;
	}

	/**
	 * Author summary as returned by these routes.
	 *
	 * @param WP_Post $author Author post.
	 * @param int     $position Position on the book.
	 */
	private function author_summary( WP_Post $author, int $position ): array {
		return array(
			'id'       => $author->ID,
			'name'     => get_the_title( $author ),
			'slug'     => $author->post_name,
			'link'     => get_permalink( $author ),
			'position' => $position,
		);
	}

	/**
	 * GET /books/<id>/authors.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_book_authors( WP_REST_Request $request ) {
		$book = $this->get_typed_post( (int) $request['id'], Post_Types::BOOK );
		if ( is_wp_error( $book ) ) {
			return $book;
		}
		$data = array();
		foreach ( Relationships::get_author_ids( $book->ID ) as $position => $author_id ) {
			$author = get_post( $author_id );
			if ( $author && Post_Types::AUTHOR === $author->post_type ) {
				$data[] = $this->author_summary( $author, $position );
			}
		}
		return new WP_REST_Response( $data );
	}

	/**
	 * POST /books/<id>/authors.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function set_book_authors( WP_REST_Request $request ) {
		$book = $this->get_typed_post( (int) $request['id'], Post_Types::BOOK );
		if ( is_wp_error( $book ) ) {
			return $book;
		}
		$ids = array();
		foreach ( (array) $request['authors'] as $author_id ) {
			$author = get_post( (int) $author_id );
			if ( ! $author || Post_Types::AUTHOR !== $author->post_type ) {
				return new WP_Error(
					'acme_library_invalid_author',
					/* translators: %d: post ID. */
					sprintf( __( '%d is not an author.', 'acme-library' ), (int) $author_id ),
					array( 'status' => 400 )
				);
			}
			$ids[] = $author->ID;
		}
		Relationships::set_author_ids( $book->ID, $ids );
		return $this->get_book_authors( $request );
	}

	/**
	 * GET /authors/<id>/books.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_author_books( WP_REST_Request $request ) {
		$author = $this->get_typed_post( (int) $request['id'], Post_Types::AUTHOR );
		if ( is_wp_error( $author ) ) {
			return $author;
		}
		$data = array();
		foreach ( Relationships::get_book_ids( $author->ID ) as $book_id ) {
			$book = get_post( $book_id );
			if ( ! $book || Post_Types::BOOK !== $book->post_type ) {
				continue;
			}
			$data[] = array(
				'id'    => $book->ID,
				'title' => get_the_title( $book ),
				'link'  => get_permalink( $book ),
				'year'  => (int) get_post_meta( $book->ID, 'acme_year', true ),
			);
		}
		return new WP_REST_Response( $data );
	}
}
