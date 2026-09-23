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

	/**
	 * Constructor.
	 *
	 * @param Calculator $calculator Calculator.
	 * @param Cache      $cache      Cache.
	 */
	public function __construct( Calculator $calculator, Cache $cache ) {
		$this->calculator = $calculator;
		$this->cache      = $cache;
	}

	/**
	 * Stats of a scope: from the cache while nothing they count has changed.
	 *
	 * @param Scope $scope Scope.
	 * @return array
	 */
	public function get( Scope $scope ) {
		$cached = $this->cache->get( $scope );
		if ( null !== $cached ) {
			return $cached;
		}
		return $this->compute( $scope );
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
