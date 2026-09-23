<?php
/**
 * Storage for computed stats.
 *
 * Every scope ('site', 'author:<id>') has its own entry. Entries carry the "generation"
 * they were computed for; any change to what the stats count starts a new generation
 * (see Invalidator), which makes every older entry stale at once. Entries are stored as
 * transients, so a persistent object cache is used when the site has one, and the
 * database otherwise.
 *
 * @package Acme\Stats
 */

namespace Acme\Stats;

defined( 'ABSPATH' ) || exit;

/**
 * Stats cache.
 */
class Cache {

	/** Autoloaded option holding the current generation. */
	const GENERATION_OPTION = 'acme_stats_generation';

	/** How long an entry is kept at most (it is replaced long before, normally). */
	const TTL = WEEK_IN_SECONDS;

	/**
	 * Current generation.
	 *
	 * @return int
	 */
	public function generation() {
		$generation = get_option( self::GENERATION_OPTION );
		if ( false === $generation ) {
			// First run after an update (the plugin is not re-activated on deploys).
			add_option( self::GENERATION_OPTION, 1, '', true );
			return 1;
		}
		return (int) $generation;
	}

	/**
	 * Start a new generation: every stored entry becomes stale.
	 */
	public function invalidate() {
		update_option( self::GENERATION_OPTION, $this->generation() + 1, true );
	}

	/**
	 * Transient name of a scope.
	 *
	 * @param Scope $scope Scope.
	 * @return string
	 */
	private function key( Scope $scope ) {
		return 'acme_stats_' . str_replace( ':', '_', $scope->key() );
	}

	/**
	 * Stored entry of a scope, whatever its generation.
	 *
	 * @param Scope $scope Scope.
	 * @return array|null { generation: int, stats: array }
	 */
	public function entry( Scope $scope ) {
		$entry = get_transient( $this->key( $scope ) );
		if ( ! is_array( $entry ) || ! isset( $entry['generation'], $entry['stats'] ) || ! is_array( $entry['stats'] ) ) {
			return null;
		}
		return $entry;
	}

	/**
	 * Fresh stats of a scope, or null.
	 *
	 * @param Scope $scope Scope.
	 * @return array|null
	 */
	public function get( Scope $scope ) {
		$entry = $this->entry( $scope );
		if ( ! $entry || (int) $entry['generation'] !== $this->generation() ) {
			return null;
		}
		return $entry['stats'];
	}

	/**
	 * Store stats computed for a generation.
	 *
	 * @param Scope $scope      Scope.
	 * @param array $stats      Stats.
	 * @param int   $generation Generation they were computed for.
	 */
	public function set( Scope $scope, array $stats, $generation ) {
		set_transient(
			$this->key( $scope ),
			array(
				'generation' => (int) $generation,
				'stats'      => $stats,
			),
			self::TTL
		);
	}

	/**
	 * Remove the entry of a scope.
	 *
	 * @param Scope $scope Scope.
	 */
	public function delete( Scope $scope ) {
		delete_transient( $this->key( $scope ) );
	}
}
