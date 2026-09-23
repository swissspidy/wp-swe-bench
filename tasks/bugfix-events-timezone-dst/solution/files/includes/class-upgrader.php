<?php
/**
 * Database upgrades.
 *
 * @package Acme\Events
 */

namespace Acme\Events;

defined( 'ABSPATH' ) || exit;

/**
 * Runs the data upgrades when a new version of the plugin is deployed.
 *
 * The schema version is kept in the `acme_events_db_version` option.
 */
class Upgrader {

	const OPTION     = 'acme_events_db_version';
	const DB_VERSION = '2.0';

	/**
	 * Events converted per batch.
	 */
	const BATCH = 200;

	/**
	 * Runs pending upgrades (hooked early on `init`, so it also runs for WP-CLI and REST requests).
	 */
	public static function maybe_upgrade() {
		$installed = (string) get_option( self::OPTION, '' );
		if ( version_compare( $installed, self::DB_VERSION, '>=' ) ) {
			return;
		}

		if ( '' === $installed || version_compare( $installed, '1.6', '<' ) ) {
			self::upgrade_16();
		}

		if ( version_compare( $installed, '2.0', '<' ) ) {
			self::upgrade_20();
		}

		update_option( self::OPTION, self::DB_VERSION );
	}

	/**
	 * 1.6: the settings were split into a dedicated option.
	 */
	protected static function upgrade_16() {
		$legacy = get_option( 'acme_events_options' );
		if ( is_array( $legacy ) && false === get_option( 'acme_events_settings' ) ) {
			add_option(
				'acme_events_settings',
				array(
					'upcoming_limit' => isset( $legacy['limit'] ) ? absint( $legacy['limit'] ) : 5,
					'feed_enabled'   => ! empty( $legacy['ical'] ),
				)
			);
			delete_option( 'acme_events_options' );
		}
	}

	/**
	 * 2.0: every event stores its start/end in UTC plus its own timezone.
	 *
	 * The wall-clock times editors entered (1.3+: local strings, 1.0: local timestamp + duration) are
	 * authoritative and are interpreted in the site's timezone at the time of the upgrade. The
	 * timestamps stored by 1.6 used the UTC offset of the day the event was *saved* and are discarded.
	 * Events that already have a timezone are left alone, so the upgrade can safely run again.
	 */
	public static function upgrade_20() {
		$last_id = 0;
		do {
			$ids = self::event_ids_after( $last_id );
			foreach ( $ids as $id ) {
				$event = Event::get( $id );
				if ( $event ) {
					$event->upgrade();
				}
				$last_id = $id;
			}
			wp_cache_flush_runtime();
		} while ( count( $ids ) === self::BATCH );
	}

	/**
	 * IDs of events (any status) after an ID, in ID order.
	 *
	 * @param int $after_id ID.
	 * @return int[]
	 */
	protected static function event_ids_after( $after_id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off upgrade.
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND ID > %d ORDER BY ID ASC LIMIT %d",
				Post_Type::NAME,
				$after_id,
				self::BATCH
			)
		);
		return array_map( 'intval', $ids );
	}
}
