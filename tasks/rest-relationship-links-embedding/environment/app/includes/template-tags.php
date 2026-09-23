<?php
/**
 * Template tags used by the Acme Publishing theme.
 *
 * @package Acme\Library
 */

namespace Acme\Library;

defined( 'ABSPATH' ) || exit;

/**
 * Published author posts of a book, in order.
 *
 * @param int $book_id Book ID.
 * @return \WP_Post[]
 */
function get_book_authors( int $book_id ): array {
	$authors = array();
	foreach ( Relationships::get_author_ids( $book_id ) as $author_id ) {
		$author = get_post( $author_id );
		if ( $author && Post_Types::AUTHOR === $author->post_type && 'publish' === $author->post_status ) {
			$authors[] = $author;
		}
	}
	return $authors;
}

/**
 * "By Jane Doe and John Roe" byline markup.
 *
 * @param int $book_id Book ID.
 */
function book_byline( int $book_id ): string {
	$links = array();
	foreach ( get_book_authors( $book_id ) as $author ) {
		$links[] = sprintf( '<a class="acme-book-author" href="%s">%s</a>', esc_url( get_permalink( $author ) ), esc_html( get_the_title( $author ) ) );
	}
	if ( ! $links ) {
		return '';
	}
	/* translators: %s: list of author names. */
	return '<p class="acme-book-byline">' . sprintf( __( 'By %s', 'acme-library' ), wp_sprintf( '%l', $links ) ) . '</p>';
}

/**
 * Adds the byline to single book pages.
 *
 * @param string $content Content.
 */
function filter_book_content( $content ) {
	if ( is_singular( Post_Types::BOOK ) && in_the_loop() && is_main_query() ) {
		$content = book_byline( get_the_ID() ) . $content;
	}
	return $content;
}
