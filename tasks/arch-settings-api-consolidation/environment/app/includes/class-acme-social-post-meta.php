<?php
/**
 * Per-post "Hide share buttons" switch.
 *
 * @package Acme_Social
 */

defined( 'ABSPATH' ) || exit;

/**
 * Meta box + meta registration for `_acme_social_hide_buttons`.
 */
class Acme_Social_Post_Meta {

	const META_KEY = '_acme_social_hide_buttons';

	/**
	 * Hooks.
	 */
	public function register() {
		add_action( 'init', array( $this, 'register_meta' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'save_post', array( $this, 'save' ), 10, 2 );
	}

	/**
	 * Exposes the flag to the block editor.
	 */
	public function register_meta() {
		foreach ( acme_social_share_post_types() as $post_type ) {
			register_post_meta(
				$post_type,
				self::META_KEY,
				array(
					'type'          => 'boolean',
					'single'        => true,
					'show_in_rest'  => true,
					'auth_callback' => static function ( $allowed, $meta_key, $post_id ) {
						return current_user_can( 'edit_post', $post_id );
					},
				)
			);
		}
	}

	/**
	 * Classic meta box.
	 *
	 * @param string $post_type Post type.
	 */
	public function add_meta_box( $post_type ) {
		if ( ! in_array( $post_type, acme_social_share_post_types(), true ) ) {
			return;
		}
		add_meta_box(
			'acme-social',
			__( 'Share buttons', 'acme-social' ),
			array( $this, 'render_meta_box' ),
			$post_type,
			'side',
			'default',
			array( '__back_compat_meta_box' => true )
		);
	}

	/**
	 * Meta box content.
	 *
	 * @param WP_Post $post Post.
	 */
	public function render_meta_box( $post ) {
		wp_nonce_field( 'acme_social_post_meta', '_acme_social_meta_nonce' );
		printf(
			'<label><input type="checkbox" name="acme_social_hide_buttons" value="1" %1$s /> %2$s</label>',
			checked( (bool) get_post_meta( $post->ID, self::META_KEY, true ), true, false ),
			esc_html__( 'Hide share buttons on this post', 'acme-social' )
		);
	}

	/**
	 * Saves the meta box.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post.
	 */
	public function save( $post_id, $post ) {
		if ( ! isset( $_POST['_acme_social_meta_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_acme_social_meta_nonce'] ), 'acme_social_post_meta' ) ) {
			return;
		}
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( ! empty( $_POST['acme_social_hide_buttons'] ) ) {
			update_post_meta( $post_id, self::META_KEY, '1' );
		} else {
			delete_post_meta( $post_id, self::META_KEY );
		}
	}
}
