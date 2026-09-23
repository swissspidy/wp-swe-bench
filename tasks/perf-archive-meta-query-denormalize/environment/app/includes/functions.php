<?php
/**
 * Public API (used by our theme and the agency's CRM sync).
 *
 * @package Acme\RealEstate
 */

use Acme\RealEstate\Listing;
use Acme\RealEstate\Search;

defined( 'ABSPATH' ) || exit;

/**
 * Search listings. See Acme\RealEstate\Search for the arguments.
 *
 * @param array $args Search arguments.
 * @return array{ids: int[], total: int, pages: int, page: int, per_page: int, args: array}
 */
function acme_re_search( array $args = array() ) {
	return Search::run( $args );
}

/**
 * Public data of a listing.
 *
 * @param int|WP_Post $post Listing.
 * @return array|null
 */
function acme_re_get_listing( $post ) {
	$listing = Listing::get( $post );
	return $listing ? $listing->to_array() : null;
}
