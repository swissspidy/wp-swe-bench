<?php
/**
 * Extra columns on the Books / Authors list screens.
 *
 * @package Acme\Library\Admin
 */

namespace Acme\Library\Admin;

use Acme\Library\Book_Counts;
use Acme\Library\Post_Types;
use Acme\Library\Relationships;

defined( 'ABSPATH' ) || exit;

/**
 * Columns.
 */
class List_Columns {

	/**
	 * Hooks.
	 */
	public function hooks(): void {
		add_filter( 'manage_' . Post_Types::BOOK . '_posts_columns', array( $this, 'book_columns' ) );
		add_action( 'manage_' . Post_Types::BOOK . '_posts_custom_column', array( $this, 'book_column' ), 10, 2 );
		add_filter( 'manage_' . Post_Types::AUTHOR . '_posts_columns', array( $this, 'author_columns' ) );
		add_action( 'manage_' . Post_Types::AUTHOR . '_posts_custom_column', array( $this, 'author_column' ), 10, 2 );
	}

	/**
	 * Book columns.
	 *
	 * @param array $columns Columns.
	 */
	public function book_columns( array $columns ): array {
		return array_slice( $columns, 0, 2, true ) + array( 'acme_authors' => __( 'Authors', 'acme-library' ) ) + array_slice( $columns, 2, null, true );
	}

	/**
	 * Book column output.
	 *
	 * @param string $column  Column.
	 * @param int    $post_id Book ID.
	 */
	public function book_column( string $column, int $post_id ): void {
		if ( 'acme_authors' !== $column ) {
			return;
		}
		$names = array();
		foreach ( Relationships::get_author_ids( $post_id ) as $author_id ) {
			$names[] = sprintf( '<a href="%s">%s</a>', esc_url( (string) get_edit_post_link( $author_id ) ), esc_html( get_the_title( $author_id ) ) );
		}
		echo $names ? implode( ', ', $names ) : '&mdash;'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Author columns.
	 *
	 * @param array $columns Columns.
	 */
	public function author_columns( array $columns ): array {
		return array_slice( $columns, 0, 2, true ) + array( 'acme_books' => __( 'Books', 'acme-library' ) ) + array_slice( $columns, 2, null, true );
	}

	/**
	 * Author column output.
	 *
	 * @param string $column  Column.
	 * @param int    $post_id Author ID.
	 */
	public function author_column( string $column, int $post_id ): void {
		if ( 'acme_books' === $column ) {
			echo (int) get_post_meta( $post_id, Book_Counts::META, true );
		}
	}
}
