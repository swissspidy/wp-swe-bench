<?php
/**
 * Per-post SEO fields (edited in the block editor sidebar, see src/editor).
 *
 * @package Acme\SEO
 */

namespace Acme\SEO;

defined( 'ABSPATH' ) || exit;

/**
 * Registers `_acme_seo_title`, `_acme_seo_description` and `_acme_seo_noindex`.
 */
class Post_Meta {

	const TITLE       = '_acme_seo_title';
	const DESCRIPTION = '_acme_seo_description';
	const NOINDEX     = '_acme_seo_noindex';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ), 20 );
	}

	/**
	 * Register the meta for every public post type that supports the editor.
	 */
	public static function register() {
		foreach ( get_post_types( array( 'public' => true ) ) as $post_type ) {
			if ( 'attachment' === $post_type || ! post_type_supports( $post_type, 'custom-fields' ) ) {
				continue;
			}
			$auth = static function ( $allowed, $meta_key, $post_id ) {
				return current_user_can( 'edit_post', $post_id );
			};
			register_post_meta(
				$post_type,
				self::TITLE,
				array(
					'type'              => 'string',
					'single'            => true,
					'default'           => '',
					'show_in_rest'      => true,
					'sanitize_callback' => 'sanitize_text_field',
					'auth_callback'     => $auth,
				)
			);
			register_post_meta(
				$post_type,
				self::DESCRIPTION,
				array(
					'type'              => 'string',
					'single'            => true,
					'default'           => '',
					'show_in_rest'      => true,
					'sanitize_callback' => 'sanitize_textarea_field',
					'auth_callback'     => $auth,
				)
			);
			register_post_meta(
				$post_type,
				self::NOINDEX,
				array(
					'type'          => 'boolean',
					'single'        => true,
					'default'       => false,
					'show_in_rest'  => true,
					'auth_callback' => $auth,
				)
			);
		}
	}
}
