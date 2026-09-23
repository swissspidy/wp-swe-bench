<?php
/**
 * Public API.
 *
 * @package Acme\Docs
 */

defined( 'ABSPATH' ) || exit;

use Acme\Docs\Permalinks;
use Acme\Docs\Post_Types;

/**
 * Finds a doc by product, path and version.
 *
 * Every segment of the path must match a doc of that product and version below the previous one:
 * the same path can exist in several products and versions.
 *
 * @since 1.2.0
 * @since 2.0.0 The `$statuses` parameter.
 *
 * @param string   $product  Product slug.
 * @param string   $path     Doc path, e.g. `getting-started/installation`.
 * @param string   $version  Version slug (`v2`), or '' for the current docs.
 * @param string[] $statuses Allowed post statuses of the doc (its parents may have any status).
 * @return WP_Post|null
 */
function acme_docs_get_doc_by_path( $product, $path, $version = '', $statuses = array( 'publish' ) ) {
	$path = trim( (string) $path, '/' );
	$term = get_term_by( 'slug', (string) $product, Post_Types::PRODUCT );
	if ( '' === $path || ! $term ) {
		return null;
	}

	$segments = explode( '/', $path );
	$parent   = 0;
	$doc      = null;
	foreach ( $segments as $i => $segment ) {
		$is_last    = count( $segments ) - 1 === $i;
		$candidates = get_posts(
			array(
				'post_type'              => Post_Types::DOC,
				'post_status'            => $is_last ? (array) $statuses : array( 'publish', 'private', 'draft', 'pending', 'future' ),
				'name'                   => sanitize_title_for_query( $segment ),
				'post_parent'            => $parent,
				'posts_per_page'         => -1,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				// Note: WordPress ignores tax queries for queries by slug, so the product is checked below.
			)
		);
		$doc = null;
		foreach ( $candidates as $candidate ) {
			$candidate_product = Permalinks::get_product( $candidate );
			if ( $candidate_product && (int) $candidate_product->term_id === (int) $term->term_id
				&& Permalinks::get_version( $candidate ) === (string) $version ) {
				$doc = $candidate;
				break;
			}
		}
		if ( ! $doc ) {
			return null;
		}
		$parent = $doc->ID;
	}
	return $doc;
}

/**
 * URL of a product's docs.
 *
 * @since 1.0.0
 *
 * @param string|WP_Term $product Product slug or term.
 * @return string
 */
function acme_docs_product_url( $product ) {
	$term = $product instanceof WP_Term ? $product : get_term_by( 'slug', $product, Post_Types::PRODUCT );
	if ( ! $term ) {
		return '';
	}
	$link = get_term_link( $term );
	return is_wp_error( $link ) ? '' : $link;
}
