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

	/**
	 * Constructor.
	 *
	 * @param Calculator $calculator Calculator.
	 */
	public function __construct( Calculator $calculator ) {
		$this->calculator = $calculator;
	}

	/**
	 * Stats of a scope.
	 *
	 * Cached for 10 minutes per scope.
	 *
	 * @param Scope $scope Scope.
	 * @return array
	 */
	public function get( Scope $scope ) {
		$key    = 'acme_stats_' . str_replace( ':', '_', $scope->key() );
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$stats                 = $this->calculator->compute( $scope );
		$stats['scope']        = $scope->key();
		$stats['generated_at'] = gmdate( 'c' );
		// Numbers may be up to 10 minutes old; that's fine for a dashboard.
		set_transient( $key, $stats, 10 * MINUTE_IN_SECONDS );
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
