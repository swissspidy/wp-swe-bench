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
	if ( '' === $path ) {
		return null;
	}
	$doc = get_page_by_path( $path, OBJECT, Post_Types::DOC );
	if ( ! $doc || ! in_array( $doc->post_status, (array) $statuses, true ) ) {
		return null;
	}
	$doc_product = Permalinks::get_product( $doc );
	if ( ! $doc_product || $doc_product->slug !== $product || Permalinks::get_version( $doc ) !== (string) $version ) {
		return null;
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
