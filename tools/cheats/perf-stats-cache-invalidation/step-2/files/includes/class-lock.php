<?php
/**
 * "Only one regeneration per scope at a time", across all PHP processes of the site.
 *
 * With a persistent object cache the lock is an atomic cache add (shared by all web
 * servers). Without one, it is a row in the options table, created with a single atomic
 * insert. Locks expire, so a regeneration that died can't block the scope forever.
 *
 * @package Acme\Stats
 */

namespace Acme\Stats;

defined( 'ABSPATH' ) || exit;

/**
 * Regeneration lock.
 */
class Lock {

	/** Seconds after which a lock is considered abandoned. */
	const TIMEOUT = 120;

	/** Object cache group (persistent caches). */
	const GROUP = 'acme_stats_locks';

	/** Option name prefix (no persistent cache). */
	const OPTION_PREFIX = '_acme_stats_lock_';

	/**
	 * Name of the lock of a scope.
	 *
	 * @param Scope $scope Scope.
	 * @return string
	 */
	private function name( Scope $scope ) {
		return self::OPTION_PREFIX . str_replace( ':', '_', $scope->key() );
	}

	/**
	 * Try to take the lock of a scope.
	 *
	 * @param Scope $scope Scope.
	 * @return bool Whether this process holds the lock now.
	 */
	public function acquire( Scope $scope ) {
		return (bool) wp_cache_add( $this->name( $scope ), time(), self::GROUP, self::TIMEOUT );
	}

	/**
	 * Release the lock of a scope.
	 *
	 * @param Scope $scope Scope.
	 */
	public function release( Scope $scope ) {
		wp_cache_delete( $this->name( $scope ), self::GROUP );
	}

	/**
	 * Release every lock (wp acme-stats flush).
	 */
	public function release_all() {
		global $wpdb;
		if ( wp_using_ext_object_cache() ) {
			if ( wp_cache_supports( 'flush_group' ) ) {
				wp_cache_flush_group( self::GROUP );
			}
			return;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( self::OPTION_PREFIX ) . '%' ) );
	}
}
