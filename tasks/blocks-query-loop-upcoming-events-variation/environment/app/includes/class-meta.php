<?php
/**
 * Event meta registration.
 *
 * @package Acme\Events
 */

namespace Acme\Events;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the event meta for the REST API (editor sidebar).
 */
class Meta {

	/**
	 * Register hooks.
	 */
	public function register() {
		add_action( 'init', array( $this, 'register_meta' ) );
	}

	/**
	 * Register the meta keys.
	 */
	public function register_meta() {
		$auth = static function ( $allowed, $meta_key, $post_id ) {
			return current_user_can( 'edit_post', $post_id );
		};

		$keys = array(
			META_START   => array(
				'type'              => 'integer',
				'default'           => 0,
				'sanitize_callback' => 'absint',
			),
			META_END     => array(
				'type'              => 'integer',
				'default'           => 0,
				'sanitize_callback' => 'absint',
			),
			META_ALL_DAY => array(
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
			),
			META_STATUS  => array(
				'type'              => 'string',
				'default'           => 'scheduled',
				'sanitize_callback' => array( $this, 'sanitize_status' ),
			),
			META_VENUE   => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
		);

		foreach ( $keys as $key => $args ) {
			register_post_meta(
				POST_TYPE,
				$key,
				array_merge(
					$args,
					array(
						'single'        => true,
						'show_in_rest'  => true,
						'auth_callback' => $auth,
					)
				)
			);
		}
	}

	/**
	 * Sanitize a status value.
	 *
	 * @param mixed $value Raw.
	 * @return string
	 */
	public function sanitize_status( $value ) {
		$value = sanitize_key( (string) $value );
		if ( 'canceled' === $value ) {
			$value = 'cancelled';
		}
		return in_array( $value, array( 'scheduled', 'postponed', 'cancelled' ), true ) ? $value : 'scheduled';
	}
}
