<?php
/**
 * The glossary term post type and its "Short definition" field.
 *
 * @package Acme\Glossary
 */

namespace Acme\Glossary;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the `glossary_term` post type (edited in the classic screen with a meta box).
 */
class Post_Type {

	const NONCE = 'acme_glossary_short_nonce';

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_action( 'init', array( $this, 'register' ) );
		add_action( 'add_meta_boxes_' . ACME_GLOSSARY_POST_TYPE, array( $this, 'add_meta_box' ) );
		add_action( 'save_post_' . ACME_GLOSSARY_POST_TYPE, array( $this, 'save_meta' ), 10, 2 );
		add_filter( 'manage_' . ACME_GLOSSARY_POST_TYPE . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . ACME_GLOSSARY_POST_TYPE . '_posts_custom_column', array( $this, 'column' ), 10, 2 );
	}

	/**
	 * Register the post type.
	 */
	public function register() {
		register_post_type(
			ACME_GLOSSARY_POST_TYPE,
			array(
				'labels'       => array(
					'name'          => __( 'Glossary', 'acme-glossary' ),
					'singular_name' => __( 'Glossary term', 'acme-glossary' ),
					'add_new_item'  => __( 'Add new term', 'acme-glossary' ),
					'edit_item'     => __( 'Edit term', 'acme-glossary' ),
					'search_items'  => __( 'Search terms', 'acme-glossary' ),
					'not_found'     => __( 'No terms found.', 'acme-glossary' ),
					'menu_name'     => __( 'Glossary', 'acme-glossary' ),
				),
				'public'       => true,
				'has_archive'  => 'glossary',
				'rewrite'      => array( 'slug' => 'glossary' ),
				'menu_icon'    => 'dashicons-book-alt',
				'supports'     => array( 'title', 'editor', 'excerpt', 'revisions' ),
				// Terms are edited in the classic screen (short definition meta box).
				'show_in_rest' => false,
			)
		);
	}

	/**
	 * Add the "Short definition" meta box.
	 */
	public function add_meta_box() {
		add_meta_box( 'acme-glossary-short', __( 'Short definition', 'acme-glossary' ), array( $this, 'render_meta_box' ), null, 'normal', 'high' );
	}

	/**
	 * Render the meta box.
	 *
	 * @param \WP_Post $post Term.
	 */
	public function render_meta_box( $post ) {
		wp_nonce_field( 'acme_glossary_save_short', self::NONCE );
		printf(
			'<p><label for="acme-glossary-short-field">%s</label></p><textarea id="acme-glossary-short-field" name="acme_glossary_short" rows="3" class="large-text">%s</textarea>',
			esc_html__( 'One or two sentences, shown in tooltips and in the glossary index.', 'acme-glossary' ),
			esc_textarea( get_post_meta( $post->ID, ACME_GLOSSARY_SHORT_META, true ) )
		);
	}

	/**
	 * Save the short definition.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post.
	 */
	public function save_meta( $post_id, $post ) {
		if ( ! isset( $_POST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST[ self::NONCE ] ) ), 'acme_glossary_save_short' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$short = isset( $_POST['acme_glossary_short'] ) ? sanitize_textarea_field( wp_unslash( $_POST['acme_glossary_short'] ) ) : '';
		update_post_meta( $post_id, ACME_GLOSSARY_SHORT_META, $short );
	}

	/**
	 * Admin list columns.
	 *
	 * @param string[] $columns Columns.
	 * @return string[]
	 */
	public function columns( $columns ) {
		$columns['acme_glossary_short'] = __( 'Short definition', 'acme-glossary' );
		return $columns;
	}

	/**
	 * Admin list column content.
	 *
	 * @param string $column  Column.
	 * @param int    $post_id Post ID.
	 */
	public function column( $column, $post_id ) {
		if ( 'acme_glossary_short' === $column ) {
			echo esc_html( wp_trim_words( acme_glossary_get_definition( $post_id ), 15 ) );
		}
	}
}
