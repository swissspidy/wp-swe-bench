<?php
/**
 * Stats service: what the dashboard widget, the admin page, the shortcode and the REST API use.
 *
 * @package Acme\Stats
 */

namespace Acme\Stats;

defined( 'ABSPATH' ) || exit;

/**
 * Access to the stats.
 */
class Stats {

	/** @var Calculator */
	private $calculator;

	/** @var Cache */
	private $cache;

	/** @var Lock */
	private $lock;

	/**
	 * Constructor.
	 *
	 * @param Calculator $calculator Calculator.
	 * @param Cache      $cache      Cache.
	 * @param Lock       $lock       Lock.
	 */
	public function __construct( Calculator $calculator, Cache $cache, Lock $lock ) {
		$this->calculator = $calculator;
		$this->cache      = $cache;
		$this->lock       = $lock;
	}

	/**
	 * Stats of a scope.
	 *
	 * - Fresh cached numbers are returned as they are.
	 * - Stale numbers (something changed since): the first request regenerates them; while
	 *   it does, every other request gets the stale numbers (`stale` => true) right away.
	 * - Nothing cached at all: computed right away.
	 *
	 * @param Scope $scope Scope.
	 * @return array
	 */
	public function get( Scope $scope ) {
		$entry = $this->cache->entry( $scope );
		if ( $entry && (int) $entry['generation'] === $this->cache->generation() ) {
			return $entry['stats'] + array( 'stale' => false );
		}

		$locked = $this->lock->acquire( $scope );
		if ( ! $locked && $entry ) {
			return $entry['stats'] + array( 'stale' => true );
		}
		try {
			return $this->compute( $scope ) + array( 'stale' => false );
		} finally {
			if ( $locked ) {
				$this->lock->release( $scope );
			}
		}
	}

	/**
	 * Forget everything (wp acme-stats flush).
	 *
	 * @return int Entries removed.
	 */
	public function flush() {
		$count = $this->cache->flush();
		$this->lock->release_all();
		return $count;
	}

	/**
	 * Compute and store the stats of a scope.
	 *
	 * @param Scope $scope Scope.
	 * @return array
	 */
	public function compute( Scope $scope ) {
		// Read the generation first: a change made while computing makes the result stale.
		$generation            = $this->cache->generation();
		$stats                 = $this->calculator->compute( $scope );
		$stats['scope']        = $scope->key();
		$stats['generated_at'] = gmdate( 'c' );
		$this->cache->set( $scope, $stats, $generation );
		return $stats;
	}

	/**
	 * Stats a user sees by default (site for editors/admins, own numbers for everybody else).
	 *
	 * @param int $user_id User.
	 * @return array|null Null if the user can't see stats.
	 */
	public function for_user( $user_id ) {
		$scope = Scope::for_user( $user_id );
		if ( ! $scope->visible_to( $user_id ) ) {
			return null;
		}
		return $this->get( $scope );
	}
}
