<?php
/**
 * Team member binding source.
 *
 * @package Acme\Team
 */

namespace Acme\Team;

defined( 'ABSPATH' ) || exit;

add_action(
	'init',
	static function () {
		register_block_bindings_source(
			'acme/team-member',
			array(
				'label'              => __( 'Team member', 'acme-team' ),
				'uses_context'       => array( 'postId' ),
				'get_value_callback' => static function ( array $args, $block, $attribute ) {
					$id  = isset( $args['memberId'] ) ? (int) $args['memberId'] : (int) ( $block->context['postId'] ?? 0 );
					$key = (string) ( $args['key'] ?? '' );
					if ( ! $id || '' === $key ) {
						return null;
					}
					$member = Member::get( $id );
					if ( ! $member ) {
						return null;
					}
					if ( 'photo' === $key ) {
						return 'url' === $attribute ? $member->photo_url() : $member->photo_alt();
					}
					$value = $member->get_field( $key );
					if ( 'url' === $attribute ) {
						if ( 'email' === $key ) {
							$value = 'mailto:' . $value;
						} elseif ( 'phone' === $key ) {
							$value = $member->phone_href();
						} elseif ( 'name' === $key ) {
							$value = get_permalink( $id );
						}
					}
					return '' === $value ? null : $value;
				},
			)
		);
	}
);
