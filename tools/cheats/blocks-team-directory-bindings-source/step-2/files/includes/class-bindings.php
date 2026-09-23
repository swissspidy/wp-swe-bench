<?php
/**
 * "Team member" block bindings source (`acme/team-member`).
 *
 * Lets core blocks (paragraph, heading, image, button…) take their content
 * from a member's fields:
 *
 *     <!-- wp:paragraph {"metadata":{"bindings":{"content":{
 *         "source":"acme/team-member","args":{"key":"role","memberId":12}}}}} -->
 *
 * Without `memberId`, the member is the post in the block's context (Query
 * Loop, single member template).
 *
 * @package Acme\Team
 */

namespace Acme\Team;

defined( 'ABSPATH' ) || exit;

/**
 * Server side of the binding source.
 */
class Bindings {

	const SOURCE = 'acme/team-member';

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_action( 'init', array( $this, 'register' ) );
	}

	/**
	 * Register the source.
	 */
	public function register() {
		if ( ! function_exists( 'register_block_bindings_source' ) ) {
			return;
		}
		register_block_bindings_source(
			self::SOURCE,
			array(
				'label'              => _x( 'Team member', 'block bindings source', 'acme-team' ),
				'get_value_callback' => array( $this, 'get_value' ),
				'uses_context'       => array( 'postId', 'postType' ),
			)
		);
	}

	/**
	 * Resolve the member a binding refers to.
	 *
	 * @param array          $args  Source args.
	 * @param \WP_Block|null $block Block instance.
	 * @return Member|null
	 */
	public static function resolve_member( array $args, $block = null ) {
		if ( isset( $args['memberId'] ) && '' !== $args['memberId'] ) {
			$id = $args['memberId'];
			if ( is_string( $id ) && ctype_digit( $id ) ) {
				$id = (int) $id;
			}
			if ( ! is_int( $id ) || $id <= 0 ) {
				return null;
			}
			return Member::get( $id );
		}

		$context = ( $block instanceof \WP_Block ) ? $block->context : array();
		if ( empty( $context['postId'] ) ) {
			return null;
		}
		if ( isset( $context['postType'] ) && Post_Type::POST_TYPE !== $context['postType'] ) {
			return null;
		}
		return Member::get( (int) $context['postId'] );
	}

	/**
	 * Raw (unescaped) value of a binding for a public member, or null.
	 *
	 * @param Member $member    Member.
	 * @param string $key       Field key.
	 * @param string $attribute Bound attribute name.
	 * @return string|null
	 */
	public static function raw_value( Member $member, $key, $attribute ) {
		$fields = acme_team_fields();
		if ( ! is_string( $key ) || ! isset( $fields[ $key ] ) ) {
			return null;
		}

		if ( 'url' === $attribute ) {
			switch ( $key ) {
				case 'email':
					$email = sanitize_email( $member->email() );
					$value = $email ? 'mailto:' . $email : '';
					break;
				case 'phone':
					$value = $member->phone_href();
					break;
				case 'profile_url':
					$value = $member->profile_url();
					break;
				case 'name':
					$value = (string) get_permalink( $member->post() );
					break;
				case 'photo':
					$value = $member->photo_url( 'full' );
					break;
				default:
					$value = '';
			}
			return '' === $value ? null : $value;
		}

		if ( in_array( $attribute, array( 'id', 'linkTarget', 'rel', 'datetime' ), true ) ) {
			return null;
		}

		if ( 'photo' === $key ) {
			$value = $member->photo_alt();
		} else {
			$value = $member->get_field( $key );
		}
		return '' === $value ? null : $value;
	}

	/**
	 * Binding value callback.
	 *
	 * @param array     $source_args    Source args.
	 * @param \WP_Block $block_instance Block instance.
	 * @param string    $attribute_name Attribute name.
	 * @return string|null Null keeps the block's saved content.
	 */
	public function get_value( array $source_args, $block_instance, string $attribute_name ) {
		$member = self::resolve_member( $source_args, $block_instance );
		if ( ! $member || ! $member->is_public() ) {
			return null;
		}
		$value = self::raw_value( $member, $source_args['key'] ?? '', $attribute_name );
		if ( null === $value ) {
			return null;
		}

		// Rich text attributes are inserted as HTML: the value must be shown as text.
		$source = $block_instance->block_type->attributes[ $attribute_name ]['source'] ?? '';
		if ( in_array( $source, array( 'html', 'rich-text' ), true ) ) {
			return esc_html( $value );
		}
		if ( 'url' === $attribute_name ) {
			return esc_url_raw( $value, array( 'http', 'https', 'mailto', 'tel' ) );
		}
		// HTML attributes (alt, title) are escaped when they are written.
		return $value;
	}
}
