<?php
/**
 * "Authors" box on the book edit screen.
 *
 * @package Acme\Library
 */

namespace Acme\Library\Admin;

use Acme\Library\Post_Types;
use Acme\Library\Relationships;

defined( 'ABSPATH' ) || exit;

/**
 * Metabox.
 */
class Book_Authors_Metabox {

	const NONCE = 'acme_library_book_authors';

	/**
	 * Hooks.
	 */
	public function hooks(): void {
		add_action( 'add_meta_boxes_' . Post_Types::BOOK, array( $this, 'add' ) );
		add_action( 'save_post_' . Post_Types::BOOK, array( $this, 'save' ), 10, 2 );
	}

	/**
	 * Registers the box.
	 */
	public function add(): void {
		add_meta_box( 'acme-library-authors', __( 'Authors', 'acme-library' ), array( $this, 'render' ), Post_Types::BOOK, 'side' );
	}

	/**
	 * Renders the box: a checklist of authors + an order field.
	 *
	 * @param \WP_Post $post Book.
	 */
	public function render( $post ): void {
		$current = Relationships::get_author_ids( $post->ID );
		$authors = get_posts(
			array(
				'post_type'   => Post_Types::AUTHOR,
				'post_status' => array( 'publish', 'draft', 'private' ),
				'numberposts' => 500,
				'orderby'     => 'title',
				'order'       => 'ASC',
			)
		);
		wp_nonce_field( self::NONCE, self::NONCE . '_nonce' );
		echo '<p>' . esc_html__( 'Order (comma separated IDs):', 'acme-library' ) . '</p>';
		printf( '<input type="text" class="widefat" name="acme_library_author_order" value="%s" />', esc_attr( implode( ',', $current ) ) );
		echo '<ul class="acme-library-author-list" style="max-height:220px;overflow:auto">';
		foreach ( $authors as $author ) {
			printf(
				'<li><label><input type="checkbox" name="acme_library_authors[]" value="%d" %s /> %s%s</label></li>',
				(int) $author->ID,
				checked( in_array( $author->ID, $current, true ), true, false ),
				esc_html( get_the_title( $author ) ),
				'publish' === $author->post_status ? '' : ' <em>(' . esc_html( $author->post_status ) . ')</em>'
			);
		}
		echo '</ul>';
	}

	/**
	 * Saves the box.
	 *
	 * @param int      $post_id Book ID.
	 * @param \WP_Post $post    Book.
	 */
	public function save( $post_id, $post ): void {
		if ( ! isset( $_POST[ self::NONCE . '_nonce' ] ) || ! wp_verify_nonce( sanitize_key( $_POST[ self::NONCE . '_nonce' ] ), self::NONCE ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$checked = array_map( 'intval', (array) wp_unslash( $_POST['acme_library_authors'] ?? array() ) );
		$order   = Relationships::parse_legacy( sanitize_text_field( wp_unslash( $_POST['acme_library_author_order'] ?? '' ) ) );
		// Keep the typed order for checked authors, append newly checked ones.
		$ids = array_values( array_intersect( $order, $checked ) );
		foreach ( $checked as $id ) {
			if ( ! in_array( $id, $ids, true ) && Post_Types::AUTHOR === get_post_type( $id ) ) {
				$ids[] = $id;
			}
		}
		Relationships::set_author_ids( (int) $post_id, $ids );
	}
}
