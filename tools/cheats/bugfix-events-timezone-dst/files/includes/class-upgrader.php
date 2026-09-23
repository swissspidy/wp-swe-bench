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
			// Recompute the stored timestamps with the offset of the event's date.
			foreach ( get_posts( array( 'post_type' => Post_Type::NAME, 'post_status' => 'any', 'numberposts' => -1 ) ) as $post ) {
				$event = Event::get( $post );
				if ( $event && $event->has_dates() ) {
					$all_day = $event->is_all_day();
					$event->save_dates( $all_day ? Dates::date_part( $event->get_start_local() ) : $event->get_start_local(), $all_day ? Dates::date_part( $event->get_end_local() ) : $event->get_end_local(), $all_day );
				}
			}
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
}
