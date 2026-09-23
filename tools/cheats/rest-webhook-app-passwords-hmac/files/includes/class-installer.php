<?php
/**
 * Activation / upgrades.
 *
 * @package Acme\OrdersSync
 */

namespace Acme\OrdersSync;

defined( 'ABSPATH' ) || exit;

/**
 * Installer.
 */
class Installer {

	const VERSION_OPTION = 'acme_orders_sync_version';

	/**
	 * Activation hook.
	 */
	public static function activate(): void {
		Order_Post_Type::register();
		flush_rewrite_rules( false );
		self::maybe_upgrade();
	}

	/**
	 * Runs upgrade routines when the stored version is older than the code.
	 */
	public static function maybe_upgrade(): void {
		$stored = (string) get_option( self::VERSION_OPTION, '0' );
		if ( version_compare( $stored, VERSION, '>=' ) ) {
			return;
		}

		if ( version_compare( $stored, '1.2.0', '<' ) ) {
			self::upgrade_120();
		}

		if ( version_compare( $stored, '1.4.0', '<' ) ) {
			self::upgrade_140();
		}

		update_option( self::VERSION_OPTION, VERSION );
	}

	/**
	 * 1.4.0: event table for the queue/idempotency, and remove secrets that older
	 * versions wrote to the log and to the stored payloads.
	 */
	private static function upgrade_140(): void {
		Delivery_Store::install();

		$settings = new Settings();
		$logger   = new Logger( $settings );

		$redactor = $logger->redactor();
		$ids      = get_posts(
			array(
				'post_type'   => Order_Post_Type::POST_TYPE,
				'post_status' => 'any',
				'numberposts' => -1,
				'fields'      => 'ids',
			)
		);
		foreach ( $ids as $id ) {
			$raw = get_post_meta( $id, '_acme_raw_payload', true );
			if ( ! is_string( $raw ) || '' === $raw ) {
				continue;
			}
			$event = json_decode( $raw, true );
			$clean = is_array( $event ) ? wp_json_encode( $redactor->redact( $event ) ) : $redactor->scrub( $raw );
			update_post_meta( $id, '_acme_raw_payload', wp_slash( $clean ) );
		}
	}

	/**
	 * 1.2.0: order totals are stored in minor units (they were decimal strings).
	 */
	private static function upgrade_120(): void {
		$ids = get_posts(
			array(
				'post_type'   => Order_Post_Type::POST_TYPE,
				'post_status' => 'any',
				'numberposts' => -1,
				'fields'      => 'ids',
			)
		);
		foreach ( $ids as $id ) {
			$total = get_post_meta( $id, '_acme_order_total', true );
			if ( is_string( $total ) && str_contains( $total, '.' ) ) {
				update_post_meta( $id, '_acme_order_total', to_minor_units( $total ) );
			}
		}
	}
}
