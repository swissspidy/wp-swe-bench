<?php
/**
 * Team member value object.
 *
 * @package Acme\Team
 */

namespace Acme\Team;

defined( 'ABSPATH' ) || exit;

/**
 * Read access to a member's fields (with 1.x fallbacks).
 *
 * 1.x stored role, email and phone in one serialized `acme_member_meta`
 * array. 2.0 moved every field to its own meta key; members that were never
 * edited since still only have the old array, so reads fall back to it.
 */
class Member {

	/**
	 * The member post.
	 *
	 * @var \WP_Post
	 */
	private $post;

	/**
	 * Constructor.
	 *
	 * @param \WP_Post $post Member post.
	 */
	private function __construct( \WP_Post $post ) {
		$this->post = $post;
	}

	/**
	 * Get a member by ID or post. Returns null for anything that isn't a member.
	 *
	 * @param int|\WP_Post $member Post ID or object.
	 * @return Member|null
	 */
	public static function get( $member ) {
		$post = get_post( $member );
		if ( ! $post || Post_Type::POST_TYPE !== $post->post_type ) {
			return null;
		}
		return new self( $post );
	}

	/**
	 * Member post ID.
	 *
	 * @return int
	 */
	public function id() {
		return (int) $this->post->ID;
	}

	/**
	 * The underlying post.
	 *
	 * @return \WP_Post
	 */
	public function post() {
		return $this->post;
	}

	/**
	 * Can this member be shown to anyone visiting the site?
	 *
	 * Only published members without a password. Drafts, pending, private,
	 * scheduled and password-protected members are internal.
	 *
	 * @return bool
	 */
	public function is_public() {
		return 'publish' === $this->post->post_status && '' === $this->post->post_password;
	}

	/**
	 * Raw value of a field (as stored), or '' if not set.
	 *
	 * @param string $key Field key (see acme_team_fields()).
	 * @return string
	 */
	public function get_field( $key ) {
		$fields = acme_team_fields();
		if ( ! isset( $fields[ $key ] ) ) {
			return '';
		}
		if ( 'name' === $key ) {
			return (string) $this->post->post_title;
		}

		$value = get_post_meta( $this->post->ID, $fields[ $key ]['meta_key'], true );
		if ( ( '' === $value || false === $value ) && ! empty( $fields[ $key ]['legacy'] ) ) {
			$legacy = get_post_meta( $this->post->ID, 'acme_member_meta', true );
			if ( is_array( $legacy ) && isset( $legacy[ $fields[ $key ]['legacy'] ] ) ) {
				$value = $legacy[ $fields[ $key ]['legacy'] ];
			}
		}
		return is_scalar( $value ) ? trim( (string) $value ) : '';
	}

	/**
	 * Name (post title).
	 *
	 * @return string
	 */
	public function name() {
		return $this->get_field( 'name' );
	}

	/**
	 * Role title.
	 *
	 * @return string
	 */
	public function role() {
		return $this->get_field( 'role' );
	}

	/**
	 * Email address.
	 *
	 * @return string
	 */
	public function email() {
		return $this->get_field( 'email' );
	}

	/**
	 * Phone number (as entered).
	 *
	 * @return string
	 */
	public function phone() {
		return $this->get_field( 'phone' );
	}

	/**
	 * `tel:` link for the phone number, or ''.
	 *
	 * @return string
	 */
	public function phone_href() {
		$digits = acme_team_phone_digits( $this->phone() );
		return '' === $digits ? '' : 'tel:' . $digits;
	}

	/**
	 * Photo attachment ID (0 if none or not an image).
	 *
	 * @return int
	 */
	public function photo_id() {
		$id = (int) $this->get_field( 'photo' );
		return ( $id > 0 && wp_attachment_is_image( $id ) ) ? $id : 0;
	}

	/**
	 * Photo URL.
	 *
	 * @param string $size Image size.
	 * @return string
	 */
	public function photo_url( $size = 'full' ) {
		$id = $this->photo_id();
		if ( ! $id ) {
			return '';
		}
		$src = wp_get_attachment_image_src( $id, $size );
		return $src ? (string) $src[0] : '';
	}

	/**
	 * Alternative text of the photo; the member's name if the photo has none.
	 *
	 * @return string
	 */
	public function photo_alt() {
		$id = $this->photo_id();
		if ( ! $id ) {
			return '';
		}
		$alt = trim( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) );
		return '' !== $alt ? $alt : $this->name();
	}

	/**
	 * Profile URL (http/https only).
	 *
	 * @return string
	 */
	public function profile_url() {
		return esc_url_raw( $this->get_field( 'profile_url' ), array( 'http', 'https' ) );
	}

	/**
	 * Internal HR notes. Never shown on the front end.
	 *
	 * @return string
	 */
	public function internal_notes() {
		return (string) get_post_meta( $this->post->ID, Meta_Box::NOTES_KEY, true );
	}
}
