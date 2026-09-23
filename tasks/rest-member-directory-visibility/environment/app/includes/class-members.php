<?php
/**
 * Members: the "Member" role, member queries and profile URLs.
 *
 * @package Acme\Members
 */

namespace Acme\Members;

defined( 'ABSPATH' ) || exit;

/**
 * Member helpers.
 */
class Members {

	const ROLE = 'acme_member';
	const CAP  = 'read_acme_members';

	/**
	 * Activation: role + capability.
	 */
	public static function activate() {
		self::register_role();
		Profile_Page::add_rewrite_rules();
		flush_rewrite_rules();
	}

	/**
	 * Register the Member role; members and administrators can see members-only data.
	 */
	public static function register_role() {
		if ( ! get_role( self::ROLE ) ) {
			add_role(
				self::ROLE,
				__( 'Member', 'acme-members' ),
				array(
					'read'    => true,
					self::CAP => true,
				)
			);
		}
		$admin = get_role( 'administrator' );
		if ( $admin && ! $admin->has_cap( self::CAP ) ) {
			$admin->add_cap( self::CAP );
		}
	}

	/**
	 * Is the user listed in the directory (has the Member role)?
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function is_member( $user_id ) {
		$user = get_userdata( (int) $user_id );
		return $user && in_array( self::ROLE, (array) $user->roles, true );
	}

	/**
	 * All member IDs, ordered by display name.
	 *
	 * @return int[]
	 */
	public static function all_ids() {
		return array_map(
			'intval',
			get_users(
				array(
					'role'    => self::ROLE,
					'orderby' => 'display_name',
					'order'   => 'ASC',
					'fields'  => 'ID',
				)
			)
		);
	}

	/**
	 * Public profile URL (/members/{nicename}/).
	 *
	 * @param int|\WP_User $user User.
	 * @return string
	 */
	public static function profile_url( $user ) {
		$user = $user instanceof \WP_User ? $user : get_userdata( (int) $user );
		if ( ! $user ) {
			return '';
		}
		return home_url( user_trailingslashit( 'members/' . $user->user_nicename ) );
	}

	/**
	 * Member by nicename.
	 *
	 * @param string $slug Nicename.
	 * @return \WP_User|null
	 */
	public static function by_slug( $slug ) {
		$user = get_user_by( 'slug', $slug );
		return ( $user && self::is_member( $user->ID ) ) ? $user : null;
	}
}
