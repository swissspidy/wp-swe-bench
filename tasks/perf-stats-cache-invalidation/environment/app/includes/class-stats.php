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
	 * TODO: this is computed on every call. Fine for 50 posts, not for 5,000.
	 *
	 * @param Scope $scope Scope.
	 * @return array
	 */
	public function get( Scope $scope ) {
		$stats                 = $this->calculator->compute( $scope );
		$stats['scope']        = $scope->key();
		$stats['generated_at'] = gmdate( 'c' );
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
