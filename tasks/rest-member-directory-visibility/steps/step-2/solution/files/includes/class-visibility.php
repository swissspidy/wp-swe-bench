<?php
/**
 * Who may see which profile and which field.
 *
 * Levels, from most to least open: public (everyone), members (logged-in directory members, i.e.
 * users with the `read_acme_members` capability), connections (members the member is connected
 * with, see Connections), private (only the member themself and people who can edit users).
 *
 * Storage:
 * - profile level: user meta `acme_member_visibility` (2.x). 1.x: `acme_member_hide_profile` = '1'
 *   meant private.
 * - field levels: user meta `acme_member_field_visibility`, array field => level (2.x). Fields
 *   without an entry use the field's default. 1.x: `acme_member_hide_phone` = '1' meant the phone
 *   number was private.
 *
 * @package Acme\Members
 */

namespace Acme\Members;

defined( 'ABSPATH' ) || exit;

/**
 * Visibility rules.
 */
class Visibility {

	const PUBLIC_LEVEL      = 'public';
	const MEMBERS_LEVEL     = 'members';
	const CONNECTIONS_LEVEL = 'connections';
	const PRIVATE_LEVEL     = 'private';

	/**
	 * Levels ordered from open to closed.
	 *
	 * @return array<string, string> level => label
	 */
	public static function levels() {
		return array(
			self::PUBLIC_LEVEL      => __( 'Everyone', 'acme-members' ),
			self::MEMBERS_LEVEL     => __( 'Members only', 'acme-members' ),
			self::CONNECTIONS_LEVEL => __( 'My connections', 'acme-members' ),
			self::PRIVATE_LEVEL     => __( 'Only me', 'acme-members' ),
		);
	}

	/**
	 * Numeric rank of a level.
	 *
	 * @param string $level Level.
	 * @return int
	 */
	public static function rank( $level ) {
		$ranks = array(
			self::PUBLIC_LEVEL      => 0,
			self::MEMBERS_LEVEL     => 1,
			self::CONNECTIONS_LEVEL => 2,
			self::PRIVATE_LEVEL     => 3,
		);
		return isset( $ranks[ $level ] ) ? $ranks[ $level ] : 3;
	}

	/**
	 * Is this a valid level?
	 *
	 * @param mixed $level Level.
	 * @return bool
	 */
	public static function is_level( $level ) {
		return is_string( $level ) && array_key_exists( $level, self::levels() );
	}

	/**
	 * Profile level of a member.
	 *
	 * @param int $user_id Member.
	 * @return string
	 */
	public static function profile_level( $user_id ) {
		$level = get_user_meta( $user_id, 'acme_member_visibility', true );
		if ( self::is_level( $level ) ) {
			return $level;
		}
		if ( '1' === (string) get_user_meta( $user_id, 'acme_member_hide_profile', true ) ) {
			return self::PRIVATE_LEVEL;
		}
		return self::PUBLIC_LEVEL;
	}

	/**
	 * Level of one field of a member.
	 *
	 * @param int    $user_id Member.
	 * @param string $key     Field key.
	 * @return string
	 */
	public static function field_level( $user_id, $key ) {
		$levels = get_user_meta( $user_id, 'acme_member_field_visibility', true );
		if ( is_array( $levels ) && isset( $levels[ $key ] ) && self::is_level( $levels[ $key ] ) ) {
			return $levels[ $key ];
		}
		if ( 'phone' === $key && '1' === (string) get_user_meta( $user_id, 'acme_member_hide_phone', true ) ) {
			return self::PRIVATE_LEVEL;
		}
		$fields = Fields::all();
		return isset( $fields[ $key ] ) ? $fields[ $key ]['visibility'] : self::PRIVATE_LEVEL;
	}

	/**
	 * All field levels of a member.
	 *
	 * @param int $user_id Member.
	 * @return array<string, string>
	 */
	public static function field_levels( $user_id ) {
		$out = array();
		foreach ( Fields::keys() as $key ) {
			$out[ $key ] = self::field_level( $user_id, $key );
		}
		return $out;
	}

	/**
	 * The most closed level a viewer may see for a member.
	 *
	 * @param int $member_id Member being looked at.
	 * @param int $viewer_id Viewer (0 = anonymous).
	 * @return string
	 */
	public static function viewer_level( $member_id, $viewer_id ) {
		if ( $viewer_id && ( (int) $viewer_id === (int) $member_id || user_can( $viewer_id, 'edit_users' ) ) ) {
			return self::PRIVATE_LEVEL;
		}
		if ( $viewer_id && Connections::are_connected( $member_id, $viewer_id ) ) {
			return self::CONNECTIONS_LEVEL;
		}
		if ( $viewer_id && user_can( $viewer_id, 'read_acme_members' ) ) {
			return self::MEMBERS_LEVEL;
		}
		return self::PUBLIC_LEVEL;
	}

	/**
	 * May the viewer see the profile at all?
	 *
	 * @param int $member_id Member.
	 * @param int $viewer_id Viewer (0 = anonymous).
	 * @return bool
	 */
	public static function can_view_profile( $member_id, $viewer_id ) {
		if ( ! Members::is_member( $member_id ) ) {
			return false;
		}
		return self::rank( self::profile_level( $member_id ) ) <= self::rank( self::viewer_level( $member_id, $viewer_id ) );
	}

	/**
	 * May the viewer see a field?
	 *
	 * @param int    $member_id Member.
	 * @param string $key       Field key.
	 * @param int    $viewer_id Viewer (0 = anonymous).
	 * @return bool
	 */
	public static function can_view_field( $member_id, $key, $viewer_id ) {
		return self::can_view_profile( $member_id, $viewer_id )
			&& self::rank( self::field_level( $member_id, $key ) ) <= self::rank( self::viewer_level( $member_id, $viewer_id ) );
	}

	/**
	 * Non-empty field values the viewer may see.
	 *
	 * @param int $member_id Member.
	 * @param int $viewer_id Viewer (0 = anonymous).
	 * @return array<string, string>
	 */
	public static function visible_fields( $member_id, $viewer_id ) {
		$out = array();
		if ( ! self::can_view_profile( $member_id, $viewer_id ) ) {
			return $out;
		}
		foreach ( Fields::get_all( $member_id ) as $key => $value ) {
			if ( '' !== $value && self::can_view_field( $member_id, $key, $viewer_id ) ) {
				$out[ $key ] = $value;
			}
		}
		return $out;
	}
}
