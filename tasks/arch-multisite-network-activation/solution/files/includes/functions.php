<?php
/**
 * Public template functions. Themes and the network mu-plugin use these; keep them stable.
 *
 * @package Acme\Directory
 */

defined( 'ABSPATH' ) || exit;

use Acme\Directory\Listings;
use Acme\Directory\Schema;

/**
 * Full (prefixed) name of one of the directory tables for the current site.
 *
 * @param string $which 'listings' or 'categories'.
 * @return string Table name, or '' for an unknown table.
 */
function acme_directory_table( $which ) {
	switch ( $which ) {
		case 'listings':
			return Schema::listings();
		case 'categories':
			return Schema::categories();
	}
	return '';
}

/**
 * Published listings for templates.
 *
 * @param array $args See Listings::query().
 * @return array[] Prepared listings.
 */
function acme_directory_get_listings( $args = array() ) {
	return array_map( array( Listings::class, 'prepare' ), Listings::query( $args ) );
}
