<?php
/**
 * Connections between members (mutual, request + accept).
 *
 * Storage (user meta, arrays of user IDs):
 * - acme_member_connections        accepted connections (kept on both members),
 * - acme_member_requests_in        pending requests this member received,
 * - acme_member_requests_out       pending requests this member sent.
 *
 * Legacy: `acme_member_buddies` (one-sided buddy lists imported from the old forum). Mutual
 * entries become connections, one-sided entries pending requests (see maybe_migrate_legacy()).
 *
 * @package Acme\Members
 */

namespace Acme\Members;

defined( 'ABSPATH' ) || exit;

/**
 * Connections repository.
 */
class Connections {

	const META_CONNECTED = 'acme_member_connections';
	const META_IN        = 'acme_member_requests_in';
	const META_OUT       = 'acme_member_requests_out';
	const MIGRATION      = 'acme_members_connections_migrated';

	/**
	 * IDs stored in a meta key.
	 *
	 * @param int    $user_id User.
	 * @param string $key     Meta key.
	 * @return int[]
	 */
	private static function ids( $user_id, $key ) {
		$ids = get_user_meta( (int) $user_id, $key, true );
		$ids = is_array( $ids ) ? array_map( 'intval', $ids ) : array();
		$ids = array_values( array_unique( array_filter( $ids ) ) );
		sort( $ids );
		return $ids;
	}

	/**
	 * Add or remove an ID in a meta list.
	 *
	 * @param int    $user_id User.
	 * @param string $key     Meta key.
	 * @param int    $id      ID.
	 * @param bool   $add     Add (true) or remove (false).
	 */
	private static function toggle( $user_id, $key, $id, $add ) {
		$ids = self::ids( $user_id, $key );
		$ids = $add ? array_merge( $ids, array( (int) $id ) ) : array_diff( $ids, array( (int) $id ) );
		$ids = array_values( array_unique( $ids ) );
		sort( $ids );
		update_user_meta( (int) $user_id, $key, $ids );
	}

	/**
	 * Accepted connections of a member.
	 *
	 * @param int $user_id Member.
	 * @return int[]
	 */
	public static function connected( $user_id ) {
		return self::ids( $user_id, self::META_CONNECTED );
	}

	/**
	 * Pending requests received.
	 *
	 * @param int $user_id Member.
	 * @return int[]
	 */
	public static function incoming( $user_id ) {
		return self::ids( $user_id, self::META_IN );
	}

	/**
	 * Pending requests sent.
	 *
	 * @param int $user_id Member.
	 * @return int[]
	 */
	public static function outgoing( $user_id ) {
		return self::ids( $user_id, self::META_OUT );
	}

	/**
	 * Are two members connected (accepted)?
	 *
	 * @param int $a Member.
	 * @param int $b Member.
	 * @return bool
	 */
	public static function are_connected( $a, $b ) {
		$a = (int) $a;
		$b = (int) $b;
		if ( ! $a || ! $b || $a === $b ) {
			return false;
		}
		return in_array( $b, self::connected( $a ), true ) && in_array( $a, self::connected( $b ), true );
	}

	/**
	 * Request a connection (or accept, if the other member already asked).
	 *
	 * @param int $from Requesting member.
	 * @param int $to   Other member.
	 * @return string 'connected' or 'requested'.
	 */
	public static function request( $from, $to ) {
		if ( self::are_connected( $from, $to ) ) {
			return 'connected';
		}
		if ( in_array( (int) $to, self::incoming( $from ), true ) ) {
			self::connect( $from, $to );
			return 'connected';
		}
		self::toggle( $from, self::META_OUT, $to, true );
		self::toggle( $to, self::META_IN, $from, true );
		self::changed( $from, $to );
		return 'requested';
	}

	/**
	 * Connect two members (clears pending requests both ways).
	 *
	 * @param int $a Member.
	 * @param int $b Member.
	 */
	public static function connect( $a, $b ) {
		foreach ( array( array( $a, $b ), array( $b, $a ) ) as list( $x, $y ) ) {
			self::toggle( $x, self::META_CONNECTED, $y, true );
			self::toggle( $x, self::META_IN, $y, false );
			self::toggle( $x, self::META_OUT, $y, false );
		}
		self::changed( $a, $b );
	}

	/**
	 * Remove a connection, cancel or decline a request.
	 *
	 * @param int $a Member.
	 * @param int $b Other member.
	 * @return bool Whether anything existed.
	 */
	public static function remove( $a, $b ) {
		$existed = in_array( (int) $b, array_merge( self::connected( $a ), self::incoming( $a ), self::outgoing( $a ) ), true );
		foreach ( array( array( $a, $b ), array( $b, $a ) ) as list( $x, $y ) ) {
			foreach ( array( self::META_CONNECTED, self::META_IN, self::META_OUT ) as $key ) {
				self::toggle( $x, $key, $y, false );
			}
		}
		self::changed( $a, $b );
		return $existed;
	}

	/**
	 * Something changed between two members.
	 *
	 * @param int $a Member.
	 * @param int $b Member.
	 */
	private static function changed( $a, $b ) {
		/**
		 * Fires when a connection or request between two members changed.
		 *
		 * @param int $a Member.
		 * @param int $b Member.
		 */
		do_action( 'acme_members_connections_changed', (int) $a, (int) $b );
	}

	/**
	 * Import the forum buddy lists once (sites update the plugin without re-activating it).
	 */
	public static function maybe_migrate_legacy() {
		if ( get_option( self::MIGRATION ) ) {
			return;
		}
		update_option( self::MIGRATION, 1 );

		$lists = array();
		foreach ( Members::all_ids() as $id ) {
			$buddies = get_user_meta( $id, 'acme_member_buddies', true );
			if ( is_array( $buddies ) ) {
				$lists[ $id ] = array_map( 'intval', $buddies );
			}
		}
		foreach ( $lists as $a => $buddies ) {
			foreach ( $buddies as $b ) {
				if ( $b === $a || ! Members::is_member( $b ) ) {
					continue;
				}
				// Buddies were friends on the forum: connect them.
				self::connect( $a, $b );
			}
		}
	}
}
