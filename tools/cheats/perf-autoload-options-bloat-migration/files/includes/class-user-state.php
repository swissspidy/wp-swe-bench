<?php
/**
 * Per-user UI state of the log screen: rows per page, hidden columns and the
 * time of the last visit (entries newer than that are highlighted).
 *
 * Storage: user meta `acme_activity_ui` (3.0+).
 *
 * Before 3.0 the state lived in options, which the 3.0 migration moves to user meta:
 * - 2.x: one option `acme_activity_ui_state` = array( user_id => state ).
 * - 1.x: one option per user `acme_activity_ui_{user_id}` = array( 'rows' => int,
 *   'hide' => 'col1,col2', 'seen' => unix ).
 *
 * @package Acme\ActivityLog
 */

namespace Acme\ActivityLog;

defined( 'ABSPATH' ) || exit;

/**
 * Per-user UI state.
 */
class User_State {

	/**
	 * User meta key.
	 */
	const META_KEY = 'acme_activity_ui';

	/**
	 * 2.x option (migrated).
	 */
	const OPTION = 'acme_activity_ui_state';

	/**
	 * 1.x per-user option prefix.
	 */
	const LEGACY_PREFIX = 'acme_activity_ui_';

	/**
	 * Allowed rows-per-page values.
	 *
	 * @var int[]
	 */
	const PER_PAGE_CHOICES = array( 10, 20, 25, 50, 100 );

	/**
	 * Columns that can be hidden.
	 *
	 * @var string[]
	 */
	const HIDEABLE = array( 'user', 'object', 'ip' );

	/**
	 * Defaults.
	 *
	 * @return array{per_page:int, hidden_columns:string[], last_seen:int}
	 */
	public static function defaults() {
		return array(
			'per_page'       => 20,
			'hidden_columns' => array(),
			'last_seen'      => 0,
		);
	}

	/**
	 * Get the state of a user.
	 *
	 * @param int $user_id User ID.
	 * @return array{per_page:int, hidden_columns:string[], last_seen:int}
	 */
	public function get( $user_id ) {
		$user_id = (int) $user_id;
		if ( $user_id <= 0 ) {
			return self::defaults();
		}

		$state = get_user_meta( $user_id, self::META_KEY, true );
		return is_array( $state ) ? self::sanitize( $state ) : self::defaults();
	}

	/**
	 * Update (merge) the state of a user.
	 *
	 * @param int   $user_id User ID.
	 * @param array $changes Keys to change.
	 * @return array New state.
	 */
	public function update( $user_id, array $changes ) {
		$user_id = (int) $user_id;
		if ( ! $user_id ) {
			return self::defaults();
		}
		$state = self::sanitize( array_merge( $this->get( $user_id ), $changes ) );
		update_user_meta( $user_id, self::META_KEY, $state );

		return $state;
	}

	/**
	 * Convert a 1.x state.
	 *
	 * @param array $legacy 1.x state.
	 * @return array
	 */
	public static function from_legacy( array $legacy ) {
		$hidden = isset( $legacy['hide'] ) ? array_filter( array_map( 'trim', explode( ',', (string) $legacy['hide'] ) ) ) : array();
		return array(
			'per_page'       => isset( $legacy['rows'] ) ? (int) $legacy['rows'] : 20,
			'hidden_columns' => array_values( $hidden ),
			'last_seen'      => isset( $legacy['seen'] ) ? (int) $legacy['seen'] : 0,
		);
	}

	/**
	 * Sanitize a state array.
	 *
	 * @param array $state Raw state.
	 * @return array{per_page:int, hidden_columns:string[], last_seen:int}
	 */
	public static function sanitize( array $state ) {
		$state = array_merge( self::defaults(), array_intersect_key( $state, self::defaults() ) );

		$per_page = (int) $state['per_page'];
		if ( ! in_array( $per_page, self::PER_PAGE_CHOICES, true ) ) {
			$per_page = 20;
		}

		$hidden = array_values( array_intersect( self::HIDEABLE, array_map( 'strval', (array) $state['hidden_columns'] ) ) );

		return array(
			'per_page'       => $per_page,
			'hidden_columns' => $hidden,
			'last_seen'      => max( 0, (int) $state['last_seen'] ),
		);
	}
}
