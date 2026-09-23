<?php
/**
 * Points ledger: every points movement is one row (append-only, never updated).
 *
 * Finance reconciles the ledger against the POS every quarter, so rows must never be deleted:
 * corrections are made with a new "manual" row.
 *
 * @package Acme\Loyalty
 */

namespace Acme\Loyalty;

defined( 'ABSPATH' ) || exit;

/**
 * Ledger repository.
 */
class Ledger {

	/**
	 * Add a points movement.
	 *
	 * @param int    $user_id  Member user ID.
	 * @param int    $points   Points (negative for redemptions/expiry).
	 * @param string $reason   One of acme_loyalty_reasons().
	 * @param array  $args     Optional: order_id, note, created_at (UTC), ip_address.
	 * @return int|false Row ID.
	 */
	public static function add( $user_id, $points, $reason = 'manual', array $args = array() ) {
		global $wpdb;

		$user = get_userdata( (int) $user_id );
		if ( ! $user ) {
			return false;
		}
		if ( ! array_key_exists( $reason, acme_loyalty_reasons() ) ) {
			$reason = 'manual';
		}

		$row = array(
			'user_id'    => (int) $user_id,
			'email'      => $user->user_email,
			'points'     => (int) $points,
			'reason'     => $reason,
			'order_id'   => isset( $args['order_id'] ) ? (int) $args['order_id'] : 0,
			'note'       => isset( $args['note'] ) ? sanitize_textarea_field( $args['note'] ) : '',
			'ip_address' => isset( $args['ip_address'] ) ? $args['ip_address'] : acme_loyalty_client_ip(),
			'created_at' => isset( $args['created_at'] ) ? $args['created_at'] : current_time( 'mysql', true ),
		);

		if ( ! $wpdb->insert( Installer::ledger_table(), $row ) ) {
			return false;
		}
		$id = (int) $wpdb->insert_id;

		wp_cache_delete( 'balance_' . (int) $user_id, 'acme_loyalty' );

		/**
		 * Fires after points were added to (or removed from) a member's balance.
		 *
		 * @param int   $id      Ledger row ID.
		 * @param int   $user_id Member.
		 * @param array $row     Row data.
		 */
		do_action( 'acme_loyalty_points_awarded', $id, (int) $user_id, $row );

		return $id;
	}

	/**
	 * Current balance of a member.
	 *
	 * @param int $user_id Member.
	 * @return int
	 */
	public static function balance( $user_id ) {
		global $wpdb;
		$cached = wp_cache_get( 'balance_' . (int) $user_id, 'acme_loyalty' );
		if ( false !== $cached ) {
			return (int) $cached;
		}
		$table   = Installer::ledger_table();
		$balance = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(points), 0) FROM {$table} WHERE user_id = %d", $user_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		wp_cache_set( 'balance_' . (int) $user_id, $balance, 'acme_loyalty' );
		return $balance;
	}

	/**
	 * Lifetime points earned (positive movements only), used for tiers.
	 *
	 * @param int $user_id Member.
	 * @return int
	 */
	public static function lifetime_points( $user_id ) {
		global $wpdb;
		$table = Installer::ledger_table();
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(points), 0) FROM {$table} WHERE user_id = %d AND points > 0", $user_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Ledger rows of a member, newest first.
	 *
	 * @param int $user_id Member.
	 * @param int $limit   Max rows.
	 * @param int $offset  Offset.
	 * @return object[]
	 */
	public static function history( $user_id, $limit = 20, $offset = 0 ) {
		global $wpdb;
		$table = Installer::ledger_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d", $user_id, $limit, $offset ) );
	}

	/**
	 * Number of ledger rows of a member.
	 *
	 * @param int $user_id Member.
	 * @return int
	 */
	public static function count_for_user( $user_id ) {
		global $wpdb;
		$table = Installer::ledger_table();
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE user_id = %d", $user_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Award purchase points for a completed order (idempotent per order).
	 *
	 * @param int $order_id Order post ID.
	 * @return int|false Ledger row ID, false if nothing was awarded.
	 */
	public static function award_for_order( $order_id ) {
		global $wpdb;
		$user_id = (int) get_post_meta( $order_id, '_acme_order_customer_id', true );
		if ( ! $user_id ) {
			return false; // Guests don't collect points.
		}
		$table  = Installer::ledger_table();
		$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE order_id = %d AND reason = 'purchase'", $order_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $exists ) {
			return false;
		}
		$settings = acme_loyalty_settings();
		$total    = (float) get_post_meta( $order_id, '_acme_order_total', true );
		$points   = (int) floor( $total * (int) $settings['points_per_currency'] );

		/**
		 * Filters the points awarded for an order.
		 *
		 * @param int $points   Points.
		 * @param int $order_id Order post ID.
		 * @param int $user_id  Member.
		 */
		$points = (int) apply_filters( 'acme_loyalty_points_for_order', $points, $order_id, $user_id );
		if ( $points <= 0 ) {
			return false;
		}
		return self::add(
			$user_id,
			$points,
			'purchase',
			array(
				'order_id' => $order_id,
				'note'     => sprintf( 'Order %s', Orders::number( $order_id ) ),
			)
		);
	}
}
