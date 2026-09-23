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
				'show_in_rest'      => $this->rest_args( $field ),
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

	/**
	 * REST API exposure of a field.
	 *
	 * @param array $field Field definition.
	 * @return bool|array
	 */
	private function rest_args( $field ) {
		if ( ! $field['sidebar'] ) {
			return false;
		}
		if ( 'checkbox' !== $field['type'] ) {
			return true;
		}
		return array(
			'schema'           => array( 'type' => 'boolean' ),
			// Products imported from 1.x store "yes"/"no" and "on"/"off": always expose real booleans.
			'prepare_callback' => static function ( $value ) {
				return Fields::to_bool( $value );
			},
		);
	}
}
