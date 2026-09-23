<?php
/**
 * Post meta registration (REST API / block editor).
 *
 * @package Acme\ProductFields
 */

namespace Acme\ProductFields;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the sidebar fields as post meta so the block editor can read and write them through
 * the REST API (`meta` on /wp/v2/acme_product). Staff-only fields are not exposed.
 */
class Meta {

	/**
	 * Hooks.
	 */
	public function register_hooks() {
		add_action( 'init', array( $this, 'register' ), 20 );
	}

	/**
	 * Register the meta keys.
	 */
	public function register() {
		foreach ( Fields::all() as $key => $field ) {
			$is_checkbox = 'checkbox' === $field['type'];
			$args        = array(
				'type'              => $is_checkbox ? 'boolean' : 'string',
				'single'            => true,
				'default'           => $field['default'],
				'show_in_rest'      => (bool) $field['sidebar'],
				'sanitize_callback' => static function ( $value ) use ( $key, $is_checkbox ) {
					$value = Fields::sanitize( $key, $value );
					if ( $is_checkbox ) {
						return $value ? '1' : '';
					}
					return $value;
				},
				'auth_callback'     => static function ( $allowed, $meta_key, $object_id ) {
					return current_user_can( 'edit_post', $object_id );
				},
			);

			register_post_meta( Post_Type::POST_TYPE, $field['meta_key'], $args );
		}
	}
}
