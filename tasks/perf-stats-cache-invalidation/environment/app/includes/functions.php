<?php
/**
 * Public helpers (used by the theme and other plugins).
 *
 * @package Acme\Stats
 */

defined( 'ABSPATH' ) || exit;

/**
 * Stats of the whole site (author 0) or of one author.
 *
 * @param int $author_id Author ID, 0 for the whole site.
 * @return array
 */
function acme_stats_get( $author_id = 0 ) {
	$scope = $author_id ? Acme\Stats\Scope::author( $author_id ) : Acme\Stats\Scope::site();
	return Acme\Stats\Plugin::instance()->stats->get( $scope );
}
